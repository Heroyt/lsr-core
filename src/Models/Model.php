<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace Lsr\Core\Models;

use ArrayAccess;
use DateInterval;
use DateTime;
use Dibi\Exception;
use Dibi\Row;
use Error;
use JsonSerializable;
use Lsr\Core\App;
use Lsr\Core\Caching\Cache;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\UndefinedPropertyException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\ManyToMany;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\NoDB;
use Lsr\Core\Models\Attributes\OneToMany;
use Lsr\Core\Models\Attributes\OneToOne;
use Lsr\Core\Models\Attributes\Validation\Required;
use Lsr\Core\Models\Attributes\Validation\Validator;
use Lsr\Core\Models\Interfaces\InsertExtendInterface;
use Lsr\Helpers\Tools\Strings;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Lsr\Logging\Logger;
use Nette\Caching\Cache as CacheParent;
use ReflectionAttribute;
use RuntimeException;

/**
 * @implements ArrayAccess<string, mixed>
 * @phpstan-consistent-constructor
 * @phpstan-import-type RelationConfig from ModelConfig
 * @phpstan-import-type PropertyConfig from ModelConfig
 */
abstract class Model implements JsonSerializable, ArrayAccess
{
	use ModelConfigProvider;

	/** @var string Database table name */
	public const TABLE = '';
	/** @var string[] Static tags to add to all cache records for this model */
	public const    CACHE_TAGS              = [];
	protected const JSON_EXCLUDE_PROPERTIES = ['row', 'cacheTags', 'logger', 'relationIds'];

	/** @var static[][] Model instance cache */
	protected static array $instances = [];
	/** @var array<string, Logger> */
	protected static array $modelLoggers = [];
	#[NoDB]
	public ?int      $id  = null;
	protected ?Row   $row = null;
	protected Logger $logger;
	/** @var string[] Dynamic tags to add to cache records for this model instance */
	protected array $cacheTags = [];

	/** @var array<string, int> */
	protected array $relationIds = [];

	/**
	 * @param int|null $id    DB model ID
	 * @param Row|null $dbRow Prefetched database row
	 *
	 * @throws DirectoryCreationException
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 */
	public function __construct(?int $id = null, ?Row $dbRow = null) {
		if (!isset(self::$instances[$this::class])) {
			self::$instances[$this::class] = [];
		}
		$pk = $this::getPrimaryKey();
		if (isset($dbRow->$pk) && !isset($id)) {
			/** @noinspection NullPointerExceptionInspection */
			$id = (int)$dbRow->$pk;
		}
		if (isset($id) && !empty($this::TABLE)) {
			$this->id = $id;
			self::$instances[$this::class][$this->id] = $this;
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
	 * Fetch model's data from DB
	 *
	 * @param bool $refresh
	 *
	 * @throws DirectoryCreationException
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 */
	public function fetch(bool $refresh = false): void {
		if (!isset($this->id) || $this->id <= 0) {
			throw new RuntimeException('Id needs to be set before fetching model\'s data.');
		}
		if ($refresh || !isset($this->row)) {
			/** @var Row|null $row */
			$row = DB::select($this::TABLE, '*')->where('%n = %i', $this::getPrimaryKey(), $this->id)->cacheTags(
				...
				$this->getCacheTags()
			)->fetch();
			$this->row = $row;
		}
		if (!isset($this->row)) {
			throw new ModelNotFoundException(get_class($this) . ' model of ID ' . $this->id . ' was not found.');
		}
		$this->fillFromRow();
	}

	/**
	 * @return string[]
	 */
	protected function getCacheTags(): array {
		return array_merge(
			['models', $this::TABLE, $this::TABLE . '/' . $this->id],
			$this::CACHE_TAGS,
			$this->cacheTags,
		);
	}

	/**
	 * @return void
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 */
	protected function fillFromRow(): void {
		if (!isset($this->row)) {
			return;
		}

		$row = $this->row->toArray();

		foreach ($this::getProperties() as $name => $property) {
			if ($property['isExtend']) {
				/** @var class-string<InsertExtendInterface> $class */
				$class = $property['type'];
				// @phpstan-ignore-next-line
				$this->$name = $class::parseRow($this->row);
				continue;
			}

			if (isset($property['relation'])) {
				$this->processRelation($name, $property['relation'], $property);
				continue;
			}

			if (array_key_exists($name, $row)) {
				$val = $row[$name];
			}
			else {
				$snakeName = Strings::toSnakeCase($name);
				if (!array_key_exists($snakeName, $row)) {
					// TODO: Maybe throw an exception
					continue;
				}
				$val = $row[$snakeName];
			}

			if ($property['isPrimaryKey']) {
				$this->id = $val;
				continue;
			}

			$this->setProperty($name, $val, $property);
		}
	}

	/**
	 * @param string              $propertyName
	 * @param RelationConfig      $relation
	 * @param PropertyConfig|null $property
	 *
	 * @return void
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 */
	protected function processRelation(string $propertyName, array $relation, ?array $property = null): void {
		if (!isset($property)) {
			$property = $this::getProperties()[$propertyName] ?? null;
			if (!isset($property)) {
				throw new UndefinedPropertyException('Undefined property ' . $this::class . '::$' . $propertyName);
			}
		}

		$className = $relation['class'];
		$factory = $className::getFactory();

		$foreignKey = $relation['foreignKey'];
		$localKey = $relation['localKey'];

		switch ($relation['type']) {
			case ManyToOne::class:
			case OneToOne::class:
				/** @var int|null $id */ $id = $this->row?->$localKey;
				$this->relationIds[$propertyName] = $id;

				// Skip lazy-loaded relations
				// The model class should implement its own loading method
				if ($relation['loadingType'] === LoadingType::LAZY) {
					break;
				}

				// Check for nullable relations
				if (is_null($id)) {
					if (!$property['allowsNull']) {
						throw new ValidationException('Cannot assign null to a non nullable relation');
					}
					$this->$propertyName = null;
					break;
				}

				// Get the relation
				try {
					$this->$propertyName = isset($factory) ? $factory->factoryClass::getById(
						$id,
						$factory->defaultOptions
					) : $className::get($id);
				} catch (ModelNotFoundException $e) {
					if (!$property['allowsNull']) {
						throw $e;
					}
					// Default to null
					$this->$propertyName = null;
				}
				break;
			case OneToMany::class:
				// Skip lazy-loaded relations
				if ($relation['loadingType'] === LoadingType::LAZY) {
					break;
				}
				$id = $this->id;
				$this->$propertyName = $className::query()->where('%n = %i', $foreignKey, $id)->cacheTags(
						$this::TABLE . '/' . $this->id . '/relations'
					)->get();
				break;
			case ManyToMany::class:
				// Skip lazy-loaded relations
				if ($relation['loadingType'] === LoadingType::LAZY) {
					break;
				}
				/** @var ManyToMany $attributeClass */
				$attributeClass = unserialize($relation['instance'], ['allowedClasses' => [ManyToMany::class]]);
				/** @var int $id */
				$id = $this->id;
				$this->$propertyName = $className::query()->where(
					'%n IN %sql',
					$foreignKey,
					$attributeClass->getConnectionQuery($id, $className, $this)
					)->cacheTags($this::TABLE . '/' . $this->id . '/relations')->get();
				break;
		}
	}

	/**
	 * Get one instance of the model by its ID
	 *
	 * @param int      $id
	 * @param Row|null $row
	 *
	 * @return static
	 * @throws DirectoryCreationException
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 */
	public static function get(int $id, ?Row $row = null): static {
		return static::$instances[static::class][$id] ?? new static($id, $row);
	}

	/**
	 * Start to query the model
	 *
	 * @return ModelQuery<static>
	 */
	public static function query(): ModelQuery {
		return new ModelQuery(static::class);
	}

	/**
	 * Set property value from the database
	 *
	 * @param string              $name
	 * @param mixed               $value
	 * @param PropertyConfig|null $property
	 *
	 * @return void
	 */
	protected function setProperty(string $name, mixed $value, ?array $property = null): void {
		if (empty($property)) {
			$property = $this::getProperties()[$name] ?? null;
			if (!isset($property)) {
				throw new UndefinedPropertyException('Undefined property ' . $this::class . '::$' . $name);
			}
		}

		if (!$property['isBuiltin']) {
			if ($value instanceof DateInterval && $property['isDateTime']) {
				$value = new DateTime($value->format('%H:%i:%s'));
			}
			if ($property['isEnum']) {
				$enum = $property['type'];
				$value = $enum::tryFrom($value);
			}
		}

		if ($value === null && $property['isBuiltin'] && !$property['allowsNull']) {
			switch ($property['type']) {
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

		$this->$name = $value;
	}

	/**
	 * Instantiate properties that have the Instantiate attribute
	 *
	 * Can instantiate only properties that have an installable class as its type.
	 *
	 * @return void
	 */
	protected function instantiateProperties(): void {
		$properties = $this::getProperties();
		foreach ($properties as $propertyName => $property) {
			// If the property does not have the Instantiate attribute - skip
			// If the property already has a value - skip
			if (!$property['instantiate'] || isset($this->$propertyName)) {
				continue;
			}

			// Check type
			if (!$property['type']) {
				throw new RuntimeException(
					'Cannot initialize property ' . static::class . '::' . $propertyName . ' with no type.'
				);
			}
			$className = $property['type'];
			if ($property['isBuiltin'] || !class_exists($className)) {
				// Built in types are not supported - string, int, float,...
				// Non-built in types can also be interfaces or traits which is invalid. The type needs to be an instantiable class.
				throw new RuntimeException(
					'Cannot initialize property ' . static::class . '::' . $propertyName . ' with type ' . $property['type'] . '.'
				);
			}
			$this->$propertyName = new $className;
		}
	}

	/**
	 * Get logger for this model type
	 *
	 * @return Logger
	 */
	public function getLogger(): Logger {
		if (!isset($this->logger)) {
			if (!isset(self::$modelLoggers[$this::TABLE])) {
				self::$modelLoggers[$this::TABLE] = new Logger(LOG_DIR . 'models/', $this::TABLE);
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
	public static function exists(int $id): bool {
		$test = DB::select(static::TABLE, 'count(*)')->where('%n = %i', static::getPrimaryKey(), $id)->fetchSingle(
			cache: false
		);
		return $test > 0;
	}

	/**
	 * Get all models
	 *
	 * @return static[]
	 * @throws ValidationException
	 */
	public static function getAll(): array {
		return static::query()->get();
	}

	/**
	 * Clear cache for this model
	 *
	 * @return void
	 */
	public static function clearModelCache(): void {
		/** @var Cache $cache */
		$cache = App::getService('cache');
		$cache->clean([
			              CacheParent::Tags => [
				              static::TABLE,
			              ],
		              ]);
	}

	/**
	 * Clear instance cache
	 *
	 * @return void
	 */
	public static function clearInstances(): void {
		static::$instances = [];
	}

	/**
	 * Save the model into a database
	 *
	 * @return bool
	 * @throws ValidationException
	 */
	public function save(): bool {
		$this->validate();
		return isset($this->id) ? $this->update() : $this->insert();
	}

	/**
	 * Validate the model's value
	 *
	 * @return void
	 * @throws ValidationException
	 */
	public function validate(): void {
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
	 * Update model in the DB
	 *
	 * @return bool If the update was successful
	 * @throws ValidationException
	 */
	public function update(): bool {
		if (!isset($this->id)) {
			return false;
		}
		$this->logger->info('Updating model - ' . $this->id);
		try {
			DB::update($this::TABLE, $this->getQueryData(), ['%n = %i', $this::getPrimaryKey(), $this->id]);
			$this->clearCache();
		} catch (Exception $e) {
			$this->logger->error('Error running update query: ' . $e->getMessage());
			$this->logger->debug('Query: ' . $e->getSql());
			$this->logger->debug('Trace: ' . $e->getTraceAsString());
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
	public function getQueryData(): array {
		$data = [];

		foreach ($this::getProperties() as $propertyName => $property) {
			if ($property['noDb'] || (!isset($this->$propertyName) && $property['isPrimaryKey'])) {
				continue;
			}

			// Handle relations
			if ($property['relation'] !== null) {
				/** @var RelationConfig $relation */
				$relation = $property['relation'];

				// Do not include lazy-loaded fields that have not been set yet
				try {
					if ($relation['loadingType'] === LoadingType::LAZY && !isset($this->$propertyName) && !is_null(
							$this->$propertyName
						)) {
						continue;
					}
				} catch (Error $e) {
					if (str_contains($e->getMessage(), 'must not be accessed before initialization')) {
						continue;
					}
					throw $e;
				}

				switch ($relation['type']) {
					case OneToOne::class:
					case ManyToOne::class:
						$data[empty($relation['localKey']) ? $relation['foreignKey'] : $relation['localKey']] = $this->$propertyName?->id;
						break;
				}
				continue;
			}

			// Handle insert-extend mapping
			if ($property['isExtend']) {
				$this->$propertyName->addQueryData($data);
				continue;
			}

			$columnName = Strings::toSnakeCase($propertyName);

			// Handle enum values
			if ($property['isEnum']) {
				$data[$columnName] = $this->$propertyName->value;
				continue;
			}

			$data[$columnName] = $this->$propertyName ?? null;
		}
		return $data;
	}

	/**
	 * Clear cache for this model instance
	 *
	 * @post Clear cache for this specific instance
	 *
	 * @return void
	 * @see  Cache
	 *
	 */
	public function clearCache(): void {
		if (isset($this->id)) {
			/** @var Cache $cache */
			$cache = App::getService('cache');
			$cache->clean([
				              CacheParent::Tags => [
					              $this::TABLE . '/query',
					              $this::TABLE . '/' . $this->id,
					              $this::TABLE . '/' . $this->id . '/relations',
				              ],
			              ]);
		}
	}

	/**
	 * Insert a new model into the DB
	 *
	 * @return bool
	 * @throws ValidationException
	 */
	public function insert(): bool {
		$this->logger->info('Inserting new model');
		try {
			DB::insert($this::TABLE, $this->getQueryData());
			$this->id = DB::getInsertId();
			$this::clearQueryCache();
		} catch (Exception $e) {
			$this->logger->error('Error running insert query: ' . $e->getMessage());
			$this->logger->debug('Query: ' . $e->getSql());
			$this->logger->debug('Trace: ' . $e->getTraceAsString());
			return false;
		}
		if (empty($this->id)) {
			$this->logger->error('Insert query passed, but ID was not returned.');
			return false;
		}
		self::$instances[$this::class][$this->id] = $this;
		return true;
	}

	/**
	 * Clear cache for model queries (the Model::query() method)
	 *
	 * @return void
	 * @see Model::query()
	 *
	 */
	public static function clearQueryCache(): void {
		/** @var Cache $cache */
		$cache = App::getService('cache');
		$cache->clean([
			              CacheParent::Tags => [
				              static::TABLE . '/query',
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
	public function jsonSerialize(): array {
		$vars = get_object_vars($this);
		foreach ($this::JSON_EXCLUDE_PROPERTIES as $prop) {
			if (isset($vars[$prop])) {
				unset($vars[$prop]);
			}
		}
		return $vars;
	}

	/**
	 * @inheritdoc
	 */
	public function offsetGet($offset): mixed {
		if ($this->offsetExists($offset)) {
			return $this->$offset;
		}
		return null;
	}

	/**
	 * @inheritdoc
	 */
	public function offsetExists($offset): bool {
		return property_exists($this, $offset);
	}

	/**
	 * @inheritdoc
	 */
	public function offsetSet($offset, $value): void {
		if (isset($offset) && is_string($offset) && $this->offsetExists($offset)) {
			$this->$offset = $value;
		}
	}

	/**
	 * @inheritdoc
	 */
	public function offsetUnset($offset): void {
		// Do nothing
	}

	/**
	 * Delete model from DB
	 *
	 * @return bool
	 */
	public function delete(): bool {
		if (!isset($this->id)) {
			return false;
		}
		$this->getLogger()->info('Delete model: ' . $this::TABLE . ' of ID: ' . $this->id);
		try {
			DB::delete($this::TABLE, ['%n = %i', $this::getPrimaryKey(), $this->id]);
			unset(static::$instances[$this::class][$this->id]);
			$this->clearCache();
		} catch (Exception $e) {
			$this->getLogger()->error($e->getMessage());
			$this->getLogger()->debug($e->getTraceAsString());
			return false;
		}
		return true;
	}
}