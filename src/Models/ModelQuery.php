<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace Lsr\Core\Models;

use Lsr\Core\DB;
use Lsr\Core\Dibi\Fluent;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;

/**
 * @template T of Model
 */
class ModelQuery
{

    protected Fluent $query;

    /**
     * @param  class-string<T>  $className
     */
    public function __construct(
      protected string $className
    ) {
        $this->query = DB::select([$this->className::TABLE, 'a'], '*')
                         ->cacheTags(
                           'models',
                           $this->className::TABLE,
                           $this->className::TABLE.'/query',
                           ...
                           $this->className::CACHE_TAGS
                         );
    }

    public function cacheTags(string ...$tags) : static {
        $this->query->cacheTags(...$tags);
        return $this;
    }

    /**
     * @param  mixed  ...$cond
     *
     * @return $this
     */
    public function where(...$cond) : ModelQuery {
        $this->query->where(...$cond);
        return $this;
    }

    /**
     * @param  int  $limit
     *
     * @return $this
     */
    public function limit(int $limit) : ModelQuery {
        $this->query->limit($limit);
        return $this;
    }

    /**
     * @param  int  $offset
     *
     * @return $this
     */
    public function offset(int $offset) : ModelQuery {
        $this->query->offset($offset);
        return $this;
    }

    /**
     * @param  mixed  ...$table
     *
     * @return $this
     */
    public function join(...$table) : ModelQuery {
        $this->query->join(...$table);
        return $this;
    }

    /**
     * @param  mixed  ...$table
     *
     * @return $this
     */
    public function leftJoin(...$table) : ModelQuery {
        $this->query->leftJoin(...$table);
        return $this;
    }

    /**
     * @param  mixed  ...$table
     *
     * @return $this
     */
    public function rightJoin(...$table) : ModelQuery {
        $this->query->rightJoin(...$table);
        return $this;
    }

    /**
     * @param  mixed  ...$cond
     *
     * @return $this
     */
    public function on(...$cond) : ModelQuery {
        $this->query->on(...$cond);
        return $this;
    }

    /**
     * @return $this
     */
    public function asc() : ModelQuery {
        $this->query->asc();
        return $this;
    }

    /**
     * @return $this
     */
    public function desc() : ModelQuery {
        $this->query->desc();
        return $this;
    }

    /**
     * @param  mixed  ...$field
     *
     * @return $this
     */
    public function orderBy(...$field) : ModelQuery {
        $this->query->orderBy(...$field);
        return $this;
    }

    public function count(bool $cache = true) : int {
        return $this->query->count(cache: $cache);
    }

    /**
     * @return T|null
     */
    public function first(bool $cache = true) : ?Model {
        $row = $this->query->fetch(cache: $cache);
        if (!isset($row)) {
            return null;
        }
        $className = $this->className;
        return new $className($row->{$this->className::getPrimaryKey()}, $row);
    }

    /**
     * @return T[]
     * @throws ValidationException
     */
    public function get(bool $cache = true) : array {
        $pk = $this->className::getPrimaryKey();
        $rows = $this->query->fetchAll(cache: $cache);
        $className = $this->className;
        $models = [];
        foreach ($rows as $row) {
            try {
                $models[$row->{$pk}] = $className::get($row->$pk, $row);
            } catch (ModelNotFoundException) {
            }
        }
        return $models;
    }

}