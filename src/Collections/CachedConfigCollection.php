<?php

namespace micmania1\config\Collections;

use micmania1\config\Middleware\Middleware;
use micmania1\config\Middleware\MiddlewareAware;
use Psr\Cache\CacheItemPoolInterface;
use Exception;

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
        if ($flush) {
            $pool->clear();
    }
    }

    /**
     * @param CacheItemPoolInterface $pool
     * @param callable $collectionCreator
     * @param bool $flush
     * @return static
     */
    public static function create(CacheItemPoolInterface $pool, $collectionCreator, $flush = false)
    {
        return new static($pool, $collectionCreator, $flush);
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
}
