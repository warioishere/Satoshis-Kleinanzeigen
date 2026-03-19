<?php

namespace SK\Core\Install;

use SK\Core\Rewrites;
use WP_Roles;

class Installer {

    public function do_install() {
        $this->add_version_info();
        $this->user_roles();
        $this->setup_pages();
        $this->woocommerce_settings();
        $this->create_tables();
        $this->add_store_name_meta_key_for_admin_users();
        $this->schedule_cron_jobs();

        $rewrites = new Rewrites();
        $rewrites->register_rule();
        flush_rewrite_rules();
    }

    public function add_store_name_meta_key_for_admin_users() {
        $users = new \WP_User_Query( [
            'role__in' => [ 'administrator', 'shop_manager' ],
            'fields'   => 'ID',
        ] );

        foreach ( $users->get_results() as $user_id ) {
            if ( get_user_meta( $user_id, 'sk_store_name', true ) ) {
                continue;
            }
            $user = get_user_by( 'id', $user_id );
            update_user_meta( $user_id, 'sk_store_name', $user->display_name );
        }
    }

    private function schedule_cron_jobs() {
        if ( ! function_exists( 'WC' ) || ! WC()->queue() ) {
            return;
        }

        $hook = 'sk_daily_midnight_cron';
        if ( as_next_scheduled_action( $hook ) ) {
            as_unschedule_all_actions( $hook );
        }

        $now = sk_current_datetime()->modify( 'midnight' )->getTimestamp();
        WC()->queue()->schedule_cron( $now, '0 0 * * *', $hook, [], 'sk' );
    }

    public function add_version_info() {
        if ( empty( get_option( 'sk_installed_time' ) ) ) {
            update_option( 'sk_installed_time', sk_current_datetime()->getTimestamp() );
        }
    }

    public function woocommerce_settings() {
        update_option( 'woocommerce_enable_myaccount_registration', 'yes' );
    }

    public function user_roles() {
        global $wp_roles;

        if ( class_exists( 'WP_Roles' ) && ! isset( $wp_roles ) ) {
            $wp_roles = new WP_Roles();
        }

        add_role( 'seller', __( 'Vendor', 'sk-core' ), [
            'read'                      => true,
            'publish_posts'             => true,
            'edit_posts'                => true,
            'delete_published_posts'    => true,
            'edit_published_posts'      => true,
            'delete_posts'              => true,
            'manage_categories'         => true,
            'moderate_comments'         => true,
            'upload_files'              => true,
            'edit_shop_orders'          => true,
            'edit_product'              => true,
            'read_product'              => true,
            'delete_product'            => true,
            'edit_products'             => true,
            'publish_products'          => true,
            'read_private_products'     => true,
            'delete_products'           => true,
            'delete_private_products'   => true,
            'delete_published_products' => true,
            'edit_private_products'     => true,
            'edit_published_products'   => true,
            'manage_product_terms'      => true,
            'delete_product_terms'      => true,
            'assign_product_terms'      => true,
            'skdar'                     => true,
        ] );

        $capabilities = [];
        foreach ( sk_get_all_caps() as $cap ) {
            $capabilities = array_merge( $capabilities, array_keys( $cap ) );
        }

        $wp_roles->add_cap( 'shop_manager', 'skdar' );
        $wp_roles->add_cap( 'administrator', 'skdar' );

        foreach ( $capabilities as $capability ) {
            $wp_roles->add_cap( 'seller', $capability );
            $wp_roles->add_cap( 'administrator', $capability );
            $wp_roles->add_cap( 'shop_manager', $capability );
        }
    }

    public function setup_pages() {
        if ( get_option( 'sk_pages_created', false ) ) {
            return;
        }

        $pages = [
            [ 'post_title' => __( 'Dashboard', 'sk-core' ), 'slug' => 'dashboard', 'page_id' => 'dashboard', 'content' => '[sk-dashboard]' ],
            [ 'post_title' => __( 'Store List', 'sk-core' ), 'slug' => 'store-listing', 'page_id' => 'store_listing', 'content' => '[sk-stores]' ],
            [ 'post_title' => __( 'My Orders', 'sk-core' ), 'slug' => 'my-orders', 'page_id' => 'my_orders', 'content' => '[sk-my-orders]' ],
        ];

        $settings = [];
        foreach ( $pages as $page ) {
            $page_id = $this->create_page( $page );
            if ( $page_id ) {
                $settings[ $page['page_id'] ] = $page_id;
            }
        }

        update_option( 'sk_pages', $settings );
        update_option( 'sk_pages_created', true );
    }

    public function create_page( $page ) {
        if ( get_page_by_path( $page['post_title'] ) ) {
            return false;
        }

        $page_id = wp_insert_post( [
            'post_title'     => $page['post_title'],
            'post_name'      => $page['slug'],
            'post_content'   => $page['content'],
            'post_status'    => 'publish',
            'post_type'      => 'page',
            'comment_status' => 'closed',
        ] );

        return ( $page_id && ! is_wp_error( $page_id ) ) ? $page_id : false;
    }

    public function create_tables() {
        include_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $this->create_announcement_table();
    }

    private function create_announcement_table() {
        global $wpdb;
        dbDelta( "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}sk_announcement` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `user_id` bigint(20) unsigned NOT NULL,
            `post_id` bigint(11) NOT NULL,
            `status` varchar(30) NOT NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB {$wpdb->get_charset_collate()};" );
    }

}
