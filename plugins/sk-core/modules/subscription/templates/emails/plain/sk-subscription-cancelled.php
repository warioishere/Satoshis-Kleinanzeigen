<?php
/**
 * Subscription Cancelled Email
 *
 * An email is sent to admin when a subscription is get cancalled by the vendor
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! $vendor ) {
    return;
}
$vendor_name = method_exists( $vendor, 'get_shop_name' ) ? $vendor->get_shop_name() : __( 'Vendor', 'sk-core' );

echo '= ' . esc_html( wp_strip_all_tags( wptexturize( $email_heading ) ) ) . " =\n\n";

esc_html_e( 'Hello there,', 'sk-core' );
echo " \n\n";

// translators: %s Vendor Store Name.
printf( __( 'A subscription has been cancelled by %s', 'sk-core' ), esc_html( wptexturize( $vendor_name ) ) );
echo " \n\n";

if ( $subscription ) {
    esc_html_e( 'Subscription Details:', 'sk-core' );
    echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";


    // translators: %s Subscription Pack.
    printf( '<p>%s</p>', sprintf( __( 'Subscription Pack: %s', 'sk-core' ), $subscription->get_package_title() ) );
    echo " \n";

    // translators: %s Price.
    printf( '<p>%s</p>', sprintf( __( 'Price: %s', 'sk-core' ), esc_html( wp_strip_all_tags( wptexturize( sk()->email->currency_symbol( $subscription->get_price() ) ) ) ) ) );
    echo " \n";
}

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( ! empty( $additional_content ) ) {
    echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) );
    echo "\n\n----------------------------------------\n\n";
}

echo esc_html( wp_strip_all_tags( wptexturize( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ) ) );
