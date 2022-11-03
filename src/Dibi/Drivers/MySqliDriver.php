<?php

namespace Lsr\Core\Dibi\Drivers;

use Dibi;
use Lsr\Core\App;
use Lsr\Core\Caching\Cache;
use Nette\Caching\Cache as CacheParent;

class MySqliDriver extends Dibi\Drivers\MySqliDriver
{

	public bool      $cacheEnabled = true;
	protected ?Cache $cache        = null;

	public function __construct(array $config) {
		/** @noinspection PhpFieldAssignmentTypeMismatchInspection */
		/** @phpstan-ignore-next-line */
		$this->cache = App::getService('cache');
		parent::__construct($config);
	}

	public function query(string $sql) : ?Dibi\ResultDriver {
		// Cache select queries
		if ($this->cacheEnabled && isset($this->cache) && str_starts_with($sql, 'SELECT')) {
			/** @var null|Dibi\ResultDriver $result */
			$result = $this->cache->load('sql/'.md5($sql), function(array &$dependencies) use ($sql) {
				$dependencies[CacheParent::Tags] = [
					'sql',
				];
				$dependencies[CacheParent::EXPIRE] = '1 hours';
				return parent::query($sql);
			});
			if (isset($result)) {
				return $result;
			}
		}
		return parent::query($sql);
	}

}