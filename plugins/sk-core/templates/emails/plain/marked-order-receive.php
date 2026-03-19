<?php
/**
 * Update Order Marked as Receive Email.
 *
 * An email sent to the vendor when an order marked as receive from customer.
 *
 * @class   SK_Email_Marked_Order_Received
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

echo '= ' . esc_html( wp_strip_all_tags( $email_heading ) ) . " =\n\n";

/* translators: 1: Newline character. */
printf( __( 'Hi, %1$s', 'sk' ), " \n\n" );

printf(
    /* translators: 1: Order id, 2: Customer name, 3: Newline character. */
    __( 'Your order id %1$s has been marked as receive by %2$s. %3$s', 'sk' ),
    /* translators: 1: Order Link, 2: Order Id. */
    sprintf( '<a href="%1$s">%2$s</a>', esc_url( $order_link ), number_format_i18n( $order_id ) ),
    esc_html( $customer_name ),
    " \n\n"
);

/* translators: 1: Order page URL, 2: Newline character. */
printf( __( 'You can view the order details by visiting this link. %1$s %2$s', 'sk' ), esc_url( $order_link ), " \n" );

/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
    echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) );
    echo "\n\n----------------------------------------\n\n";
}

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) );
