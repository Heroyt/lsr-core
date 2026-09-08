<?php

declare(strict_types=1);

namespace TestCases;

use Lsr\Caching\Cache;
use Lsr\Core\App;
use Lsr\Core\Http\Lifecycle\RequestOperation;
use Lsr\Core\Requests\Request;
use Lsr\Core\RouteHandler;
use Lsr\Core\Routing\AliasRoute;
use Lsr\Core\Routing\Route;
use Lsr\Core\Routing\Router;
use Lsr\Enums\RequestMethod;
use Lsr\Serializer\Mapper;
use Nette\Caching\Storages\MemoryStorage;
use Nette\DI\Container;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\BackupStaticProperties;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionProperty;
use RuntimeException;

final class RouteHandlerTest extends TestCase
{
    public function test_route_middleware_runs_on_consecutive_dispatches(): void {
        $calls = 0;
        $middleware = new class ($calls) implements MiddlewareInterface {
            public function __construct(private int &$calls) {
            }

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
                $this->calls++;
                return new Response();
            }
        };
        $route = Route::create(RequestMethod::GET, '/repeated', static fn () => new Response())
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
    public function test_argument_cache_is_isolated_between_domains_with_the_same_path(): void {
        $router = new Router();
        $router->unregisterAll();
        $controller = new class {
            public function number(int $value): ResponseInterface {
                return new Response(200, [], 'number:' . $value);
            }

            public function word(string $value): ResponseInterface {
                return new Response(200, [], 'word:' . $value);
            }
        };
        $router->domain('numbers.example')->get('/items/{value}', [$controller, 'number']);
        $router->domain('words.example')->get('/items/{value}', [$controller, 'word']);
        $router->resolveDomains();
        $handler = new class (new Cache(new MemoryStorage(), debug: false), $this->createStub(Mapper::class)) extends RouteHandler {
            protected function withCookies(ResponseInterface $response): ResponseInterface {
                return $response;
            }
        };
        try {
            foreach ([
                ['numbers.example', '42', 'number:42'],
                ['words.example', 'forty', 'word:forty'],
                ['numbers.example', '7', 'number:7'],
            ] as [$host, $value, $expected]) {
                $params = [];
                $route = Router::getRoute(RequestMethod::GET, ['items', $value], $params, host: $host);
                self::assertInstanceOf(Route::class, $route);
                $request = new Request(new ServerRequest('GET', 'https://' . $host . '/items/' . $value));
                $request->setParams(['value' => $value]);
                $response = $handler->setRoute($route)->handle($request);
                self::assertSame($expected, (string) $response->getBody());
            }
        } finally {
            $router->unregisterAll();
        }
    }

    #[BackupStaticProperties(true)]
    public function test_cross_host_alias_dispatch_injects_the_psr_request(): void {
        $target = Route::create(RequestMethod::GET, '/items/{id}', static fn () => new Response());
        $target->restoreDomain('admin.example');
        $alias = AliasRoute::createAlias(RequestMethod::GET, '/items/{id}', $target);
        $handler = new class (new Cache(new MemoryStorage(), debug: false), $this->createStub(Mapper::class)) extends RouteHandler {
            protected function withCookies(ResponseInterface $response): ResponseInterface {
                return $response;
            }
        };
        $request = new Request(new ServerRequest('GET', 'https://public.example:8443/items/42?tab=score'));
        $request = $request->withAttribute('id', '42');
        $response = $handler->setRoute($alias)->handle($request);
        self::assertSame(308, $response->getStatusCode());
        self::assertSame('https://admin.example:8443/items/42?tab=score', $response->getHeaderLine('Location'));
    }

    #[BackupStaticProperties(true)]
    public function test_object_handler_keeps_argument_errors_as_runtime_exceptions(): void {
        $controller = new class {
            public function show(int|float $value): ResponseInterface {
                return new Response();
            }
        };
        $route = Route::create(RequestMethod::GET, '/missing-value', [$controller, 'show']);
        $handler = new RouteHandler(
            new Cache(new MemoryStorage(), debug: false),
            $this->createStub(Mapper::class),
        );

        $this->expectException(RuntimeException::class);
        $handler->setRoute($route)->handle(new Request(new ServerRequest('GET', '/missing-value')));
    }

    #[BackupStaticProperties(true)]
    public function test_controller_dispatch_reports_nested_lifecycle_operations(): void {
        $controller = new InstrumentedRouteController();
        $dependency = new RouteHandlerDependency();
        $container = new class ($controller, $dependency) extends Container {
            public function __construct(
                private readonly InstrumentedRouteController $controller,
                private readonly RouteHandlerDependency $dependency,
            ) {
                parent::__construct();
            }

            public function getByType(string $type, bool $throw = true): ?object {
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
        $handler = new class ($cache, $this->createStub(Mapper::class)) extends RouteHandler {
            protected function withCookies(ResponseInterface $response): ResponseInterface {
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
