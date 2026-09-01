<?php

declare(strict_types=1);

namespace TestCases;

use Lsr\Core\Http\Lifecycle\RequestLifecycleHookInterface;
use Lsr\Core\Http\Lifecycle\RequestLifecycleScopeInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class RecordingRequestHook implements RequestLifecycleHookInterface
{
    public function __construct(private FpmLifecycleEvents $events)
    {
    }

    public function begin(ServerRequestInterface $request): RequestLifecycleScopeInterface
    {
        $this->events->record('request.begin');
        return new RecordingRequestScope($this->events);
    }
}
