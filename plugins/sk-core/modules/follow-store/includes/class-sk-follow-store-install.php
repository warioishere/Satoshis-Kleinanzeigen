<?php

class SK_Follow_Store_Install {

    /** Bump to let maybe_upgrade_tables() apply schema changes. */
    const DB_VERSION = '1';

    const VERSION_OPTION = 'sk_follow_store_db_version';

    /**
     * Class constructor
     *
     *
     * @return void
     */
    public function __construct() {
        add_action( 'sk_activated_module_follow_store', array( $this, 'activate' ) );
        add_action( 'sk_deactivated_module_follow_store', array( $this, 'deactivate' ) );
        add_action( 'deleted_user', array( $this, 'delete_user_rows' ) );
        add_action( 'admin_init', array( __CLASS__, 'maybe_upgrade_tables' ) );
    }

    /**
     * Apply schema changes outside the activation hook.
     *
     * create_tables() only ever ran when the module was activated, and a deploy
     * that resets the working tree never activates anything — a new column would
     * have stayed unapplied. Cheap no-op once the version matches.
     *
     *
     * @return void
     */
    public static function maybe_upgrade_tables() {
        if ( get_option( self::VERSION_OPTION ) === self::DB_VERSION ) {
            return;
        }

        ( new self() )->create_tables();

        update_option( self::VERSION_OPTION, self::DB_VERSION, false );
    }

    /**
     * Drop follow rows of a deleted user, on both sides of the relation.
     *
     * Without this the table keeps rows pointing at accounts that are gone; the
     * vendor then sees them in the follower list as "(no name)" while the count
     * still includes them.
     *
     *
     * @param int $user_id
     *
     * @return void
     */
    public function delete_user_rows( $user_id ) {
        $user_id = absint( $user_id );

        if ( ! $user_id ) {
            return;
        }

        global $wpdb;

        $vendor_ids = $wpdb->get_col( $wpdb->prepare(
            "select vendor_id from {$wpdb->prefix}sk_follow_store_followers where follower_id = %d",
            $user_id
        ) );

        $wpdb->query( $wpdb->prepare(
            "delete from {$wpdb->prefix}sk_follow_store_followers where vendor_id = %d or follower_id = %d",
            $user_id,
            $user_id
        ) );

        foreach ( array_unique( array_merge( $vendor_ids, [ $user_id ] ) ) as $vendor_id ) {
            \SK\Core\Cache::invalidate_group( 'followers_' . absint( $vendor_id ) );
        }
    }

    /**
     * Fires after module activated
     *
     *
     * @return void
     */
    public function activate() {
        $this->queue_flash_rewrite_rules();
        $this->create_tables();
    }

    /**
     * Fires after module deactivated
     *
     *
     * @return void
     */
    public function deactivate() {
        $this->queue_flash_rewrite_rules();
        SK_Follow_Store_Cron::unschedule_event();

        global $sk_follow_store_updates_bg;
        $sk_follow_store_updates_bg->cancel_process();
    }

    /**
     * Add option to flush rewrite rules
     *
     * Process handled by WC_Post_Types::maybe_flush_rewrite_rules()
     *
     *
     * @return void
     */
    private function queue_flash_rewrite_rules() {
        update_option( 'woocommerce_queue_flush_rewrite_rules', 'yes' );
    }

    /**
     * Create module tables
     *
     *
     * @return void
     */
    public function create_tables() {
        global $wpdb;

        $collate = '';

        if ( $wpdb->has_cap( 'collation' ) ) {
            if ( ! empty($wpdb->charset ) ) {
                $collate .= "DEFAULT CHARACTER SET $wpdb->charset";
            }

            if ( ! empty($wpdb->collate ) ) {
                $collate .= " COLLATE $wpdb->collate";
            }
        }

        $table_schema = array(
            "CREATE TABLE `{$wpdb->prefix}sk_follow_store_followers` (
              `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
              `vendor_id` bigint(20) unsigned NOT NULL,
              `follower_id` bigint(20) unsigned NOT NULL,
              `followed_at` datetime NOT NULL,
              `unfollowed_at` datetime DEFAULT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `sk_follow_store_followers_vendor_id_follower_id_unique` (`vendor_id`,`follower_id`),
              KEY `sk_follow_store_followers_vendor_id` (`vendor_id`),
              KEY `sk_follow_store_followers_follower_id` (`follower_id`)
            ) $collate;",
        );

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        foreach ( $table_schema as $table ) {
            dbDelta( $table );
        }
    }
}
