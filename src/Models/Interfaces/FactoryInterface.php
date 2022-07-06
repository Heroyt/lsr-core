<?php

namespace Lsr\Core\Models\Interfaces;

use Lsr\Core\Models\Model;

interface FactoryInterface
{

	public static function getAll() : array;

	public static function getById(int $id) : Model;

}