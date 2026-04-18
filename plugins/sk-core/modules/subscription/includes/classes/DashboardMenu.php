<?php

namespace SK\Modules\ProductSubscription;

use SK\Core\Dashboard\DashboardModule;

defined( 'ABSPATH' ) || exit;

/**
 * Subscription dashboard menu entry — "Subscription" in the vendor sidebar.
 *
 * Conditionally visible: hidden for vendor_staff role.
 * URL differs depending on whether SK is loaded (sk_get_navigation_url) or
 * fallback to the manually configured subscription page.
 */
class DashboardMenu extends DashboardModule {

    public function config(): ?array {
        if ( current_user_can( 'vendor_staff' ) ) {
            return null;
        }

        if ( Module::is_sk_plugin() ) {
            $permalink = sk_get_navigation_url( 'subscription' );
        } else {
            $page_id   = sk_get_option( 'subscription_pack', 'sk_product_subscription' );
            $permalink = get_permalink( $page_id );
        }

        return [
            'slug'       => 'subscription',
            'title'      => __( 'Subscription', 'sk' ),
            'icon'       => '<i class="fas fa-book"></i>',
            'url'        => $permalink,
            'pos'        => 180,
            'permission' => 'read',
            'template'   => [ $this, 'render_template' ],
        ];
    }

    public function render_template( $query_vars ): void {
        if ( current_user_can( 'vendor_staff' ) ) {
            sk_get_template_part( 'global/no-permission' );
            return;
        }

        sk_get_template_part( 'vendor-subscription-php', '', [ 'is_subscription' => true ] );
    }
}
