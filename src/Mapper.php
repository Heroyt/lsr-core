<?php
declare(strict_types=1);

namespace Lsr\Core;

use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;
use function array_key_exists;
use function in_array;

/**
 * Object mapper based on Symfony serializer
 */
class Mapper
{

    public function __construct(
      private readonly DenormalizerInterface $serializer,
    ) {
    }


    /**
     * @template T of object
     *
     * @param class-string<T> $type
     *
     * @return T
     *
     * @throws NotNormalizableValueException
     * @throws PartialDenormalizationException Occurs when one or more properties of $type fails to denormalize
     * @throws ExceptionInterface
     */
    public function map(mixed $data, string $type, array $context = []): mixed
    {
        return $this->serializer->denormalize($data, $type, null, $context);
    }

}