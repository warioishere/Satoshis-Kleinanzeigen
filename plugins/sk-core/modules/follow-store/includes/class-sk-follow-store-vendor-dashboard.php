<?php

class SK_Follow_Store_Vendor_Dashboard extends \SK\Core\Dashboard\DashboardModule {

    public function config(): ?array {
        return [
            'slug'       => 'followers',
            'title'      => __( 'Followers', 'sk-core' ),
            'icon'       => '<i class="fas fa-heart"></i>',
            'icon_name'  => 'UserStar',
            'pos'        => 175,
            'permission' => 'sk_view_overview_menu',
            'template'   => [ $this, 'render_dashboard' ],
        ];
    }

    protected function register_extras(): void {
        add_action( 'init', [ $this, 'add_endpoint' ] );
    }

    public function add_endpoint() {
        add_rewrite_endpoint( 'followers', EP_PAGES );
    }

    public function render_dashboard( $query_vars ): void {
        $vendor_id = sk_get_current_user_id();
        $followers = sk_follow_store_get_vendor_followers( $vendor_id );
        $response  = [
            'vendor_id' => $vendor_id,
            'followers' => $followers['followers'],
            'customers' => $followers['customers'],
        ];

        sk_follow_store_get_template( 'vendor-dashboard', $response );
    }
}
