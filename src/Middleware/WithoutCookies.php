<?php
declare(strict_types=1);

namespace Lsr\Core\Middleware;

use Lsr\Core\Routing\Middleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class WithoutCookies implements Middleware
{

    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface {
        return $handler->handle($request)->withoutHeader('Set-Cookie');
    }
}