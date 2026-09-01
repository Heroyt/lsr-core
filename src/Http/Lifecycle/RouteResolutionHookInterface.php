<?php

declare(strict_types=1);

namespace Lsr\Core\Http\Lifecycle;

interface RouteResolutionHookInterface
{
    public function record(RouteResolutionEvent $event): void;
}
