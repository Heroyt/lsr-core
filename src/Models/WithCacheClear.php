<?php
declare(strict_types=1);

namespace Lsr\Core\Models;

use Lsr\Caching\Cache;
use Lsr\Core\App;
use Lsr\Orm\Attributes\Hooks\AfterDelete;
use Lsr\Orm\Attributes\Hooks\AfterInsert;
use Lsr\Orm\Attributes\Hooks\AfterUpdate;
use Nette\Caching\Cache as CacheParent;

/**
 * @mixin \Lsr\Orm\Model
 * @phpstan-ignore trait.unused
 */
trait WithCacheClear
{

    /**
     * Clear cache for model queries (the Model::query() method)
     *
     * @return void
     * @see Model::query()
     *
     */
    public static function clearQueryCache() : void {
        /** @var Cache $cache */
        $cache = App::getService('cache');
        $cache->clean(
          [
            CacheParent::Tags => [
              static::TABLE.'/query',
            ],
          ]
        );
    }

    /**
     * Clear cache for this model
     *
     * @return void
     */
    public static function clearModelCache() : void {
        /** @var Cache $cache */
        $cache = App::getService('cache');
        $cache->clean(
          [
            CacheParent::Tags => [
              static::TABLE,
              static::TABLE.'/query',
            ],
          ]
        );
    }

    /**
     * Clear cache for this model instance
     *
     * @post Clear cache for this specific instance
     *
     * @return void
     * @see  Cache
     *
     */
    #[AfterUpdate, AfterDelete, AfterInsert]
    public function clearCache() : void {
        if (isset($this->id)) {
            /** @var Cache $cache */
            $cache = App::getService('cache');
            $tags = [
              $this::TABLE,
              $this::TABLE.'/query',
              $this::TABLE.'/'.$this->id,
              $this::TABLE.'/'.$this->id.'/relations',
            ];
            if (method_exists($this, 'getCacheTags')) {
                $tags = array_merge($tags, $this->getCacheTags());
            }
            $cache->clean(
              [
                CacheParent::Tags => $tags,
              ]
            );
        }
    }
}