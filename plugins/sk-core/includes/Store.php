<?php

namespace SK\Core;

/**
 * Store Class
 *
 */
class Store {

    /**
     * Load automatically when class initiate
     *
     *
     * @uses action hook
     * @uses filter hook
     */
    public function __construct() {
        add_action( 'sk_rewrite_rules_loaded', array( $this, 'load_rewrite_rules' ) );
        add_filter( 'query_vars', array( $this, 'register_store_query_vars' ) );
        add_filter( 'sk_store_tabs', array( $this, 'add_review_tab_in_store' ), 10, 2 );
        add_filter( 'template_include', array( $this, 'store_review_template' ), 99 );

        // vendor biography
        add_action( 'sk_rewrite_rules_loaded', array( $this, 'load_biography_rewrite_rules' ) );
        add_filter( 'sk_store_tabs', array( $this, 'add_vendor_biography_tab' ), 10, 2 );
        add_filter( 'template_include', array( $this, 'load_vendor_biography_template' ), 99 );
    }

    /**
     * Load Store Review query vars for store page
     *
     *
     * @param  array $vars
     *
     * @return array
     */
    public function register_store_query_vars( $vars ) {
        $vars[] = 'store_review';
        $vars[] = 'biography';
        $vars[] = 'support';
        $vars[] = 'support-tickets';
        $vars[] = 'booking';

        return $vars;
    }

    /**
     * Load Rewrite Rules for store page
     *
     *
     * @param  string $custom_store_url
     *
     * @return void
     */
    public function load_rewrite_rules( $custom_store_url ) {
        add_rewrite_rule( $custom_store_url . '/([^/]+)/reviews?$', 'index.php?' . $custom_store_url . '=$matches[1]&store_review=true', 'top' );
        add_rewrite_rule( $custom_store_url . '/([^/]+)/reviews/page/?([0-9]{1,})/?$', 'index.php?' . $custom_store_url . '=$matches[1]&paged=$matches[2]&store_review=true', 'top' );
    }

    /**
     * Add Review Tab in Store Page
     *
     *
     * @param array $tabs
     * @param integer $store_id
     *
     * @return array
     */
    public function add_review_tab_in_store( $tabs, $store_id ) {
        if ( 'yes' === get_option( 'woocommerce_enable_reviews' ) ) {
            $tabs['reviews'] = array(
                'title' => __( 'Reviews', 'sk' ),
                'url'   => sk_get_review_url( $store_id ),
            );
        }

        return $tabs;
    }

    /**
     * Returns the store review template
     *
     *
     * @param string  $template
     *
     * @return string
     */
    public function store_review_template( $template ) {
        if ( ! function_exists( 'WC' ) ) {
            return $template;
        }

        if ( get_query_var( 'store_review' ) ) {
            return sk_locate_template( 'store-reviews.php', '', SK_CORE_DIR . '/templates/', true );
        }

        return $template;
    }

    /**
     * Add vendor biography tab
     *
     * @param array $tabs
     * @param int $store_id
     *
     *
     * @return array
     */
    public function add_vendor_biography_tab( $tabs, $store_id ) {
        $store_info = sk_get_store_info( $store_id );

        if ( empty( $store_info['vendor_biography'] ) ) {
            return $tabs;
        }

        $tabs['vendor_biography'] = [
            'title' => apply_filters( 'sk_vendor_biography_title', __( 'Vendor Biography', 'sk' ) ),
            'url'   => sk_get_store_url( $store_id, 'biography' ),
        ];

        return $tabs;
    }

    /**
     * Load biography rewrite rules
     *
     * @param string $store_url
     *
     *
     * @return void
     */
    public function load_biography_rewrite_rules( $store_url ) {
        add_rewrite_rule( $store_url . '/([^/]+)/biography?$', 'index.php?' . $store_url . '=$matches[1]&biography=true', 'top' );
    }

    /**
     * Load biography query var
     *
     * @param array $query_vars
     *
     *
     * @return array
     */

    /**
     * Load vendor biography template
     *
     * @param string $template
     *
     *
     * @return string
     */
    public function load_vendor_biography_template( $template ) {
        if ( ! get_query_var( 'biography' ) ) {
            return $template;
        }

        return sk_locate_template( 'vendor-biography.php', '', SK_CORE_DIR . '/templates/', true );
    }
}
