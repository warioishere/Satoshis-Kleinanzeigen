<?php

namespace SK\Core\Shortcodes;

use SK\Core\Abstracts\SkShortcode;
use SK\Core\Vendor\StoreListsFilter;

class Stores extends SkShortcode {

    protected $shortcode = 'sk-stores';

    /**
     * Displays the store lists
     *
     *
     * @param  array $atts
     *
     * @return string
     */
    public function render_shortcode( $atts ) {
        $defaults = array(
            'per_page'           => 10,
            'search'             => 'yes',
            'per_row'            => 3,
            'featured'           => 'no',
            'category'           => '',
            'order'              => '',
            'orderby'            => '',
            'store_id'           => '',
            'with_products_only' => '',
        );

        /**
         * Filter return the number of store listing number per page.
         *
         *
         * @param array
         */
        $attr   = shortcode_atts( apply_filters( 'sk_store_listing_per_page', $defaults ), $atts );
        $paged  = (int) is_front_page() ? max( 1, get_query_var( 'page' ) ) : max( 1, get_query_var( 'paged' ) );
        $limit  = $attr['per_page'];
        $offset = ( $paged - 1 ) * $limit;

        // prepare filter data
        $sk_seller_search = '';
        $requested_data      = [];

        // check if nonce verified
        if ( isset( $_GET['_store_filter_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_store_filter_nonce'] ) ), 'sk_store_lists_filter_nonce' ) ) {
            $sk_seller_search = isset( $_GET['sk_seller_search'] ) ? sanitize_text_field( wp_unslash( $_GET['sk_seller_search'] ) ) : $sk_seller_search;
            $requested_data = wc_clean( wp_unslash( $_GET ) );
        }

        // Sorting is a whitelisted public GET param, it works without the filter nonce.
        if ( isset( $_GET['stores_orderby'] ) ) {
            $stores_orderby = sanitize_key( wp_unslash( $_GET['stores_orderby'] ) );

            if ( array_key_exists( $stores_orderby, StoreListsFilter::sort_by_options() ) ) {
                $requested_data['stores_orderby'] = $stores_orderby;
            }
        }

        // Check if store categories exists in the GET request.
        if ( isset( $_GET['store_categories'] ) ) {
            $requested_data['store_categories'] = wc_clean( wp_unslash( $_GET['store_categories'] ) );
        }

        $seller_args = array(
            'number' => $limit,
            'offset' => $offset,
            'order'  => 'DESC',
        );

        // if search is enabled, perform a search
        if ( 'yes' === $attr['search'] ) {
            if ( ! empty( $sk_seller_search ) ) {
                $seller_args['meta_query'] = [ // phpcs:ignore
                    [
                        'key'     => 'sk_store_name',
                        'value'   => $sk_seller_search,
                        'compare' => 'LIKE',
                    ],
                ];
            }
        }

        if ( 'yes' === $attr['featured'] ) {
            $seller_args['featured'] = 'yes';
        }

        if ( ! empty( $attr['category'] ) ) {
            $seller_args['store_category_query'][] = array(
                'taxonomy' => 'store_category',
                'field'    => 'slug',
                'terms'    => explode( ',', $attr['category'] ),
            );
        }

        if ( ! empty( $attr['order'] ) ) {
            $seller_args['order'] = $attr['order'];
        }

        if ( ! empty( $attr['orderby'] ) ) {
            $seller_args['orderby'] = $attr['orderby'];
        }

        if ( ! empty( $attr['with_products_only'] ) && 'yes' === $attr['with_products_only'] ) {
            $seller_args['has_published_posts'] = [ 'product' ];
        }

        if ( ! empty( $attr['store_id'] ) ) {
            $seller_args['include'] = explode( ',', $attr['store_id'] );
        }

        $sellers = sk_get_sellers( apply_filters( 'sk_seller_listing_args', $seller_args, $requested_data ) );

        /**
         * Filter for store listing args
         *
         */
        $template_args = apply_filters(
            'sk_store_list_args', [
				'sellers'             => $sellers,
				'limit'               => $limit,
				'offset'              => $offset,
				'paged'               => $paged,
				'image_size'          => 'full',
				'search'              => $attr['search'],
				'per_row'             => $attr['per_row'],
                'sk_seller_search' => $sk_seller_search,
            ]
        );

        ob_start();
        sk_get_template_part( 'store-lists', false, $template_args );
        $content = ob_get_clean();

        return apply_filters( 'sk_seller_listing', $content, $attr );
    }
}
