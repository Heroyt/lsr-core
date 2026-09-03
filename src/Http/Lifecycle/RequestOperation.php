<?php

declare(strict_types=1);

namespace Lsr\Core\Http\Lifecycle;

enum RequestOperation: string
{
    case RouteResolution = 'lsr.routing.resolve';
    case RouteDispatch = 'lsr.route.dispatch';
    case Middleware = 'lsr.middleware.process';
    case DependencyResolution = 'lsr.di.resolve';
    case ControllerInitialization = 'lsr.controller.init';
    case ActionArgumentResolution = 'lsr.controller.arguments';
    case ControllerAction = 'lsr.controller.action';
}
