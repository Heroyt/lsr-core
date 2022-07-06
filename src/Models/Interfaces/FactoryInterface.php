<?php

namespace Lsr\Core\Models\Interfaces;

use Lsr\Core\Models\Model;

interface FactoryInterface
{

	public static function getAll(array $options = []) : array;

	public static function getById(int $id, array $options = []) : ?Model;

}