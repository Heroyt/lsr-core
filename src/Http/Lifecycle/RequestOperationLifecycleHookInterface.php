<?php

declare(strict_types=1);

namespace Lsr\Core\Http\Lifecycle;

interface RequestOperationLifecycleHookInterface
{
    /**
     * @param array<non-empty-string, bool|int|float|string|list<bool|int|float|string>|null> $attributes
     */
    public function begin(RequestOperation $operation, array $attributes = []): RequestOperationLifecycleScopeInterface;
}
