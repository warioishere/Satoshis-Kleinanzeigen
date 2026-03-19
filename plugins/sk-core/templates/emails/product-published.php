<?php
/**
 * Product published Email.
 *
 * An email sent to the vendor when a new Product is published from pending.
 *
 * @class       SK_Email_Product_Published
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>
<p>
    <?php
    // translators: 1) seller name
    printf( esc_html__( 'Hello %s', 'sk-core' ), esc_html( $data['{store_name}'] ) );
    ?>
</p>
<p>
    <?php
    // translators: 1) Product url 2) Product title
    echo wp_kses_post( sprintf( __( 'Your product : <a href="%1$s">%2$s</a> is approved by the admin, congrats!', 'sk-core' ), esc_html( $data['{product_url}'] ), esc_html( $data['{product_title}'] ) ) );
    ?>
</p>
<p>
    <?php
    // translators: 1) product edit link
    echo wp_kses_post( sprintf( __( 'To Edit product click : <a href="%s">here</a>', 'sk-core' ), esc_html( $data['{product_edit_link}'] ) ) );
    ?>
</p>
<?php
/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
    echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}
do_action( 'woocommerce_email_footer', $email );
