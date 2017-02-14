<?php

namespace micmania1\config\Middleware;

interface Middleware
{
    /**
     * Get config for a class
     *
     * @param string $class
     * @param callable $next
     * @return string
     */
    public function getClassConfig($class, $next);
}
