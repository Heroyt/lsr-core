<?php

declare(strict_types=1);

namespace TestCases;

use Lsr\Caching\Cache;
use Lsr\Core\App;
use Lsr\Core\Http\Lifecycle\RequestOperation;
use Lsr\Core\Requests\Request;
use Lsr\Core\RouteHandler;
use Lsr\Core\Routing\Route;
use Lsr\Enums\RequestMethod;
use Lsr\Serializer\Mapper;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Nette\DI\Container;
use PHPUnit\Framework\Attributes\BackupStaticProperties;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionProperty;

final class RouteHandlerTest extends TestCase
{

    public function testRouteMiddlewareRunsOnConsecutiveDispatches(): void
    {
        $calls = 0;
        $middleware = new class($calls) implements MiddlewareInterface {
            public function __construct(private int &$calls)
            {
            }

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $this->calls++;
                return new Response();
            }
        };
        $route = Route::create(RequestMethod::GET, '/repeated', static fn() => new Response())
            ->middleware($middleware);
        $handler = new RouteHandler(
            $this->createStub(Cache::class),
            $this->createStub(Mapper::class),
        );
        $hook = new RecordingRequestOperationHook();
        $handler->setRequestOperationLifecycleHook($hook);

        $handler->setRoute($route)->handle(new Request(new ServerRequest('GET', '/repeated')));
        $handler->setRoute($route)->handle(new Request(new ServerRequest('GET', '/repeated')));

        self::assertSame(2, $calls);
        self::assertSame(
            [RequestOperation::Middleware, RequestOperation::Middleware],
            array_column($hook->begun, 'operation'),
        );
        self::assertCount(2, $hook->completed);
    }

    #[BackupStaticProperties(true)]
    public function testControllerDispatchReportsNestedLifecycleOperations(): void
    {
        $controller = new InstrumentedRouteController();
        $dependency = new RouteHandlerDependency();
        $container = new class($controller, $dependency) extends Container {
            public function __construct(
                private readonly InstrumentedRouteController $controller,
                private readonly RouteHandlerDependency $dependency,
            ) {
                parent::__construct();
            }

            public function getByType(string $type, bool $throw = true): ?object
            {
                /** @phpstan-ignore return.type */
                return match ($type) {
                    InstrumentedRouteController::class => $this->controller,
                    RouteHandlerDependency::class => $this->dependency,
                    default => parent::getByType($type, $throw),
                };
            }
        };
        (new ReflectionProperty(App::class, 'container'))->setValue(null, $container);

        $cache = $this->createStub(Cache::class);
        $cache->method('load')->willReturn([
            'dependency' => [
                'optional' => false,
                'union' => false,
                'unionHasModel' => false,
                'type' => RouteHandlerDependency::class,
                'nullable' => false,
                'mapRequest' => false,
            ],
        ]);
        $handler = new class($cache, $this->createStub(Mapper::class)) extends RouteHandler {
            protected function withCookies(ResponseInterface $response): ResponseInterface
            {
                return $response;
            }
        };
        $hook = new RecordingRequestOperationHook();
        $handler->setRequestOperationLifecycleHook($hook);
        $route = Route::create(
            RequestMethod::GET,
            '/instrumented',
            [InstrumentedRouteController::class, 'show'],
        );

        $response = $handler
            ->setRoute($route)
            ->handle(new Request(new ServerRequest('GET', '/instrumented')));

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($controller->initialized);
        self::assertSame(
            [
                RequestOperation::DependencyResolution,
                RequestOperation::ControllerInitialization,
                RequestOperation::ActionArgumentResolution,
                RequestOperation::DependencyResolution,
                RequestOperation::ControllerAction,
            ],
            array_column($hook->begun, 'operation'),
        );
        self::assertSame('controller', $hook->begun[0]['attributes']['lsr.di.kind']);
        self::assertSame('argument', $hook->begun[3]['attributes']['lsr.di.kind']);
        self::assertCount(5, $hook->completed);
    }
}
