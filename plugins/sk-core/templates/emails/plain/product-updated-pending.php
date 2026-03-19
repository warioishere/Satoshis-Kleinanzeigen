<?php
/**
 * Pending Updated Product Email. (plain text)
 *
 * An email sent to the admin when a new Product is updated by vendor and in pending status.
 *
 * @class       SK_Email_New_Product_Pending
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
echo '= ' . esc_html( wp_strip_all_tags( $email_heading ) ) . " =\n\n";

esc_html_e( 'Hello there,', 'sk' );
echo " \n\n";

esc_html_e( 'A product has been updated in your site.', 'sk' );
echo " \n\n";

esc_html_e( 'Summary of the product:', 'sk' );

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

// translators: 1: Product Title.
echo esc_html( sprintf( __( 'Title: %1$s', 'sk' ), wptexturize( $data['{product_title}'] ) ) );
echo " \n";
// translators: 1: Price.
echo esc_html( sprintf( __( 'Price: %1$s', 'sk' ), wptexturize( $data['{price}'] ) ) );
echo " \n";
// translators: 1: Vendor.
echo esc_html( sprintf( __( 'Vendor: %1$s', 'sk' ), wptexturize( $data['{seller_name}'] ) ) );
echo " \n";
// translators: 1: Category.
echo esc_html( sprintf( __( 'Category: %1$s', 'sk' ), wptexturize( $data['{category}'] ) ) );
echo " \n";

esc_html_e( 'The product is currently in "pending" state.', 'sk' );
echo " \n";

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( ! empty( $additional_content ) ) {
    echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) );
    echo "\n\n----------------------------------------\n\n";
}

echo esc_html( wp_strip_all_tags( wptexturize( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ) ) );
