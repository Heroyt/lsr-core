<?php

declare(strict_types=1);

namespace TestCases;

use Lsr\Core\Http\Lifecycle\RequestOperation;
use Lsr\Core\Http\Lifecycle\RequestOperationLifecycleHookInterface;
use Lsr\Core\Http\Lifecycle\RequestOperationLifecycleScopeInterface;
use RuntimeException;

final class RecordingRequestOperationHook implements RequestOperationLifecycleHookInterface
{
    /** @var list<array{operation: RequestOperation, attributes: array<string, mixed>}> */
    public array $begun = [];

    /** @var list<array{operation: RequestOperation, attributes: array<string, mixed>, exception: ?\Throwable}> */
    public array $completed = [];

    public bool $failBegin = false;
    public bool $failComplete = false;

    public function begin(
        RequestOperation $operation,
        array $attributes = [],
    ): RequestOperationLifecycleScopeInterface {
        if ($this->failBegin) {
            throw new RuntimeException('Operation begin failed.');
        }

        $this->begun[] = ['operation' => $operation, 'attributes' => $attributes];
        return new RecordingRequestOperationScope($this, $operation, $this->failComplete);
    }
}
