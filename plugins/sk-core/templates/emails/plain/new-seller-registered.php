<?php
/**
 * New Seller Email ( plain text )
 *
 * An email sent to the admin when a new vendor is registered.
 *
 * @class       SK_Email_New_Seller
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html( wp_strip_all_tags( $email_heading ) );
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

esc_html_e( 'Hello there,', 'sk-core' );
echo " \n";

esc_html_e( 'A new vendor has registered in your marketplace  ', 'sk-core' );
echo " \n";

esc_html_e( 'Vendor Details:', 'sk-core' );
echo " \n";

echo "\n\n----------------------------------------\n\n";

// translators: 1) seller name
printf( esc_html__( 'Vendor: %s', 'sk-core' ), esc_html( $data['{seller_name}'] ) );
echo " \n";

// translators: 1) store name
printf( esc_html__( 'Vendor Store: %s', 'sk-core' ), esc_html( $data['{store_name}'] ) );
echo " \n";

// translators: 1) seller edit url
printf( esc_html__( 'To edit vendor access and details visit : %s', 'sk-core' ), esc_url( $data['{seller_edit}'] ) );

echo "\n\n----------------------------------------\n\n";

/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( ! empty( $additional_content ) ) {
    echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) );
    echo "\n\n----------------------------------------\n\n";
}

echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
