<?php

namespace Lsr\Core\Models\Attributes\Validation;

use Attribute;
use Lsr\Core\Exceptions\ValidationException;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
/**
 * Checks a string property's length
 */
class StringLength implements Validator
{

    /**
     * @param  int|null  $length
     * @param  int|null  $max
     */
    public function __construct(
      public ?int $length = null,
      public ?int $max = null,
    ) {}

    public function validateValue(mixed $value, string | object $class, string $property) : void {
        if (!is_string($value)) {
            throw ValidationException::createWithValue(
              'Property '.(is_string($class) ? $class : $class::class).'::'.$property.' must be a string. (value: %s)',
              $value
            );
        }
        if ($this->length === null && $this->max === null) {
            return;
        }

        $len = mb_strlen($value, 'UTF-8');
        if (isset($this->length, $this->max) && ($len < $this->length || $len > $this->max)) {
            throw ValidationException::createWithValue(
              'Value\'s length must be between '.$this->length.' and '.$this->max.'. (value: %s)',
              $value
            );
        }

        if (isset($this->length) && !isset($this->max) && $len !== $this->length) {
            throw ValidationException::createWithValue(
              'Value\'s length must be '.$this->length.'. (value: %s)',
              $value
            );
        }

        if (!isset($this->length) && isset($this->max) && $len > $this->max) {
            throw ValidationException::createWithValue(
              'Value\'s length must be less than '.$this->max.'. (value: %s)',
              $value
            );
        }
    }
}