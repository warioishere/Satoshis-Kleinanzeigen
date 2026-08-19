<?php wp_enqueue_style( 'sk-subscription-related-orders' ); ?>
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
<div class="sk_vendor_subscriptions_related_orders">
	<table>
		<thead>
			<tr>
				<th><?php esc_html_e( 'Order Number', 'sk-core' ); ?></th>
				<th><?php esc_html_e( 'Relationship', 'sk-core' ); ?></th>
				<th><?php esc_html_e( 'Date', 'sk-core' ); ?></th>
				<th><?php esc_html_e( 'Status', 'sk-core' ); ?></th>
				<th><?php echo esc_html_x( 'Total', 'table heading', 'sk-core' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php do_action( 'sk_vendor_subscription_related_orders_meta_box_rows', $post ); ?>
		</tbody>
	</table>
</div>
