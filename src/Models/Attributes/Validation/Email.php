<?php

namespace Lsr\Core\Models\Attributes\Validation;

use Attribute;
use Lsr\Core\Exceptions\ValidationException;
use Nette\Utils\Validators;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class Email implements Validator
{

    public function validateValue(mixed $value, string | object $class, string $property) : void {
        if (!is_string($value) || !Validators::isEmail($value)) {
            throw ValidationException::createWithValue(
              'Property '.(is_string($class) ? $class : $class::class).'::'.$property.' must be a valid email. (value: %s)',
              $value
            );
        }
    }
}