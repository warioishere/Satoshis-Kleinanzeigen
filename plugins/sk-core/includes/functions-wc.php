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

function sk_save_variations( $post_id ) {
    global $woocommerce, $wpdb;

    $attributes = (array) maybe_unserialize( get_post_meta( $post_id, '_product_attributes', true ) );
    update_post_meta( $post_id, '_create_variation', 'yes' );

    $_post_data = wp_unslash( $_POST ); //phpcs:ignore WordPress.Security.NonceVerification.Missing

    if ( isset( $_post_data['variable_sku'] ) ) {
        $variable_post_id               = $_post_data['variable_post_id'];
        $variable_sku                   = $_post_data['variable_sku'];
        $variable_regular_price         = $_post_data['variable_regular_price'];
        $variable_sale_price            = $_post_data['variable_sale_price'];
        $upload_image_id                = $_post_data['upload_image_id'];
        $variable_download_limit        = $_post_data['variable_download_limit'];
        $variable_download_expiry       = $_post_data['variable_download_expiry'];
        $variable_tax_class             = isset( $_post_data['variable_tax_class'] ) ? $_post_data['variable_tax_class'] : [];
        $variable_menu_order            = $_post_data['variation_menu_order'];
        $variable_sale_price_dates_from = $_post_data['variable_sale_price_dates_from'];
        $variable_sale_price_dates_to   = $_post_data['variable_sale_price_dates_to'];

        $variable_weight          = isset( $_post_data['variable_weight'] ) ? $_post_data['variable_weight'] : [];
        $variable_length          = isset( $_post_data['variable_length'] ) ? $_post_data['variable_length'] : [];
        $variable_width           = isset( $_post_data['variable_width'] ) ? $_post_data['variable_width'] : [];
        $variable_height          = isset( $_post_data['variable_height'] ) ? $_post_data['variable_height'] : [];
        $variable_enabled         = isset( $_post_data['variable_enabled'] ) ? $_post_data['variable_enabled'] : [];
        $variable_is_virtual      = isset( $_post_data['variable_is_virtual'] ) ? $_post_data['variable_is_virtual'] : [];
        $variable_is_downloadable = isset( $_post_data['variable_is_downloadable'] ) ? $_post_data['variable_is_downloadable'] : [];

        $variable_manage_stock = isset( $_post_data['variable_manage_stock'] ) ? $_post_data['variable_manage_stock'] : [];
        $variable_stock        = isset( $_post_data['variable_stock'] ) ? $_post_data['variable_stock'] : [];
        $variable_low_stock_amount        = isset( $_post_data['variable_low_stock_amount'] ) ? $_post_data['variable_low_stock_amount'] : [];
        $variable_backorders   = isset( $_post_data['variable_backorders'] ) ? $_post_data['variable_backorders'] : [];
        $variable_stock_status = isset( $_post_data['variable_stock_status'] ) ? $_post_data['variable_stock_status'] : [];

        $variable_description = isset( $_post_data['variable_description'] ) ? $_post_data['variable_description'] : [];

        $max_loop = max( array_keys( $_post_data['variable_post_id'] ) );

        for ( $i = 0; $i <= $max_loop; $i++ ) {
            if ( ! isset( $variable_post_id[ $i ] ) ) {
                continue;
            }

            $variation_id = absint( $variable_post_id[ $i ] );

            // Checkboxes
            $is_virtual      = isset( $variable_is_virtual[ $i ] ) ? 'yes' : 'no';
            $is_downloadable = isset( $variable_is_downloadable[ $i ] ) ? 'yes' : 'no';
            $post_status     = isset( $variable_enabled[ $i ] ) ? 'publish' : 'private';
            $manage_stock    = isset( $variable_manage_stock[ $i ] ) ? 'yes' : 'no';

            // Update or Add post
            if ( ! $variation_id ) {
                $variation = [
                    'post_content' => '',
                    'post_status'  => $post_status,
                    'post_author'  => get_current_user_id(),
                    'post_parent'  => $post_id,
                    'post_type'    => 'product_variation',
                    'menu_order'   => $variable_menu_order[ $i ],
                ];

                $variation_id = wp_insert_post( $variation );
                $product = wc_get_product( $variation_id );

                do_action( 'woocommerce_create_product_variation', $product->get_id(), $product );
                do_action( 'sk_create_product_variation', $product->get_id(), $product );
            } else {
                $modified_date = date_i18n( 'Y-m-d H:i:s', current_time( 'timestamp' ) );//phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested

                $wpdb->update(
                    $wpdb->posts,
                    [
                        'post_status'       => $post_status,
                        'menu_order'        => $variable_menu_order[ $i ],
                        'post_modified'     => $modified_date,
                        'post_modified_gmt' => get_gmt_from_date( $modified_date ),
                    ],
                    [ 'ID' => $variation_id ]
                );

                clean_post_cache( $variation_id );

                $product = wc_get_product( $variation_id );
                do_action( 'woocommerce_update_product_variation', $product->get_id(), $product );
                do_action( 'sk_update_product_variation', $product->get_id(), $product );
            }

            // Only continue if we have a variation ID
            if ( ! $variation_id ) {
                continue;
            }

            // Unique SKU
            $sku     = get_post_meta( $variation_id, '_sku', true );
            $new_sku = wc_clean( $variable_sku[ $i ] );

            if ( '' === $new_sku ) {
                update_post_meta( $variation_id, '_sku', '' );
            } elseif ( $new_sku !== $sku ) {
                if ( ! empty( $new_sku ) ) {
                    $unique_sku = wc_product_has_unique_sku( $variation_id, $new_sku );

                    if ( ! $unique_sku ) {
                        /* translators: %s: variation id  */
                        $woocommerce_errors[] = sprintf( __( '#%s &ndash; Variation SKU must be unique.', 'sk-core' ), $variation_id );
                    } else {
                        update_post_meta( $variation_id, '_sku', $new_sku );
                    }
                } else {
                    update_post_meta( $variation_id, '_sku', '' );
                }
            }

            // Update post meta
            update_post_meta( $variation_id, '_thumbnail_id', absint( $upload_image_id[ $i ] ) );
            update_post_meta( $variation_id, '_virtual', wc_clean( $is_virtual ) );
            update_post_meta( $variation_id, '_downloadable', wc_clean( $is_downloadable ) );

            if ( isset( $variable_weight[ $i ] ) ) {
                update_post_meta( $variation_id, '_weight', ( '' === $variable_weight[ $i ] ) ? '' : wc_format_decimal( $variable_weight[ $i ] ) );
            }

            if ( isset( $variable_length[ $i ] ) ) {
                update_post_meta( $variation_id, '_length', ( '' === $variable_length[ $i ] ) ? '' : wc_format_decimal( $variable_length[ $i ] ) );
            }

            if ( isset( $variable_width[ $i ] ) ) {
                update_post_meta( $variation_id, '_width', ( '' === $variable_width[ $i ] ) ? '' : wc_format_decimal( $variable_width[ $i ] ) );
            }

            if ( isset( $variable_height[ $i ] ) ) {
                update_post_meta( $variation_id, '_height', ( '' === $variable_height[ $i ] ) ? '' : wc_format_decimal( $variable_height[ $i ] ) );
            }

            // Stock handling
            update_post_meta( $variation_id, '_manage_stock', $manage_stock );

            if ( 'yes' === $manage_stock ) {
                update_post_meta( $variation_id, '_backorders', wc_clean( $variable_backorders[ $i ] ) );
                wc_update_product_stock( $variation_id, wc_stock_amount( $variable_stock[ $i ] ) );
                update_post_meta( $variable_low_stock_amount, '_low_stock_amount', wc_format_decimal( $variable_low_stock_amount[ $i ] ) );
            } else {
                $parent_manage_stock = ! empty( $_post_data['_manage_stock'] ) ? 'yes' : 'no';
                $parent_stock_amount = isset( $_post_data['_stock'] ) ? wc_clean( $_post_data['_stock'] ) : '';
                $parent_stock_amount = 'yes' === $parent_manage_stock ? wc_stock_amount( wp_unslash( $parent_stock_amount ) ) : '';

                delete_post_meta( $variation_id, '_backorders' );
                wc_update_product_stock( $variation_id, $parent_stock_amount );
            }

            // Only update stock status to user setting if changed by the user, but do so before looking at stock levels at variation level
            if ( ! empty( $variable_stock_status[ $i ] ) ) {
                wc_update_product_stock_status( $variation_id, $variable_stock_status[ $i ] );
            }

            // Price handling
            sk_save_product_price( $variation_id, $variable_regular_price[ $i ], $variable_sale_price[ $i ], $variable_sale_price_dates_from[ $i ], $variable_sale_price_dates_to[ $i ] );

            if ( isset( $variable_tax_class[ $i ] ) && 'parent' !== $variable_tax_class[ $i ] ) {
                update_post_meta( $variation_id, '_tax_class', wc_clean( $variable_tax_class[ $i ] ) );
            } else {
                delete_post_meta( $variation_id, '_tax_class' );
            }

            if ( 'yes' === $is_downloadable ) {
                // fix download limit
                $download_limit = intval( $variable_download_limit[ $i ] );
                if ( ! $download_limit || -1 === $download_limit ) {
                    $download_limit = '';
                }
                // fix download expiry
                $download_expiry = intval( $variable_download_expiry[ $i ] );
                if ( ! $download_expiry || -1 === $download_expiry ) {
                    $download_expiry = '';
                }
                update_post_meta( $variation_id, '_download_limit', $download_limit );
                update_post_meta( $variation_id, '_download_expiry', $download_expiry );

                $files         = [];
                $_post_data    = wp_unslash( $_POST );//phpcs:ignore WordPress.Security.NonceVerification.Missing
                $file_names    = isset( $_post_data['_wc_variation_file_names'][ $variation_id ] ) ? array_map( 'wc_clean', $_post_data['_wc_variation_file_names'][ $variation_id ] ) : [];
                $file_urls     = isset( $_post_data['_wc_variation_file_urls'][ $variation_id ] ) ? array_map( 'esc_url_raw', array_map( 'trim', $_post_data['_wc_variation_file_urls'][ $variation_id ] ) ) : [];
                $file_url_size = count( $file_urls );

                for ( $ii = 0; $ii < $file_url_size; $ii++ ) {
                    if ( ! empty( $file_urls[ $ii ] ) ) {
                        $files[ md5( $file_urls[ $ii ] ) ] = [
                            'name' => $file_names[ $ii ],
                            'file' => $file_urls[ $ii ],
                        ];
                    }
                }

                // grant permission to any newly added files on any existing orders for this product prior to saving
                do_action( 'sk_process_file_download', $post_id, $variation_id, $files );
                update_post_meta( $variation_id, '_downloadable_files', $files );
            } else {
                update_post_meta( $variation_id, '_download_limit', '' );
                update_post_meta( $variation_id, '_download_expiry', '' );
                update_post_meta( $variation_id, '_downloadable_files', '' );
            }

            // Update variation description
            update_post_meta( $variation_id, '_variation_description', wp_kses_post( $variable_description[ $i ] ) );

            // Update Attributes
            $updated_attribute_keys = [];
            foreach ( $attributes as $attribute ) {
                if ( $attribute['is_variation'] ) {
                    $attribute_key            = 'attribute_' . sanitize_title( $attribute['name'] );
                    $updated_attribute_keys[] = $attribute_key;

                    if ( $attribute['is_taxonomy'] ) {
                        // Don't use wc_clean as it destroys sanitized characters
                        $value = isset( $_post_data[ $attribute_key ][ $i ] ) ? sanitize_title( stripslashes( $_post_data[ $attribute_key ][ $i ] ) ) : '';
                    } else {
                        $value = isset( $_post_data[ $attribute_key ][ $i ] ) ? wc_clean( stripslashes( $_post_data[ $attribute_key ][ $i ] ) ) : '';
                    }

                    update_post_meta( $variation_id, $attribute_key, $value );
                }
            }

            // Remove old taxonomies attributes so data is kept up to date - first get attribute key names
            $delete_attribute_keys = $wpdb->get_col( $wpdb->prepare( "SELECT meta_key FROM {$wpdb->postmeta} WHERE meta_key LIKE 'attribute_%%' AND meta_key NOT IN ( '" . implode( "','", $updated_attribute_keys ) . "' ) AND post_id = %d;", $variation_id ) ); //phpcs:ignore

            foreach ( $delete_attribute_keys as $key ) {
                delete_post_meta( $variation_id, $key );
            }

            do_action( 'woocommerce_save_product_variation', $variation_id, $i );
            do_action( 'sk_save_product_variation', $variation_id, $i );
        }
    }

    // Update parent if variable so price sorting works and stays in sync with the cheapest child
    WC_Product_Variable::sync( $post_id );

    // Update default attribute options setting
    $default_attributes = [];

    foreach ( $attributes as $attribute ) {
        if ( $attribute['is_variation'] ) {
            $value = '';

            if ( isset( $_post_data[ 'default_attribute_' . sanitize_title( $attribute['name'] ) ] ) ) {
                if ( $attribute['is_taxonomy'] ) {
                    // Don't use wc_clean as it destroys sanitized characters
                    $value = sanitize_title( trim( stripslashes( $_post_data[ 'default_attribute_' . sanitize_title( $attribute['name'] ) ] ) ) );
                } else {
                    $value = wc_clean( trim( stripslashes( $_post_data[ 'default_attribute_' . sanitize_title( $attribute['name'] ) ] ) ) );
                }
            }

            if ( $value ) {
                $default_attributes[ sanitize_title( $attribute['name'] ) ] = $value;
            }
        }
    }

    update_post_meta( $post_id, '_default_attributes', $default_attributes );
}


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

