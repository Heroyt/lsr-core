<?php

declare(strict_types=1);

namespace TestCases;

use Lsr\Core\Http\AsyncHandlerInterface;

final readonly class RecordingAsyncHandler implements AsyncHandlerInterface
{
    public function __construct(private FpmLifecycleEvents $events)
    {
    }

    public function run(): void
    {
        $this->events->record('async.run');
    }
}
