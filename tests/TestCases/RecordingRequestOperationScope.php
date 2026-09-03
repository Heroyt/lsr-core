<?php

declare(strict_types=1);

namespace TestCases;

use Lsr\Core\Http\Lifecycle\RequestOperation;
use Lsr\Core\Http\Lifecycle\RequestOperationLifecycleScopeInterface;
use RuntimeException;
use Throwable;

final class RecordingRequestOperationScope implements RequestOperationLifecycleScopeInterface
{
    private bool $completed = false;

    public function __construct(
        private readonly RecordingRequestOperationHook $hook,
        private readonly RequestOperation $operation,
        private readonly bool $fail,
    ) {
    }

    public function complete(array $attributes = [], ?Throwable $exception = null): void
    {
        if ($this->completed) {
            return;
        }
        $this->completed = true;

        if ($this->fail) {
            throw new RuntimeException('Operation completion failed.');
        }

        $this->hook->completed[] = [
            'operation' => $this->operation,
            'attributes' => $attributes,
            'exception' => $exception,
        ];
    }
}
