<?php

declare(strict_types=1);

namespace Lsr\Core\Http\Lifecycle;

use Psr\Http\Message\ServerRequestInterface;

interface RequestLifecycleHookInterface
{
    public function begin(ServerRequestInterface $request): RequestLifecycleScopeInterface;
}
