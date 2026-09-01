<?php

declare(strict_types=1);

namespace TestCases;

use Lsr\Core\FpmHandler;
use Lsr\Interfaces\RequestFactoryInterface;
use Lsr\Interfaces\SessionInterface;
use Psr\Http\Message\ResponseInterface;

final class RecordingFpmHandler extends FpmHandler
{
    public function __construct(
        RequestFactoryInterface $requestFactory,
        SessionInterface $session,
        private readonly FpmLifecycleEvents $events,
    ) {
        parent::__construct($requestFactory, $session);
    }

    protected function sendResponse(ResponseInterface $response): void
    {
        $this->events->record('response.send');
    }
}
