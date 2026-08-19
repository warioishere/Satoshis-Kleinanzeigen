<?php
/**
 * New Review Email.
 *
 * An email sent to the vendor and admin when a new review is created by customer.
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$text_align = is_rtl() ? 'right' : 'left';

/**
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email );

?>
    <div>
        <table cellspacing='0'>
            <tr>
                <th class='store-name'><?php esc_html_e( 'Store Name', 'sk-core' ); ?></th>
                <td class="store-name"><?php echo esc_html( $store_name ); ?> </td>
            </tr>
            <tr>
                <th class='store-name'><?php esc_html_e( 'Reviewed by', 'sk-core' ); ?></th>
                <td class="store-name"><?php echo esc_html( $reviewer_name ); ?> </td>
            </tr>
            <tr>
                <th class="quote-date"><?php esc_html_e( 'Rating', 'sk-core' ); ?></th>
                <td class="quote-date">
                    <p class='sk-stars'>
                        <?php
                        for ( $i = 0; $i < $rating; $i++ ) {
                            echo '<span>&#9733;</span>';
                        }
                        ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th class='store-name'><?php esc_html_e( 'Title', 'sk-core' ); ?></th>
                <td class="store-name"><?php echo esc_html( $post_title ); ?> </td>
            </tr>
            <tr>
                <th class='store-name'><?php esc_html_e( 'Details', 'sk-core' ); ?></th>
                <td class="store-name"><?php echo wp_kses_post( $post_details ); ?> </td>
            </tr>
        </table>
    </div>

<?php
/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
    echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

/**
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email );
