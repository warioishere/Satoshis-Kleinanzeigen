<?php

/**
* Admin class for store reviews
*
*/
class DSR_Admin {

    /**
     * Load autometically when class initiate
     *
     */
    public function __construct() {
        add_action( 'sk_admin_menu', array( $this, 'load_store_review_menu' ) );
        add_action( 'init', array( $this, 'register_scripts' ) );
    }

    /**
     * Initializes the DSR_Admin() class
     *
     * Checks for an existing DSR_Admin() instance
     * and if it doesn't find one, creates it.
     */
    public static function init() {
        static $instance = false;

        if ( !$instance ) {
            $instance = new DSR_Admin();
        }

        return $instance;
    }

    /**
     * Load store review menu
     *
     *
     * @return void
     */
    public function load_store_review_menu( $capability ) {
        if ( current_user_can( $capability ) ) {
            global $submenu;

            $title = esc_html__( 'Store Reviews', 'sk-core' );
            $slug  = 'sk';

            $submenu[ $slug ][] = [ $title, $capability, 'admin.php?page=' . $slug . '&tab=store-reviews' ];
        }
    }

    /**
     * Register Scripts
     *
     */
    public function register_scripts() {
        list( $suffix, $version ) = sk_get_script_suffix_and_version();

        wp_register_style( 'dsr-admin-css', SK_SELLER_RATINGS_PLUGIN_ASSEST . '/js/admin' . $suffix . '.css', false, $version );
        wp_register_script( 'dsr-admin', SK_SELLER_RATINGS_PLUGIN_ASSEST . '/js/admin' . $suffix . '.js', array( 'jquery' ), $version, true );
    }

}

$dsr_admin = DSR_Admin::init();
