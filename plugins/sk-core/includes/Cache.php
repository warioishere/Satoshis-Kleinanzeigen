<?php

namespace SK\Core;

use SK\Core\Abstracts\SkCache;

/**
 * Cache Helper class.
 *
 * Manage all of the caches of SK and handles it beautifully.
 *
 */
class Cache extends SkCache {

    /**
     * Set Cache Group Prefix.
     *
     *
     * @param string $cache_group_prefix
     *
     * @return string
     */
    protected static function get_cache_group_prefix() {
        return 'sk_cache';
    }

    /**
     * Get Cache Key Prefix.
     *
     *
     * @return string
     */
    protected static function get_cache_prefix() {
        return 'sk';
    }
}
