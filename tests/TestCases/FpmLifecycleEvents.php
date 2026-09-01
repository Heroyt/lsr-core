<?php

declare(strict_types=1);

namespace TestCases;

use Psr\Http\Message\ResponseInterface;

final class FpmLifecycleEvents
{
    /** @var list<string> */
    public array $events = [];
    public ?ResponseInterface $response = null;

    public function record(string $event): void
    {
        $this->events[] = $event;
    }
}
