<?php

namespace SK_Lightning\Dashboard;

defined( 'ABSPATH' ) || exit;

class TransactionsPage {

    public function __construct() {
        add_filter( 'sk_get_dashboard_nav', [ $this, 'register_nav' ] );
        add_filter( 'sk_dashboard_nav_active', [ $this, 'set_active' ], 10, 3 );
        add_filter( 'sk_query_var_filter', [ $this, 'add_query_var' ] );
        add_action( 'sk_load_custom_template', [ $this, 'load_template' ] );
    }

    public function register_nav( array $nav ): array {
        $nav['lightning-transactions'] = [
            'title'      => 'Käufe/Verkäufe',
            'icon'       => '<i class="fas fa-bolt"></i>',
            'url'        => sk_get_navigation_url( 'lightning-transactions' ),
            'pos'        => 31,
            'permission' => 'sk_view_overview_menu',
        ];
        return $nav;
    }

    public function set_active( $active_menu, $request, $active ) {
        if ( isset( $request ) && false !== strpos( $request, 'lightning-transactions' ) ) {
            return 'lightning-transactions';
        }
        if ( get_query_var( 'lightning-transactions' ) ) {
            return 'lightning-transactions';
        }
        return $active_menu;
    }

    public function add_query_var( array $vars ): array {
        $vars[] = 'lightning-transactions';
        return $vars;
    }

    public function load_template( array $query_vars ): void {
        if ( isset( $query_vars['lightning-transactions'] ) ) {
            require SK_LIGHTNING_DIR . 'templates/dashboard-transactions.php';
        }
    }
}
