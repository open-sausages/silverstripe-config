<?php

namespace micmania1\config\Collections;

use micmania1\config\Middleware\Middleware;

/**
 * This represents a colletction of config keys and values.
 */
interface ConfigCollectionInterface
{

    /**
     * Fetches value for a class, or a field on that class
     *
     * @param string $class
     * @param string $name Optional sub-key to get
     * @param bool $includeMiddleware Apply middleware
     *
     * @return mixed
     */
    public function get($class, $name = null, $includeMiddleware = true);

    /**
     * Checks to see if a config item exists, or a field on that class
     *
     * @param string $class
     * @param string $name
     * @return bool
     */
    public function exists($class, $name = null);

    /**
     * Returns the entire metadata
     *
     * @return array
     */
    public function getMetadata();

    /*
     * Returns the entire history
     *
     * @return array
     */
    public function getHistory();

    /**
     * Get nested version of this config,
     * which is a duplicated version of this config.
     *
     * @return static
     */
    public function nest();

    /**
     * @return Middleware[]
     */
    public function getMiddlewares();

    /**
     * @param Middleware[] $middlewares
     * @return $this
     */
    public function setMiddlewares($middlewares);

    /**
     * @param Middleware $middleware
     * @return $this
     */
    public function addMiddleware($middleware);

    /**
     * Get complete config (excludes middleware)
     *
     * @return array
     */
    public function getAll();
}
