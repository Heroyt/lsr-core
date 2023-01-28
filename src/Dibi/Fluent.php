<?php

namespace Lsr\Core\Dibi;

use Dibi\Fluent as DibiFluent;
use Dibi\Row;
use Lsr\Core\App;
use Lsr\Core\Caching\Cache;
use Nette\Caching\Cache as CacheParent;
use Throwable;

/**
 * SQL builder via fluent interfaces.
 *
 * @method Fluent select(...$field)
 * @method Fluent distinct()
 * @method Fluent from($table, ...$args = null)
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
 * @method Fluent update(...$cond)
 * @method Fluent insert(...$cond)
 * @method Fluent delete(...$cond)
 * @method Fluent into(...$cond)
 * @method Fluent values(...$cond)
 * @method Fluent set(...$args)
 * @method Fluent asc()
 * @method Fluent desc()
 */
class Fluent
{

	protected ?string $queryHash = null;

	protected Cache $cache;

	/** @var string[] */
	protected array $cacheTags = [];

	public function __construct(public DibiFluent $fluent) {
	}

	public function __clone() : void {
		$this->fluent = clone $this->fluent;
	}

	public static function __callStatic($name, $arguments) : mixed {
		return DibiFluent::$name(...$arguments);
	}

	public function __toString() {
		return $this->fluent->__toString();
	}

	/**
	 * Generates, executes SQL query and fetches the single row.
	 *
	 * @return Row|null|array<string,mixed>
	 */
	public function fetch(bool $cache = true) : Row|array|null {
		if (!$cache) {
			return $this->fluent->fetch();
		}
		try {
			/** @phpstan-ignore-next-line */
			return $this->getCache()->load('sql/'.$this->getQueryHash().'/fetch', function(array &$dependencies) {
				$dependencies[CacheParent::EXPIRE] = '1 hours';
				$dependencies[CacheParent::Tags] = array_merge($this->cacheTags, [
					'sql',
				]);
				return $this->fluent->fetch();
			});
		} catch (Throwable) {
			return $this->fluent->fetch();
		}
	}

	/**
	 * @return Cache
	 * @noinspection PhpFieldAssignmentTypeMismatchInspection
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
			return $this->getCache()->load('sql/'.$this->getQueryHash().'/fetchSingle', function(array &$dependencies) {
				$dependencies[CacheParent::EXPIRE] = '1 hours';
				$dependencies[CacheParent::Tags] = array_merge($this->cacheTags, [
					'sql',
				]);
				return $this->fluent->fetchSingle();
			});
		} catch (Throwable) {
			return $this->fluent->fetchSingle();
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
			/** @phpstan-ignore-next-line */
			return $this->getCache()->load('sql/'.$this->getQueryHash().'/fetchAll/'.$offset.'/'.$limit, function(array &$dependencies) use ($offset, $limit) {
				$dependencies[CacheParent::EXPIRE] = '1 hours';
				$dependencies[CacheParent::Tags] = array_merge($this->cacheTags, [
					'sql',
				]);
				return $this->fluent->fetchAll($offset, $limit);
			});
		} catch (Throwable) {
			return $this->fluent->fetchAll($offset, $limit);
		}
	}

	/**
	 * Fetches all records from table and returns associative tree.
	 *
	 * @param string $assoc associative descriptor
	 *
	 * @return array<string, Row>|array<int, Row>
	 */
	public function fetchAssoc(string $assoc, bool $cache = true) : array {
		if (!$cache) {
			return $this->fluent->fetchAssoc($assoc);
		}
		try {
			/** @phpstan-ignore-next-line */
			return $this->getCache()->load('sql/'.$this->getQueryHash().'/fetchAssoc/'.$assoc, function(array &$dependencies) use ($assoc) {
				$dependencies[CacheParent::EXPIRE] = '1 hours';
				$dependencies[CacheParent::Tags] = array_merge($this->cacheTags, [
					'sql',
				]);
				return $this->fluent->fetchAssoc($assoc);
			});
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
			return $this->getCache()->load('sql/'.$this->getQueryHash().'/fetchPairs/'.$key.'/'.$value, function(array &$dependencies) use ($key, $value) {
				$dependencies[CacheParent::EXPIRE] = '1 hours';
				$dependencies[CacheParent::Tags] = array_merge($this->cacheTags, [
					'sql',
				]);
				return $this->fluent->fetchPairs($key, $value);
			});
		} catch (Throwable) {
			return $this->fluent->fetchPairs($key, $value);
		}
	}

	/**
	 * Fetches the row count
	 *
	 * @param bool $cache
	 *
	 * @return int
	 */
	public function count(bool $cache = true) : int {
		if (!$cache) {
			return $this->fluent->count();
		}
		try {
			/** @phpstan-ignore-next-line */
			return $this->getCache()->load('sql/'.$this->getQueryHash().'/count', function(array &$dependencies) {
				$dependencies[CacheParent::EXPIRE] = '1 hours';
				$dependencies[CacheParent::Tags] = array_merge($this->cacheTags, [
					'sql',
				]);
				return $this->fluent->count();
			});
		} catch (Throwable) {
			return $this->fluent->count();
		}
	}

	public function __get($name) : mixed {
		return $this->fluent->$name;
	}

	public function __set($name, $value) : void {
		$this->fluent->$name = $value;
	}

	public function __isset($name) {
		return isset($this->fluent->$name);
	}

	public function __call($name, $arguments) {
		foreach ($arguments as $key => $argument) {
			if ($argument instanceof Fluent) {
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

}