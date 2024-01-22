<?php

namespace Lsr\Core\Models;

use Lsr\Core\Models\Attributes\Factory;
use Lsr\Core\Models\Attributes\ModelRelation;
use Lsr\Core\Models\Interfaces\FactoryInterface;

/**
 * @phpstan-type FactoryConfig array{factoryClass: class-string<FactoryInterface<Model>>, defaultOptions: array<string, mixed>}
 * @phpstan-type RelationConfig array{type:class-string<ModelRelation>, instance: string, class: class-string<Model>, factory: class-string|null, foreignKey: string, localKey: string, loadingType: LoadingType}
 * @phpstan-type PropertyConfig array{name:string, isPrimaryKey: bool, allowsNull: bool, isBuiltin: bool, isExtend: bool, isEnum: bool, isDateTime: bool, instantiate: bool, noDb: bool, type: class-string|string, relation:null|RelationConfig}
 */
abstract class ModelConfig
{

	public string $primaryKey;

	/** @var FactoryConfig|null */
	public ?array $factoryConfig = null;

	/** @var array<string, PropertyConfig> */
	public array $properties = [];

	private Factory $factory;

	public function getFactory(): ?Factory {
		if (!isset($this->factoryConfig)) {
			return null;
		}

		$this->factory ??= new Factory($this->factoryConfig['factoryClass'], $this->factoryConfig['defaultOptions']);
		return $this->factory;
	}

}