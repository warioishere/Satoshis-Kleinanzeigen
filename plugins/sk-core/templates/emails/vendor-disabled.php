<?php
/**
* Vendor enable email to vendors.
*
* An email sent to the vendor(s) when a he or she is enabled by the admin
*
* @class    SK_Email_Vendor_Disable
*
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>
<p>
    <?php printf( __( 'Hello %s', 'sk-core' ), $data['{display_name}'] ); ?>
</p>
<p>
    <?php esc_html_e( 'Sorry, your vendor account is deactivated.', 'sk-core' ); ?>
</p>
<p>
    <?php esc_html_e( 'You can\'t sell or upload product anymore. To activate your account please contact with the admin.', 'sk-core' ); ?>
</p>
<?php
/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
    echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}
do_action( 'woocommerce_email_footer', $email );
