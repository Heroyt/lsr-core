<?php

namespace Lsr\Core\Models\Interfaces;

use Lsr\Core\Models\Model;

/**
 * @template T of Model
 */
interface FactoryInterface
{

    /**
     * @param  array<string, mixed>  $options
     *
     * @return T[]
     */
    public static function getAll(array $options = []) : array;

    /**
     * @param  int  $id
     * @param  array<string, mixed>  $options
     *
     * @return T|null
     */
    public static function getById(int $id, array $options = []) : ?Model;

}