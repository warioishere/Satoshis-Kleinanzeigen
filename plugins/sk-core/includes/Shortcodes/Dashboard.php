<?php

namespace SK\Core\Shortcodes;

use SK\Core\Abstracts\SkShortcode;
use SK\Core\Utilities\OrderUtil;
use SK\Core\Utilities\VendorUtil;

class Dashboard extends SkShortcode {

    protected $shortcode = 'sk-dashboard';

    /**
     * Load template files
     *
     * Based on the query vars, load the appropriate template files
     * in the frontend user dashboard.
     *
     * @param array $atts
     *
     * @return string
     */
    public function render_shortcode( $atts ) {
        global $wp;

        if ( ! function_exists( 'WC' ) ) {
            // translators: 1) wooCommerce installation url
            return sprintf( __( 'Please install <a href="%s"><strong>WooCommerce</strong></a> plugin first', 'sk-core' ), 'http://wordpress.org/plugins/woocommerce/' );
        }

        if ( ! sk_is_user_seller( get_current_user_id() ) ) {
            return __( 'You have no permission to view this page', 'sk-core' );
        }

        ob_start();

        /**
         * Filter query var before rendering sk vendor shortcode
         */
        $query_vars = apply_filters( 'sk_dashboard_shortcode_query_vars', $wp->query_vars );

        if ( is_wp_error( $query_vars ) ) {
            sk_get_template_part(
                'global/sk-error', '', [
                    'deleted' => false,
                    'message' => $query_vars->get_error_message(),
                ]
            );
            return ob_get_clean();
        }

        if ( isset( $query_vars['products'] ) ) {
            if ( ! current_user_can( 'sk_view_product_menu' ) ) {
                sk_get_template_part( 'global/no-permission' );
            } else {
                sk_get_template_part( 'products/products' );
            }

            return ob_get_clean();
        }

        if ( isset( $query_vars['new-product'] ) ) {
            if ( ! current_user_can( 'sk_add_product' ) ) {
                sk_get_template_part( 'global/no-permission' );
            } else {
                do_action( 'sk_render_new_product_template', $wp->query_vars );
            }

            return ob_get_clean();
        }

        if ( isset( $query_vars['settings'] ) ) {
            sk_get_template_part( 'settings/store' );

            return ob_get_clean();
        }

        if ( isset( $query_vars['page'] ) ) {
            if ( ! current_user_can( 'sk_view_overview_menu' ) ) {
                sk_get_template_part( 'global/no-permission' );
            } else {
                sk_get_template_part( 'dashboard/dashboard' );
            }

            return ob_get_clean();
        }
        if ( isset( $query_vars['edit-account'] ) ) {
            sk_get_template_part( 'dashboard/edit-account' );

            return ob_get_clean();
        }

        do_action( 'sk_load_custom_template', $query_vars );

        return ob_get_clean();
    }
}
