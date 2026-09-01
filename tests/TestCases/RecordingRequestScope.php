<?php

declare(strict_types=1);

namespace TestCases;

use Lsr\Core\Http\Lifecycle\RequestLifecycleScopeInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

final readonly class RecordingRequestScope implements RequestLifecycleScopeInterface
{
    public function __construct(private FpmLifecycleEvents $events)
    {
    }

    public function recordException(Throwable $exception): void
    {
        $this->events->record('request.exception');
    }

    public function complete(?ResponseInterface $response = null): void
    {
        $this->events->response = $response;
        $this->events->record('request.complete');
    }
}
