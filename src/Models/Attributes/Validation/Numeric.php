<?php

namespace Lsr\Core\Models\Attributes\Validation;

use Attribute;
use Lsr\Core\Exceptions\ValidationException;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class Numeric implements Validator
{

    public function validateValue(mixed $value, string | object $class, string $property) : void {
        if (!is_numeric($value)) {
            throw ValidationException::createWithValue(
              'Property '.(is_string($class) ? $class :
                $class::class).'::'.$property.' must be numeric (string, int or float). (value: %s)',
              $value,
            );
        }
    }
}