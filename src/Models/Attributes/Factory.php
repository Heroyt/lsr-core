<?php

namespace Lsr\Core\Models\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Factory
{

	public function __construct(
		public string $factoryClass
	) {
	}

}