<?php

namespace Lsr\Core\Models\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
abstract class ModelRelation
{

}