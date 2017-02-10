<?php

namespace micmania1\config;

use Psr\Cache\CacheItemPoolInterface;
use Exception;

class CachedConfigCollection implements ConfigCollectionInterface
{
    /**
     * @const string
     */
    const CACHE_KEY = '__CONFIG__';

    /**
     * @var CacheItemPoolInterface
     */
    protected $pool;

    /**
     * @var ConfigCollectionInterface
     */
    protected $collection;

    /**
     * @var callable
     */
    protected $collectionCreator;

    /**
     * @var bool
     */
    protected $flush = false;

    /**
     * Set to true if cached item is dirty and marked for deferred write
     *
     * @var bool
     */
    protected $dirty = false;

    /**
     * Provides a cached interface over the top of a core config
     *
     * @param CacheItemPoolInterface $pool
     * @param callable $collectionCreator Factory to generate cached collection
     * @param bool $flush Set to true to force the cache to regenerate
     */
    public function __construct(CacheItemPoolInterface $pool, $collectionCreator, $flush = false)
    {
        $this->pool = $pool;
        $this->collectionCreator = $collectionCreator;
        $this->flush = $flush;
    }

    public function set($key, $value, $metadata = [])
    {
        return $this->collection->set($key, $value, $metadata);
    }

    /**
     * {@inheritdoc}
     */
    public function get($key)
    {
        return $this->getCollection()->get($key);
    }

    /**
     * {@inheritdoc}
     */
    public function exists($key)
    {
        return $this->getCollection()->exists($key);
    }

    public function delete($key)
    {
        return $this->getCollection()->delete($key);
    }

    public function deleteAll()
    {
        $this->getCollection()->deleteAll();
    }

    public function getMetadata()
    {
        return $this->getCollection()->getMetadata();
    }

    /**
     * {@inheritdoc}
     */
    public function getHistory()
    {
        return $this->getCollection()->getHistory();
    }

    /**
     * Get or build collection
     *
     * @return ConfigCollectionInterface
     */
    public function getCollection()
    {
        // Get current collection
        if ($this->collection) {
            return $this->collection;
        }

        // Init cached item
        $cacheItem = $this->pool->getItem(self::CACHE_KEY);

        // Load from cache (unless flushing)
        if (!$this->flush && $cacheItem->isHit()) {
            $this->collection = $cacheItem->get();
            return $this->collection;
    }

        // Cache missed
        $this->collection = call_user_func($this->collectionCreator);

        // Note: We re-cache the cloned item, so that local modifications aren't cached
        $cacheItem->set(clone $this->collection);

        // Defer this save
        $this->dirty = true;
        $this->pool->saveDeferred($cacheItem);
        return $this->collection;
    }

    /**
     * @param ConfigCollectionInterface $collection
     * @return $this
     */
    public function setCollection($collection)
    {
        $this->collection = $collection;
        return $this;
    }

    /**
     * We replace backslashes with commas as backslashes are not allowed in PSR-6
     * implementations. Commas will rarely (if ever) be used for cache keys. We also
     * convert the key to lowercase to ensure case insensitivity.
     *
     * @param string $key
     *
     * @return string
     */
    protected function normaliseKey($key)
    {
        return str_replace('\\', ',', strtolower($key));
    }

    /**
     * Commits the cache
     */
    public function __destruct()
    {
        if ($this->dirty) {
        $this->pool->commit();
    }
}

    public function getNest()
    {
        return $this->getCollection()->getNest();
    }
}
