<?php

namespace Lsr\Core\Dibi;

use Dibi\Fluent as DibiFluent;
use Dibi\Result;
use Dibi\Row;
use Lsr\Core\App;
use Lsr\Core\Caching\Cache;
use Nette\Caching\Cache as CacheParent;
use Throwable;

/**
 * SQL builder via fluent interfaces.
 *
 * @method Fluent distinct()
 * @method Fluent where(...$cond)
 * @method Fluent groupBy(...$field)
 * @method Fluent having(...$cond)
 * @method Fluent orderBy(...$field)
 * @method Fluent limit(int $limit)
 * @method Fluent offset(int $offset)
 * @method Fluent join(...$table)
 * @method Fluent leftJoin(...$table)
 * @method Fluent innerJoin(...$table)
 * @method Fluent rightJoin(...$table)
 * @method Fluent outerJoin(...$table)
 * @method Fluent as(...$field)
 * @method Fluent on(...$cond)
 * @method Fluent and (...$cond)
 * @method Fluent or (...$cond)
 * @method Fluent using(...$cond)
 * @method Fluent into(...$cond)
 * @method Fluent values(...$cond)
 * @method Fluent set(...$args)
 * @method Fluent asc()
 * @method Fluent desc()
 * @method Result|int|null execute(string $return = null)
 */
class Fluent
{

    protected const CACHE_EXPIRE = '1 hours';
    protected ?string $queryHash = null;

    protected Cache $cache;

    /** @var string[] */
    protected array $cacheTags = [];
    private string $table;
    private string $method;

    public function __construct(public DibiFluent $fluent) {}

    /**
     * @param  string  $name
     * @param  array<string|int,mixed>  $arguments
     *
     * @return mixed
     * @noinspection PhpMissingParamTypeInspection
     */
    public static function __callStatic($name, $arguments) : mixed {
        return DibiFluent::$name(...$arguments);
    }

    public function select(mixed ...$field) : Fluent {
        foreach ($field as $key => $arg) {
            if ($arg instanceof static) {
                $field[$key] = $arg->fluent;
            }
        }
        $this->method = 'select';
        $this->fluent->select(...$field);
        $this->queryHash = null;
        return $this;
    }

    public function delete(mixed ...$cond) : Fluent {
        foreach ($cond as $key => $arg) {
            if ($arg instanceof static) {
                $cond[$key] = $arg->fluent;
            }
        }
        $this->method = 'delete';
        $this->fluent->delete(...$cond);
        $this->queryHash = null;
        return $this;
    }

    public function update(mixed ...$cond) : Fluent {
        foreach ($cond as $key => $arg) {
            if ($arg instanceof static) {
                $cond[$key] = $arg->fluent;
            }
        }
        $this->method = 'update';
        $this->fluent->update(...$cond);
        $this->queryHash = null;
        return $this;
    }

    public function insert(mixed ...$cond) : Fluent {
        foreach ($cond as $key => $arg) {
            if ($arg instanceof static) {
                $cond[$key] = $arg->fluent;
            }
        }
        $this->method = 'insert';
        $this->fluent->insert(...$cond);
        $this->queryHash = null;
        return $this;
    }

    public function __clone() : void {
        $this->fluent = clone $this->fluent;
    }

    /**
     * Generates, executes SQL query and fetches the single row.
     *
     * @return Row|null|array<string,mixed>
     */
    public function fetch(bool $cache = true) : Row | array | null {
        if (!$cache) {
            return $this->fluent->fetch();
        }
        try {
            /** @phpstan-ignore-next-line */
            return $this->getCache()->load(
              'sql/'.$this->getQueryHash().'/fetch',
              function () {
                  return $this->fluent->fetch();
              },
              [
                CacheParent::Expire => $this::CACHE_EXPIRE,
                CacheParent::Tags   => $this->getCacheTags(),
              ]
            );
        } catch (Throwable) {
            return $this->fluent->fetch();
        }
    }

    /**
     * @return Cache
     */
    public function getCache() : Cache {
        if (!isset($this->cache)) {
            /** @phpstan-ignore-next-line */
            $this->cache = App::getService('cache');
        }
        /** @phpstan-ignore-next-line */
        return $this->cache;
    }

    protected function getQueryHash() : string {
        if (!isset($this->queryHash)) {
            $this->queryHash = md5($this->fluent->__toString());
        }
        return $this->queryHash;
    }

    public function __toString() {
        return $this->fluent->__toString();
    }

    /**
     * @return string[]
     */
    private function getCacheTags() : array {
        $tags = $this->cacheTags;
        $tags[] = 'sql';
        if (isset($this->table)) {
            $tags[] = 'sql/'.$this->table;
        }
        return $tags;
    }

    public function cacheTags(string ...$tags) : static {
        $this->cacheTags = array_merge($this->cacheTags, $tags);
        return $this;
    }

    /**
     * Like fetch(), but returns only first field.
     *
     * @return mixed  value on success, null if no next record
     */
    public function fetchSingle(bool $cache = true) : mixed {
        if (!$cache) {
            return $this->fluent->fetchSingle();
        }
        try {
            return $this->getCache()->load(
              'sql/'.$this->getQueryHash().'/fetchSingle',
              function (array &$dependencies) {
                  $dependencies[CacheParent::Expire] = $this::CACHE_EXPIRE;
                  $dependencies[CacheParent::Tags] = array_merge(
                    $this->cacheTags,
                    [
                      'sql',
                    ]
                  );
                  return $this->fluent->fetchSingle();
              }
            );
        } catch (Throwable) {
            return $this->fluent->fetchSingle();
        }
    }

    public function from(string $table, mixed ...$args) : Fluent {
        foreach ($args as $key => $arg) {
            if ($arg instanceof static) {
                $args[$key] = $arg->fluent;
            }
        }
        $this->table = $table;
        $this->fluent->from($table, ...$args);
        $this->queryHash = null;
        return $this;
    }

    /**
     * Fetches all records from table.
     *
     * @return Row[]
     */
    public function fetchAll(?int $offset = null, ?int $limit = null, bool $cache = true) : array {
        if (!$cache) {
            return $this->fluent->fetchAll($offset, $limit);
        }
        try {
            /** @phpstan-ignore-next-line */
            return $this->getCache()->load(
              'sql/'.$this->getQueryHash().'/fetchAll/'.$offset.'/'.$limit,
              function () use ($offset, $limit) {
                  return $this->fluent->fetchAll($offset, $limit);
              },
              [
                CacheParent::Expire => $this::CACHE_EXPIRE,
                CacheParent::Tags   => $this->getCacheTags(),
              ]
            );
        } catch (Throwable) {
            return $this->fluent->fetchAll($offset, $limit);
        }
    }

    /**
     * Fetches all records from table and returns associative tree.
     *
     * @param  string  $assoc  associative descriptor
     *
     * @return array<string, Row>|array<int, Row>
     */
    public function fetchAssoc(string $assoc, bool $cache = true) : array {
        if (!$cache) {
            return $this->fluent->fetchAssoc($assoc);
        }
        try {
            /** @phpstan-ignore-next-line */
            return $this->getCache()->load(
              'sql/'.$this->getQueryHash().'/fetchAssoc/'.$assoc,
              function () use ($assoc) {
                  return $this->fluent->fetchAssoc($assoc);
              },
              [
                CacheParent::Expire => $this::CACHE_EXPIRE,
                CacheParent::Tags   => $this->getCacheTags(),
              ]
            );
        } catch (Throwable) {
            return $this->fluent->fetchAssoc($assoc);
        }
    }

    /**
     * Fetches all records from table like $key => $value pairs.
     *
     * @return array<string, mixed>|array<int,mixed>
     */
    public function fetchPairs(?string $key = null, ?string $value = null, bool $cache = true) : array {
        if (!$cache) {
            return $this->fluent->fetchPairs($key, $value);
        }
        try {
            /** @phpstan-ignore-next-line */
            return $this->getCache()->load(
              'sql/'.$this->getQueryHash().'/fetchPairs/'.$key.'/'.$value,
              function () use ($key, $value) {
                  return $this->fluent->fetchPairs($key, $value);
              },
              [
                CacheParent::Expire => $this::CACHE_EXPIRE,
                CacheParent::Tags   => $this->getCacheTags(),
              ]
            );
        } catch (Throwable) {
            return $this->fluent->fetchPairs($key, $value);
        }
    }

    /**
     * Fetches the row count
     *
     * @param  bool  $cache
     *
     * @return int
     */
    public function count(bool $cache = true) : int {
        if (!$cache) {
            return $this->fluent->count();
        }
        try {
            /** @phpstan-ignore-next-line */
            return $this->getCache()->load(
              'sql/'.$this->getQueryHash().'/count',
              function () : int {
                  return $this->fluent->count();
              },
              [
                CacheParent::Expire => '1 hours',
                CacheParent::Tags   => $this->getCacheTags(),
              ]
            );
        } catch (Throwable) {
            return $this->fluent->count();
        }
    }

    /**
     * @param  string  $name
     *
     * @return mixed
     */
    public function __get($name) : mixed {
        return $this->fluent->$name;
    }

    /**
     * @param  string  $name
     * @param  mixed  $value
     *
     * @return void
     */
    public function __set($name, $value) : void {
        $this->fluent->$name = $value;
    }

    /**
     * @param  string  $name
     *
     * @return bool
     */
    public function __isset($name) {
        return isset($this->fluent->$name);
    }

    /**
     * @param  string  $name
     * @param  array<string|int,mixed>  $arguments
     *
     * @return $this|mixed
     */
    public function __call($name, $arguments) {
        foreach ($arguments as $key => $argument) {
            if ($argument instanceof static) {
                $arguments[$key] = $argument->fluent;
            }
        }
        $return = $this->fluent->$name(...$arguments);
        if ($return === $this->fluent) {
            $this->queryHash = null;
            return $this;
        }
        return $return;
    }

    public function getMethod() : string {
        return $this->method;
    }

}