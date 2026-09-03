<?php

declare(strict_types=1);

namespace TestCases;

use Lsr\Interfaces\RequestInterface;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

final class InstrumentedRouteController
{
    public bool $initialized = false;

    public function init(RequestInterface $request): void
    {
        $this->initialized = true;
    }

    public function show(RouteHandlerDependency $dependency): ResponseInterface
    {
        return new Response();
    }
}
