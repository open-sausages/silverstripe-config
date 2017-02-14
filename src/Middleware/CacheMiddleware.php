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
        // Process hit from non-persistant cache
        if (isset($this->memoryCache[$class])) {
            return $this->memoryCache[$class];
        }

        // Process hit from persistant cache
        $item = $this->pool->getItem($class);
        if ($item->isHit()) {
            $result = $item->get();
            $this->memoryCache[$class] = $result;
            return $result;
        }

        // Process miss
        $result = $next();
        $item->set($result);
        $this->pool->save($result);
        $this->memoryCache[$class] = $result;
        return $result;
    }
}
