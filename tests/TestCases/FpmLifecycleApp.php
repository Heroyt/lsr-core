<?php

declare(strict_types=1);

namespace TestCases;

use Lsr\Core\App;
use Lsr\Core\Config;
use Lsr\Core\RouteHandler;
use Lsr\Core\Routing\Router;
use Lsr\Core\Translations;
use Lsr\Interfaces\SessionInterface;
use Psr\Http\Message\ResponseInterface;

final class FpmLifecycleApp extends App
{
    public function __construct(
        Router $router,
        RouteHandler $routeHandler,
        SessionInterface $session,
        Config $config,
        Translations $translations,
        private readonly ResponseInterface $response,
    ) {
        parent::__construct($router, $routeHandler, $session, $config, $translations);
    }

    public function run(): ResponseInterface
    {
        return $this->response;
    }
}
