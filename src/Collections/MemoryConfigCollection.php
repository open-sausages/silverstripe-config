<?php

namespace micmania1\config\Collections;

use BadMethodCallException;
use micmania1\config\MergeStrategy\Priority;
use micmania1\config\Middleware\MiddlewareAware;
use micmania1\config\Transformer\TransformerInterface;
use Serializable;

/**
 * Basic mutable config collection stored in memory
 */
class MemoryConfigCollection implements MutableConfigCollectionInterface, Serializable
{
    use MiddlewareAware;

    /**
     * Stores a list of key/value config.
     * Note: This set does not include middleware-applied values.
     * Use getClassConfig() if needed.
     *
     * @var array
     */
    protected $config = [];

    /**
     * @var array
     */
    protected $metadata = [];

    /**
     * @var array
     */
    protected $history = [];

    /**
     * @var boolean
     */
    protected $trackMetadata = false;

    /**
     * ConfigCollection constructor.
     *
     * @param bool $trackMetadata
     */
    public function __construct($trackMetadata = false)
    {
        $this->trackMetadata = $trackMetadata;
    }

    /**
     * @return static
     */
    public static function create()
    {
        return new static();
    }

    /**
     * Trigger transformers to load into this store
     *
     * @param TransformerInterface[] $transformers
     * @return $this
     */
    public function transform($transformers)
    {
        foreach ($transformers as $transformer) {
            $transformer->transform($this);
        }
        return $this;
    }

    public function set($class, $name, $data, $metadata = [])
    {
        $class = strtolower($class);
        if ($this->trackMetadata) {
            if (isset($this->metadata[$class]) && isset($this->config[$class])) {
                if (!isset($this->history[$class])) {
                    $this->history[$class] = [];
                }

                array_unshift($this->history[$class], [
                    'value' => $this->config[$class],
                    'metadata' => $this->metadata[$class]
                ]);
            }

            $this->metadata[$class] = $metadata;
        }

        if ($name) {
            if (!isset($this->config[$class])) {
                $this->config[$class] = [];
            }
            $this->config[$class][$name] = $data;
        } else {
            $this->config[$class] = $data;
        }
        return $this;
    }

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

    public function remove($class, $name = null)
    {
        $class = strtolower($class);
        if ($name) {
            unset($this->config[$class][$name]);
        } else {
            unset($this->config[$class]);
        }
        return $this;
    }

    public function removeAll()
    {
        $this->config = [];
        $this->metadata = [];
        $this->history = [];
    }

    /**
     * Get complete config (excludes middleware-applied config)
     *
     * @return array
     */
    public function getAll()
    {
        return $this->config;
    }

    /**
     * synonym for merge()
     *
     * @param string $class
     * @param string $name
     * @param mixed $value
     * @return $this
     */
    public function update($class, $name, $value)
    {
        $this->merge($class, $name, $value);
        return $this;
    }

    public function merge($class, $name, $value)
    {
        // Detect mergeable config
        $existing = $this->get($class, $name, false);
        if (is_array($value) && is_array($existing)) {
            $value = Priority::mergeArray($value, $existing);
        }

        // Apply
        $this->set($class, $name, $value);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata()
    {
        if (!$this->trackMetadata || !is_array($this->metadata)) {
            return [];
        }

        return $this->metadata;
    }

    /**
     * {@inheritdoc}
     */
    public function getHistory()
    {
        if (!$this->trackMetadata || !is_array($this->history)) {
            return [];
        }

        return $this->history;
    }

    public function serialize()
    {
        if ($this->getMiddlewares()) {
            throw new BadMethodCallException("Can't serialise with middlewares");
        }
        return json_encode([
            $this->config,
            $this->history,
            $this->metadata,
            $this->trackMetadata
        ]);
    }

    public function unserialize($serialized)
    {
        list(
            $this->config,
            $this->history,
            $this->metadata,
            $this->trackMetadata
        ) = json_decode($serialized, true);
    }

    public function nest()
    {
        return clone $this;
    }

    /**
     * @param string $class
     * @param bool $includeMiddleware
     * @return array|mixed
     */
    protected function getClassConfig($class, $includeMiddleware)
    {
        $class = strtolower($class);
        // Can't apply middleware to config on non-existant class
        if (!isset($this->config[$class])) {
            return null;
        }

        if ($includeMiddleware) {
            $config = $this->callMiddleware($class, function () use ($class) {
                return $this->config[$class];
            });
            return $config;
        } else {
            $config = $this->config[$class];
            return $config;
        }
    }
}
