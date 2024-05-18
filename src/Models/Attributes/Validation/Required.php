<?php

namespace Lsr\Core\Models\Attributes\Validation;

use Attribute;
use Lsr\Core\Exceptions\ValidationException;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class Required implements Validator
{

    public function validateValue(mixed $value, string | object $class, string $property) : void {
        if (is_null($value)) {
            $this->throw($class, $property);
        }
    }

    /**
     * @param  string|object  $class
     * @param  string  $property
     *
     * @return void
     * @throws ValidationException
     */
    public function throw(string | object $class, string $property) : void {
        throw new ValidationException(
          'Property '.(is_string($class) ? $class : $class::class).'::'.$property.' is required.'
        );
    }
}