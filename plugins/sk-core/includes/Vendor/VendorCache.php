<?php

namespace SK\Core\Vendor;

use SK\Core\Cache;
use SK\Core\Product\ProductCache;

/**
 * Vendor Cache class.
 *
 * Manage all of the caches for vendor.
 *
 *
 * @see \SK\Core\Cache
 */
class VendorCache {

    public function __construct() {
        add_action( 'sk_new_vendor', [ $this, 'clear_cache_group' ], 10 );
        add_action( 'sk_update_vendor', [ $this, 'clear_cache_group' ], 10 );
        add_action( 'sk_delete_vendor', [ $this, 'clear_cache_group' ], 10 );
        add_action( 'sk_vendor_enabled', [ $this, 'clear_cache_group' ], 10 );
        add_action( 'sk_vendor_disabled', [ $this, 'clear_cache_group' ], 10 );

        /* Clear wp-user related caches */
        add_action( 'user_register', [ $this, 'after_created_new_wp_user' ], 10 );
        add_action( 'profile_update', [ $this, 'after_updated_wp_user' ], 10, 2 );
        add_action( 'delete_user', [ $this, 'before_deleting_wp_user' ], 10, 2 );
    }

    /**
     * Clear Vendor Cache Group.
     *
     *
     * @param int $vendor_id
     *
     * @return void
     */
    public function clear_cache_group( $vendor_id ) {
        // Nothing is cached under a 'vendors' group — the invalidation that used
        // to stand here flushed an empty Redis group on every vendor and user
        // save, at roughly 7ms a call. Vendor listings are not object-cached.
        ProductCache::delete( $vendor_id );
    }

    /**
     * Clear Vendor Cache Group after changing wp_user.
     *
     *
     * @param int  $user_id
     * @param bool $is_user_delete; if user deletes, pass it to true. default - false
     *
     * @return void
     */
    private function clear_wp_user_cache( $user_id, $is_user_delete = false ) {
        // check user has skdar capability
        if ( ! user_can( $user_id, 'skdar' ) ) {
            return;
        }

        // On delete user, clear product caches of that vendor too.
        if ( $is_user_delete ) {
            ProductCache::delete( $user_id );
        }
    }

    /**
     * Clear Vendor Cache Group after new user added to wp user.
     *
     *
     * @param int $user_id
     *
     * @return void
     */
    public function after_created_new_wp_user( $user_id ) {
        $this->clear_wp_user_cache( $user_id );
    }

    /**
     * Clear Vendor Cache Group after updated wp user.
     *
     *
     * @param int   $user_id
     * @param array $old_user_data
     *
     * @return void
     */
    public function after_updated_wp_user( $user_id, $old_user_data ) {
        $this->clear_wp_user_cache( $user_id );
    }

    /**
     * Clear Vendor Cache Group before deleting wp user.
     *
     *
     * @param int   $user_id
     * @param array $reassign
     *
     * @return void
     */
    public function before_deleting_wp_user( $user_id, $reassign ) {
        $this->clear_wp_user_cache( $user_id, true );
    }
}
