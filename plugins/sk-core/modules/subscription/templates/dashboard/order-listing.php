<?php
/**
 * Dashboard Subscription Order Listing Template.
 *
 *
 * @var array $subscription_orders Subscription Orders.
 */

$status_colors = [
    'completed'  => ['bg' => 'rgba(40,167,69,0.15)',  'border' => 'rgba(40,167,69,0.3)',  'color' => '#5cb85c'],
    'processing' => ['bg' => 'rgba(247,147,26,0.15)', 'border' => 'rgba(247,147,26,0.3)', 'color' => '#F7931A'],
    'pending'    => ['bg' => 'rgba(255,193,7,0.12)',  'border' => 'rgba(255,193,7,0.25)', 'color' => '#f0c040'],
    'on-hold'    => ['bg' => 'rgba(90,116,153,0.15)', 'border' => 'rgba(90,116,153,0.3)', 'color' => '#7a9bbf'],
    'cancelled'  => ['bg' => 'rgba(220,53,69,0.12)',  'border' => 'rgba(220,53,69,0.25)', 'color' => '#e06c75'],
    'refunded'   => ['bg' => 'rgba(220,53,69,0.12)',  'border' => 'rgba(220,53,69,0.25)', 'color' => '#e06c75'],
    'failed'     => ['bg' => 'rgba(220,53,69,0.15)',  'border' => 'rgba(220,53,69,0.3)',  'color' => '#e06c75'],
];
?>
<div class="sk-subscription-content">

    <?php if ( ! empty( $subscription_orders['orders'] ) ) : ?>

        <div class="sk-sub-orders-list">
            <?php foreach ( $subscription_orders['orders'] as $order ) :
                $status     = $order->get_status();
                $sc         = $status_colors[ $status ] ?? ['bg' => 'rgba(255,255,255,0.06)', 'border' => 'rgba(255,255,255,0.1)', 'color' => '#8a9bb0'];

                // Gather the purchased pack name(s) from the order's line items —
                // subscription orders usually have a single product_pack item.
                $pack_names = [];
                foreach ( $order->get_items() as $item ) {
                    $pack_names[] = $item->get_name();
                }
                $pack_label = implode( ', ', array_filter( $pack_names ) );
            ?>
                <div class="sk-sub-order-card">
                    <div class="sk-sub-order-card__left">
                        <span class="sk-sub-order-card__number">#<?php echo esc_html( $order->get_order_number() ); ?></span>
                        <?php if ( $pack_label ) : ?>
                            <span class="sk-sub-order-card__pack"><i class="fas fa-box-open"></i> <?php echo esc_html( $pack_label ); ?></span>
                        <?php endif; ?>
                        <span class="sk-sub-order-card__date">
                            <i class="fas fa-calendar-alt"></i>
                            <?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?>
                        </span>
                    </div>
                    <div class="sk-sub-order-card__right">
                        <span class="sk-sub-order-card__status" style="background:<?php echo esc_attr( $sc['bg'] ); ?>;border-color:<?php echo esc_attr( $sc['border'] ); ?>;color:<?php echo esc_attr( $sc['color'] ); ?>">
                            <?php echo esc_html( wc_get_order_status_name( $status ) ); ?>
                        </span>
                        <span class="sk-sub-order-card__total">
                            <?php echo wp_kses_post( $order->get_formatted_order_total() ); ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php
        $total_pages  = $subscription_orders['total_pages'];
        $current_page = $subscription_orders['current_page'];
        $base_url     = sk_get_navigation_url( 'subscription' );

        if ( $total_pages > 1 ) :
            echo '<div class="pagination-wrap">';
            $page_links = paginate_links( [
                'current'  => $current_page,
                'total'    => $total_pages,
                'base'     => $base_url . '%_%',
                'format'   => '?pagenum=%#%',
                'add_args' => false,
                'type'     => 'array',
            ] );
            echo "<ul class='pagination'>\n\t<li>";
            echo join( "</li>\n\t<li>", $page_links );
            echo "</li>\n</ul>\n";
            echo '</div>';
        endif;
        ?>

    <?php else : ?>
        <div class="sk-sub-empty">
            <i class="fas fa-receipt"></i>
            <p><?php esc_html_e( 'Keine Bestellungen gefunden.', 'sk' ); ?></p>
        </div>
    <?php endif; ?>

</div>
