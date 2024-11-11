<?php

namespace Lsr\Core\Serializer\Normalizer;

use Dibi\Row;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorFromClassMetadata;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorResolverInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use function is_callable;

final class DibiRowNormalizer extends AbstractObjectNormalizer
{

    /**
     * @param  array<string,mixed>  $defaultContext
     */
    public function __construct(
      ?ClassMetadataFactoryInterface       $classMetadataFactory = null,
      ?NameConverterInterface              $nameConverter = null,
      ?PropertyTypeExtractorInterface      $propertyTypeExtractor = null,
      ?ClassDiscriminatorResolverInterface $classDiscriminatorResolver = null,
      ?callable                            $objectClassResolver = null,
      array                                $defaultContext = [],
    ) {
        parent::__construct(
          $classMetadataFactory,
          $nameConverter,
          $propertyTypeExtractor,
          $classDiscriminatorResolver,
          $objectClassResolver,
          $defaultContext
        );

        if (isset($this->defaultContext[self::MAX_DEPTH_HANDLER]) && !is_callable(
            $this->defaultContext[self::MAX_DEPTH_HANDLER]
          )) {
            throw new InvalidArgumentException(
              sprintf('The "%s" given in the default context is not callable.', self::MAX_DEPTH_HANDLER)
            );
        }

        $this->defaultContext[self::EXCLUDE_FROM_CACHE_KEY] = array_merge(
          $this->defaultContext[self::EXCLUDE_FROM_CACHE_KEY] ?? [],
          [self::CIRCULAR_REFERENCE_LIMIT_COUNTERS]
        );

        if ($classMetadataFactory) {
            $classDiscriminatorResolver ??= new ClassDiscriminatorFromClassMetadata($classMetadataFactory);
        }
        $this->classDiscriminatorResolver = $classDiscriminatorResolver;
    }

    public function getSupportedTypes(?string $format) : array {
        return [
          Row::class => true,
        ];
    }

    /**
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    protected function extractAttributes(object $object, ?string $format = null, array $context = []) : array {
        assert($object instanceof Row, 'Invalid input object.');
        return $object->toArray();
    }

    /**
     * @param  array<string,mixed>  $context
     */
    protected function getAttributeValue(
      object  $object,
      string  $attribute,
      ?string $format = null,
      array   $context = []
    ) : mixed {
        assert($object instanceof Row, 'Invalid input object.');
        return $object->{$attribute};
    }

    /**
     * @param  array<string,mixed>  $context
     */
    protected function setAttributeValue(
      object  $object,
      string  $attribute,
      mixed   $value,
      ?string $format = null,
      array   $context = []
    ) : void {
        assert($object instanceof Row, 'Invalid input object.');
        $object->{$attribute} = $value;
    }
}
