<?php

use SK\Core\Cache;

/**
 *  General Functions for SK Pro features
 *
 *
 */

/**
 * Returns Current User Profile progress bar HTML
 *
 *
 * @return string
 */
/**
 * SK progressbar translated string
 *
 * @param string $string
 * @param int    $value
 * @param int    $progress
 *
 * @return string
 */
function sk_progressbar_translated_string( $string = '', $value = 15, $progress = 0 ) {
    $translated_string = '';
    if ( 100 === absint( $progress ) ) :
        $translated_string = __( 'Congratulation, your profile is fully completed', 'sk' );
    else :
        switch ( $string ) {
            case 'profile_picture_val':
                // translators: %s%% is the progressbar progress value
                $translated_string = sprintf( __( '<a href="%1$s">Add Profile Picture</a> to gain %2$s%% progress', 'sk' ), untrailingslashit( sk_get_navigation_url( 'settings/store#sk-profile-picture-wrapper' ) ), number_format_i18n( $value ) );
                break;

            case 'phone_val':
                // translators: %s%% is the progressbar progress value
                $translated_string = sprintf( __( '<a href="%1$s">Add Phone</a> to gain %2$s%% progress', 'sk' ), untrailingslashit( sk_get_navigation_url( 'settings/store#setting_phone' ) ), number_format_i18n( $value ) );
                break;

            case 'banner_val':
                // translators: %s%% is the progressbar progress value
                $translated_string = sprintf( __( '<a href="%1$s">Add Banner</a> to gain %2$s%% progress', 'sk' ), untrailingslashit( sk_get_navigation_url( 'settings/store#sk-banner-wrapper' ) ), number_format_i18n( $value ) );
                break;

            case 'store_name_val':
                // translators: %s%% is the progressbar progress value
                $translated_string = sprintf( __( '<a href="%1$s">Add Store Name</a> to gain %2$s%% progress', 'sk' ), untrailingslashit( sk_get_navigation_url( 'settings/store#sk_store_name' ) ), number_format_i18n( $value ) );
                break;

            case 'address_val':
                // translators: %s%% is the progressbar progress value
                $translated_string = sprintf( __( '<a href="%1$s">Add address</a> to gain %2$s%% progress', 'sk' ), untrailingslashit( sk_get_navigation_url( 'settings/store#sk-address-fields-wrapper' ) ), number_format_i18n( $value ) );
                break;

            case 'payment_method_val':
                // translators: %s%% is the progressbar progress value
                $translated_string = sprintf( __( '<a href="%1$s">Add a Payment method</a> to gain %2$s%% progress', 'sk' ), untrailingslashit( sk_get_navigation_url( 'settings/payment#sk-payment-methods-listing-wrapper' ) ), number_format_i18n( $value ) );
                break;

            case 'map_val':
                // translators: %s%% is the progressbar progress value
                $translated_string = sprintf( __( '<a href="%1$s">Add Map location</a> to gain %2$s%% progress', 'sk' ), untrailingslashit( sk_get_navigation_url( 'settings/store#sk-add-store-location-section' ) ), number_format_i18n( $value ) );
                break;

            case 'fb':
                // translators: %s%% is the progressbar progress value
                $translated_string = sprintf( __( '<a href="%1$s">Add facebook</a> to gain %2$s%% progress', 'sk' ), untrailingslashit( sk_get_navigation_url( 'settings/social#profile-form' ) ), number_format_i18n( $value ) );
                break;

            case 'twitter':
                // translators: %s%% is the progressbar progress value
                $translated_string = sprintf( __( '<a href="%1$s">Add Twitter</a> to gain %2$s%% progress', 'sk' ), untrailingslashit( sk_get_navigation_url( 'settings/social#profile-form' ) ), number_format_i18n( $value ) );
                break;

            case 'youtube':
                // translators: %s%% is the progressbar progress value
                $translated_string = sprintf( __( '<a href="%1$s">Add Youtube</a> to gain %2$s%% progress', 'sk' ), untrailingslashit( sk_get_navigation_url( 'settings/social#profile-form' ) ), number_format_i18n( $value ) );
                break;

            case 'linkedin':
                // translators: %s%% is the progressbar progress value
                $translated_string = sprintf( __( '<a href="%1$s">Add LinkedIn</a> to gain %2$s%% progress', 'sk' ), untrailingslashit( sk_get_navigation_url( 'settings/social#profile-form' ) ), number_format_i18n( $value ) );
                break;

            default:
                $translated_string = sprintf( __( 'Start with <a href="%s">adding a Banner</a> to gain profile progress', 'sk' ), untrailingslashit( sk_get_navigation_url( 'settings/store#sk-banner-wrapper' ) ) );
                break;
        }
    endif;

    return apply_filters( 'sk_progressbar_translated_string', $translated_string, $string, $value, $progress );
}

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
 * SK get shipping tracking providers list
 *
 *
 * @return array
 */
function sk_shipping_status_tracking_providers_list() {
    $providers = [
        'sp-australia-post'            => [
            'label' => __( 'Australia Post', 'sk' ),
            'url'   => 'https://auspost.com.au/mypost/track/#/search?tracking={tracking_number}',
        ],
        'sp-canada-post'               => [
            'label' => __( 'Canada Post', 'sk' ),
            'url'   => 'https://www.canadapost.ca/track-reperage/en#/home/?tracking={tracking_number}',
        ],
        'sp-city-link'                 => [
            'label' => __( 'City Link', 'sk' ),
            'url'   => 'https://www.citylinkexpress.com/tracking-result/?track0={tracking_number}',
        ],
        'sp-dhl'                       => [
            'label' => __( 'DHL', 'sk' ),
            'url'   => 'https://www.dhl.com/en/express/tracking.html?AWB={tracking_number}&brand=DHL',
        ],
        'sp-dpd'                       => [
            'label' => __( 'DPD', 'sk' ),
            'url'   => 'https://tracking.dpd.de/status/en_NL/parcel/{tracking_number}',
        ],
        'sp-fastway-south-africa'      => [
            'label' => __( 'Fastway South Africa', 'sk' ),
            'url'   => 'https://www.fastway.co.za/our-services/track-your-parcel/?track={tracking_number}',
        ],
        'sp-fedex'                     => [
            'label' => __( 'Fedex', 'sk' ),
            'url'   => 'https://www.fedex.com/fedextrack/no-results-found?trknbr={tracking_number}',
        ],
        'sp-ontrac'                    => [
            'label' => __( 'OnTrac', 'sk' ),
            'url'   => 'https://www.ontrac.com/trackingdetail.asp/?track={tracking_number}',
        ],
        'sp-parcelforce'               => [
            'label' => __( 'ParcelForce', 'sk' ),
            'url'   => 'https://www.parcelforce.com/track-trace/?trackNumber={tracking_number}',
        ],
        'sp-polish-shipping-providers' => [
            'label' => __( 'Polish shipping providers', 'sk' ),
            'url'   => 'https://www.parcelmonitor.com/track-poland/track-it-online/?pParcelIds={tracking_number}',
        ],
        'sp-royal-mail'                => [
            'label' => __( 'Royal Mail', 'sk' ),
            'url'   => 'https://www.royalmail.com/track-your-item#/?track={tracking_number}',
        ],
        'sp-tnt-express-consignment'   => [
            'label' => __( 'TNT Express (consignment)', 'sk' ),
            'url'   => 'https://www.tnt.com/express/site/tracking.html/?track={tracking_number}',
        ],
        'sp-tnt-express-reference'     => [
            'label' => __( 'TNT Express (reference)', 'sk' ),
            'url'   => 'https://www.tnt.com/express/site/tracking.html/?track={tracking_number}',
        ],
        'sp-fedex-sameday'             => [
            'label' => __( 'FedEx Sameday', 'sk' ),
            'url'   => 'https://www.fedex.com/fedextrack/?action=track&tracknumbers={tracking_number}',
        ],
        'sp-ups'                       => [
            'label' => __( 'UPS', 'sk' ),
            'url'   => 'https://www.ups.com/track/?trackingNumber={tracking_number}',
        ],
        'sp-usps'                      => [
            'label' => __( 'USPS', 'sk' ),
            'url'   => 'https://tools.usps.com/go/TrackConfirmAction?tRef=fullpage&tLabels={tracking_number}',
        ],
        'sp-dhl-us'                    => [
            'label' => __( 'DHL US', 'sk' ),
            'url'   => 'https://www.dhl.com/us-en/home/tracking/tracking-global-forwarding.html?submit=1&tracking-id={tracking_number}',
        ],
        'sp-other'                     => [
            'label' => __( 'Other', 'sk' ),
            'url'   => '',
        ],
    ];

    return apply_filters( 'sk_shipping_status_tracking_providers_list', $providers );
}

/**
 * SK get shipping tracking providers list
 *
 *
 * @return array
 */
function sk_get_shipping_tracking_providers_list() {
    $providers = [];

    if ( ! empty( sk_shipping_status_tracking_providers_list() ) && is_array( sk_shipping_status_tracking_providers_list() ) ) {
        foreach ( sk_shipping_status_tracking_providers_list() as $data_key => $data_label ) {
            $providers[ $data_key ] = $data_label['label'];
        }
    }

    return apply_filters( 'sk_get_shipping_tracking_providers_list', $providers );
}

/**
 * SK get shipping tracking default providers list
 *
 *
 * @return array
 */
function sk_get_shipping_tracking_default_providers_list() {
    $providers = [
        'sp-dhl'                       => 'sp-dhl',
        'sp-dpd'                       => 'sp-dpd',
        'sp-fedex'                     => 'sp-fedex',
        'sp-polish-shipping-providers' => 'sp-polish-shipping-providers',
        'sp-ups'                       => 'sp-ups',
        'sp-usps'                      => 'sp-usps',
        'sp-other'                     => 'sp-other',
    ];

    return apply_filters( 'sk_shipping_status_default_providers', $providers );
}

/**
 * SK get shipping tracking default providers list
 *
 *
 * @param string $key_data
 *
 * @return string
 */
function sk_get_shipping_tracking_status_by_key( $key_data ) {
    $status_list = sk_get_option( 'shipping_status_list', 'sk_shipping_status_setting' );

    if ( ! empty( $status_list ) && is_array( $status_list ) ) {
        foreach ( $status_list as $s_status ) {
            if ( isset( $s_status['id'] ) && $s_status['id'] === $key_data ) {
                return $s_status['value'];
            }
        }
    }

    return '';
}

/**
 * SK get shipping tracking provider name by key
 *
 *
 * @param string $key_data
 * @param string $return_type
 * @param string $tracking_number
 *
 * @return string
 */
function sk_get_shipping_tracking_provider_by_key( $key_data, $return_type = 'label', $tracking_number = '' ) {
    if ( empty( $key_data ) ) {
        return '';
    }

    $providers_list = sk_shipping_status_tracking_providers_list();

    if ( ! empty( $providers_list ) && is_array( $providers_list ) && isset( $providers_list[ $key_data ] ) && isset( $providers_list[ $key_data ][ $return_type ] ) ) {
        $provider = $providers_list[ $key_data ][ $return_type ];

        if ( 'url' === $return_type && ! empty( $tracking_number ) ) {
            $provider = str_replace( '{tracking_number}', $tracking_number, $provider );
        }

        return $provider;
    }

    return 'N/A';
}

/**
 * SK get shipping tracking current status by order id
 *
 *
 * @param int $order_id
 * @param int $need_label
 *
 * @return string
 */
function sk_shipping_tracking_status_by_orderid( $order_id, $need_label = 0 ) {
    if ( empty( $order_id ) ) {
        return '';
    }

    $order = wc_get_order( $order_id );

    if ( $order ) {
        $tracking_info = $order->get_meta( '_sk_shipping_status_tracking_info' );
    }

    if ( is_array( $tracking_info ) && isset( $tracking_info['status'] ) ) {
        return $need_label === 0 ? $tracking_info['status'] : sk_get_shipping_tracking_status_by_key( $tracking_info['status'] );
    }
}

/**
 * SK get shipping tracking current provider by oder id
 *
 *
 * @param int $order_id
 *
 * @return string
 */
function sk_shipping_tracking_provider_by_orderid( $order_id ) {
    if ( empty( $order_id ) ) {
        return '';
    }

    $order = wc_get_order( $order_id );

    if ( $order ) {
        $tracking_info = $order->get_meta( '_sk_shipping_status_tracking_info' );
    }

    if ( is_array( $tracking_info ) && isset( $tracking_info['provider'] ) ) {
        return $tracking_info['provider'];
    }
}

/**
 * Get order current shipment status
 *
 *
 * @param int  $order_id
 * @param bool $get_only_status
 *
 * @return string|bool
 */
function sk_get_order_shipment_current_status( $order_id, $get_only_status = false ) {
    if ( empty( $order_id ) ) {
        return '';
    }

    $cache_group = "seller_shipment_tracking_data_{$order_id}";
    $cache_key   = "order_shipment_tracking_status_{$order_id}";
    $get_status  = Cache::get( $cache_key, $cache_group );

    // early return if cached data found
    if ( false !== $get_status ) {
        if ( $get_only_status ) {
            return $get_status;
        }

        return sk_get_order_shipment_status_html( $get_status );
    }

    $shipment_tracking_data = sk_ext()->shipment->get_shipping_tracking_data( $order_id );

    // early return if no shipment tracking data found
    if ( empty( $shipment_tracking_data ) ) {
        $get_status = '--';
        // set cache
        Cache::set( $cache_key, $get_status, $cache_group );
        if ( $get_only_status ) {
            return $get_status;
        }

        return sk_get_order_shipment_status_html( $get_status );
    }

    $order = wc_get_order( $order_id );
    // total item remaining for shipping
    $shipment_remaining_count = 0;
    // order line items total count
    $order_qty_count = 0;
    // total delivered shipping status count
    $delivered_count = isset( $shipment_tracking_data['shipping_status_count']['ss_delivered'] ) ? intval( $shipment_tracking_data['shipping_status_count']['ss_delivered'] ) : 0;
    // no of shipping item without cancel status
    $total_shipments = isset( $shipment_tracking_data['total_except_cancelled'] ) ? intval( $shipment_tracking_data['total_except_cancelled'] ) : 0;

    // count total order items
    $line_item_count = $shipment_tracking_data['line_item_count'];
    foreach ( $order->get_items() as $item_id => $item ) {
        // count the remaining item
        $shipped_item             = isset( $line_item_count[ $item_id ] ) ? intval( $line_item_count[ $item_id ] ) : 0;
        $remaining_item           = intval( $item['qty'] ) - $shipped_item;
        $shipment_remaining_count += $remaining_item;

        // order line item total count
        $order_qty_count += intval( $item['qty'] );
    }

    if ( 0 === $shipment_remaining_count && $delivered_count === $total_shipments ) {
        $get_status = \SK\Core\Shipping\Helper::is_order_fully_shipped( $order ) ? 'received' : 'shipped';
    } elseif ( $shipment_remaining_count < $order_qty_count && $delivered_count > 0 ) {
        $get_status = 'partially';
    } else {
        $get_status = 'not_shipped';
    }

    Cache::set( $cache_key, $get_status, $cache_group );

    if ( $get_only_status ) {
        return $get_status;
    }

    return sk_get_order_shipment_status_html( $get_status );
}

/**
 * Get main order current shipment status
 *
 *
 * @param int $order_id
 *
 * @return string
 */
function sk_get_main_order_shipment_current_status( $order_id ) {
    if ( empty( $order_id ) ) {
        return '';
    }

    $user_id     = sk_get_current_user_id();
    $cache_group = "seller_shipment_tracking_data_{$order_id}";
    $cache_key   = "order_shipment_tracking_status_{$order_id}";
    $get_status  = Cache::get( $cache_key, $cache_group );

    if ( false === $get_status ) {
        $sub_orders = [];

        $shipped       = 0;
        $received      = 0;
        $partially     = 0;
        $others_status = 0;
        $count_total   = 0;

        if ( $sub_orders ) {
            foreach ( $sub_orders as $order ) {
                $get_status = sk_get_order_shipment_current_status( $order->get_id(), true );

                if ( 'received' === $get_status ) {
                    ++$received;
                }

                if ( 'shipped' === $get_status ) {
                    ++$shipped;
                }

                if ( 'partially' === $get_status ) {
                    ++$partially;
                }

                if ( in_array( $get_status, [ 'received', 'shipped', 'partially', 'not_shipped' ], true ) ) {
                    ++$others_status;
                }

                ++$count_total;
            }
        }

        if ( $count_total === $received ) {
            $get_status = 'received';
        } elseif ( $count_total === $shipped ) {
            $get_status = 'shipped';
        } elseif ( $partially > 0 || $shipped > 0 ) {
            $get_status = 'partially';
        } elseif ( $others_status > 0 ) {
            $get_status = 'not_shipped';
        } else {
            $get_status = '--';
        }

        Cache::set( $cache_key, $get_status, $cache_group );
    }

    return sk_get_order_shipment_status_html( $get_status );
}

/**
 * Get order current shipment status html view
 *
 *
 * @param string $get_status
 *
 * @param string
 */
function sk_get_order_shipment_status_html( $get_status ) {
    $shipment_statuses = apply_filters(
        'sk_shipment_statuses', [
            'received'    => apply_filters( 'sk_shipment_status_label_shipped', __( 'Received', 'sk' ) ),
            'shipped'     => apply_filters( 'sk_shipment_status_label_shipped', __( 'Delivered', 'sk' ) ),
            'partially'   => apply_filters( 'sk_shipment_status_label_partially_shipped', __( 'Partially Delivered', 'sk' ) ),
            'not_shipped' => apply_filters( 'sk_shipment_status_label_not_shipped', __( 'Not-Delivered', 'sk' ) ),
        ]
    );

    $shipping_label = $shipment_statuses[ $get_status ] ?? '';
    switch ( $get_status ) {
        case 'received':
        case 'shipped':
            $shipping_label_html = sprintf( '<span class="sk-label sk-label-success">%s</span>', $shipping_label );
            break;
        case 'partially':
            $shipping_label_html = sprintf( '<span class="sk-label sk-label-info">%s</span>', $shipping_label );
            break;
        case 'not_shipped':
            $shipping_label_html = sprintf( '<span class="sk-label sk-label-default">%s</span>', $shipping_label );
            break;
        default:
            $shipping_label_html = apply_filters( 'sk_shipment_status_label_null', '--', $get_status );
            break;
    }

    return apply_filters( 'sk_shipment_statuses_html', $shipping_label_html, $get_status );
}

/**
 * Shipping clear cache values by group name
 *
 *
 * @param int $order_id
 *
 * @return void
 */
function sk_shipment_cache_clear_group( $order_id ) {
    $group               = 'seller_shipment_tracking_data_' . $order_id;
    $tracking_data_key   = 'shipping_tracking_data_' . $order_id;
    $tracking_status_key = 'order_shipment_tracking_status_' . $order_id;

    Cache::delete( $tracking_data_key, $group );
    Cache::delete( $tracking_status_key, $group );
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
