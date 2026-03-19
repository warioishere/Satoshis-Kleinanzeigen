<?php
/**
 * Dashboard Subscription Index Template.
 *
 *
 * @var string $link               Link URL for Dashboard Subscription Page.
 * @var string $active_tab         Active Tab.
 * @var int    $user_id            User ID.
 * @var object $subscription_packs Subscription Packs.
 */

use SK\Modules\Subscription\Helper;

// Count packs and orders for tab badges
$pack_count  = $subscription_packs instanceof WP_Query ? $subscription_packs->found_posts : 0;
$orders_data = Helper::get_paginated_subscription_orders_by_vendor_id( sk_get_current_user_id(), 1 );
$order_count = ! empty( $orders_data['total'] ) ? intval( $orders_data['total'] ) : ( ! empty( $orders_data['orders'] ) ? count( $orders_data['orders'] ) : 0 );
?>

<div class="sk-dashboard-subscription-wrap">

    <div class="sk-sub-page-header">
        <h2><i class="fas fa-layer-group"></i> <?php esc_html_e( 'Abonnements', 'sk' ); ?></h2>
    </div>

    <div class="sk-sub-tab-filter">
        <a href="<?php echo esc_url( add_query_arg( [ 'tab' => 'subscription_packs' ], $link ) ); ?>"
           class="sk-sub-tab<?php echo 'subscription_orders' !== $active_tab ? ' active' : ''; ?>">
            <i class="fas fa-box"></i>
            <?php esc_html_e( 'Pakete', 'sk' ); ?>
            <?php if ( $pack_count > 0 ) : ?>
                <span class="sk-sub-tab-count"><?php echo $pack_count; ?></span>
            <?php endif; ?>
        </a>
        <a href="<?php echo esc_url( add_query_arg( [ 'tab' => 'subscription_orders' ], $link ) ); ?>"
           class="sk-sub-tab<?php echo 'subscription_orders' === $active_tab ? ' active' : ''; ?>">
            <i class="fas fa-receipt"></i>
            <?php esc_html_e( 'Bestellungen', 'sk' ); ?>
            <?php if ( $order_count > 0 ) : ?>
                <span class="sk-sub-tab-count"><?php echo $order_count; ?></span>
            <?php endif; ?>
        </a>
    </div>

    <div id="sk_tabs_container">
        <?php if ( 'subscription_orders' === $active_tab ) : ?>
            <div class="tab-pane active" id="sk-dashboard-subscription-orders">
                <?php
                $page = ! empty( $_GET['pagenum'] ) ? absint( wp_unslash( $_GET['pagenum'] ) ) : 1;
                sk_get_template_part(
                    'dashboard/order-listing', '',
                    [
                        'is_subscription'     => true,
                        'subscription_orders' => Helper::get_paginated_subscription_orders_by_vendor_id( sk_get_current_user_id(), $page ),
                    ]
                );
                ?>
            </div>
        <?php else : ?>
            <div class="tab-pane active" id="sk-dashboard-subscription-packs">
                <?php
                sk_get_template_part(
                    'dashboard/pack-listing', '',
                    [
                        'is_subscription'    => true,
                        'user_id'            => $user_id,
                        'subscription_packs' => $subscription_packs,
                    ]
                );
                ?>
            </div>
        <?php endif ?>
    </div>
</div>
