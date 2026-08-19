<?php
/**
 * Report abuse email.
 *
 * An email sent to the admin.
 *
 * @class   SK_Report_Abuse_Admin_Email
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once ABSPATH . WPINC . '/formatting.php';

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>
<div id="sk-report-abuse-email">
    <?php
        printf(
            '<p>%s <strong><a href="%s">%s</a></strong></p>',
            esc_html__( 'You have got a new abuse report for the product', 'sk-core' ),
            esc_url( $data['product_link'] ),
            esc_html( $data['product_title'] )
        );

        printf( '<p><strong>%s:</strong> %s</p>', esc_html__( 'Reason', 'sk-core' ), esc_html( $data['reason'] ) );

        if ( $data['description'] ) {
            printf( '<p><strong>%s:</strong> %s</p>', esc_html__( 'Description', 'sk-core' ), esc_html( $data['description'] ) );
        }

        if ( $data['customer'] ) {
            $customer = $data['customer'];
            $customer_link = admin_url( sprintf( 'user-edit.php?user_id=%d', $customer->get_id() ) );
            printf(
                '<p><strong>%s:</strong> <a href="%s">%s</a></p>',
                esc_html__( 'Reported by', 'sk-core' ),
                $customer_link,
                esc_html( $customer->get_username() )
            );
        } else {
            printf(
                '<p><strong>%s:</strong> %s &lt;%s&gt;</p>',
                esc_html__( 'Reported by', 'sk-core' ),
                esc_html( $data['customer_name'] ),
                esc_html( $data['customer_email'] )
            );
        }

        printf(
            '<p><strong>%s:</strong> %s</p>',
            esc_html__( 'Reported At', 'sk-core' ),
            sk_current_datetime()->modify( $data['reported_at'] )->format( wc_date_format() . ' ' . wc_time_format() )
        );

        printf(
            '<p><strong>%s:</strong> <a href="%s">%s</a></p>',
            esc_html__( 'Product Vendor', 'sk-core' ),
            esc_url( $data['vendor_link'] ),
            esc_html( $data['vendor_name'] )
        );

        printf(
            '<p>%s</p>',
            esc_html__( 'You can draft or remove the product or you can ignore this email if you think the product is OK.', 'sk-core' )
        );
        ?>
</div>
<?php
/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
    echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}
do_action( 'woocommerce_email_footer', $email );
