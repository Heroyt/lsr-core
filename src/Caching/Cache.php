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

	public static int $hit  = 0;
	public static int $miss = 0;
	/** @var array<string, array{0:int, 1:int}> */
	public static array $loadedKeys = [];

	/**
	 * Reads multiple items from the cache.
	 *
	 * @param string[] $keys
	 *
	 * @return array<string,mixed>
	 * @throws Throwable
	 *
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
		if (!$this->getStorage() instanceof BulkReader) {
			foreach ($keys as $key) {
				$result[$key] = $this->load(
					$key,
					$generator
						? static function(&$dependencies) use ($key, $generator) {
						return $generator(...[$key, &$dependencies]);
					}
						: null
				);
			}

			return $result;
		}

		$storageKeys = array_map([$this, 'generateKey'], $keys);
		$cacheData = $this->getStorage()->bulkRead($storageKeys);
		foreach ($keys as $i => $key) {
			$storageKey = $storageKeys[$i];
			if (isset($cacheData[$storageKey])) {
				$this->logLoadedKey($key);
				self::$hit++;
				$result[$key] = $cacheData[$storageKey];
			}
			elseif ($generator) {
				$this->logLoadedKey($key, true);
				self::$miss++;
				$result[$key] = $this->load($key, function(&$dependencies) use ($key, $generator) {
					return $generator(...[$key, &$dependencies]);
				});
			}
			else {
				$this->logLoadedKey($key, true);
				self::$miss++;
				$result[$key] = null;
			}
		}

		return $result;
	}

	/**
	 * Reads the specified item from the cache or generate it.
	 *
	 * @param mixed                    $key
	 * @param callable|null            $generator
	 * @param array<string,mixed>|null $dependencies
	 *
	 * @return mixed
	 * @throws Throwable
	 */
	public function load($key, ?callable $generator = null, ?array $dependencies = null) : mixed {
		$storageKey = $this->generateKey($key);
		$data = $this->getStorage()->read($storageKey);
		if ($data === null && $generator) {
			$this->logLoadedKey($key, true);
			self::$miss++;
			$this->getStorage()->lock($storageKey);
			try {
				$dependencies = [];
				$data = $generator(...[&$dependencies]);
			} catch (Throwable $e) {
				$this->getStorage()->remove($storageKey);
				throw $e;
			}

			$this->save($key, $data, $dependencies);
		}
		else if ($data !== null) {
			$this->logLoadedKey($key);
			self::$hit++;
		}

		return $data;
	}

	/**
	 * @param mixed $key
	 * @param bool  $miss
	 *
	 * @return void
	 */
	private function logLoadedKey(mixed $key, bool $miss = false) : void {
		$key = is_scalar($key) ? (string) $key : serialize($key);
		if (!isset(self::$loadedKeys[$key])) {
			self::$loadedKeys[$key] = [0, 0];
		}
		self::$loadedKeys[$key][0]++;
		if ($miss) {
			self::$loadedKeys[$key][1]++;
		}
	}

	/**
	 * @return int
	 */
	public function getCalls() : int {
		return self::$hit + self::$miss;
	}

}