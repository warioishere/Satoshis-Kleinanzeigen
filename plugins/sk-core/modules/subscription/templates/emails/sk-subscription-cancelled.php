<?php
/**
 * Subscription Cancelled Email
 *
 * An email is sent to admin when a subscription is get cancelled by the vendor
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! $vendor ) {
    return;
}

$vendor_name = method_exists( $vendor, 'get_shop_name' ) ? $vendor->get_shop_name() : __( 'Vendor', 'sk-core' );

do_action( 'woocommerce_email_header', $email_heading, $email );

printf( '<p>%s</p>', __( 'Hello there', 'sk-core' ) );

/* translators: %s is the store name */
printf( '<p>%s</p>', sprintf( __( 'A subscription has been cancelled by %s', 'sk-core' ), $vendor_name ) );

if ( $subscription ) {
    printf( '<p>%s</p>', __( 'Subscription Details:', 'sk-core' ) );

    /* translators: %s is the subscription package title */
    printf( '<p>%s</p>', sprintf( __( 'Subscription Pack: %s', 'sk-core' ), $subscription->get_package_title() ) );
    /* translators: %s is the subscription package price */
    printf( '<p>%s</p>', sprintf( __( 'Price: %s', 'sk-core' ), wc_price( $subscription->get_price() ) ) );
}

/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
    echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
