<?php

namespace Lsr\Core\Caching;

use Nette\Caching\BulkReader;
use Nette\InvalidArgumentException;
use Throwable;

/**
 * Wrapper over Nette caching class adding statistics information
 */
class Cache extends \Nette\Caching\Cache
{

	public int $hit  = 0;
	public int $miss = 0;
	/** @var array<string|int> */
	public array $loadedKeys = [];

	/**
	 * Reads multiple items from the cache.
	 *
	 * @throws Throwable
	 */
	public function bulkLoad(array $keys, ?callable $generator = null) : array {
		if (count($keys) === 0) {
			return [];
		}

		foreach ($keys as $key) {
			if (!is_scalar($key)) {
				throw new InvalidArgumentException('Only scalar keys are allowed in bulkLoad()');
			}
		}

		$result = [];
		if (!$this->storage instanceof BulkReader) {
			foreach ($keys as $key) {
				$result[$key] = $this->load(
					$key,
					$generator
						? function(&$dependencies) use ($key, $generator) {
						return $generator(...[$key, &$dependencies]);
					}
						: null
				);
			}

			return $result;
		}

		$storageKeys = array_map([$this, 'generateKey'], $keys);
		$cacheData = $this->storage->bulkRead($storageKeys);
		foreach ($keys as $i => $key) {
			$this->logLoadedKey($key);
			$storageKey = $storageKeys[$i];
			if (isset($cacheData[$storageKey])) {
				$this->hit++;
				$result[$key] = $cacheData[$storageKey];
			}
			elseif ($generator) {
				$this->miss++;
				$result[$key] = $this->load($key, function(&$dependencies) use ($key, $generator) {
					return $generator(...[$key, &$dependencies]);
				});
			}
			else {
				$this->miss++;
				$result[$key] = null;
			}
		}

		return $result;
	}

	/**
	 * Reads the specified item from the cache or generate it.
	 *
	 * @param mixed         $key
	 * @param callable|null $generator
	 *
	 * @return mixed
	 * @throws Throwable
	 */
	public function load($key, ?callable $generator = null) : mixed {
		$this->logLoadedKey($key);
		$storageKey = $this->generateKey($key);
		$data = $this->storage->read($storageKey);
		if ($data === null && $generator) {
			$this->miss++;
			$this->storage->lock($storageKey);
			try {
				$data = $generator(...[&$dependencies]);
			} catch (Throwable $e) {
				$this->storage->remove($storageKey);
				throw $e;
			}

			$this->save($key, $data, $dependencies);
		}
		else {
			$this->hit++;
		}

		return $data;
	}

	/**
	 * @param mixed $key
	 *
	 * @return void
	 */
	private function logLoadedKey(mixed $key) : void {
		$key = is_scalar($key) ? (string) $key : serialize($key);
		if (!isset($this->loadedKeys[$key])) {
			$this->loadedKeys[$key] = 0;
		}
		$this->loadedKeys[$key]++;
	}

	/**
	 * @return int
	 */
	public function getCalls() : int {
		return $this->hit + $this->miss;
	}

}