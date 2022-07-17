<?php

namespace Lsr\Core\Models\Attributes;

use Error;
use Lsr\Core\Models\Model;
use ReflectionProperty;
use ReflectionType;

trait WithType
{

	protected bool $nullable;

	/**
	 * Get a class name for a property for a model relation
	 *
	 * @param ReflectionProperty $property
	 *
	 * @return object{class: string|Model, nullable: bool} Class name
	 */
	public function getType(ReflectionProperty $property) : object {
		if (!is_null($this->class)) {
			if (!isset($this->nullable)) {
				$this->nullable = false;
				if ($property->hasType()) {
					$this->nullable = $property->getType()->allowsNull();
				}
			}
			return (object) ['class' => $this->class, 'nullable' => $this->nullable];
		}
		if ($property->hasType()) {
			/** @var ReflectionType $type */
			$type = $property->getType();
			if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
				$this->class = $type->getName();
				$this->nullable = $type->allowsNull();
				return (object) ['class' => $this->class, 'nullable' => $this->nullable];
			}
			throw new Error('Cannot create relation for a scalar type in Model '.$this::class.' and property '.$property->getName());
		}

		// TODO: Maybe add docblock parsing
		throw new Error('Cannot create relation in Model '.$this::class.' and property '.$property->getName().' - no type definition found');
	}

}