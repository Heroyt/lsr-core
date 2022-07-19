<?php

namespace Lsr\Core\Models\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Factory
{

	/**
	 * @param string               $factoryClass
	 * @param array<string, mixed> $defaultOptions
	 */
	public function __construct(
		public string $factoryClass,
		public array  $defaultOptions = [],
	) {
	}

}