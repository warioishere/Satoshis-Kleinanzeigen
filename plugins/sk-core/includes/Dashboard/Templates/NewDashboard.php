<?php

namespace SK\Core\Dashboard\Templates;

class NewDashboard {

	/**
	 * Class constructor
	 *
	 */
    public function __construct() {
        add_filter( 'sk_query_var_filter', [ $this, 'add_query_var' ] );
        add_action( 'sk_load_custom_template', [ $this, 'new_dashboard_content' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_dashboard_scripts' ], 50 );
        add_filter( 'sk_get_dashboard_nav', [ $this, 'fix_dashboard_nav_url' ], 999 );
        add_filter( 'sk_is_dashboard_nav_dependency_resolved', '__return_false', PHP_INT_MAX );
        add_filter( 'sk_seller_setup_wizard_url', 'sk_get_navigation_url' );
    }

	/**
	 * Add query var for new dashboard.
	 *
	 *
	 * @param array $query_vars
	 *
	 * @return array
	 */
    public function add_query_var( $query_vars ) {
        $query_vars['new'] = 'new';
        return $query_vars;
    }

	/**
	 * Load new dashboard content — pure PHP overview.
	 *
	 *
	 * @param array $query_vars
	 *
	 * @return void
	 */
    public function new_dashboard_content( $query_vars ) {
        if ( isset( $query_vars['new'] ) ) {
            if ( ! current_user_can( 'sk_view_overview_menu' ) ) {
                sk_get_template_part( 'global/no-permission' );
            } else {
                sk_get_template_part( 'dashboard/dashboard' );
            }
        }
    }

	/**
	 * Enqueue the AJAX live navigation script for the dashboard.
	 *
	 *
	 * @return void
	 */
	public function enqueue_dashboard_scripts() {
		if ( ! sk_is_seller_dashboard() ) {
			return;
		}

		wp_enqueue_script(
			'sk-dashboard-nav',
			SK_CORE_ASSETS . '/js/sk-dashboard-nav.js',
			[],
			SK_CORE_VERSION,
			true
		);
	}

	/**
	 * Point the "Dashboard" nav item to the plain PHP overview URL.
	 *
	 * VendorNavMenuChecker (priority 999) runs on the same hook; we run at
	 * the same priority but register last, so this executes after it.
	 *
	 *
	 * @param array $nav
	 *
	 * @return array
	 */
    public function fix_dashboard_nav_url( $nav ) {
        if ( isset( $nav['dashboard'] ) ) {
            $nav['dashboard']['url'] = sk_get_navigation_url( '' );
        }
        return $nav;
    }

}
