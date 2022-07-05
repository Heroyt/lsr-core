<?php

namespace Lsr\Core\Models\Attributes;

use Attribute;
use Lsr\Core\Models\Model;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class ManyToOne extends ModelRelation
{
	use WithType;

	public function __construct(
		public string  $foreignKey = '',
		public string  $localKey = '',
		public ?string $class = null,
	) {
	}

	/**
	 * @param string|Model $targetClass
	 * @param string|Model $class
	 *
	 * @return string
	 */
	public function getForeignKey(string|Model $targetClass, string|Model $class) : string {
		if (empty($this->foreignKey)) {
			$this->foreignKey = $targetClass::getPrimaryKey();
		}
		return $this->foreignKey;
	}

}