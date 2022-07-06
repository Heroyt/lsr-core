<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace Lsr\Core\Models;

use Dibi\Fluent;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;

class ModelQuery
{

	protected Fluent $query;

	public function __construct(
		protected Model|string $className
	) {
		$this->query = DB::select([$this->className::TABLE, 'a'], '*');
	}

	public function where(...$cond) : ModelQuery {
		$this->query->where(...$cond);
		return $this;
	}

	public function limit(int $limit) : ModelQuery {
		$this->query->limit($limit);
		return $this;
	}

	public function offset(int $offset) : ModelQuery {
		$this->query->offset($offset);
		return $this;
	}

	public function join(...$table) : ModelQuery {
		$this->query->join(...$table);
		return $this;
	}

	public function on(...$cond) : ModelQuery {
		$this->query->on(...$cond);
		return $this;
	}

	public function asc() : ModelQuery {
		$this->query->asc();
		return $this;
	}

	public function desc() : ModelQuery {
		$this->query->desc();
		return $this;
	}

	public function orderBy(...$field) : ModelQuery {
		$this->query->orderBy(...$field);
		return $this;
	}

	public function count() : int {
		return $this->query->count();
	}

	public function first() : ?Model {
		$row = $this->query->fetch();
		if (!isset($row)) {
			return null;
		}
		$className = $this->className;
		return new $className($row->{$this->className::getPrimaryKey()}, $row);
	}

	/**
	 * @return Model[]
	 * @throws ValidationException
	 */
	public function get() : array {
		$pk = $this->className::getPrimaryKey();
		$rows = $this->query->fetchAll();
		$className = $this->className;
		$model = [];
		foreach ($rows as $row) {
			try {
				$model[$row->{$pk}] = $className::get($row->$pk, $row);
			} catch (ModelNotFoundException $e) {
			}
		}
		return $model;
	}

}