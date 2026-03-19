<?php
/**
 * Report abuse email plain template.
 *
 * An email sent to the admin.
 *
 * @class   SK_Report_Abuse_Admin_Email
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html( wp_strip_all_tags( $email_heading ) );
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

printf(
    "%s \n %s \n %s",
    esc_html__( 'You have got a new abuse report for the product', 'sk' ),
    esc_html( wptexturize( $data['product_title'] ) ),
    esc_url( $data['product_link'] )
);
echo " \n\n";
printf( '%s: %s', esc_html__( 'Reason', 'sk' ), esc_html( wp_strip_all_tags( wptexturize( $data['reason'] ) ) ) );
echo " \n\n";
if ( $data['description'] ) {
    printf( '%s: %s', esc_html__( 'Description', 'sk' ), esc_html( wp_strip_all_tags( wptexturize( $data['description'] ) ) ) );
}
echo " \n\n";
if ( $data['customer'] ) {
    $customer = $data['customer'];
    $customer_link = admin_url( sprintf( 'user-edit.php?user_id=%d', $customer->get_id() ) );
    printf(
        "%s: %s \n %s \n\n",
        esc_html__( 'Reported by', 'sk' ),
        esc_html( $customer->get_username() ),
        $customer_link
    );
} else {
    printf(
        "%s: %s, %s: %s \n\n",
        esc_html__( 'Reported by', 'sk' ),
        esc_html( wptexturize( $data['customer_name'] ) ),
        esc_html__( 'Email', 'sk' ),
        esc_html( $data['customer_email'] )
    );
}

printf(
    '%s: %s',
    esc_html__( 'Reported At', 'sk' ),
    sk_current_datetime()->modify( $data['reported_at'] )->format( wc_date_format() . ' ' . wc_time_format() )
);

printf(
    "%s: %s\n %s \n\n",
    esc_html__( 'Product Vendor', 'sk' ),
    esc_html( wptexturize( $data['vendor_name'] ) ),
    esc_url( $data['vendor_link'] )
);

esc_html_e( 'You can draft or remove the product or you can ignore this email if you think the product is OK.', 'sk' );

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( ! empty( $additional_content ) ) {
    echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) );
    echo "\n\n----------------------------------------\n\n";
}

echo esc_html( wp_strip_all_tags( wptexturize( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ) ) );
