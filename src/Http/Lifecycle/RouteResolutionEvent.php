<?php

declare(strict_types=1);

namespace Lsr\Core\Http\Lifecycle;

use Lsr\Enums\RequestMethod;
use Lsr\Interfaces\RouteInterface;

final readonly class RouteResolutionEvent
{
    public const string MATCHED = 'matched';
    public const string NOT_FOUND = 'not_found';
    public const string ERROR = 'error';

    public function __construct(
        public RequestMethod $method,
        public ?RouteInterface $route,
        public float $durationSeconds,
        public ?string $errorType = null,
    ) {
    }

    public function outcome(): string
    {
        if ($this->errorType !== null) {
            return self::ERROR;
        }
        return $this->route === null ? self::NOT_FOUND : self::MATCHED;
    }
}
