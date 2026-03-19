<?php
/**
 * Announcement email to vendors.
 *
 * An email sent to the vendor(s) when a announcement is created by admin.
 *
 * @class       SK_Email_Announcement
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html( wp_strip_all_tags( $email_heading ) );
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";
?>

------------------------------------------------------------

<?php
echo esc_html( wp_strip_all_tags( wptexturize( $announcement_body ) ) );
echo " \n\n";
?>

------------------------------------------------------------


<?php esc_attr_e( 'You can check this announcement in your dashboard ', 'sk' );  echo " \n";?>

<?php
echo $data['{announcement_url}'] . "\n";
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( ! empty( $additional_content ) ) {
    echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) );
    echo "\n\n----------------------------------------\n\n";
}

echo esc_html( wp_strip_all_tags( wptexturize( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ) ) );
