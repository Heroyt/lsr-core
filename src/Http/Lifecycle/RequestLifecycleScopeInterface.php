<?php

declare(strict_types=1);

namespace Lsr\Core\Http\Lifecycle;

use Psr\Http\Message\ResponseInterface;
use Throwable;

interface RequestLifecycleScopeInterface
{
    public function recordException(Throwable $exception): void;

    public function complete(?ResponseInterface $response = null): void;
}
