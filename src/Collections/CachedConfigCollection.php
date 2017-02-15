<?php

namespace micmania1\config\Collections;

use micmania1\config\Middleware\Middleware;
use micmania1\config\Middleware\MiddlewareAware;
use Psr\Cache\CacheItemPoolInterface;

class CachedConfigCollection implements ConfigCollectionInterface
{
    use MiddlewareAware;

    /**
     * @const string
     */
    const CACHE_KEY = '__CONFIG__';

    /**
     * @var CacheItemPoolInterface
     */
    protected $pool;

    /**
     * Nested config to delegate to
     *
     * @var ConfigCollectionInterface
     */
    protected $collection;

    /**
     * @var callable
     */
    protected $collectionCreator;

    /**
     * Middlewares stored for nested configs
     *
     * @var Middleware[]
     */
    protected $nestedMiddlewares = null;

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
     * @return static
     */
    public static function create()
    {
        return new static();
    }

    public function get($class, $name = null, $includeMiddleware = true)
    {
        if (!$includeMiddleware) {
            return $this->getCollection()->get($class, $name, false);
        }

        // Apply local middleware against this request
        $getConfig = function () use ($class) {
            return $this->getCollection()->get($class, null, false);
        };
        $config = $this->callMiddleware($class, $getConfig);
        if ($name) {
            return isset($config[$name]) ? $config[$name] : null;
        }
        return $config;
    }

    public function getAll()
    {
        return $this->getCollection()->getAll();
    }

    public function exists($class, $name = null)
    {
        $config = $this->get($class);
        if (!isset($config)) {
            return false;
        }
        if ($name && !array_key_exists($name, $config)) {
            return false;
        }
        return true;
    }

    public function getMetadata()
    {
        return $this->getCollection()->getMetadata();
    }

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

        // Note: Config may be yet modified prior to deferred save, but after Core.php
        // however no formal api for this yet
        $cacheItem->set($this->collection);

        // Defer this save
        $this->dirty = true;
        $this->pool->saveDeferred($cacheItem);
        return $this->collection;
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

    public function nest()
    {
        // Create locally-modifiable collection which points back to
        // this self-reference.
        // This allows un-modified clases to continue to benefit from
        // any caches provided by CachedConfigCollection
        return DeltaConfigCollection::create()
            ->setParent($this)
            ->setMiddlewares($this->nestedMiddlewares);
    }

    /**
     * Set middlewares to apply to nested configs
     *
     * @param $middleares
     * @return $this
     */
    public function setNestedMiddlewares($middleares)
    {
        $this->nestedMiddlewares = $middleares;
        return $this;
    }

    /**
     * Set a pool
     *
     * @param CacheItemPoolInterface $pool
     * @return $this
     */
    public function setPool(CacheItemPoolInterface $pool)
    {
        $this->pool = $pool;
        if ($this->flush) {
            $pool->clear();
        }
        return $this;
    }

    /**
     * @param callable $collectionCreator
     * @return $this
     */
    public function setCollectionCreator($collectionCreator)
    {
        $this->collectionCreator = $collectionCreator;
        return $this;
    }

    /**
     * @return callable
     */
    public function getCollectionCreator()
    {
        return $this->collectionCreator;
    }

    /**
     * @return CacheItemPoolInterface
     */
    public function getPool()
    {
        return $this->pool;
    }

    /**
     * @param bool $flush
     * @return $this
     */
    public function setFlush($flush)
    {
        $this->flush = $flush;
        if ($flush && $this->pool) {
            $this->pool->clear();
        }
        return $this;
    }

    /**
     * @return bool
     */
    public function getFlush()
    {
        return $this->flush;
    }
}
