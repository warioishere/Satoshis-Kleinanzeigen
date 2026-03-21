<?php

namespace SK\Core\Admin;


class Menu {

    /**
     * Class constructor
     *
     *
     * @return void
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
    }

    /**
     * Add SK admin menu
     *
     *
     * @return void
     */
    public function add_admin_menu() {
        global $submenu;

        $capability = ska_admin_menu_capability();
        if ( ! current_user_can( $capability ) ) {
            return;
        }

        $menu_position = sk_admin_menu_position();
        $slug = 'sk';
        $menu_icon  = 'dashicons-store';

        $dashboard = add_menu_page(
            __( 'SK', 'sk-core' ),
            __( 'SK', 'sk-core' ),
            $capability,
            $slug,
            [ $this, 'dashboard' ],
            $menu_icon,
            $menu_position
        );

        // Submenu items are now registered by PhpDashboard at priority 5.
        // Fire the hook so sk-pro can register its pages.
        do_action( 'sk_admin_menu', $capability, $menu_position );

        // phpcs:enable

        add_action( $dashboard, [ $this, 'dashboard_script' ] );
    }

    /**
     * Enqueue basic admin styles for the dashboard page.
     */
    public function dashboard_script() {
        wp_enqueue_style( 'sk-admin-css' );
    }

    /**
     * Load Dashboard Template
     *
     *
     * @return void
     */
    public function dashboard() {
        sk_get_container()->get( \SK\Core\Admin\PhpDashboard::class )->render();
    }
}
