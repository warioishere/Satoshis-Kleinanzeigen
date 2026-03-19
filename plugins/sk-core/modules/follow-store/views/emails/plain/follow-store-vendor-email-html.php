<?php
/**
 * Follow Store Vendor Email plain template.
 *
 * An email sent to the vendor about store follower update.
 *
 * @class       SK_Follow_Store_Vendor_Email
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

echo '= ' . esc_html( wp_strip_all_tags( $email_heading ) ) . " =\n\n";
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";
echo " \n\n";
if ( 'following' === $data['status'] ) {
    esc_html_e( 'You have got a new store follower!', 'sk' );
} else {
    esc_html_e( 'Someone just unfollowed your store!', 'sk' );
}
echo " \n\n";
if ( 'following' === $data['status'] ) {
    // translators: Follower Name.
    printf( __( '%s has just followed your store.', 'sk' ), $data['follower']->display_name );
} else {
    // translators: Follower Name.
    printf( __( '%s has just unfollowed your store.', 'sk' ), $data['follower']->display_name );
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
