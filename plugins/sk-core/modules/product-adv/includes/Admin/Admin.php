<?php
namespace SK\Modules\ProductAdvertisement\Admin;

use SK\Modules\ProductAdvertisement\Helper;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class Admin
 *
 *
 */
class Admin {
    /**
     * Admin constructor.
     */
    public function __construct() {
        //enqueue required scripts
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ], 10, 1 );
        // register admin menu
        add_action( 'sk_admin_menu', [ $this, 'add_submenu' ], '16.1' );
        add_filter( 'sk-admin-routes', [ $this, 'admin_routes' ] );

        add_action( 'wp_trash_post', array( $this, 'delete_base_product' ) );
    }

    /**
     * Enqueue Admin Scripts
     *
     * @param string $hook
     *
     *
     * @return void
     */
    public function admin_enqueue_scripts( $hook ) {
        if ( 'toplevel_page_sk' !== $hook ) {
            return;
        }

    }

    /**
     * Add submenu page in sk Dashboard
     *
     * @param string $capability
     *
     *
     * @return void
     */
    public function add_submenu( $capability ) {
        if ( ! current_user_can( $capability ) ) {
            return;
        }

        global $submenu;

        $title = esc_html__( 'Advertising', 'sk' );
        $slug  = 'sk';

        $submenu[ $slug ][] = [ $title, $capability, 'admin.php?page=' . $slug . '#/product-advertising' ]; // phpcs:ignore
    }

    /**
     * Add subscripton route
     *
     * @param  array $routes
     *
     *
     * @return array
     */
    public function admin_routes( $routes ) {
        $routes[] = [
            'path'      => '/product-advertising',
            'name'      => 'ProductAdvertisement',
            'component' => 'ProductAdvertisement',
        ];

        return $routes;
    }

    /**
     * Remove advertisement base product if page has been trashed
     *
     * @sience 3.7.0
     *
     * @param int $post_id
     *
     * @return void
     */
    public function delete_base_product( $post_id ) {
        if ( 'product' !== get_post_type( $post_id ) ) {
            return;
        }

        $option_key = Helper::get_advertisement_base_product_option_key();

        // read the option directly, get_advertisement_base_product() would save the
        // product we are about to trash
        if ( (int) $post_id === (int) get_option( $option_key, 0 ) ) {
            delete_option( $option_key );
        }
    }
}
