<?php
declare(strict_types=1);

namespace Lsr\Core\Caching;

use Nette\Caching\BulkReader;
use Nette\Caching\Cache;
use Nette\Caching\Storage;
use Nette\Caching\Storages\Journal;
use Nette\InvalidStateException;
use Nette\NotSupportedException;
use Nette\SmartObject;
use Redis;

class RedisStorage implements Storage, BulkReader
{
	use SmartObject;

	/** @internal cache structure */
	private const
		MetaCallbacks = 'callbacks', MetaData = 'data', MetaDelta = 'delta';

	public function __construct(
		private readonly Redis    $redis,
		private readonly string   $prefix = '',
		private readonly ?Journal $journal = null,
	) {
		if (!static::isAvailable()) {
			throw new NotSupportedException("PHP extension 'redis' is not loaded.");
		}
	}

	/**
	 * Checks if Redis extension is available.
	 */
	public static function isAvailable(): bool {
		return extension_loaded('redis');
	}

	public function getConnection(): Redis {
		return $this->redis;
	}

	/**
	 * @inheritDoc
	 */
	public function read(string $key): mixed {
		$key = urlencode($this->prefix . $key);
		$meta = $this->redis->get($key);
		if ($meta === false) {
			return null;
		}
		/**
		 * @var array{data: mixed, delta: int, callbacks: callable[]} $data
		 */
		$data = unserialize($meta, ['allowed_classes' => true]);

		// verify dependencies
		if (!empty($data[self::MetaCallbacks]) && !Cache::checkCallbacks($data[self::MetaCallbacks])) {
			$this->redis->del($key);
			return null;
		}

		if (!empty($data[self::MetaDelta])) {
			$this->redis->setex($key, $data[self::MetaDelta] + time(), $meta);
		}

		return $data[self::MetaData];
	}

	/**
	 * @inheritDoc
	 */
	public function lock(string $key): void {
	}

	/**
	 * @inheritDoc
	 *
	 * @param array{items?: string[], cache?: string, callbacks?: callable[], tags?: string[], priority?: int, sliding?: bool, files: string[]|string} $dependencies
	 */
	public function write(string $key, mixed $data, array $dependencies): void {
		if (isset($dependencies[Cache::Items])) {
			throw new NotSupportedException('Dependent items are not supported by RedisStorage.');
		}
		$key = urlencode($this->prefix . $key);
		$meta = [
			self::MetaData => $data,
		];

		$expire = 0;
		if (isset($dependencies[Cache::Expire])) {
			$expire = (int)$dependencies[Cache::Expire];
			if (!empty($dependencies[Cache::Sliding])) {
				$meta[self::MetaDelta] = $expire; // sliding time
			}
		}

		if (isset($dependencies[Cache::Callbacks])) {
			$meta[self::MetaCallbacks] = $dependencies[Cache::Callbacks];
		}

		if (isset($dependencies[Cache::Tags]) || isset($dependencies[Cache::Priority])) {
			if (!$this->journal) {
				throw new InvalidStateException('CacheJournal has not been provided.');
			}

			$this->journal->write($key, $dependencies);
		}

		$this->redis->setex($key, $expire, serialize($meta));
	}

	/**
	 * @inheritDoc
	 */
	public function remove(string $key): void {
		$this->redis->delete(urlencode($this->prefix . $key));
	}

	/**
	 * @inheritDoc
	 *
	 * @param array{all?: bool, tags?: string[]} $conditions
	 */
	public function clean(array $conditions): void {
		if (!empty($conditions[Cache::All])) {
			$this->redis->flushAll();
			return;
		}

		if ($this->journal) {
			$keys = $this->journal->clean($conditions);
			if ($keys) {
				$this->redis->delete(...$keys);
			}
		}
	}

	/**
	 * Reads from cache in bulk.
	 *
	 * @param string[] $keys
	 *
	 * @return array<string, mixed> key => value pairs, missing items are omitted
	 */
	public function bulkRead(array $keys): array {
		$prefixedKeys = array_map(fn($key) => urlencode($this->prefix . $key), $keys);
		$keys = array_combine($prefixedKeys, $keys);
		/** @var array<string,string> $metas */
		$metas = $this->redis->getMultiple($prefixedKeys);
		$result = [];
		$deleteKeys = [];
		foreach ($metas as $prefixedKey => $meta) {
			/**
			 * @var array{data: mixed, delta: int, callbacks: callable[]} $data
			 */
			$data = unserialize($meta, ['allowed_classes' => true]);
			if (!empty($data[self::MetaCallbacks]) && !Cache::checkCallbacks($data[self::MetaCallbacks])) {
				$deleteKeys[] = $prefixedKey;
			}
			else {
				$result[$keys[$prefixedKey]] = $data[self::MetaData];
			}

			if (!empty($data[self::MetaDelta])) {
				$this->redis->setex($prefixedKey, $data[self::MetaDelta] + time(), $meta);
			}
		}

		if (!empty($deleteKeys)) {
			$this->redis->delete(...$deleteKeys);
		}

		return $result;
	}
}