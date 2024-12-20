<?php

namespace Lsr\Core\Models;

use BackedEnum;
use DateTimeInterface;
use Lsr\Core\Models\Attributes\Factory;
use Lsr\Core\Models\Attributes\Instantiate;
use Lsr\Core\Models\Attributes\ManyToMany;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\ModelRelation;
use Lsr\Core\Models\Attributes\NoDB;
use Lsr\Core\Models\Attributes\OneToMany;
use Lsr\Core\Models\Attributes\OneToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Interfaces\InsertExtendInterface;
use Lsr\Helpers\Tools\Strings;
use Lsr\Logging\FsHelper;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;
use RuntimeException;
use stdClass;

/**
 * @phpstan-import-type PropertyConfig from ModelConfig
 */
trait ModelConfigProvider
{

    /** @var string[] Primary key cache */
    protected static array $primaryKeys = [];

    /** @var ReflectionClass<Model>[] */
    protected static array $reflections = [];

    /** @var Factory[] */
    protected static array $factory = [];

    /** @var string|null If the config file is currently generating, this is set to the Model's class name */
    protected static ?string $generatingConfig = null;
    /** @var array<class-string<Model>, string> */
    private static array $cacheFileName = [];
    /** @var array<class-string<Model>, string> */
    private static array $cacheClassName = [];
    /** @var array<class-string<Model>, ModelConfig> */
    private static array $modelConfig = [];

    /**
     * Get model's primary key
     *
     * @return string Primary key's column name
     * @todo: Support mixed primary keys
     *
     */
    public static function getPrimaryKey() : string {
        // Prevent infinite loop due to cyclic relations
        if (!static::canUseConfig()) {
            return static::findPrimaryKey();
        }
        return static::getModelConfig()->primaryKey;
    }

    protected static function canUseConfig() : bool {
        if (isset(self::$modelConfig[static::class]) || !isset(self::$generatingConfig)) {
            return true;
        }
        if (file_exists(static::getCacheFileName())) {
            return true;
        }
        return false;
    }

    /**
     * Get generated config file path
     *
     * @return string Absolute path to the generated PHP file
     */
    protected static function getCacheFileName() : string {
        self::$cacheFileName[static::class] ??= TMP_DIR.'models/'.static::getCacheClassName().'.php';
        return self::$cacheFileName[static::class];
    }

    /**
     * Get generated config class name
     *
     * @return string
     */
    public static function getCacheClassName() : string {
        self::$cacheClassName[static::class] ??= str_replace('\\', '_', static::class).'_Config';
        return self::$cacheClassName[static::class];
    }

    /**
     * Find the class's primary key from the class attribute
     *
     * @return string
     */
    public static function findPrimaryKey() : string {
        if (!empty(self::$primaryKeys[static::class])) {
            return self::$primaryKeys[static::class];
        }
        try {
            // Try to find a property with a PrimaryKey attribute
            $reflection = new ReflectionClass(static::class);

            $attributes = $reflection->getAttributes(PrimaryKey::class);
            if (!empty($attributes)) {
                /** @var ReflectionAttribute<ModelRelation> $attribute */
                $attribute = first($attributes);
                /** @var PrimaryKey $attr */
                $attr = $attribute->newInstance();
                self::$primaryKeys[static::class] = $attr->column;
                return self::$primaryKeys[static::class];
            }

            // Try to create a primary key name from model name
            $pascal = $reflection->getShortName();
            $camel = Strings::toCamelCase($reflection->getShortName());

            $snakeCase = Strings::toSnakeCase($reflection->getShortName());
            if (property_exists(static::class, 'id_'.$snakeCase) || property_exists(
                static::class,
                'id'.$pascal
              )) {
                self::$primaryKeys[static::class] = 'id_'.$snakeCase;
                return self::$primaryKeys[static::class];
            }
            if (property_exists(static::class, $snakeCase.'_id') || property_exists(
                static::class,
                $camel.'Id'
              )) {
                self::$primaryKeys[static::class] = $snakeCase.'_id';
                return self::$primaryKeys[static::class];
            }
        } catch (ReflectionException) {
        }

        self::$primaryKeys[static::class] = 'id';
        return self::$primaryKeys[static::class];
    }

    /**
     * Get the Model's config cache object
     *
     * @return ModelConfig
     */
    protected static function getModelConfig() : ModelConfig {
        if (isset(self::$modelConfig[static::class])) {
            return self::$modelConfig[static::class];
        }
        // Check cache
        if (!file_exists(static::getCacheFileName())) {
            static::createConfigModel();
        }

        self::$modelConfig[static::class] = require static::getCacheFileName();
        return self::$modelConfig[static::class];
    }

    /**
     * Create the config cache model and save it to the PHP cache file
     *
     * @post The cache file will be created.
     *
     * @return void
     */
    protected static function createConfigModel() : void {
        self::$generatingConfig = static::class;
        $file = new PhpFile();
        $file->addComment('This is an autogenerated file containing model configuration of '.static::class)
             ->setStrictTypes();

        $class = $file->addClass(static::getCacheClassName());
        $class->setExtends(ModelConfig::class)->setFinal();

        $class->addProperty('primaryKey', static::findPrimaryKey())->setType('string');
        $class->addProperty('properties', static::findProperties())->setType('array');

        $factory = static::findFactory();
        $class->addProperty(
          'factoryConfig',
          isset($factory) ? [
            'factoryClass'   => $factory->factoryClass,
            'defaultOptions' => $factory->defaultOptions,
          ] : null
        )->setType('array')->setNullable();

        self::$generatingConfig = null;

        // Maybe create the cache directory
        $helper = FsHelper::getInstance();
        $helper->createDirRecursive($helper->extractPath(TMP_DIR.'models'));

        if (file_put_contents(
            static::getCacheFileName(),
            (new PsrPrinter())->printFile($file)."\nreturn new ".$class->getName().';'
          ) === false) {
            throw new RuntimeException('Cannot save file: '.static::getCacheFileName());
        }
    }

    /**
     * Find all Model's properties and relations that should be mapped from the DB
     *
     * @return array<non-empty-string, PropertyConfig>
     *
     * @phpstan-ignore return.type
     */
    protected static function findProperties() : array {
        $properties = [];
        foreach (static::getPropertyReflections(ReflectionProperty::IS_PUBLIC) as $property) {
            $propertyName = $property->getName();
            $properties[$propertyName] = [
              'name'         => $propertyName,
              'isPrimaryKey' => $propertyName === static::findPrimaryKey(),
              'allowsNull'   => false,
              'isBuiltin'    => false,
              'isExtend'     => false,
              'isEnum'       => false,
              'isDateTime'   => false,
              'instantiate'  => !empty($property->getAttributes(Instantiate::class)),
              'noDb'         => !empty($property->getAttributes(NoDB::class)),
              'type'         => null,
              'relation'     => null,
            ];
            if ($property->hasType()) {
                // Check enum and date values
                $type = $property->getType();
                if ($type instanceof ReflectionNamedType) {
                    $properties[$propertyName]['allowsNull'] = $type->allowsNull();
                    $properties[$propertyName]['type'] = $type->getName();
                    if ($type->isBuiltin()) {
                        $properties[$propertyName]['isBuiltin'] = true;
                        $properties[$propertyName]['type'] = $type->getName();
                    }
                    else {
                        $implements = class_implements($type->getName());
                        if (!is_array($implements)) {
                            $implements = [];
                        }
                        $properties[$propertyName]['isExtend'] = in_array(
                          InsertExtendInterface::class,
                          $implements,
                          true
                        );
                        $properties[$propertyName]['isEnum'] = in_array(
                          BackedEnum::class,
                          $implements,
                          true
                        );
                        $properties[$propertyName]['isDateTime'] = in_array(
                          DateTimeInterface::class,
                          $implements,
                          true
                        );
                    }
                }
            }

            // Check relations
            $attributes = $property->getAttributes(ModelRelation::class, ReflectionAttribute::IS_INSTANCEOF);
            if (count($attributes) > 1) {
                throw new RuntimeException(
                  'Cannot have more than 1 relation attribute on a property: '.static::class.'::$'.$propertyName
                );
            }
            foreach ($attributes as $attribute) {
                /** @var ManyToOne|OneToMany|OneToOne|ManyToMany $attributeClass */
                $attributeClass = $attribute->newInstance();

                /** @var stdClass $info */
                $info = $attributeClass->getType($property);
                /** @var Model $className */
                $className = $info->class;
                $factory = $className::getFactory();

                $foreignKey = $attributeClass->getForeignKey($className, static::class);
                $localKey = $attributeClass->getLocalKey($className, static::class);

                $properties[$propertyName]['relation'] = [
                  'type'        => $attributeClass::class,
                  'instance'    => serialize($attributeClass),
                  'class'       => $className,
                  'factory'     => isset($factory) ? $factory::class : null,
                  'foreignKey'  => $foreignKey,
                  'localKey'    => $localKey,
                  'loadingType' => $attributeClass->loadingType,
                ];
            }
        }

        /** @phpstan-ignore return.type */
        return $properties;
    }

    /**
     * Get reflections for all the Model's properties
     *
     * @return ReflectionProperty[]
     */
    protected static function getPropertyReflections(?int $filter = null) : array {
        return static::getReflection()->getProperties($filter);
    }

    /**
     * Get all Model's properties
     *
     * @return array<string, PropertyConfig>
     */
    protected static function getProperties() : array {
        // Prevent infinite loop due to cyclic relations
        if (!static::canUseConfig()) {
            return static::findProperties();
        }
        return static::getModelConfig()->properties;
    }

    /**
     * Get a reflection class for the whole model
     *
     * @return ReflectionClass<Model>
     */
    protected static function getReflection() : ReflectionClass {
        if (!isset(self::$reflections[static::class])) {
            self::$reflections[static::class] = (new ReflectionClass(static::class));
        }
        return self::$reflections[static::class];
    }

    /**
     * Get model's factory
     *
     * @return Factory|null
     */
    public static function getFactory() : ?Factory {
        // Prevent infinite loop due to cyclic relations
        if (!static::canUseConfig()) {
            return static::findFactory();
        }
        return static::getModelConfig()->getFactory();
    }

    /**
     * Find a model's factory
     *
     * @return Factory|null
     */
    protected static function findFactory() : ?Factory {
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
     * Get a reflection class for a property
     *
     * @param  string  $name  Property name
     *
     * @return ReflectionProperty
     */
    public static function getPropertyReflection(string $name) : ReflectionProperty {
        return static::getReflection()->getProperty($name);
    }

}