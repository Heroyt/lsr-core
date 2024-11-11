<?php
declare(strict_types=1);

namespace Lsr\Core;

use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Object mapper based on Symfony serializer
 */
class Mapper
{

    public function __construct(
      private readonly DenormalizerInterface $serializer,
    ) {}


    /**
     * @template T of object
     *
     * @param  class-string<T>  $type
     * @param  array<string,mixed>  $context
     *
     * @return T
     *
     * @throws NotNormalizableValueException
     * @throws PartialDenormalizationException Occurs when one or more properties of $type fails to denormalize
     * @throws ExceptionInterface
     */
    public function map(mixed $data, string $type, array $context = []) : mixed {
        return $this->serializer->denormalize($data, $type, null, $context);
    }

}