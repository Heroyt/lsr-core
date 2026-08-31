<?php

declare(strict_types=1);

namespace TestCases;

use Lsr\Caching\Cache;
use Lsr\Core\Requests\Request;
use Lsr\Core\RouteHandler;
use Lsr\Core\Routing\Route;
use Lsr\Enums\RequestMethod;
use Lsr\Serializer\Mapper;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

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

        $handler->setRoute($route)->handle(new Request(new ServerRequest('GET', '/repeated')));
        $handler->setRoute($route)->handle(new Request(new ServerRequest('GET', '/repeated')));

        self::assertSame(2, $calls);
    }
}
