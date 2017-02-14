<?php

namespace micmania1\config\Middleware;

use Psr\Cache\CacheItemPoolInterface;

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
        $item = $this->pool->getItem($class);
        if ($item->isHit()) {
            return $item->get();
        }
        $result = $next();
        $item->set($result);
        $this->pool->save($result);
        return $result;
    }
}
