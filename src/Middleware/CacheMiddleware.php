<?php

namespace micmania1\config\Middleware;

use Psr\Cache\CacheItemPoolInterface;

/**
 * Provides a level of persistant and local-memory caching of middleware-applied
 * logic to class configuration.
 */
class CacheMiddleware implements Middleware
{

    /**
     * @var CacheItemPoolInterface
     */
    protected $pool;

    /**
     * @var bool
     */
    protected $flush = false;

    /**
     * In-memory cache
     *
     * @var array
     */
    protected $memoryCache = [];

    /**
     * Provides a cached interface over the top of a core config
     *
     * @param CacheItemPoolInterface $pool
     * @param bool $flush Set to true to force the cache to regenerate
     */
    public function __construct(CacheItemPoolInterface $pool, $flush = false)
    {
        $this->pool = $pool;
        $this->flush = $flush;
        if ($flush) {
            $pool->clear();
        }
    }

    /**
     * Get config for a class
     *
     * @param string $class
     * @param callable $next
     * @return string
     */
    public function getClassConfig($class, $next)
    {
        $key = $this->normaliseKey($class);

        // Process hit from non-persistant cache
        if (isset($this->memoryCache[$key])) {
            return $this->memoryCache[$key];
        }

        // Process hit from persistant cache
        $item = $this->pool->getItem($key);
        if ($item->isHit()) {
            $result = $item->get();
            $this->memoryCache[$key] = $result;
            return $result;
        }

        // Process miss
        $result = $next($class);
        $item->set($result);
        $this->pool->save($item);
        $this->memoryCache[$key] = $result;
        return $result;
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
}
