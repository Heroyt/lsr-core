<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace Lsr\Core\Models;

use ArrayAccess;
use BackedEnum;
use DateInterval;
use DateTime;
use DateTimeInterface;
use Dibi\Exception;
use Dibi\Row;
use JsonSerializable;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\ManyToMany;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\ModelRelation;
use Lsr\Core\Models\Attributes\NoDB;
use Lsr\Core\Models\Attributes\OneToMany;
use Lsr\Core\Models\Attributes\OneToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Interfaces\InsertExtendInterface;
use Lsr\Helpers\Tools\Strings;
use Lsr\Logging\Logger;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;
use RuntimeException;

abstract class Model implements JsonSerializable, ArrayAccess
{

	/** @var string Database table name */
	public const TABLE = '';

	/** @var self[][] Model instance cache */
	protected static array $instances = [];
	/** @var string[] Primary key cache */
	protected static array $primaryKeys = [];

	#[NoDB]
	public ?int      $id  = null;
	protected ?Row   $row = null;
	protected Logger $logger;

	/**
	 * @param int|null $id    DB model ID
	 * @param Row|null $dbRow Prefetched database row
	 *
	 * @throws ModelNotFoundException|ValidationException
	 */
	public function __construct(?int $id = null, ?Row $dbRow = null) {
		if (!isset(self::$instances[$this::TABLE])) {
			self::$instances[$this::TABLE] = [];
		}
		if (!isset($id) && isset($dbRow, $dbRow->{$this::getPrimaryKey()})) {
			/** @noinspection CallableParameterUseCaseInTypeContextInspection */
			$id = $dbRow->{$this::getPrimaryKey()};
		}
		if (isset($id) && !empty($this::TABLE)) {
			$this->id = $id;
			$this->row = $dbRow;
			self::$instances[$this::TABLE][$this->id] = $this;
			$this->fetch();
		}
		else if (isset($dbRow)) {
			$this->row = $dbRow;
			$this->fillFromRow();
		}
		$this->logger = new Logger(LOG_DIR.'models/', $this::TABLE);
	}

	/**
	 * Get model's primary key
	 *
	 * @return string Primary key's column name
	 * @todo: Support mixed primary keys
	 *
	 */
	public static function getPrimaryKey() : string {
		if (empty(static::$primaryKeys[static::class])) {
			try {
				// Try to find a property with a PrimaryKey attribute
				$reflection = new ReflectionClass(static::class);

				$attributes = $reflection->getAttributes(PrimaryKey::class);
				if (!empty($attributes)) {
					$attribute = first($attributes);
					/** @var PrimaryKey $attr */
					$attr = $attribute->newInstance();
					static::$primaryKeys[static::class] = $attr->column;
					return static::$primaryKeys[static::class];
				}

				// Try to create a primary key name from model name
				$pascal = $reflection->getShortName();
				$camel = Strings::toCamelCase($reflection->getShortName());
				$snakeCase = Strings::toSnakeCase($reflection->getShortName());
				if (property_exists(static::class, 'id_'.$snakeCase) || property_exists(static::class, 'id'.$pascal)) {
					static::$primaryKeys[static::class] = 'id_'.$snakeCase;
					return static::$primaryKeys[static::class];
				}
				if (property_exists(static::class, $snakeCase.'_id') || property_exists(static::class, $camel.'Id')) {
					static::$primaryKeys[static::class] = $snakeCase.'_id';
					return static::$primaryKeys[static::class];
				}
			} catch (ReflectionException) {
			}

			// Default
			static::$primaryKeys[static::class] = 'id';
		}
		return static::$primaryKeys[static::class];
	}

	/**
	 * Fetch model's data from DB
	 *
	 * @throws ModelNotFoundException|ValidationException
	 */
	public function fetch(bool $refresh = false) : void {
		if (!isset($this->id) || $this->id <= 0) {
			throw new RuntimeException('Id needs to be set before fetching model\'s data.');
		}
		if ($refresh || !isset($this->row)) {
			$this->row = DB::select($this::TABLE, '*')->where('%n = %i', $this::getPrimaryKey(), $this->id)->fetch();
		}
		if (!isset($this->row)) {
			throw new ModelNotFoundException(get_class($this).' model of ID '.$this->id.' was not found.');
		}
		$this->fillFromRow();

		foreach ($this::getPropertyReflections() as $property) {
			$attributes = $property->getAttributes(ModelRelation::class, ReflectionAttribute::IS_INSTANCEOF);
			$propertyName = $property->getName();
			if (!empty($attributes)) {
				$this->processRelations($attributes, $property, $propertyName);
			}
		}
	}

	/**
	 * @return void
	 */
	protected function fillFromRow() : void {
		foreach ($this->row as $key => $val) {
			if ($key === $this::getPrimaryKey()) {
				$this->id = $val;
			}
			if (property_exists($this, $key)) {
				$this->setProperty($key, $val);
				continue;
			}
			$key = Strings::toCamelCase($key);
			if (property_exists($this, $key)) {
				$this->setProperty($key, $val);
			}
		}

		// Find InsertExtendInterface
		foreach ($this::getPropertyReflections() as $property) {
			$propertyName = $property->getName();
			if ($property->hasType()) {
				// Check enum and date values
				$type = $property->getType();
				if (
					$type instanceof ReflectionNamedType &&
					!$type->isBuiltin() &&
					in_array(InsertExtendInterface::class, class_implements($type->getName()), true)
				) {
					/** @var InsertExtendInterface $class */
					$class = $type->getName();
					$this->$propertyName = $class::parseRow($this->row);
				}
			}
		}
	}

	protected function setProperty(string $name, mixed $value) : void {
		$property = $this::getPropertyReflection($name);
		if ($property->hasType()) {
			// Check enum and date values
			$type = $property->getType();
			if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
				if ($value instanceof DateInterval && in_array(DateTimeInterface::class, class_implements($type->getName()), true)) {
					$value = new DateTime($value->format('%H:%i:%s'));
				}
				if (in_array(BackedEnum::class, class_implements($type->getName()), true)) {
					$enum = $type->getName();
					$value = $enum::tryFrom($value);
				}
			}
		}
		$this->$name = $value;
	}

	/**
	 * @param string $name
	 *
	 * @return ReflectionProperty
	 */
	public static function getPropertyReflection(string $name) : ReflectionProperty {
		return (new ReflectionClass(static::class))->getProperty($name);
	}

	/**
	 * @return ReflectionProperty[]
	 */
	public static function getPropertyReflections(?int $filter = null) : array {
		return (new ReflectionClass(static::class))->getProperties($filter);
	}

	/**
	 * @param array              $attributes
	 * @param ReflectionProperty $property
	 * @param string             $propertyName
	 *
	 * @return void
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 */
	protected function processRelations(array $attributes, ReflectionProperty $property, string $propertyName) : void {
		foreach ($attributes as $attribute) {
			/** @var ManyToOne|OneToMany|OneToOne|ManyToMany $attributeClass */
			$attributeClass = $attribute->newInstance();
			$info = $attributeClass->getType($property);
			/** @var Model $className */
			$className = $info->class;

			$foreignKey = $attributeClass->getForeignKey($className, $this);
			$localKey = $attributeClass->getLocalKey($className, $this);

			switch ($attribute->getName()) {
				case ManyToOne::class:
				case OneToOne::class:
					/** @var int $id */
					$id = $this->row?->$localKey;
					if (is_null($id)) {
						if (!$info->nullable) {
							throw new ValidationException('Cannot assign null to a non nullable relation');
						}
						$this->$propertyName = null;
						break;
					}
					try {
						$this->$propertyName = $className::get($id);
					} catch (ModelNotFoundException $e) {
						if (!$info->nullable) {
							throw $e;
						}
						$this->$propertyName = null;
					}
					break;
				case OneToMany::class:
					$id = $this->id;
					$this->$propertyName = $className::query()->where('%n = %i', $foreignKey, $id)->get();
					break;
				case ManyToMany::class:
					$id = $this->id;
					$this->$propertyName = $className::query()
																					 ->where(
																						 '%n IN %sql',
																						 $foreignKey,
																						 $attributeClass->getConnectionQuery($id, $className, $this)
																					 )
																					 ->get();
					break;
			}
		}
	}

	/**
	 * @param int      $id
	 * @param Row|null $row
	 *
	 * @return static
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 */
	public static function get(int $id, ?Row $row = null) : static {
		return static::$instances[static::TABLE][$id] ?? new static($id, $row);
	}

	public static function query() : ModelQuery {
		return new ModelQuery(static::class);
	}

	/**
	 * Checks if a model with given ID exists in database
	 *
	 * @param int $id
	 *
	 * @return bool
	 */
	public static function exists(int $id) : bool {
		$test = DB::select(static::TABLE, 'count(*)')->where('%n = %i', static::getPrimaryKey(), $id)->fetchSingle();
		return $test > 0;
	}

	/**
	 * Get all models
	 *
	 * @return static[]
	 */
	public static function getAll() : array {
		return static::query()->get();
	}

	/**
	 * @return bool
	 * @throws ValidationException
	 */
	public function save() : bool {
		return isset($this->id) ? $this->update() : $this->insert();
	}

	/**
	 * @return bool
	 * @throws ValidationException
	 */
	public function update() : bool {
		if (!isset($this->id)) {
			return false;
		}
		$this->logger->info('Updating model - '.$this->id);
		try {
			DB::update($this::TABLE, $this->getQueryData(), ['%n = %i', $this::getPrimaryKey(), $this->id]);
		} catch (Exception $e) {
			$this->logger->error('Error running update query: '.$e->getMessage());
			$this->logger->debug('Query: '.$e->getSql());
			$this->logger->debug('Trace: '.$e->getTraceAsString());
			return false;
		}
		return true;
	}

	/**
	 * Get an array of values for DB to insert/update. Values are validated.
	 *
	 * @return array
	 * @throws ValidationException
	 */
	public function getQueryData() : array {
		$data = [];

		$properties = $this::getPropertyReflections(ReflectionProperty::IS_PUBLIC);
		foreach ($properties as $property) {
			if (!empty($property->getAttributes(NoDB::class))) {
				continue;
			}

			$propertyName = $property->getName();
			$columnName = Strings::toSnakeCase($propertyName);

			$relations = $property->getAttributes(ModelRelation::class, ReflectionAttribute::IS_INSTANCEOF);
			if (!empty($relations)) {
				foreach ($relations as $relation) {
					/** @var OneToMany|ManyToOne|OneToOne $attr */
					$attr = $relation->newInstance();
					$typeInfo = $attr->getType($property);

					$foreignKey = $attr->getForeignKey($typeInfo->class, $this);
					$localKey = $attr->localKey;

					if (empty($localKey)) {
						$localKey = $foreignKey;
					}

					switch ($relation->getName()) {
						case OneToOne::class:
						case ManyToOne::class:
							$data[$localKey] = $this->$propertyName->id;
							break;
					}
				}
				continue;
			}

			if ($property->hasType()) {
				$type = $property->getType();
				if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
					if ($this->$propertyName instanceof InsertExtendInterface) {
						$this->$propertyName->addQueryData($data);
						continue;
					}
					if ($this->$propertyName instanceof BackedEnum) {
						$data[$columnName] = $this->$propertyName->value;
						continue;
					}

					continue;
				}
			}

			// TODO: Validation
			$data[$columnName] = $this->$propertyName;
		}
		return $data;
	}

	/**
	 * @return bool
	 * @throws ValidationException
	 */
	public function insert() : bool {
		$this->logger->info('Inserting new model');
		try {
			DB::insert($this::TABLE, $this->getQueryData());
			$this->id = DB::getInsertId();
		} catch (Exception $e) {
			$this->logger->error('Error running insert query: '.$e->getMessage());
			$this->logger->debug('Query: '.$e->getSql());
			$this->logger->debug('Trace: '.$e->getTraceAsString());
			return false;
		}
		if (empty($this->id)) {
			$this->logger->error('Insert query passed, but ID was not returned.');
			return false;
		}
		self::$instances[$this::TABLE][$this->id] = $this;
		return true;
	}

	/**
	 * @inheritdoc
	 */
	public function jsonSerialize() : array {
		$vars = get_object_vars($this);
		if (isset($vars['row'])) {
			unset($vars['row']);
		}
		if (isset($vars['logger'])) {
			unset($vars['logger']);
		}
		return $vars;
	}

	/**
	 * @inheritdoc
	 */
	public function offsetGet($offset) : mixed {
		if ($this->offsetExists($offset)) {
			return $this->$offset;
		}
		return null;
	}

	/**
	 * @inheritdoc
	 */
	public function offsetExists($offset) : bool {
		return property_exists($this, $offset);
	}

	/**
	 * @inheritdoc
	 */
	public function offsetSet($offset, $value) : void {
		if ($this->offsetExists($offset)) {
			$this->$offset = $value;
		}
	}

	/**
	 * @inheritdoc
	 */
	public function offsetUnset($offset) : void {
		// Do nothing
	}

	/**
	 * Delete model from DB
	 *
	 * @return bool
	 */
	public function delete() : bool {
		if (!isset($this->id)) {
			return false;
		}
		$this->logger->info('Delete model: '.$this::TABLE.' of ID: '.$this->id);
		try {
			DB::delete($this::TABLE, ['%n = %i', $this::getPrimaryKey(), $this->id]);
			static::$instances[$this::TABLE][$this->id] = null;
		} catch (Exception $e) {
			$this->logger->error($e->getMessage());
			$this->logger->debug($e->getTraceAsString());
			return false;
		}
		return true;
	}
}