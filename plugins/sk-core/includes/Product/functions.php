<?php
/**
 * Load all product related functions
 *
 */

use SK\Core\ProductCategory\Helper;


/**
 * Get product visibility options.
 *
 *
 * @return array
 */
function sk_get_product_visibility_options() {
    return apply_filters(
        'sk_product_visibility_options', [
            'visible' => __( 'Visible', 'sk-core' ),
            'catalog' => __( 'Catalog', 'sk-core' ),
            'search'  => __( 'Search', 'sk-core' ),
            'hidden'  => __( 'Hidden', 'sk-core' ),
        ]
    );
}


/**
 * Get row action for product
 *
 *
 * @param object|int|string $post
 * @param bool              $format_html (Optional)
 *
 * @return array
 */
function sk_product_get_row_action( $post, $format_html = true ) {
    if ( is_numeric( $post ) ) {
        $post = get_post( $post );
    }

    if ( empty( $post->ID ) ) {
        return [];
    }

    $row_action      = [];
    $row_action_html = [];
    $product_id      = $post->ID;

    if ( current_user_can( 'sk_edit_product' ) ) {
        $row_action['edit'] = [
            'title' => __( 'Edit', 'sk-core' ),
            'url'   => sk_edit_product_url( $product_id ),
            'class' => 'edit',
        ];
    }

    if ( current_user_can( 'sk_delete_product' ) ) {
        $row_action['delete'] = [
            'title' => __( 'Delete Permanently', 'sk-core' ),
            'url'   => wp_nonce_url(
                add_query_arg(
                    [
                        'action'     => 'sk-delete-product',
                        'product_id' => $product_id,
                    ], sk_get_navigation_url( 'products' )
                ), 'sk-delete-product'
            ),
            'class' => 'delete',
            'other' => 'onclick="sk_show_delete_prompt( event, \'' . __( 'Are you sure?', 'sk-core' ) . '\' );"',
        ];
    }

    if ( current_user_can( 'sk_view_product' ) && $post->post_status !== 'pending' ) {
        $row_action['view'] = [
            'title' => __( 'View', 'sk-core' ),
            'url'   => get_permalink( $product_id ),
            'class' => 'view',
        ];
    }

    $row_action = apply_filters( 'sk_product_row_actions', $row_action, $post );

    if ( empty( $row_action ) ) {
        return $row_action;
    }

    if ( ! $format_html ) {
        return $row_action;
    }

    foreach ( $row_action as $key => $action ) {
        // Kein "|" zwischen den Aktionen: auf dem Desktop steht es in #444 auf
        // dunklem Grund und ist unsichtbar, auf Mobile werden die Aktionen zu
        // gestapelten Knoepfen und der Trenner haengt als Strich daneben.
        // Den Abstand setzt das Stylesheet ueber .row-actions > span.
        $row_action_html[ $key ] = sprintf( '<span class="%s"><a href="%s" %s>%s</a></span>', $action['class'], esc_url( $action['url'] ), isset( $action['other'] ) ? $action['other'] : '', $action['title'] );
    }

    $row_action_html = apply_filters( 'sk_product_row_action_html', $row_action_html, $post );

    return implode( ' ', $row_action_html );
}

/**
 * SK get vendor by product
 *
 * @param int|WC_Product $product Product ID or Product Object
 * @param bool $get_vendor return true to get vendor id, otherwise it will return \SK\Core\Vendor\Vendor object
 *
 *
 * @return int|\SK\Core\Vendor\Vendor|false on failure
 */
function sk_get_vendor_by_product( $product, $get_vendor_id = false ) {
    if ( ! $product instanceof WC_Product ) {
        $product = wc_get_product( $product );
    }

    if ( ! $product ) {
        return false;
    }

    $vendor_id = get_post_field( 'post_author', $product->get_id() );

    if ( ! $vendor_id && 'variation' === $product->get_type() ) {
        $vendor_id = get_post_field( 'post_author', $product->get_parent_id() );
    }

    $vendor_id = apply_filters( 'sk_get_vendor_by_product', $vendor_id, $product );

    return false === $get_vendor_id ? sk()->vendor->get( $vendor_id ) : (int) $vendor_id;
}


/**
 * Get sk store products filter catalog orderby
 *
 *
 * @return array
 */
function sk_store_product_catalog_orderby() {
    $show_default_orderby = 'menu_order' === apply_filters( 'sk_default_store_products_orderby', get_option( 'woocommerce_default_catalog_orderby', 'menu_order' ) );

    $catalog_orderby_options = apply_filters(
        'sk_store_product_catalog_orderby',
        array(
            'menu_order' => __( 'Default sorting', 'sk-core' ),
            'popularity' => __( 'Sort by popularity', 'sk-core' ),
            'rating'     => __( 'Sort by average rating', 'sk-core' ),
            'date'       => __( 'Sort by latest', 'sk-core' ),
            'price'      => __( 'Sort by price: low to high', 'sk-core' ),
            'price-desc' => __( 'Sort by price: high to low', 'sk-core' ),
        )
    );

    $default_orderby = wc_get_loop_prop( 'is_search' ) ? 'relevance' : apply_filters( 'sk_default_store_products_orderby', get_option( 'woocommerce_default_catalog_orderby', '' ) );
    $orderby = isset( $_GET['product_orderby'] ) ? wc_clean( wp_unslash( $_GET['product_orderby'] ) ) : $default_orderby; //phpcs:ignore

    if ( wc_get_loop_prop( 'is_search' ) ) {
        $catalog_orderby_options = array_merge( array( 'relevance' => __( 'Relevance', 'sk-core' ) ), $catalog_orderby_options );

        unset( $catalog_orderby_options['menu_order'] );
    }

    if ( ! $show_default_orderby ) {
        unset( $catalog_orderby_options['menu_order'] );
    }

    if ( ! wc_review_ratings_enabled() ) {
        unset( $catalog_orderby_options['rating'] );
    }

    if ( ! array_key_exists( $orderby, $catalog_orderby_options ) ) {
        $orderby = current( array_keys( $catalog_orderby_options ) );
    }

    $orderby_options = array(
        'show_default_orderby'    => $show_default_orderby,
        'orderby'                 => $orderby,
        'catalogs'                => $catalog_orderby_options,
    );

    return $orderby_options;
}
