<?php

namespace Lsr\Core\Models\Attributes\Validation;

use Lsr\Core\Exceptions\ValidationException;

interface Validator
{

	/**
	 * Validate a value and throw an exception on error
	 *
	 * @param mixed         $value
	 * @param string|object $class
	 * @param string        $property
	 *
	 * @return void
	 *
	 * @throws ValidationException
	 */
	public function validateValue(mixed $value, string|object $class, string $property) : void;

}