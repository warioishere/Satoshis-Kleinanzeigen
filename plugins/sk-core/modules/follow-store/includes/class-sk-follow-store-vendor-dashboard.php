<?php

class SK_Follow_Store_Vendor_Dashboard {

    /**
     * Class constructor
     *
     *
     * @return void
     */
    public function __construct() {
        add_action( 'init', array( $this, 'add_endpoint' ) );
        add_filter( 'sk_get_dashboard_nav', array( $this, 'add_dashboard_nav' ) );
        add_action( 'sk_load_custom_template', array( $this, 'load_dashboard_template' ) );
    }

    /**
     * Register new endpoint for Vendor Dashbaord page
     *
     *
     * @return void
     */
    public function add_endpoint() {
        add_rewrite_endpoint( 'followers', EP_PAGES );
    }

    /**
     * Add settings nav in settings page
     *
     *
     * @param array $settings
     */
    public function add_dashboard_nav( $settings ) {
        $settings['followers'] = array(
            'title'      => __( 'Followers', 'sk' ),
            'icon'       => '<i class="fas fa-heart"></i>',
            'url'        => sk_get_navigation_url( 'followers' ),
            'pos'        => 175,
            'icon_name'  => 'UserStar',
            'permission' => 'sk_view_overview_menu',
        );

        return $settings;
    }

    /**
     * Load dashboard page template
     *
     *
     * @param array $query_vars
     *
     * @return void
     */
    public function load_dashboard_template( $query_vars ) {
        if ( empty( $query_vars ) || ! array_key_exists( 'followers' , $query_vars ) ) {
            return;
        }

        $vendor_id = sk_get_current_user_id();
        $followers = sk_follow_store_get_vendor_followers( $vendor_id );
        $response  = array(
            'vendor_id' => $vendor_id,
            'followers' => $followers['followers'],
            'customers' => $followers['customers'],
        );

        sk_follow_store_get_template( 'vendor-dashboard', $response );
    }
}
