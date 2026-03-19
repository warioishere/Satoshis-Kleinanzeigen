<?php
/**
 * Subscription Expiry Alert to Vendor Email
 *
 *
 * An email is sent to vendor before a subscription expires.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! $vendor ) {
    return;
}

do_action( 'woocommerce_email_header', $email_heading, $email );

printf( '<p>%s</p>', __( 'Dear subscriber, Your subscription will be ending soon. Please renew your package in a timely manner for continued usage.', 'sk' ) );

if ( $subscription ) {
    printf( '<p>%s</p>', __( 'Here are your subscription pack details:', 'sk' ) );
    // translators: %s is the subscription package title
    printf( '<p>%s</p>', sprintf( __( 'Subscription Pack: %s', 'sk' ), $subscription->get_package_title() ) );
    // translators: %s is the subscription package price
    printf( '<p>%s</p>', sprintf( __( 'Price: %s', 'sk' ), wc_price( $subscription->get_price() ) ) );
}

/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
    echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
