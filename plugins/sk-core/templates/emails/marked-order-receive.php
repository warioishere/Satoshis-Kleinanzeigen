<?php
/**
 * Order Marked as Receive Email.
 *
 * An email sent to the vendor when an order marked as receive from customer.
 *
 * @class   SK_Email_Marked_Order_Received
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<?php /* translators: %s: Customer first name */ ?>
<p><?php printf( esc_html__( 'Hi %s,', 'sk' ), esc_html( $vendor_name ?? '' ) ); ?></p>

<p>
    <?php
    printf(
        /* translators: 1: Customer name. */
        __( 'The shipment for the following order has been marked as received by the %1$s:', 'sk' ),
        esc_html( $customer_name ),
        " \n\n"
    );
    ?>
</p>

<blockquote><?php echo wpautop( wptexturize( make_clickable( $ship_info ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></blockquote>

<p><?php esc_html_e( 'As a reminder, here are your order details:', 'sk' ); ?></p>

<div style="margin-bottom: 30px;">
    <table class="td" cellspacing="0" cellpadding="6" style="width: 100%; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;" border="1">
        <thead>
            <tr>
                <th class="td" scope="col"><?php esc_html_e( 'Product', 'sk' ); ?></th>
                <th class="td" scope="col"><?php esc_html_e( 'Quantity', 'sk' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( $order->get_items() ) : ?>
                <?php foreach ( $order->get_items() as $item_id => $item ) : ?>
                    <?php
                    $item_details = new \WC_Order_Item_Product( $item_id );
                    $_product     = $item_details->get_product();
                    ?>
                    <tr>
                        <td class="td" style="text-align: left;vertical-align: middle;font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;color: #636363;border: 1px solid #e5e5e5;padding: 12px">
                            <?php if ( $_product ) : ?>
                                <a target="_blank" href="<?php echo esc_url( get_permalink( absint( $_product->get_id() ) ) ); ?>">
                                    <?php echo esc_html( $item_details['name'] ); ?>
                                </a>
                            <?php else : ?>
                                <?php echo esc_html( $item_details['name'] ); ?>
                            <?php endif; ?>
                        </td>
                        <td class="td" style="text-align: left;vertical-align: middle;font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;color: #636363;border: 1px solid #e5e5e5;padding: 12px">
                            <strong><?php echo esc_html( $item['quantity'] ); ?> (<?php esc_html_e( 'Qty', 'sk' ); ?>)</strong>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            <tr>
                <th class="td" scope="row" style="text-align: left;color: #636363;border: 1px solid #e5e5e5;vertical-align: middle;padding: 12px"><?php esc_html_e( 'Payment Method :', 'sk' ); ?></th>

                <td class="td" style="text-align: left;color: #636363;border: 1px solid #e5e5e5;vertical-align: middle;padding: 12px">
                    <?php echo esc_html( $order->get_payment_method_title() ); ?>
                </td>
            </tr>
        </tbody>
        <tfoot>
            <?php if ( $order->get_customer_note() ) : ?>
                <tr>
                    <th class="td" scope="row" colspan="2"><?php esc_html_e( 'Note:', 'sk' ); ?></th>
                    <td class="td"><?php echo wp_kses_post( nl2br( wptexturize( $order->get_customer_note() ) ) ); ?></td>
                </tr>
            <?php endif; ?>
        </tfoot>
    </table>
</div>

<?php
/*
 * @hooked WC_Emails::order_meta() Shows order meta data.
 */
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );
?>

<p><strong><?php esc_html_e( 'Customer Details:', 'sk' ); ?></strong></p>

<p>
    <?php
    /* translators: 1) Name, 2) Address 1, 3) Address 2, 4) City, 5) State, 6) Postcode, 7) Phone, 8) Customer Email. */
    printf(
        '%1$s<br>%2$s<br>%3$s<br>%4$s, %5$s, %6$s<br>%7$s<br>%8$s',
        $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
        $order->get_billing_address_1(),
        $order->get_billing_address_2(),
        $order->get_billing_city(),
        $order->get_billing_state(),
        $order->get_billing_postcode(),
        $order->get_billing_phone(),
        $order->get_billing_email()
    );
    ?>
</p>

<p>
    <?php
    /**
     * Show user-defined additional content - this is set in each email's settings.
     */
    if ( $additional_content ) {
        echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
    }
    ?>
</p>

<?php

/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email );

