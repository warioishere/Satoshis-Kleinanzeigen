<?php

use SK\Core\Cache;

/**
 *  General Functions for SK Pro features
 *
 *
 */

/**
 * Get get seller coupon
 *
 *
 * @param int $seller_id
 *
 * @return array
 */
function sk_get_seller_coupon( $seller_id, $show_on_store = false ) {
    $args = [
        'post_type'   => 'shop_coupon',
        'post_status' => 'publish',
        'author'      => $seller_id,
    ];

    if ( $show_on_store ) {
        $args['meta_query'][] = [
            'key'   => 'show_on_store',
            'value' => 'yes',
        ];
    }

    $coupons = get_posts( $args );

    return $coupons;
}

/**
 * Get marketplace seller coupons
 *
 *
 * @param int  $seller_id
 * @param bool $show_on_store
 *
 * @return array
 */
function sk_get_marketplace_seller_coupon( $seller_id, $show_on_store = false ) {
    $args = [
        'post_type'   => 'shop_coupon',
        'post_status' => 'publish',
    ];

    if ( $show_on_store ) {
        $args['meta_query'][] = [
            'key'   => 'admin_coupons_show_on_stores',
            'value' => 'yes',
        ];
    }

    $coupons     = get_posts( $args );
    $get_coupons = [];

    if ( empty( $coupons ) ) {
        return $get_coupons;
    }

    foreach ( $coupons as $coupon ) {
        $vendors_ids     = get_post_meta( $coupon->ID, 'coupons_vendors_ids', true );
        $vendors_ids     = ! empty( $vendors_ids ) ? array_map( 'intval', explode( ',', $vendors_ids ) ) : [];
        $exclude_vendors = get_post_meta( $coupon->ID, 'coupons_exclude_vendors_ids', true );
        $exclude_vendors = ! empty( $exclude_vendors ) ? array_map( 'intval', explode( ',', $exclude_vendors ) ) : [];

        $coupon_meta = [
            'admin_coupons_enabled_for_vendor' => get_post_meta( $coupon->ID, 'admin_coupons_enabled_for_vendor', true ),
            'coupons_vendors_ids'              => $vendors_ids,
            'coupons_exclude_vendors_ids'      => $exclude_vendors,
        ];

        if ( sk_is_admin_created_vendor_coupon_by_meta( $coupon_meta, $seller_id ) ) {
            $get_coupons[] = $coupon;
        }
    }

    return $get_coupons;
}

/**
 * Get review page url of a seller
 *
 * @param int $user_id
 *
 * @return string
 */
function sk_get_review_url( $user_id ) {
    if ( ! $user_id ) {
        return '';
    }

    return apply_filters( 'sk_get_seller_review_url', sk_get_store_url( $user_id, 'reviews' ) );
}

/**
 * Get featured sellers list
 *
 * @param int $count
 *
 * @return array
 */
function sk_get_feature_sellers( $count = 5 ) {
    $args = [
        'role__in'   => [ 'administrator', 'seller' ],
        'meta_query' => [
            [
                'key'   => 'sk_feature_seller',
                'value' => 'yes',
            ],
            [
                'key'   => 'sk_enable_selling',
                'value' => 'yes',
            ],
        ],
        'number'     => $count,
    ];

    $sellers = get_users( apply_filters( 'sk_get_feature_sellers_args', $args ) );

    return $sellers;
}

/**
 * Set store categories
 *
 *
 * @param int            $store_id
 * @param array|int|null $categories
 *
 * @return array|WP_Error Term taxonomy IDs of the affected terms.
 */
function sk_set_store_categories( $store_id, $categories = null ) {
    if ( ! is_array( $categories ) ) {
        $categories = [ $categories ];
    }

    $categories = array_map( 'absint', $categories );
    $categories = array_filter( $categories );

    if ( empty( $categories ) ) {
        $categories = [ sk_get_default_store_category_id() ];
    }

    $categories = apply_filters( 'sk_set_store_categories', $categories );

    return wp_set_object_terms( $store_id, $categories, 'store_category' );
}

/**
 * Checks if store category feature is on or off
 *
 *
 * @return bool
 */
function sk_is_store_categories_feature_on() {
    return 'none' !== sk_get_option( 'store_category_type', 'sk_general', 'none' );
}

/**
 * Get the default store category id
 *
 *
 * @return int
 */
function sk_get_default_store_category_id() {
    $default_category = get_option( 'default_store_category', null );
    $term             = $default_category ? get_term( $default_category ) : null;

    if ( ! $term instanceof WP_Term ) {
        $uncategorized_id = term_exists( 'Uncategorized', 'store_category' );

        if ( ! $uncategorized_id ) {
            $uncategorized_id = wp_insert_term( 'Uncategorized', 'store_category' );
        }

        $default_category = $uncategorized_id['term_id'];

        sk_set_default_store_category_id( $default_category );
    }

    return absint( $default_category );
}

/**
 * Set the default store category id
 *
 * Make sure to category exists before calling
 * this function.
 *
 *
 * @param int $category_id
 *
 * @return bool
 */
function sk_set_default_store_category_id( $category_id ) {
    $general_settings                           = get_option( 'sk_general', [] );
    $general_settings['store_category_default'] = $category_id;

    $updated_settings = update_option( 'sk_general', $general_settings );
    $updated_default  = update_option( 'default_store_category', $category_id, false );

    return $updated_settings && $updated_default;
}

/**
 * Nomalize shipping postcode that contains '-' or space
 *
 *
 * @param string $code
 *
 * @return string
 */
function sk_normalize_shipping_postcode( $code ) {
    return str_replace( [ ' ', '-' ], '', $code );
}

/**
 * Include SK Pro template
 *
 * Modules should have their own get
 * template function, like `sk_geo_get_template`
 * used in Geolocation module.
 *
 *
 * @param string $name
 * @param array  $args
 *
 * @return void
 */
function sk_pro_get_template( $name, $args = [] ) {
    sk_get_template( "$name.php", $args, 'sk', trailingslashit( SK_CORE_DIR . "/templates" ) );
}

/**
 * SK is single seller mode enable
 *
 *
 * @return boolean
 */
function sk_is_single_seller_mode_enable() {
    $is_single_seller_mode = apply_filters_deprecated( 'sk_signle_seller_mode', [ sk_get_option( 'enable_single_seller_mode', 'sk_general', 'off' ) ], '3.0.0', 'sk_single_seller_mode' );

    return apply_filters( 'sk_single_seller_mode', $is_single_seller_mode );
}



/**
 * This method will return a random string
 *
 * @param int $length should be positive even number
 *
 * @return string
 */
function sk_get_random_string( $length = 8 ) {
    // ensure a minimum length
    if ( ! isset( $length ) || $length < 4 ) {
        $length = 8;
    }
    // make length as even number
    if ( $length % 2 !== 0 ) {
        ++$length;
    }
    // get random bytes via available methods
    $random_bytes = '';
    if ( function_exists( 'random_bytes' ) ) {
        try {
            $random_bytes = random_bytes( $length / 2 );
        } catch ( TypeError $e ) {
            $random_bytes = '';
        } catch ( Error $e ) {
            $random_bytes = '';
        } catch ( Exception $e ) {
            $random_bytes = '';
        }
    }
    // random_bytes failed, try another method
    if ( empty( $random_bytes ) && function_exists( 'openssl_random_pseudo_bytes' ) ) {
        $random_bytes = openssl_random_pseudo_bytes( $length / 2 );
    }

    if ( ! empty( $random_bytes ) ) {
        return bin2hex( $random_bytes );
    }

    // builtin method failed, try manual method
    return substr( str_shuffle( str_repeat( '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', wp_rand( 1, 10 ) ) ), 1, $length );
}

/**
 * Get script suffic and version for sk
 *
 *
 * @return array first element is script file suffix and second element is script file version
 */
function sk_get_script_suffix_and_version() {
    $suffix         = '';
    $script_version = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? time() : SK_CORE_VERSION;

    return [ $suffix, $script_version ];
}

if ( ! function_exists( 'sk_is_hpos_enabled' ) ) :
    /**
     * Check if HPOS is enabled
     *
     */
    function sk_is_hpos_enabled(): bool {
        if ( class_exists( '\SK\Core\Utilities\OrderUtil' ) ) {
            return \SK\Core\Utilities\OrderUtil::is_hpos_enabled();
        }

        return false;
    }
endif;

if ( ! function_exists( 'sk_is_order' ) ) {
    /**
     * Check if the given id is an order
     *
     *
     * @param int   $order_id
     * @param array $types
     *
     * @return bool
     */
    function sk_is_order( $order_id, $types = [] ): bool {
        $types = empty( $types ) ? wc_get_order_types() : $types;
        if ( sk_is_hpos_enabled() ) {
            return \SK\Core\Utilities\OrderUtil::is_order( $order_id, $types );
        }

        return in_array( get_post_type( $order_id ), $types, true );
    }
}

/**
 * Trigger product create email
 *
 *
 * @param WC_Product|int $product
 *
 * @return void
 */
function sk_trigger_product_create_email( $product ) {
    if ( is_numeric( $product ) ) {
        $product = wc_get_product( $product );
    }

    if ( ! $product ) {
        return;
    }

    $email = null;
    if ( 'publish' === $product->get_status() ) {
        $email = WC()->mailer()->get_emails()['SK_Email_New_Product'];
    } elseif ( 'pending' === $product->get_status() ) {
        $email = WC()->mailer()->get_emails()['SK_Email_New_Product_Pending'];
    }

    if ( is_object( $email ) && is_callable( [ $email, 'trigger' ] ) ) {
        $email->trigger( $product->get_id() );
    }
}

if ( ! function_exists( 'has_cart_block_in_page' ) ) {
    /**
     * Returns true if cart block is used in cart page.
     *
     *
     * @param $page_id
     *
     * @return boolean
     */
    function has_cart_block_in_page( $page_id = '' ) {
        if ( empty( $page_id ) ) {
            $page_id = wc_get_page_id( 'cart' );
        }

        return has_block( 'woocommerce/cart', $page_id );
    }
}

if ( ! function_exists( 'has_checkout_block_in_page' ) ) {
    /**
     * Returns true if checkout block is used in cart page.
     *
     *
     * @return boolean
     */
    function has_checkout_block_in_page( $page_id = '' ) {
        if ( empty( $page_id ) ) {
            $page_id = wc_get_page_id( 'cart' );
        }

        return has_block( 'woocommerce/checkout', $page_id );
    }
}
