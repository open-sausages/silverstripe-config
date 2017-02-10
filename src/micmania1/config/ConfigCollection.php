<?php

namespace micmania1\config;

use micmania1\config\Transformer\TransformerInterface;
use Serializable;

class ConfigCollection implements ConfigCollectionInterface, Serializable
{
    /**
     * Stores a list of key/value config.
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
     * @var TransformerInterface[]
     */
    protected $transformers = [];

    /**
     * @var boolean
     */
    protected $trackMetadata = false;

    /**
     * ConfigCollection constructor.
     *
     * @param TransformerInterface[] $transformers
     * @param bool $trackMetadata
     */
    public function __construct($transformers = [], $trackMetadata = false)
    {
        $this->transformers = $transformers;
        $this->trackMetadata = $trackMetadata;
        $this->transform();
    }

    /**
     * Trigger transformers to load into this store
     */
    protected function transform()
    {
        foreach ($this->transformers as $transformer) {
            $transformer->transform($this);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $metadata = [])
    {
        $key = strtolower($key);
        if($this->trackMetadata) {
            if(isset($this->metadata[$key]) && isset($this->config[$key])) {
                if(!isset($this->history[$key])) {
                    $this->history[$key] = [];
                }

                array_unshift($this->history[$key], [
                    'value' => $this->config[$key],
                    'metadata' => $this->metadata[$key]
                ]);
            }

            $this->metadata[$key] = $metadata;
        }

        $this->config[$key] = $value;
    }

    public function get($key)
    {
        $key = strtolower($key);
        if(!$this->exists($key)) {
            return null;
        }

        return $this->config[$key];
    }

    /**
     * {@inheritdoc}
     */
    public function exists($key)
    {
        $key = strtolower($key);
        return array_key_exists($key, $this->config);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key)
    {
		$key = strtolower($key);
            unset($this->config[$key]);
        }

    /**
     * {@inheritdoc}
     */
    public function deleteAll()
    {
        $this->config = [];
        $this->metadata = [];
        $this->history = [];
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata()
    {
        if(!$this->trackMetadata || !is_array($this->metadata)) {
            return [];
        }

        return $this->metadata;
    }

    /**
     * {@inheritdoc}
     */
    public function getHistory()
    {
        if(!$this->trackMetadata || !is_array($this->history)) {
            return [];
        }

        return $this->history;
    }

    /**
     * String representation of object
     * @link http://php.net/manual/en/serializable.serialize.php
     * @return string the string representation of the object or null
     * @since 5.1.0
     */
    public function serialize()
    {
        // Note: No transformers are serialized because we don't need them
        return json_encode([
            $this->config,
            $this->history,
            $this->metadata,
            $this->trackMetadata
        ]);
    }

    /**
     * Constructs the object
     * @link http://php.net/manual/en/serializable.unserialize.php
     * @param string $serialized <p>
     * The string representation of the object.
     * </p>
     * @return void
     * @since 5.1.0
     */
    public function unserialize($serialized)
    {
        list(
            $this->config,
            $this->history,
            $this->metadata,
            $this->trackMetadata
        ) = json_decode($serialized, true);
    }

    public function __clone()
    {
        // Transformers are only required on original object
        $this->transformers = [];
    }

    public function getNest()
    {
        $nested = clone $this;
        return $nested;
    }
}
