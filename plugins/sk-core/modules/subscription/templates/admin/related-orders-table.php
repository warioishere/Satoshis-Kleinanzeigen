<?php
/**
 * Display the related orders for a subscription or order
 *
 * @var object $post The primitive post object that is being displayed (as an order or subscription)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>
<style>
    .sk_vendor_subscriptions_related_orders {
        margin: 0;
        overflow: auto;
    }

    .sk_vendor_subscriptions_related_orders table {
        width: 100%;
        background: #fff;
        border-collapse: collapse;
    }

    .sk_vendor_subscriptions_related_orders table thead th {
        background: #f8f8f8;
        padding: 8px;
        font-size: 11px;
        text-align: left;
        color: #555;
        -webkit-touch-callout: none;
        -webkit-user-select: none;
        -khtml-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        user-select: none;
    }

    .sk_vendor_subscriptions_related_orders table thead th:last-child {
        padding-right: 12px;
    }

    .sk_vendor_subscriptions_related_orders table thead th:first-child {
        padding-left: 12px;
    }

    .sk_vendor_subscriptions_related_orders table thead th:last-of-type,
    .sk_vendor_subscriptions_related_orders table td:last-of-type {
        text-align: right;
    }

    .sk_vendor_subscriptions_related_orders table tbody th,
    .sk_vendor_subscriptions_related_orders table td {
        padding: 8px;
        text-align: left;
        line-height: 26px;
        vertical-align: top;
        border-bottom: 1px dotted #ececec;
    }

    .sk_vendor_subscriptions_related_orders table tbody th:last-child,
    .sk_vendor_subscriptions_related_orders table td:last-child {
        padding-right: 12px;
    }

    .sk_vendor_subscriptions_related_orders table tbody th:first-child,
    .sk_vendor_subscriptions_related_orders table td:first-child {
        padding-left: 12px;
    }

    .sk_vendor_subscriptions_related_orders table tbody tr:last-child td {
        border-bottom: none;
    }
</style>
<div class="sk_vendor_subscriptions_related_orders">
	<table>
		<thead>
			<tr>
				<th><?php esc_html_e( 'Order Number', 'sk' ); ?></th>
				<th><?php esc_html_e( 'Relationship', 'sk' ); ?></th>
				<th><?php esc_html_e( 'Date', 'sk' ); ?></th>
				<th><?php esc_html_e( 'Status', 'sk' ); ?></th>
				<th><?php echo esc_html_x( 'Total', 'table heading', 'sk' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php do_action( 'sk_vendor_subscription_related_orders_meta_box_rows', $post ); ?>
		</tbody>
	</table>
</div>
