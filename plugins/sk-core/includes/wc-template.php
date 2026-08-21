<?php

use SK\Core\Vendor\Vendor;

/**
 * Injects seller name on cart and other areas
 *
 * @param array $item_data
 * @param array $cart_item
 *
 * @return array
 */
function sk_product_seller_info( $item_data, $cart_item ) {
    $vendor = sk_get_vendor_by_product( $cart_item['product_id'] );

    if ( ! $vendor || ! $vendor->get_id() ) {
        return $item_data;
    }

    $item_data[] = array(
        'name'  => __( 'Vendor', 'sk-core' ),
        'value' => $vendor->get_shop_name(),
        'type' => 'vendor', // It is required to identify the vendor data type in REST API.
    );

    return $item_data;
}

add_filter( 'woocommerce_get_item_data', 'sk_product_seller_info', 10, 2 );

/**
 * Adds a seller tab in product single page
 *
 * @param array $tabs
 *
 * @return array
 */
function sk_seller_product_tab( $tabs ) {
    if ( is_enabled_vendor_info_product_tab() ) {
        $tabs['seller'] = [
            'title' => __( 'Vendor Info', 'sk-core' ),
            'priority' => 90,
            'callback' => 'sk_product_seller_tab',
        ];
    }

    return $tabs;
}

add_filter( 'woocommerce_product_tabs', 'sk_seller_product_tab' );

/**
 * Prints seller info in product single page
 *
 * @global WC_Product $product
 */
function sk_product_seller_tab() {
    global $product;

    if ( ! $product instanceof WC_Product ) {
        return;
    }

    $vendor = sk_get_vendor_by_product( $product );
    if ( ! $vendor instanceof Vendor ) {
        return;
    }

    $store_info = $vendor->get_shop_info();
    $author     = get_user_by( 'id', $vendor->get_id() );

    sk_get_template_part(
        'global/product-tab',
        '',
        [
            'author'     => $author,
            'store_info' => $store_info,
        ]
    );
}

/**
 * Show sub-orders on a parent order if available
 *
 * @param WC_Order $parent_order
 * @return void
 */
function sk_order_show_suborders( $parent_order ) {
    return;
    if ( empty( $sub_orders ) ) {
        return;
    }

    $statuses = wc_get_order_statuses();

    sk_get_template_part(
        'sub-orders',
        '',
        [
            'parent_order' => $parent_order,
            'sub_orders'   => $sub_orders,
            'statuses'     => $statuses,
        ]
    );
}

add_action( 'woocommerce_order_details_after_order_table', 'sk_order_show_suborders' );


/**
 * Override Customer Orders array
 *
 * @param post_arg_query array()
 *
 * @return array() post_arg_query
 */
function sk_get_customer_main_order( $customer_orders ) {
    $customer_orders['post_parent'] = 0;

    return $customer_orders;
}

add_filter( 'woocommerce_my_account_my_orders_query', 'sk_get_customer_main_order' );

/**
 * Add edit post capability to woocommerce proudct post type
 *
 *
 * @param capability array
 *
 * @return capability array
 */
function sk_manage_capability_for_woocommerce_product( $capability ) {
    $capability['capabilities'] = array(
        'edit_post' => 'edit_product',
    );

    return $capability;
}

add_filter( 'woocommerce_register_post_type_product', 'sk_manage_capability_for_woocommerce_product' );

/**
 * Author field for product quick edit
 *
 * @return void
 */
function sk_author_field_quick_edit( $scope = null ) {
    wp_enqueue_script( 'sk-author-quick-edit' );

    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        return;
    }
    ?>
    <div class="sk-product-author-field inline-edit-group">
        <label class="alignleft">
            <span class="title">
                <?php esc_html_e( 'Vendor', 'sk-core' ); ?>
            </span>
            <span class="input-text-wrap">
                <select
                    name="sk_product_author_override"
                    class="sk_product_author_override<?php echo esc_attr( $scope ); ?>"
                    data-action="sk_product_search_author"
                    data-close_on_select="true"
                    style="width: 20rem !important;">
                    <option value=""><?php esc_html_e( '— No change —', 'sk-core' ); ?></option>
                </select>
            </span>
        </label>
    </div>

    <?php
}

add_action(
    'woocommerce_product_quick_edit_end', function () {
		sk_author_field_quick_edit( '_quick' );
	}
);
add_action(
    'woocommerce_product_bulk_edit_end', function () {
		sk_author_field_quick_edit();
	}
);

/**
 * Assign value for quick edit data
 *
 * @param array $column
 * @param integer $post_id
 *
 * @return void
 */
function sk_vendor_quick_edit_data( $column, $post_id ) {
    switch ( $column ) {
        case 'name':
            ?>
            <div class="hidden sk_vendor_id_inline" id="sk_vendor_id_inline_<?php echo esc_attr( $post_id ); ?>">
                <div id="sk_vendor_id"><?php echo esc_html( get_post_field( 'post_author', $post_id ) ); ?></div>
            </div>
            <?php
            break;

        default:
            break;
    }

}

add_action( 'manage_product_posts_custom_column', 'sk_vendor_quick_edit_data', 99, 2 );

/**
 * Save quick edit data
 *
 * @param WC_Product $product
 *
 * @return void
 */
function sk_save_quick_edit_vendor_data( $product ) {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        return;
    }

    if ( ! isset( $_REQUEST['woocommerce_quick_edit_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['woocommerce_quick_edit_nonce'] ), 'woocommerce_quick_edit_nonce' ) ) {
        return;
    }

    $posted_vendor_id = ! empty( $_REQUEST['sk_product_author_override'] ) ? (int) $_REQUEST['sk_product_author_override'] : 0;

    if ( ! $posted_vendor_id ) {
        return;
    }

    $vendor = sk_get_vendor_by_product( $product );

    if ( ! $vendor ) {
        return;
    }

    if ( $posted_vendor_id === $vendor->get_id() ) {
        return;
    }

    sk_override_product_author( $product, $posted_vendor_id );
}

add_action( 'woocommerce_product_quick_edit_save', 'sk_save_quick_edit_vendor_data', 10, 1 );
add_action( 'woocommerce_product_bulk_edit_save', 'sk_save_quick_edit_vendor_data', 10, 1 );

/**
 * Add go to vendor dashboard button to my account page
 *
 *
 * @return string
 */
function sk_set_go_to_vendor_dashboard_btn() {
    if ( ! sk_is_user_seller( get_current_user_id() ) ) {
        return;
    }

    printf(
        '<p><a href="%s" class="sk-btn sk-btn-theme vendor-dashboard" >%s</a></p>',
        esc_url( sk_get_navigation_url() ),
        esc_html( apply_filters( 'sk_set_go_to_vendor_dashboard_btn_text', __( 'Go to Vendor Dashboard', 'sk-core' ) ) )
    );
}

add_action( 'woocommerce_account_dashboard', 'sk_set_go_to_vendor_dashboard_btn' );

/**
 * Attach vendor name into order details
 *
 * @param  int item_id
 *
 * @param  object order
 *
 *
 * @return void
 */
function sk_attach_vendor_name( $item_id, $order ) {
    $product_id = $order->get_product_id();

    if ( ! $product_id ) {
        return;
    }

    $vendor_id = get_post_field( 'post_author', $product_id );
    $vendor    = sk()->vendor->get( $vendor_id );

    if ( ! $vendor->is_vendor() ) {
        return;
    }

    printf( '<br>%s: <a href="%s">%s</a>', esc_html__( 'Vendor', 'sk-core' ), esc_url( $vendor->get_shop_url() ), esc_html( $vendor->get_shop_name() ) );
}

add_action( 'woocommerce_order_item_meta_start', 'sk_attach_vendor_name', 10, 2 );

/**
 * Enable yoast seo breadcrums in sk store page
 *
 * @param  array $crumbs
 *
 * @return array
 */
function enable_yoast_breadcrumb( $crumbs ) {
    if ( ! sk_is_store_page() ) {
        return $crumbs;
    }

    $vendor    = sk()->vendor->get( get_query_var( 'author' ) );
    $store_url = sk_get_option( 'custom_store_url', 'sk_general', 'store' );

    if ( $vendor->get_id() === 0 ) {
        return $crumbs;
    }

    $crumbs[1]['text']  = ucwords( $store_url );
    $crumbs[1]['url']   = site_url() . '/' . $store_url;
    $crumbs[2]['text']  = $vendor->get_shop_name();
    $crumbs[2]['url']   = $vendor->get_shop_url();

    return $crumbs;
}

add_filter( 'wpseo_breadcrumb_links', 'enable_yoast_breadcrumb' );

/**
 * SK add privacy policy
 *
 * @return string
 */
function sk_add_privacy_policy() {
    echo '<div class="sk-privacy-policy-text">';
    sk_privacy_policy_text();
    echo '</div>';
}

add_action( 'sk_contact_form', 'sk_add_privacy_policy' );

/**
 * Remove store avatar set by ultimate member from store and store listing page
 *
 */
add_action(
    'pre_get_avatar',
    function () {
        $page_id = get_queried_object_id();
        $page    = get_post( $page_id );

        if ( ! $page instanceof WP_Post ) {
            return;
        }

        if ( sk_is_store_page() || sk_is_store_listing() || has_shortcode( $page->post_content, 'sk-stores' ) ) {
            remove_filter( 'get_avatar', 'um_get_avatar', 99999 );
        }
    }
);
