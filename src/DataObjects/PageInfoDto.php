<?php

namespace Lsr\Core\DataObjects;

use JsonSerializable;
use Lsr\Enums\RequestMethod;

readonly class PageInfoDto implements JsonSerializable
{

    /**
     * @param  RequestMethod  $type
     * @param  string|null  $routeName
     * @param  string[]  $path
     * @param  array<string, string|numeric-string>  $params
     */
    public function __construct(
      public RequestMethod $type,
      public ?string       $routeName = null,
      public array         $path = [],
      public array         $params = [],
    ) {}

    /**
     * @inheritDoc
     *
     * @return array<string,mixed>
     */
    public function jsonSerialize() : array {
        return get_object_vars($this);
    }
}