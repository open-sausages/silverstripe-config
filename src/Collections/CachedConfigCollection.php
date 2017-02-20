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

    /**
     * In-memory cache
     *
     * @var array
     */
    protected $cache = [];

    public function get($class, $name = null, $includeMiddleware = true)
    {
        // Get config for complete class
        $class = strtolower($class);
        $config = $this->getClassConfig($class, $includeMiddleware);

        // Return either name, or whole-class config
        if ($name) {
            return isset($config[$name]) ? $config[$name] : null;
        }
        return $config;
    }

    public function getAll()
    {
        return $this->getCollection()->getAll();
    }

    public function exists($class, $name = null, $includeMiddleware = true)
    {
        $config = $this->get($class, null, $includeMiddleware);
        if (!isset($config)) {
            return false;
        }
        if ($name) {
            return array_key_exists($name, $config);
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
        // @todo - Inject nested collection creater

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
     * @todo - make redundant through having a nested creator factory apply this
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

    /**
     * Get cache class config, or cache and return
     *
     * @param string $class
     * @param bool $includeMiddleware
     * @return mixed
     */
    public function getClassConfig($class, $includeMiddleware)
    {
        $key = $class . '-' . $includeMiddleware;
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $result = $this->getUncachedClassConfig($class, $includeMiddleware);
        $this->cache[$key] = $result;
        return $result;
    }

    /**
     * Get uncached class config
     *
     * @param string $class
     * @param bool $includeMiddleware
     * @return mixed
     */
    protected function getUncachedClassConfig($class, $includeMiddleware)
    {
        if (!$includeMiddleware) {
            return $this->getCollection()->get($class, null, false);
        }

        // Apply local middleware against this request
        $getConfig = function () use ($class) {
            return $this->getCollection()->get($class, null, false);
        };
        return $this->callMiddleware($class, $getConfig);
    }
}
