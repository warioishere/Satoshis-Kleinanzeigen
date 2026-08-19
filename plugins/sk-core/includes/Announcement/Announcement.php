<?php

namespace SK\Core\Announcement;

use SK\Core\Cache;
use SK\Core\Traits\ChainableContainer;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 *  SK Announcement class for Admin
 *
 *  Announcement for seller
 *
 *
 * 
 *
 * @property Manager           $manager
 * @property BackgroundProcess $processor
 */
class Announcement {

    use ChainableContainer;

    /**
     *  Automatically load all actions
     */
    public function __construct() {
        $this->set_controllers();

        add_action( 'admin_init', [ $this->manager, 'maybe_upgrade_table' ] );
    }

    public function set_controllers() {
        $this->container['post_type'] = new PostType();
        $this->container['template']  = new Frontend\Template();
        $this->container['manager']   = new Manager();
        $this->container['mails']     = new Mails();
        $this->container['processor'] = new BackgroundProcess();
    }

    /**
     * Delete individual seller announcement cache.
     *
     *
     * @param array|int $seller_ids
     * @param int       $post_id
     *
     * @return void
     */
    public function delete_announcement_cache( $seller_ids, $post_id = null ) {
        if ( is_array( $seller_ids ) ) {
            $seller_ids = array_filter( $seller_ids, 'is_numeric' );
        } else {
            $seller_ids = is_numeric( $seller_ids ) ? [ $seller_ids ] : [];
        }

        // Trashing, deleting and untrashing pass an empty recipient list, so the
        // recipients have to be looked up or their lists stay stale.
        if ( empty( $seller_ids ) && is_numeric( $post_id ) ) {
            $seller_ids = $this->manager->get_assigned_seller_from_db( $post_id );
        }

        foreach ( $seller_ids as $seller_id ) {
            Cache::invalidate_group( "seller_announcement_{$seller_id}" );
        }

        // remove the main cache group — this has to match the group Manager::all()
        // writes the admin lists to.
        Cache::invalidate_group( 'announcement' );
    }
}
