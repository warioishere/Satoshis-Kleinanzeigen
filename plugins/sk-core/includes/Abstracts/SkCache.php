<?php

namespace SK\Core\Abstracts;

use SK\Core\Traits\ObjectCache;
use SK\Core\Traits\TransientCache;

/**
 * SK Cache class.
 *
 * Manage all of the caches of your WordPress plugin and handles it beautifully.
 *
 *
 */
abstract class SkCache {

    use ObjectCache, TransientCache;

    /**
     * Get Cache Group Prefix.
     *
     *
     * @return string
     */
    abstract protected static function get_cache_group_prefix();

    /**
     * Get Cache Key Prefix.
     *
     *
     * @return string
     */
    abstract protected static function get_cache_prefix();

    /**
     * Add Cache Group Prefix to group.
     *
     *
     * @param string $group
     *
     * @return string
     */
    private static function get_cache_group_with_prefix( $group ) {
        // Add prefix to group.
        return empty( $group ) ? '' : static::get_cache_group_prefix() . '_' . sanitize_key( $group );
    }

    /**
     * Get Microtime value as prefix.
     *
     * This will Replace microtime() value's dot => '.' and space => ' '
     * characters with underscore => '_' character
     *
     *
     * @return string
     */
    private static function get_time_prefix() {
        return str_replace( [ '.', ' ' ], '_', microtime() );
    }
}
