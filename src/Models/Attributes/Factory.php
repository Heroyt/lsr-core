<?php

namespace Lsr\Core\Models\Attributes;

use Attribute;
use Lsr\Core\Models\Interfaces\FactoryInterface;
use Lsr\Core\Models\Model;

#[Attribute(Attribute::TARGET_CLASS)]
class Factory
{

    /**
     * @param  class-string<FactoryInterface<Model>>  $factoryClass
     * @param  array<string, mixed>  $defaultOptions
     */
    public function __construct(
      public string $factoryClass,
      public array  $defaultOptions = [],
    ) {}

}