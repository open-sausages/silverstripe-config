<?php

namespace micmania1\config\Collections;

/**
 * Config collection designed as a temporary in-memory modified copy
 * of a parent immutable collection.
 *
 * Has extra logic for maintaining local cache of modified config,
 * as well as the ability to fail over to a parent source for un-modified.
 */
class DeltaConfigCollection extends ConfigCollection
{
    /**
     * Used to retrieve middleware-applied config for unmodified classes
     *
     * @var ConfigCollectionInterface
     */
    protected $parent = null;

    /**
     * List of classes with customisations. If any class is customised
     * we cannot rely on failover to provide current values for any class configs.
     *
     * @var array
     */
    protected $altered = [];

    /**
     * Cache of middleware-applied config
     *
     * @var array
     */
    protected $configMiddlewareCache = [];

    /**
     * @param ConfigCollectionInterface $parent
     */
    public function __construct(ConfigCollectionInterface $parent)
    {
        parent::__construct();
        $this->setParent($parent);
    }

    /**
     * @param ConfigCollectionInterface $parent
     * @return $this
     */
    public function setParent(ConfigCollectionInterface $parent)
    {
        $this->parent = $parent;
        $this->config = $parent->getAll();
        return $this;
    }

    /**
     * Get the parent collection this collection was modified from
     *
     * @return ConfigCollectionInterface
     */
    public function getParent()
    {
        return $this->parent;
    }

    protected function getClassConfig($class, $includeMiddleware = true)
    {
        $class = strtolower($class);
        // Not a class with config
        if (!isset($this->config[$class])) {
            return null;
        }

        // Without middleware applied, return direct class config
        if (!$includeMiddleware) {
            return $this->config[$class];
        }

        // Closest cache: in-memory modified version
        if (array_key_exists($class, $this->configMiddlewareCache)) {
            return $this->configMiddlewareCache[$class];
        }

        // If we have modified this class re-calculate it
        // Otherwise we can rely on failover for un-modified config
        if ($this->isAltered($class)) {
            $config = parent::getClassConfig($class, true);
        } else {
            $config = $this->parent->get($class, null, true);
        }

        // Cache to prevent having to process this again
        $this->configMiddlewareCache[$class] = $config;
        return $config;
    }

    public function remove($class, $name = null)
    {
        $this->alter($class);
        return parent::remove($class, $name);
    }

    public function set($class, $name, $data, $metadata = [])
    {
        $this->alter($class);
        return parent::set($class, $name, $data, $metadata);
    }

    public function removeAll()
    {
        parent::removeAll();
        $this->configMiddlewareCache = [];
    }

    /**
     * Mark a class as altered
     *
     * @param string $class
     * @return $this
     */
    protected function alter($class)
    {
        // Ensure middleware is re-applied for this config
        $this->altered[$class] = $class;
        unset($this->configMiddlewareCache[$class]);
        return $this;
    }

    /**
     * Determine if a class is altered.
     * If not altered we can reply on failover to return a potentially cached
     * version of this config.
     *
     * @param string $class Name of class
     * @return bool
     */
    protected function isAltered($class)
    {
        return isset($this->altered[$class]);
    }
}
