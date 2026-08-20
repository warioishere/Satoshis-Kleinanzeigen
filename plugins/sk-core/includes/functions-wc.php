<?php

/**
 * Sanitize a free-text price input before handing it to wc_format_decimal.
 *
 * Vendors type things like "180.000" (DE) or "180'000" (CH) meaning 180k —
 * wc_format_decimal reads "." as the decimal separator (WC default) and
 * silently turns that into 180.0, which then gets rounded to 180 because
 * SAT is configured with num_decimals=0. For integer-only currencies we
 * therefore strip every non-digit before parsing.
 */
function sk_parse_price_input( $value ) {
    $value = (string) $value;
    if ( '' === $value ) {
        return '';
    }
    if ( (int) wc_get_price_decimals() === 0 ) {
        return preg_replace( '/\D/', '', $value );
    }
    return $value;
}

/**
 * Save variation product price with optional sale schedule.
 */
function sk_save_product_price( $product_id, $regular_price, $sale_price = '', $date_from = '', $date_to = '' ) {
    $product = wc_get_product( absint( $product_id ) );

    if ( ! $product instanceof WC_Product ) {
        return;
    }

    $regular_price = wc_format_decimal( sk_parse_price_input( $regular_price ) );
    $sale_price    = '' === $sale_price ? '' : wc_format_decimal( sk_parse_price_input( $sale_price ) );
    $date_from     = wc_clean( $date_from );
    $date_to       = wc_clean( $date_to );
    $now           = sk_current_datetime();

    $product->set_regular_price( $regular_price );
    $product->set_sale_price( $sale_price );

    if ( $date_from && $date_to ) {
        $product->set_date_on_sale_from( $now->modify( $date_from )->modify( 'today' )->getTimestamp() );
        $product->set_date_on_sale_to( $now->modify( $date_to )->setTime( 23, 59, 59 )->getTimestamp() );
    }

    if ( $date_to && ! $date_from ) {
        $product->set_date_on_sale_from( $now->getTimestamp() );
    }

    if ( '' !== $sale_price && '' === $date_to && '' === $date_from ) {
        $product->set_price( $sale_price );
    } else {
        $product->set_price( $regular_price );
    }

    if ( '' !== $sale_price && $date_from && $now->modify( $date_from )->getTimestamp() < $now->getTimestamp() ) {
        $product->set_price( $sale_price );
    }

    if ( $date_to && $now->modify( $date_to )->getTimestamp() < $now->getTimestamp() ) {
        $product->set_price( $regular_price );
        $product->set_date_on_sale_from();
        $product->set_date_on_sale_to();
    }

    $product->save();
}

/**
 * Sync downloadable-file access permissions when a product's download list changes.
 * Hooked to sk_process_file_download (fired after variation/simple product save).
 */
function sk_process_product_file_download_paths_permission( $product_id, $variation_id, $downloadable_files ) {
    global $wpdb;

    if ( $variation_id ) {
        $product_id = $variation_id;
    }

    $product               = wc_get_product( $product_id );
    $existing_download_ids = array_keys( (array) $product->get_downloads() );
    $updated_download_ids  = array_keys( (array) $downloadable_files );

    $new_download_ids     = array_filter( array_diff( $updated_download_ids, $existing_download_ids ) );
    $removed_download_ids = array_filter( array_diff( $existing_download_ids, $updated_download_ids ) );

    if ( empty( $new_download_ids ) && empty( $removed_download_ids ) ) {
        return;
    }

    $existing_permissions = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->prepare(
            "SELECT * from {$wpdb->prefix}woocommerce_downloadable_product_permissions WHERE product_id = %d GROUP BY order_id",
            $product_id
        )
    );

    foreach ( $existing_permissions as $existing_permission ) {
        $order = wc_get_order( $existing_permission->order_id );
        if ( ! $order || ! $order->get_id() ) {
            continue;
        }

        foreach ( $removed_download_ids as $download_id ) {
            if ( apply_filters( 'woocommerce_process_product_file_download_paths_remove_access_to_old_file', true, $download_id, $product_id, $order ) ) {
                $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                    $wpdb->prepare(
                        "DELETE FROM {$wpdb->prefix}woocommerce_downloadable_product_permissions WHERE order_id = %d AND product_id = %d AND download_id = %s",
                        $order->get_id(), $product_id, $download_id
                    )
                );
            }
        }

        foreach ( $new_download_ids as $download_id ) {
            if ( apply_filters( 'woocommerce_process_product_file_download_paths_grant_access_to_new_file', true, $download_id, $product_id, $order ) ) {
                $existing = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                    $wpdb->prepare(
                        "SELECT 1=1 FROM {$wpdb->prefix}woocommerce_downloadable_product_permissions WHERE order_id = %d AND product_id = %d AND download_id = %s",
                        $order->get_id(), $product_id, $download_id
                    )
                );
                if ( ! $existing ) {
                    wc_downloadable_file_permission( $download_id, $product_id, $order );
                }
            }
        }
    }
}
add_action( 'sk_process_file_download', 'sk_process_product_file_download_paths_permission', 10, 3 );
/**
 * Handles the social registration form
 *
 * @return void
 */
if ( ! function_exists( 'sk_social_reg_handler' ) ) {

    function sk_social_reg_handler() {
        $_post_data = wp_unslash( $_POST );
        if ( isset( $_post_data['sk_social'] ) && isset( $_post_data['sk_nonce'] ) && wp_verify_nonce( $_post_data['sk_nonce'], 'account_migration' ) ) {
            $userdata = get_userdata( get_current_user_id() );

            $userdata->first_name = sanitize_text_field( $_post_data['fname'] );
            $userdata->last_name  = sanitize_text_field( $_post_data['lname'] );

            wp_update_user( $userdata );

            wp_safe_redirect( sk_get_page_url( 'dashboard', 'sk' ) );
        }
    }
}

add_action( 'template_redirect', 'sk_social_reg_handler' );

if ( function_exists( 'sk_add_privacy_policy' ) ) {
    // show privacy policy text in product enquiry form
    add_action( 'sk_product_enquiry_after_form', 'sk_add_privacy_policy' );
}

add_filter( 'woocommerce_ajax_admin_get_variations_args', 'sk_set_variations_args' );
add_filter( 'woocommerce_variable_children_args', 'sk_set_variations_args' );

/**
 * Include pending product status into variation args
 *
 * @param array $args
 *
 */
function sk_set_variations_args( $args ) {
    if ( ! is_array( $args['post_status'] ) ) {
        return $args;
    }

    $args['post_status'] = array_merge( $args['post_status'], [ 'pending' ] );

    return $args;
}

/**
 * Set variation product author to product vendor id
 *
 * @param int $variation_id
 *
 *
 * @return void
 */
function sk_override_variation_product_author( $variation_id ) {
    if ( ! is_admin() ) {
        return;
    }

    $variation_product = get_post( $variation_id );

    if ( ! $variation_product ) {
        return;
    }

    $product_id = $variation_product->post_parent;

    if ( ! $product_id ) {
        return;
    }

    $product = wc_get_product( $product_id );

    if ( ! $product ) {
        return;
    }

    $vendor    = sk_get_vendor_by_product( $product );
    $vendor_id = $vendor->get_id();

    if ( ! $vendor || ! $vendor_id ) {
        return;
    }

    if ( absint( $vendor_id ) === absint( $variation_product->post_author ) ) {
        return;
    }

    wp_update_post(
        [
            'ID'          => $variation_id,
            'post_author' => $vendor_id,
        ]
    );

    do_action( 'sk_after_override_variation_product_author', $product, $vendor_id );
}

add_action( 'woocommerce_save_product_variation', 'sk_override_variation_product_author' );

/**
 * SK enabble single seller mode
 *
 * @param bool $valid
 * @param int $product_id
 *
 *
 * @return bool
 */
function sk_validate_cart_for_single_seller_mode( $valid, $product_id ) {
    if ( ! sk_validate_boolean( sk_is_single_seller_mode_enable() ) ) {
        return $valid;
    }

    $products                = WC()->cart->get_cart();
    $products[ $product_id ] = [ 'product_id' => $product_id ];

    if ( ! $products ) {
        return $valid;
    }

    $vendors = [];

    foreach ( $products as $key => $data ) {
        $product_id = isset( $data['product_id'] ) ? $data['product_id'] : 0;
        $vendor     = sk_get_vendor_by_product( $product_id );
        $vendor_id  = $vendor && $vendor->get_id() ? $vendor->get_id() : 0;

        if ( ! $vendor_id ) {
            continue;
        }

        if ( ! in_array( $vendor_id, $vendors, true ) ) {
            array_push( $vendors, $vendor_id );
        }
    }

    if ( count( $vendors ) > 1 ) {
        wc_add_notice( __( 'Sorry, you can\'t add more than one vendor\'s product in the cart.', 'sk-core' ), 'error' );
        $valid = false;
    }

    return $valid;
}

add_filter( 'woocommerce_add_to_cart_validation', 'sk_validate_cart_for_single_seller_mode', 10, 2 );

/**
 * SK rest validate single seller mode
 *
 * @param WC_Order $order
 * @param WP_REST_Request
 * @param bool $creating
 *
 *
 * @return WC_Order|WP_REST_Response on failure
 */
function sk_rest_validate_single_seller_mode( $order, $request, $creating ) {
    if ( ! $creating ) {
        return $order;
    }

    if ( ! sk_validate_boolean( sk_is_single_seller_mode_enable() ) ) {
        return $order;
    }

    if ( $order->get_meta( 'has_sub_order' ) ) {
        return rest_ensure_response(
            new WP_Error(
                'sk_single_seller_mode',
                __( 'Sorry, you can\'t purchase from multiple vendors at once.', 'sk-core' ),
                [
                    'status' => 403,
                ]
            )
        );
    }

    return $order;
}

add_filter( 'woocommerce_rest_pre_insert_shop_order_object', 'sk_rest_validate_single_seller_mode', 15, 3 );

if ( ! function_exists( 'woocommerce_customer_available_downloads_modified' ) ) {

    /**
     * SK customer available downloads modified for sub orders
     *
     * @param array $downloads
     *
     *
     * @return array $modified_downloads|$downloads
     */
    function sk_woocommerce_customer_available_downloads_modified( $downloads ) {

        if ( empty( $downloads ) ) {
            return $downloads;
        }

        $modified_downloads = [];

        foreach ( $downloads as $download ) {
            $order_id = $download['order_id'];
            $order    = wc_get_order( $order_id );

            if ( empty( $order ) ) {
                continue;
            }

            if ( $order->get_meta( 'has_sub_order' ) ) {
                continue;
            }

            $modified_downloads[] = $download;
        }

        if ( ! empty( $modified_downloads ) ) {
            return $modified_downloads;
        }

        return $downloads;
    }

    add_filter( 'woocommerce_customer_available_downloads', 'sk_woocommerce_customer_available_downloads_modified', 15, 1 );
}

