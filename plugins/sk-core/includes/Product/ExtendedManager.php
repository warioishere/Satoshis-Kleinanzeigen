<?php

namespace SK\Core\Product;

use WC_Product;
use SK\Core\Product\Manager as ProductManager;

class ExtendedManager extends ProductManager {

    /**
     * Returns linked products.
     *
     *
     * @param string                $term
     * @param boolean|integer|array $user_ids
     * @param array                 $exclude
     * @param array                 $included_id
     * @param integer               $limit
     *
     * @return WC_Product[]
     */
    public function get_linked_products( $term = '', $user_ids = [], $exclude = [], $included_id = [], $limit = 0 ) {
        $term     = ! empty( $term ) ? sanitize_text_field( wp_unslash( $term ) ) : '';
        $user_ids = ! empty( $user_ids ) ? array_filter( array_map( 'absint', (array) wp_unslash( $user_ids ) ) ) : false;

        if ( empty( $term ) ) {
            return [];
        }

        $ids = sk_search_seller_products( $term, $user_ids );

        if ( ! empty( $exclude ) ) {
            $ids = array_diff( $ids, (array) sanitize_text_field( wp_unslash( $exclude ) ) );
        }

        if ( ! empty( $included_id ) ) {
            $ids = array_intersect( $ids, (array) sanitize_text_field( wp_unslash( $included_id ) ) );
        }

        if ( ! empty( $limit ) ) {
            $ids = array_slice( $ids, 0, absint( $limit ) );
        }

        return array_filter( array_map( 'wc_get_product', $ids ), 'sk_products_array_filter_editable' );
    }
}
