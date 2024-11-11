<?php

namespace Lsr\Core\Dibi;

use Dibi\Exception;
use Dibi\Fluent as DibiFluent;
use Dibi\Result;
use Dibi\Row;
use Generator;
use Lsr\Core\App;
use Lsr\Core\Caching\Cache;
use Lsr\Core\Mapper;
use Nette\Caching\Cache as CacheParent;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
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

    protected const ITERATOR_CHUNK_SIZE = 5;
    protected const CACHE_EXPIRE = '1 hours';
    protected ?string $queryHash = null;

    protected Cache $cache;
    protected Mapper $mapper;

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
     * @template T of object
     * @param  class-string<T>  $class
     * @param  bool  $cache
     * @return T|null
     * @throws Exception
     */
    public function fetchDto(string $class, bool $cache = true) : ?object {
        if (!$cache) {
            return $this->fluent->execute()?->setRowClass($class)?->setRowFactory($this->getRowFactory($class))?->fetch(
            );
        }
        try {
            return $this->getCache()->load(
              'sql/'.$this->getQueryHash().'/fetch/'.$class,
              fn() => $this->fluent->execute()
                                   ?->setRowClass($class)
                                   ?->setRowFactory($this->getRowFactory($class))
                                   ?->fetch(),
              [
                CacheParent::Expire => $this::CACHE_EXPIRE,
                CacheParent::Tags   => $this->getCacheTags(),
              ]
            );
        } catch (Throwable) {
            return $this->fluent->execute()?->setRowClass($class)?->setRowFactory($this->getRowFactory($class))?->fetch(
            );
        }
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

    /**
     * @param  class-string  $type
     * @return callable
     */
    public function getRowFactory(string $type) : callable {
        return fn(mixed $data) : object => $this->getMapper()->map($data, $type);
    }

    public function getMapper() : Mapper {
        if (!isset($this->mapper)) {
            $mapper = App::getService('mapper');
            assert($mapper instanceof Mapper, 'Invalid DI service');
            $this->mapper = $mapper;
        }
        return $this->mapper;
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
     * @template T of object
     * @param  class-string<T>  $class
     * @param  int|null  $offset
     * @param  int|null  $limit
     * @param  bool  $cache
     * @return T[]
     * @throws Exception
     */
    public function fetchAllDto(string $class, ?int $offset = null, ?int $limit = null, bool $cache = true) : array {
        if (!$cache) {
            /** @phpstan-ignore-next-line */
            return $this->fluent->execute()
                                ?->setRowClass($class)
                                ?->setRowFactory($this->getRowFactory($class))
                                ?->fetchAll();
        }
        try {
            return $this->getCache()->load(
              'sql/'.$this->getQueryHash().'/fetchAll/'.$offset.'/'.$limit.'/'.$class,
              fn() => $this->fluent->execute()
                                   ?->setRowClass($class)
                                   ?->setRowFactory($this->getRowFactory($class))
                                   ?->fetchAll(),
              [
                CacheParent::Expire => $this::CACHE_EXPIRE,
                CacheParent::Tags   => $this->getCacheTags(),
              ]
            );
        } catch (Throwable) {
            /** @phpstan-ignore-next-line */
            return $this->fluent->execute()
                                ?->setRowClass($class)
                                ?->setRowFactory($this->getRowFactory($class))
                                ?->fetchAll();
        }
    }

    /**
     * @template T of object
     * @param  class-string<T>  $class
     * @param  bool  $cache
     * @return Generator<T>
     * @throws Exception
     */
    public function &fetchIteratorDto(string $class, bool $cache = true) : Generator {
        if (!$cache) {
            $query = $this->fluent->execute()
                         ?->setRowClass($class)
                         ?->setRowFactory($this->getRowFactory($class));
            while ($row = $query?->fetch()) {
                yield $row;
            }
            return;
        }

        $chunkIndex = 0;
        while (true) {
            $chunk = $this->getCache()->load(
              'sql/'.$this->getQueryHash().'/iterator/'.$chunkIndex.'/'.$class,
                fn() => $this->fetchAll($chunkIndex * $this::ITERATOR_CHUNK_SIZE, $this::ITERATOR_CHUNK_SIZE, false),
              [
                CacheParent::Expire => $this::CACHE_EXPIRE,
                CacheParent::Tags   => $this->getCacheTags(),
              ]
            );
            $chunkIndex++;

            foreach ($chunk as $row) {
                yield $this->getMapper()->map($row, $class);
            }

            if (count($chunk) < $this::ITERATOR_CHUNK_SIZE) {
                break;
            }
        }
    }

    /**
     * @param  bool  $cache
     * @return Generator<Row>
     * @throws Exception
     */
    public function &fetchIterator(bool $cache = true) : Generator {
        if (!$cache) {
            $query = $this->fluent->execute();
            while ($row = $query?->fetch()) {
                yield $row;
            }
            return;
        }

        $chunkIndex = 0;
        while (true) {
            $chunk = $this->getCache()->load(
              'sql/'.$this->getQueryHash().'/iterator/'.$chunkIndex,
                fn() => $this->fetchAll($chunkIndex * $this::ITERATOR_CHUNK_SIZE, $this::ITERATOR_CHUNK_SIZE, false),
              [
                CacheParent::Expire => $this::CACHE_EXPIRE,
                CacheParent::Tags   => $this->getCacheTags(),
              ]
            );
            $chunkIndex++;

            foreach ($chunk as $row) {
                yield $row;
            }

            if (count($chunk) < $this::ITERATOR_CHUNK_SIZE) {
                break;
            }
        }
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
     * @template T of object
     *
     * @param  class-string<T>  $class
     * @param  string  $assoc  associative descriptor
     *
     * @return array<string, T>|array<int, T>
     * @throws Exception
     */
    public function fetchAssocDto(string $class, string $assoc, bool $cache = true) : array {
        if (!$cache) {
            return $this->fluent->execute()
                                ?->setRowClass($class)
                                ?->setRowFactory($this->getRowFactory($class))
                                ?->fetchAssoc($assoc) ?? [];
        }
        try {
            return $this->getCache()->load(
              'sql/'.$this->getQueryHash().'/fetchAssoc/'.$assoc,
              fn() => $this->fluent->execute()
                                   ?->setRowClass($class)
                                   ?->setRowFactory($this->getRowFactory($class))
                                   ?->fetchAssoc($assoc) ?? [],
              [
                CacheParent::Expire => $this::CACHE_EXPIRE,
                CacheParent::Tags   => $this->getCacheTags(),
              ]
            );
        } catch (Throwable) {
            return $this->fluent->execute()
                                ?->setRowClass($class)
                                ?->setRowFactory($this->getRowFactory($class))
                                ?->fetchAssoc($assoc) ?? [];
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