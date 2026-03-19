<?php

namespace SK\Modules\FollowStore;

use SK\Core\Cache;

/**
 * Follow Store Cache class.
 *
 *
 * @see \SK\Core\Cache
 */
class FollowStoreCache {

    /**
     * Constructor
     *
     */
    public function __construct() {
        add_action( 'sk_follow_store_toggle_status', [ $this, 'clear_cache' ], 20, 4 );
    }

    /**
     * Clear Cache for Follow Stores Module
     *
     *
     * @param int    $vendor_id
     * @param int    $follower_id
     * @param string $status
     * @param string $current_time
     *
     * @return void
     */
    public function clear_cache( $vendor_id, $follower_id, $status, $current_time ) {
        $cache_group = "followers_{$vendor_id}";

        Cache::delete( 'get_followers', $cache_group );
    }
}
