<?php

namespace Lsr\Core\Caching;

use Nette\Caching\Cache;
use Nette\Caching\Storages\Journal;
use Nette\NotSupportedException;
use Nette\SmartObject;
use Redis;

class RedisJournal implements Journal
{
    use SmartObject;

    private const REVERSE_TAG_PREFIX = 'journal:dependencies:reverseTags:';
    private const TAG_PREFIX = 'journal:dependencies:tags:';
    private const PRIORITY_KEY = 'journal:dependencies:priority';

    public function __construct(
      private readonly Redis $redis
    ) {
        if (!static::isAvailable()) {
            throw new NotSupportedException("PHP extension 'redis' is not loaded.");
        }
    }

    /**
     * Checks if Redis extension is available.
     */
    public static function isAvailable() : bool {
        return extension_loaded('redis');
    }

    /**
     * @inheritDoc
     *
     * @param  array{tags?:string[],priority?:float}  $dependencies
     */
    public function write(string $key, array $dependencies) : void {
        if (!empty($dependencies[Cache::Tags])) {
            $reverseTagKey = $this::REVERSE_TAG_PREFIX.$key;
            $tags = $this->redis->sMembers($reverseTagKey);
            if (!empty($tags)) {
                foreach ($tags as $tag) {
                    $this->redis->sRem($this::TAG_PREFIX.$tag, $key);
                }
            }

            foreach ($dependencies[Cache::Tags] as $tag) {
                $this->redis->sAdd(self::TAG_PREFIX.$tag, $key);
            }
            $this->redis->sAddArray($reverseTagKey, $dependencies[Cache::Tags]);
        }

        if (!empty($dependencies[Cache::Priority])) {
            $this->redis->zAdd(self::PRIORITY_KEY, $dependencies[Cache::Priority], $key);
        }
    }

    /**
     * @inheritDoc
     *
     * @param  array{all?:bool,tags?:string[],priority?:float}  $conditions
     *
     * @return null|string[]
     */
    public function clean(array $conditions) : ?array {
        if (!empty($conditions[Cache::All])) {
            return null;
        }

        /** @var string[] $keys */
        $keys = [];
        if (!empty($conditions[Cache::Tags])) {
            $tags = array_map(fn(string $tag) => $this::TAG_PREFIX.$tag, ((array) $conditions[Cache::Tags]));
            $keys = $this->redis->sUnion(...$tags);
        }

        if (!empty($conditions[Cache::Priority])) {
            $keys = array_unique(
              array_merge(
              // @phpstan-ignore-next-line
                $keys,
                // @phpstan-ignore-next-line
                $this->redis->zRangeByScore(self::PRIORITY_KEY, 0, (int) $conditions[Cache::Priority])
              )
            );
            $this->redis->zRemRangeByScore(self::PRIORITY_KEY, '0.0', (string) $conditions[Cache::Priority]);
        }

        // @phpstan-ignore-next-line
        $allTagsKeys = array_map(fn(string $key) => $this::REVERSE_TAG_PREFIX.$key, $keys);
        if (!empty($allTagsKeys)) {
            $allTags = $this->redis->sUnion(...array_map(fn(string $tag) => $this::TAG_PREFIX.$tag, $allTagsKeys));
            if (!empty($allTags)) {
                $this->redis->del(...$allTags);
            }
        }

        // @phpstan-ignore-next-line
        return $keys;
    }
}