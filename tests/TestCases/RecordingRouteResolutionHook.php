<?php

declare(strict_types=1);

namespace TestCases;

use Lsr\Core\Http\Lifecycle\RouteResolutionEvent;
use Lsr\Core\Http\Lifecycle\RouteResolutionHookInterface;
use RuntimeException;

final class RecordingRouteResolutionHook implements RouteResolutionHookInterface
{
    /** @var list<RouteResolutionEvent> */
    public array $events = [];
    public bool $fail = false;

    public function record(RouteResolutionEvent $event): void
    {
        if ($this->fail) {
            throw new RuntimeException('Hook failure');
        }
        $this->events[] = $event;
    }
}
