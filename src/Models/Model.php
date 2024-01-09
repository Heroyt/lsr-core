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
use Lsr\Core\App;
use Lsr\Core\Caching\Cache;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\Factory;
use Lsr\Core\Models\Attributes\Instantiate;
use Lsr\Core\Models\Attributes\ManyToMany;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\ModelRelation;
use Lsr\Core\Models\Attributes\NoDB;
use Lsr\Core\Models\Attributes\OneToMany;
use Lsr\Core\Models\Attributes\OneToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Attributes\Validation\Required;
use Lsr\Core\Models\Attributes\Validation\Validator;
use Lsr\Core\Models\Interfaces\InsertExtendInterface;
use Lsr\Helpers\Tools\Strings;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Lsr\Logging\Logger;
use Nette\Caching\Cache as CacheParent;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;
use RuntimeException;
use stdClass;

/**
 * @implements ArrayAccess<string, mixed>
 * @phpstan-consistent-constructor
 */
abstract class Model implements JsonSerializable, ArrayAccess
{

	/** @var string Database table name */
	public const TABLE = '';
	/** @var string[] Static tags to add to all cache records for this model */
	public const CACHE_TAGS = [];

	/** @var static[][] Model instance cache */
	protected static array $instances = [];
	/** @var string[] Primary key cache */
	protected static array $primaryKeys = [];
	/** @var ReflectionClass<Model>[] */
	protected static array $reflections = [];
	/** @var Factory[] */
	protected static array $factory = [];
	/** @var array<string, Logger> */
	protected static array $modelLoggers = [];
	#[NoDB]
	public ?int $id = null;
	protected ?Row $row = null;
	protected Logger $logger;
	/** @var string[] Dynamic tags to add to cache records for this model instance */
	protected array $cacheTags = [];

	/**
	 * @param int|null $id    DB model ID
	 * @param Row|null $dbRow Prefetched database row
	 *
	 * @throws DirectoryCreationException
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 */
	public function __construct(?int $id = null, ?Row $dbRow = null) {
		if (!isset(self::$instances[$this::TABLE])) {
			self::$instances[$this::TABLE] = [];
		}
		if (!isset($id) && isset($dbRow->{$this::getPrimaryKey()})) {
			/** @noinspection CallableParameterUseCaseInTypeContextInspection */
			$id = $dbRow->{$this::getPrimaryKey()};
		}
		if (isset($id) && !empty($this::TABLE)) {
			$this->id = $id;
			self::$instances[$this::TABLE][$this->id] = $this;
			$this->row = $dbRow;
			$this->fetch();
		}
		else if (isset($dbRow)) {
			$this->row = $dbRow;
			$this->fillFromRow();
		}
		$this->instantiateProperties();
		$this->logger = $this->getLogger();
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
					/** @var ReflectionAttribute<ModelRelation> $attribute */
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
				if (property_exists(static::class, 'id_'.$snakeCase) || property_exists(
						static::class,
						'id'.$pascal
					)) {
					static::$primaryKeys[static::class] = 'id_'.$snakeCase;
					return static::$primaryKeys[static::class];
				}
				if (property_exists(static::class, $snakeCase.'_id') || property_exists(
						static::class,
						$camel.'Id'
					)) {
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
	 * @param bool $refresh
	 *
	 * @throws DirectoryCreationException
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 */
	public function fetch(bool $refresh = false) : void {
		if (!isset($this->id) || $this->id <= 0) {
			throw new RuntimeException('Id needs to be set before fetching model\'s data.');
		}
		if ($refresh || !isset($this->row)) {
			/** @var Row|null $row */
			$row = DB::select($this::TABLE, '*')
				->where('%n = %i', $this::getPrimaryKey(), $this->id)
				->cacheTags(...$this->getCacheTags())
				->fetch();
			$this->row = $row;
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
	 * @return string[]
	 */
	protected function getCacheTags() : array {
		return array_merge(
			['models', $this::TABLE, $this::TABLE.'/'.$this->id],
			$this::CACHE_TAGS,
			$this->cacheTags,
		);
	}

	/**
	 * @return void
	 */
	protected function fillFromRow() : void {
		if (!isset($this->row)) {
			return;
		}
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
					isset($this->row) &&
					$type instanceof ReflectionNamedType &&
					!$type->isBuiltin() &&
					/** @phpstan-ignore-next-line */
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
				/** @phpstan-ignore-next-line */
				if (
					$value instanceof DateInterval &&
					in_array(DateTimeInterface::class, class_implements($type->getName()), true)
				) {
					$value = new DateTime($value->format('%H:%i:%s'));
				}
				/** @phpstan-ignore-next-line */
				if (in_array(BackedEnum::class, class_implements($type->getName()), true)) {
					$enum = $type->getName();
					$value = $enum::tryFrom($value);
				}
			}
			if ($value === null && $type->isBuiltin() && !$type->allowsNull()) {
				switch ($type->getName()) {
					case 'int':
						$value = 0;
						break;
					case 'string':
						$value = '';
						break;
					case 'bool':
						$value = false;
						break;
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
		return static::getReflection()->getProperty($name);
	}

	/**
	 * @return ReflectionClass<Model>
	 */
	protected static function getReflection() : ReflectionClass {
		if (!isset(static::$reflections[static::class])) {
			static::$reflections[static::class] = (new ReflectionClass(static::class));
		}
		return static::$reflections[static::class];
	}

	/**
	 * @return ReflectionProperty[]
	 */
	public static function getPropertyReflections(?int $filter = null) : array {
		return static::getReflection()->getProperties($filter);
	}

	/**
	 * @param ReflectionAttribute<ModelRelation>[] $attributes
	 * @param ReflectionProperty                   $property
	 * @param string                               $propertyName
	 *
	 * @return void
	 * @throws DirectoryCreationException
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 */
	protected function processRelations(array $attributes, ReflectionProperty $property, string $propertyName) : void {
		foreach ($attributes as $attribute) {
			/** @var ManyToOne|OneToMany|OneToOne|ManyToMany $attributeClass */
			$attributeClass = $attribute->newInstance();
			/** @var stdClass $info */
			$info = $attributeClass->getType($property);
			/** @var Model $className */
			$className = $info->class;
			$factory = $className::getFactory();

			$foreignKey = $attributeClass->getForeignKey($className, $this);
			$localKey = $attributeClass->getLocalKey($className, $this);

			switch ($attribute->getName()) {
				case ManyToOne::class:
				case OneToOne::class:
					/** @var int $id */
					$id = $this->row?->$localKey;
				/** @phpstan-ignore-next-line */
					if (is_null($id)) {
						if (!$info->nullable) {
							throw new ValidationException('Cannot assign null to a non nullable relation');
						}
						$this->$propertyName = null;
						break;
					}
					try {
						$this->$propertyName = isset($factory) ? $factory->factoryClass::getById(
							$id,
							$factory->defaultOptions
						) : $className::get($id);
					} catch (ModelNotFoundException $e) {
						if (!$info->nullable) {
							throw $e;
						}
						$this->$propertyName = null;
					}
					break;
				case OneToMany::class:
					$id = $this->id;
					$this->$propertyName = $className::query()
						->where('%n = %i', $foreignKey, $id)
						->cacheTags($this::TABLE.'/'.$this->id.'/relations')
						->get();
					break;
				case ManyToMany::class:
					$id = $this->id;
					$this->$propertyName = $className::query()
						->where(
							'%n IN %sql',
							$foreignKey,
							/* @phpstan-ignore-next-line */
							$attributeClass->getConnectionQuery($id, $className, $this)
						)
						->cacheTags($this::TABLE.'/'.$this->id.'/relations')
						->get();
					break;
			}
		}
	}

	/**
	 * @return Factory|null
	 */
	public static function getFactory() : ?Factory {
		if (!isset(static::$factory[static::class])) {
			try {
				$attributes = static::getReflection()->getAttributes(Factory::class);
			} catch (ReflectionException) {
				return null;
			}
			if (empty($attributes)) {
				return null;
			}
			/** @var ReflectionAttribute<Factory> $attribute */
			$attribute = first($attributes);
			static::$factory[static::class] = $attribute->newInstance();
		}
		return static::$factory[static::class];
	}

	/**
	 * @param int      $id
	 * @param Row|null $row
	 *
	 * @return static
	 * @throws DirectoryCreationException
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 */
	public static function get(int $id, ?Row $row = null) : static {
		return self::$instances[static::TABLE][$id] ?? new static($id, $row);
	}

	/**
	 * @return ModelQuery<static>
	 */
	public static function query() : ModelQuery {
		return new ModelQuery(static::class);
	}

	/**
	 * Instantiate properties that have the Instantiate attribute
	 *
	 * Can instantiate only properties that have an installable class as its type.
	 *
	 * @return void
	 */
	protected function instantiateProperties() : void {
		$properties = $this::getPropertyReflections();
		foreach ($properties as $property) {
			$propertyName = $property->getName();
			$attributes = $property->getAttributes(Instantiate::class);
			// If the property does not have the Instantiate attribute - skip
			// If the property already has a value - skip
			if (empty($attributes) || isset($this->$propertyName)) {
				continue;
			}

			// Check type
			if (!$property->hasType()) {
				throw new RuntimeException(
					'Cannot initialize property '.self::class.'::'.$propertyName.' with no type.'
				);
			}
			/** @var ReflectionNamedType $type */
			$type = $property->getType();
			$className = $type->getName();
			if (!class_exists($className) || $type->isBuiltin()) {
				// Built in types are not supported - string, int, float,...
				// Non-built in types can also be interfaces or traits which is invalid. The type needs to be an instantiable class.
				throw new RuntimeException(
					'Cannot initialize property '.self::class.'::'.$propertyName.' with type '.$type->getName().'.'
				);
			}
			$this->$propertyName = new $className;
		}
	}

	public function getLogger() : Logger {
		if (!isset($this->logger)) {
			if (!isset(self::$modelLoggers[$this::TABLE])) {
				self::$modelLoggers[$this::TABLE] = new Logger(LOG_DIR.'models/', $this::TABLE);
			}
			$this->logger = self::$modelLoggers[$this::TABLE];
		}
		return $this->logger;
	}

	/**
	 * Checks if a model with given ID exists in database
	 *
	 * @param int $id
	 *
	 * @return bool
	 */
	public static function exists(int $id) : bool {
		$test = DB::select(static::TABLE, 'count(*)')
		          ->where('%n = %i', static::getPrimaryKey(), $id)
		          ->fetchSingle(cache: false);
		return $test > 0;
	}

	/**
	 * Get all models
	 *
	 * @return static[]
	 * @throws ValidationException
	 */
	public static function getAll() : array {
		return static::query()->get();
	}

	/**
	 * Clear cache for this model
	 *
	 * @return void
	 */
	public static function clearModelCache() : void {
		/** @var Cache $cache */
		$cache = App::getService('cache');
		$cache->clean([
			              CacheParent::Tags => [
				              static::TABLE,
			              ],
		              ]);
	}

	public static function clearInstances() : void {
		static::$instances = [];
	}

	/**
	 * @return bool
	 * @throws ValidationException
	 */
	public function save() : bool {
		$this->validate();
		return isset($this->id) ? $this->update() : $this->insert();
	}

	/**
	 * Validate the model's value
	 *
	 * @return void
	 * @throws ValidationException
	 */
	public function validate() : void {
		$properties = $this::getPropertyReflections();
		foreach ($properties as $property) {
			$attributes = $property->getAttributes(Validator::class, ReflectionAttribute::IS_INSTANCEOF);
			$propertyName = $property->getName();
			foreach ($attributes as $attributeReflection) {
				/** @var Validator $attribute */
				$attribute = $attributeReflection->newInstance();

				// Property is not set
				if (!isset($this->$propertyName)) {
					if ($attribute instanceof Required) {
						$attribute->throw($this, $propertyName);
					}
					continue;
				}

				$attribute->validateValue($this->$propertyName, $this, $propertyName);
			}
		}
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
			$this->clearCache();
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
	 * @return array<string, mixed>
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
					/** @var stdClass $typeInfo */
					$typeInfo = $attr->getType($property);

					$foreignKey = $attr->getForeignKey($typeInfo->class, $this);
					$localKey = $attr->getLocalKey($typeInfo->class, $this);

					if (empty($localKey)) {
						$localKey = $foreignKey;
					}

					switch ($relation->getName()) {
						case OneToOne::class:
						case ManyToOne::class:
							$data[$localKey] = $this->$propertyName?->id;
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
					if ($this->$propertyName instanceof DateTimeInterface || $this->$propertyName instanceof DateInterval) {
						$data[$columnName] = $this->$propertyName;
					}

					continue;
				}
			}

			if (!isset($this->$propertyName) && $this::getPrimaryKey() === $columnName) {
				continue;
			}

			$data[$columnName] = $this->$propertyName ?? null;
		}
		return $data;
	}

	/**
	 * Clear cache for this model instance
	 *
	 * @return void
	 */
	public function clearCache() : void {
		if (isset($this->id)) {
			/** @var Cache $cache */
			$cache = App::getService('cache');
			$cache->clean([
				              CacheParent::Tags => [
					              $this::TABLE.'/query',
					              $this::TABLE.'/'.$this->id,
					              $this::TABLE.'/'.$this->id.'/relations',
				              ],
			              ]);
		}
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
			$this::clearQueryCache();
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

	public static function clearQueryCache() : void {
		/** @var Cache $cache */
		$cache = App::getService('cache');
		$cache->clean([
			              CacheParent::Tags => [
				              static::TABLE.'/query',
			              ],
		              ]);
	}

	/**
	 * Specify data which should be serialized to JSON
	 *
	 * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
	 * @return array<string, mixed> data which can be serialized by <b>json_encode</b>,
	 * which is a value of any type other than a resource.
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
		if (isset($offset) && is_string($offset) && $this->offsetExists($offset)) {
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
			unset(static::$instances[$this::TABLE][$this->id]);
			$this->clearCache();
		} catch (Exception $e) {
			$this->logger->error($e->getMessage());
			$this->logger->debug($e->getTraceAsString());
			return false;
		}
		return true;
	}
}