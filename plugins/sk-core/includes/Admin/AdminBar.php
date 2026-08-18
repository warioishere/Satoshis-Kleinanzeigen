<?php

namespace SK\Core\Admin;

use SK\Core\Utilities\ReportUtil;
use WP_Admin_Bar;

/**
 * WordPress settings API For SK Admin Settings class
 *
 * @author Tareq Hasan
 */
class AdminBar {

    /**
     * Class constructor
     *
     * Sets up all the appropriate hooks and actions
     * within our plugin.
     *
     * @return void
     */
    public function __construct() {
        add_action( 'wp_before_admin_bar_render', [ $this, 'sk_admin_toolbar' ] );

        if ( apply_filters( 'sk_show_admin_bar_visit_dashboard', true ) ) {
            add_action( 'admin_bar_menu', [ $this, 'visit_dashboard_menu' ], 35 );
        }
    }

    /**
     * Add Menu in Dashboard Top bar
     *
     * @return void
     */
    public function sk_admin_toolbar() {
        global $wp_admin_bar;

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $args = [
            'id'     => 'sk',
            'title'  => __( 'SK', 'sk-core' ),
            'href'   => admin_url( 'admin.php?page=sk-dashboard' ),
        ];

        $wp_admin_bar->add_menu( $args );

        $wp_admin_bar->add_menu(
            [
                'id'     => 'sk-dashboard',
                'parent' => 'sk',
                'title'  => __( 'Dashboard', 'sk-core' ),
                'href'   => admin_url( 'admin.php?page=sk-dashboard' ),
            ]
        );

        $wp_admin_bar->add_menu(
            [
                'id'     => 'sk-settings',
                'parent' => 'sk',
                'title'  => __( 'Settings', 'sk-core' ),
                'href'   => admin_url( 'admin.php?page=sk&tab=settings' ),
            ]
        );

        /*
         * Add new or remove toolbar
         *
         */
        do_action( 'sk_render_admin_toolbar', $wp_admin_bar );
    }

    /**
     * Show visit vendor dashboard
     *
     * @param WP_Admin_Bar $wp_admin_bar
     *
     * @return void
     */
    public function visit_dashboard_menu( $wp_admin_bar ) {
        if ( ! is_admin() || ! is_admin_bar_showing() ) {
            return;
        }

        // Show only when the user is a member of this site, or they're a super admin.
        if ( ! is_user_member_of_blog() && ! is_super_admin() ) {
            return;
        }

        $menus = $this->get_sk_admin_bar_menus();

        // Added admin menus for sk in wp admin bar.
        foreach ( $menus as $menu ) {
            $wp_admin_bar->add_node( $menu );
        }
    }

    /**
     * Get admin menus data for sk.
     *
     *
     * @return array
     */
    public function get_sk_admin_bar_menus() {
        $menus            = [];
        $shop             = wc_get_page_permalink( 'shop' );
        $stores           = (int) sk_get_option( 'store_listing', 'sk_pages', 0 );
        $vendor_dashboard = (int) sk_get_option( 'dashboard', 'sk_pages', 0 );

        if ( $shop ) {
            $menus[] = [
                'parent' => 'site-name',
                'id'     => 'view-store',
                'title'  => __( 'Visit Shop', 'sk-core' ),
                'href'   => wc_get_page_permalink( 'shop' ),
            ];
        }

        if ( $stores ) {
            $menus[] = [
                'parent' => 'site-name',
                'id'     => 'view-stores',
                'title'  => __( 'Visit Stores', 'sk-core' ),
                'href'   => get_permalink( $stores ),
            ];
        }

        if ( $vendor_dashboard ) {
            $menus[] = [
                'parent' => 'site-name',
                'id'     => 'view-dashboard',
                'title'  => __( 'Visit Vendor Dashboard', 'sk-core' ),
                'href'   => get_permalink( $vendor_dashboard ) . ( ReportUtil::is_analytics_enabled() ? '?path=%2Fanalytics%2FOverview' : '' ),
            ];
        }

        return $menus;
    }
}
