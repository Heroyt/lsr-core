<?php

namespace Lsr\Core\Models\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class PrimaryKey
{

    public function __construct(
      public string $column
    ) {}

}