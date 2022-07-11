<?php

namespace Lsr\Core\Models\Attributes;

use Attribute;

/**
 * Used for marking class properties as automatically instantiable
 *
 * If the property's value is not set, instantiate it as a new object.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Instantiate
{

}