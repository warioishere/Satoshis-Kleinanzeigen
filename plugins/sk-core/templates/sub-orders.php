<?php
/**
 * SK Sub Order Templates
 *
 *
 *
 * @var WC_Order $parent_order
 * @var WC_Order[] $sub_orders
 * @var array $statuses
 */

?>

<header>
    <h2><?php esc_html_e( 'Sub Orders', 'sk-core' ); ?></h2>
</header>

<div class="sk-info">
    <strong><?php esc_html_e( 'Note:', 'sk-core' ); ?></strong>
    <?php
    /**
     * @args WC_Order $parent_order
     * @args WC_Order[] $sub_orders
     * @args array $statuses
     */
    echo esc_html(
        apply_filters(
            'sk_suborder_notice_to_customer',
            esc_html__(
                'This order has products from multiple vendors. So we divided this order into multiple vendor orders. Each order will be handled by their respective vendor independently.', 'sk-core'
            ), $parent_order, $sub_orders, $statuses
        )
    );
    ?>
</div>

<table class="shop_table my_account_orders table table-striped">
    <thead>
        <tr>
            <th class="order-number"><span class="nobr"><?php esc_html_e( 'Order', 'sk-core' ); ?></span></th>
            <th class="order-date"><span class="nobr"><?php esc_html_e( 'Date', 'sk-core' ); ?></span></th>
            <th class="order-status"><span class="nobr"><?php esc_html_e( 'Status', 'sk-core' ); ?></span></th>
            <th class="order-total"><span class="nobr"><?php esc_html_e( 'Total', 'sk-core' ); ?></span></th>
            <th class="order-actions">&nbsp;</th>
        </tr>
    </thead>
    <tbody>
    <?php
    $now = sk_current_datetime();
    // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
    foreach ( $sub_orders as $order ) {
        $item_count = $order->get_item_count();
        $order_date = $order->get_date_created();
        $order_date = is_a( $order_date, 'WC_DateTime' ) ? $now->setTimestamp( $order_date->getTimestamp() ) : $now;
        ?>
            <tr class="order">
                <td class="order-number">
                    <a href="<?php echo esc_url( is_callable( [ $order, 'get_view_order_url' ] ) ? $order->get_view_order_url() : '#' ); ?>">
                        <?php echo esc_html( $order->get_order_number() ); ?>
                    </a>
                </td>
                <td class="order-date">
                    <time datetime="<?php echo esc_attr( $order_date->format( 'Y-m-dTH:i:s' ) ); ?>">
                        <?php echo esc_html( sk_format_date( $order_date ) ); ?>
                    </time>
                </td>
                <td class="order-status" style="text-align:left; white-space:nowrap;">
                    <?php echo isset( $statuses[ 'wc-' . $order->get_status() ] ) ? esc_html( $statuses[ 'wc-' . $order->get_status() ] ) : esc_html( $order->get_status() ); ?>
                </td>
                <td class="order-total">
                    <?php
                    echo wp_kses_post(
                        sprintf(
                            // translators: 1) order total amount 2) order item count
                            _n( '%1$s for %2$s item', '%1$s for %2$s items', $item_count, 'sk-core' ), $order->get_formatted_order_total(), number_format_i18n( $item_count )
                        )
                    );
                    ?>
                </td>
                <td class="order-actions">
                    <?php
                        $actions = array();

                        $actions['view'] = array(
                            'url'  => $order->get_view_order_url(),
                            'name' => __( 'View', 'sk-core' ),
                        );

                        $actions = apply_filters( 'sk_my_account_my_sub_orders_actions', $actions, $order );

                        foreach ( $actions as $key => $action ) { // phpcs:ignore
                            echo '<a href="' . esc_url( $action['url'] ) . '" class="button ' . sanitize_html_class( $key ) . '">' . esc_html( $action['name'] ) . '</a>';
                        }
						?>
                </td>
            </tr>
        <?php } ?>
    </tbody>
</table>
