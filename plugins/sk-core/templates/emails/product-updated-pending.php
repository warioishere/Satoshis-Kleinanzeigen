<?php
/**
 * Pending Updated Product Email.
 *
 * An email sent to the admin when a new Product is updated by vendor and in pending status.
 *
 * @class       SK_Email_New_Product_Pending
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p><?php esc_html_e( 'Hello,', 'sk' ); ?></p>

<p><?php esc_html_e( 'A product has been updated in your site.', 'sk' ); ?> <a href="<?php echo esc_url( $data['{site_url}'] ); ?>" ><?php echo esc_html( $data['{site_name}'] ); ?></a> </p>
<p><?php esc_html_e( 'Summary of the product:', 'sk' ); ?></p>
<hr>
<ul>
    <li>
        <strong>
            <?php esc_html_e( 'Title :', 'sk' ); ?>
        </strong>
        <?php printf( '<a href="%s">%s</a>', esc_url( $data['{product_link}'] ), esc_html( $data['{product_title}'] ) ); ?>
    </li>
    <li>
        <strong>
            <?php esc_html_e( 'Price :', 'sk' ); ?>
        </strong>
        <?php echo wc_price( $data['{price}'] ); ?>
    </li>
    <li>
        <strong>
            <?php esc_html_e( 'Vendor :', 'sk' ); ?>
        </strong>
        <?php printf( '<a href="%s">%s</a>', esc_url( $data['{seller_url}'] ), esc_html( $data['{seller_name}'] ) ); ?>
    </li>
    <li>
        <strong>
            <?php esc_html_e( 'Category :', 'sk' ); ?>
        </strong>
        <?php echo esc_html( $data['{category}'] ); ?>
    </li>

</ul>
<p><?php esc_html_e( 'The product is currently in "pending" status.', 'sk' ); ?></p>

<p>
    <?php
    // translators: %s is product moderation url.
    echo wp_kses_post( sprintf( __( 'Moderate the product by clicking <a href="%s">here</a>', 'sk' ), esc_url( $data['{product_link}'] ) ) );
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
