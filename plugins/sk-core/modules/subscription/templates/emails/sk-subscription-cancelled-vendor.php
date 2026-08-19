<?php
/**
 * Subscription Cancelled to Vendor Email
 *
 *
 * An email is sent to vendor when a subscription got cancelled.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! $vendor ) {
    return;
}

do_action( 'woocommerce_email_header', $email_heading, $email );

printf( '<p>%s</p>', __( 'Hello there', 'sk-core' ) );

printf( '<p>%s</p>', __( 'Your Subscription has been cancelled.', 'sk-core' ) );

if ( $subscription ) {
    printf( '<p>%s</p>', __( 'Here is your Subscription pack details:', 'sk-core' ) );

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
