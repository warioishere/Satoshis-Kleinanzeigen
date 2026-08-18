<?php
namespace SK\Core\Utilities;

/**
 * ReportUtil class
 *
 */
class ReportUtil {

    /**
     * Check if product listing is belongs to Report menu
     *
     *
     * @return bool
     */
    public static function is_report_products_url(): bool {

        $path = isset( $_GET['path'] ) ? sanitize_text_field( wp_unslash( $_GET['path'] ) ) : ''; // phpcs:ignore

        $should_render = $path === '/analytics/products';

        /**
         * Filter to control product listing template rendering.
         *
         *
         * @param bool $should_render Whether to render the product listing template.
         */
        return apply_filters( 'sk_is_report_products_url', $should_render );
    }


    /**
     * Get the excluded order statuses for analytics.
     *
     *
     * @return array List of excluded order statuses.
     */
    public static function get_exclude_order_statuses(): array {
        $excluded_statuses = \WC_Admin_Settings::get_option( 'woocommerce_excluded_report_order_statuses', [ 'pending', 'failed', 'cancelled' ] );
        $excluded_statuses = array_merge(
            [ 'auto-draft', 'trash' ],
            array_map( 'esc_sql', $excluded_statuses )
        );
        $excluded_statuses = apply_filters( 'woocommerce_analytics_excluded_order_statuses', $excluded_statuses );

        return apply_filters(
            'sk_analytics_excluded_order_statuses',
            array_map(
                function ( $status ) {
                    return 'wc-' . trim( $status );
                }, $excluded_statuses
            )
        );
    }
}
