<?php

namespace micmania1\config;

/**
 * This represents a colletction of config keys and values.
 */
interface ConfigCollectionInterface
{
    /**
     * Set the value of a single item
     *
     * @param string $key
     * @param mixed $value
     * @param array $metadata
     */
    public function set($key, $value, $metadata = []);

    /**
     * Fetches value
     *
     * @param string $key
     *
     * @return mixed
     */
    public function get($key);

    /**
     * Checks to see if a config item exists
     *
     * @param string $key
     *
     * @return boolean
     */
    public function exists($key);

    /**
     * Removed a config item including any associated metadata
     *
     * @param string $key
     */
    public function delete($key);

    /**
     * Delete all entries
     */
    public function deleteAll();

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
     * @return mixed
     */
    public function getNest();
}
