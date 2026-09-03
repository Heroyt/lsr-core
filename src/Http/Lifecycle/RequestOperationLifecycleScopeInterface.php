<?php

declare(strict_types=1);

namespace Lsr\Core\Http\Lifecycle;

use Throwable;

interface RequestOperationLifecycleScopeInterface
{
    /**
     * Completion must be idempotent.
     *
     * @param array<non-empty-string, bool|int|float|string|list<bool|int|float|string>|null> $attributes
     */
    public function complete(array $attributes = [], ?Throwable $exception = null): void;
}
