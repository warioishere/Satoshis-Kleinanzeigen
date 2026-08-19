<?php
namespace SK\Modules\ProductAdvertisement;

use WC_Product;
use WP_Error;
use Exception;
use SK\Core\Utilities\OrderUtil;
use Automattic\WooCommerce\Admin\API\Reports\SqlQuery;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class Helper
 *
 *
 */
class Helper {

    /**
     * This method will return true if per product advertisement is enabled from product listing and product edit page.
     *
     *
     * @return bool
     */
    public static function is_per_product_advertisement_enabled() {
        return 'on' === sk_get_option( 'per_product_enabled', 'sk_product_advertisement', 'on' );
    }

    /**
     * This method will return true if advertisement is enabled for vendor subscription pack
     *
     *
     * @return bool
     */
    public static function is_enabled_for_vendor_subscription() {
        return 'on' === sk_get_option( 'vendor_subscription_enabled', 'sk_product_advertisement', 'off' );
    }

    /**
     * This method will return true if advertisement is enabled for provided subscription pack
     *
     *
     * @param int $pack_id
     *
     * @return bool
     */
    public static function is_advertisement_enabled_for_subscription_pack( int $pack_id ): bool {
        $product = wc_get_product( $pack_id );
        if ( ! $product instanceof WC_Product ) {
            return false;
        }

        // Get the advertisement slot count from the product meta
        $slot_count = $product->get_meta( '_sk_advertisement_slot_count', true );

        return ( '' !== $slot_count && null !== $slot_count );
    }

    /**
     * This method will return if admin wants to set purchased advertisement products as featured.
     *
     *
     * @return bool
     */
    public static function is_featured_enabled() {
        return 'on' === sk_get_option( 'featured', 'sk_product_advertisement', 'off' );
    }

    /**
     * This method will return if admin wants to set purchased advertisement products as featured.
     *
     *
     * @return bool
     */
    public static function is_catalog_priority_enabled() {
        return 'on' === sk_get_option( 'catalog_priority', 'sk_product_advertisement', 'on' );
    }

    /**
     * This method will return if admin wants to out of stocks products from advertisements.
     *
     *
     * @return bool
     */
    public static function is_hide_out_of_stock_products_enabled() {
        return 'on' === sk_get_option( 'hide_out_of_stock_items', 'sk_product_advertisement', 'off' );
    }

    /**
     * This method will return advertisement cost for per product
     *
     *
     * @return float 0 for free purchase or a positive float number
     */
    public static function get_advertisement_cost() {
        return floatval( sk_get_option( 'cost', 'sk_product_advertisement', 15 ) );
    }

    /**
     * This method will return total advertisement slot mentioned under admin settings.
     *
     *
     * @return int -1 for unlimited advertisement products or a non-zero positive integer
     */
    public static function get_total_advertisement_slot_count() {
        return intval( sk_get_option( 'total_available_slot', 'sk_product_advertisement', 100 ) );
    }

    /**
     * This method will return advertisement product count for provided subscription pack
     *
     *
     * @param int $pack_id
     *
     * @return int -1 if no limit is set, non-zero positive integer otherwise
     */
    public static function get_subscription_pack_total_advertisement_slot( int $pack_id ): int {
        $product = wc_get_product( $pack_id );
        if ( ! $product instanceof WC_Product ) {
            return 0;
        }

        $slot_count = $product->get_meta( '_sk_advertisement_slot_count', true );

        return ( $slot_count !== '' && $slot_count !== null ) ? (int) $slot_count : 0;
    }

    /**
     * This method will return total number of advertisement slot is available for a vendor by subscription
     *
     * If vendor subscription module is active and if vendor is subscribed to any subscription package,
     * this method will return assigned package's slot count (if any), otherwise, this will return
     * false
     *
     *
     * @param int $vendor_id
     *
     * @return int -1 for unlimited advertisement, 0 for no advertisements, positive integer otherwise. false if no slot is assigned
     */
    public static function get_total_advertisement_slot_count_by_vendor_subscription( $vendor_id ) {
        // check if vendor subscription module is enabled and user is under a subscription
        $subscription = static::check_subscription_status_for_vendor( $vendor_id );

        if ( false === $subscription ) {
            return 0;
        }

        // get subscription product count, if any
        return static::get_subscription_pack_total_advertisement_slot( $subscription->get_id() );
    }

    /**
     * This method will return available/remaining advertisement counts
     *
     *
     * @return int -1 if no restriction is applied, positive integer otherwise
     */
    public static function get_available_advertisement_slot_count() {
        // if no of product count is -1, return from here
        if ( -1 === static::get_total_advertisement_slot_count() ) {
            return -1;
        }

        // get all active advertisement from database
        $manager = new Manager();
        $active_advertisements = $manager->all(
            [
                'status'   => 1,
                'per_page' => -1,
                'return'   => 'count',
            ]
        );

        $available = static::get_total_advertisement_slot_count() - $active_advertisements;

        // for negative available value, return 0, otherwise return $available as it is
        return $available >= 0 ? $available : 0;
    }

    /**
     * This method will return number of available advertisement slot count for a vendor by subscription
     *
     * If vendor subscription is exists and vendor is subscribe to any package, this will return available slot count from
     * package count, otherwise this will return false.
     *
     *
     * @param int $vendor_id
     *
     * @return int Values:
     *               -1 = unlimited advertisement slots
     *                0 = no advertisements allowed
     *               >0 = specific number of slots available
     */
    public static function get_available_advertisement_slot_count_by_vendor_subscription( $vendor_id ) {
        // check if vendor subscription module is enabled and user is under a subscription
        $subscription = static::check_subscription_status_for_vendor( $vendor_id );

        // if no subscription is found, return global advertisement slot count
        if ( false === $subscription ) {
            return 0;
        }

        // get total advertisement slot count
        $subscription_total_available_slot_count = static::get_total_advertisement_slot_count_by_vendor_subscription( $vendor_id );

        // return if slot count is -1 (unlimited) or 0 (no advertisement allowed)
        if ( -1 === $subscription_total_available_slot_count || 0 === $subscription_total_available_slot_count ) {
            return $subscription_total_available_slot_count;
        }

        $manager = new Manager();
        // now calculate available slot for vendor
        $active_advertised_products = $manager->all(
            [
                'vendor_id' => $vendor_id,
                'status'    => 1,
                'per_page'  => -1,
                'return'    => 'count',
            ]
        );

        $available = $subscription_total_available_slot_count - $active_advertised_products;

        // for negative available value, return 0, otherwise return $available as it is
        return $available >= 0 ? $available : 0;
    }

    /**
     * This method will return total number of days a product will be advertised.
     *
     *
     * @return int -1 if advertisement is for unlimited period of time or a non-zero positive integer
     */
    public static function get_expire_after_days() {
        return intval( sk_get_option( 'expire_after_days', 'sk_product_advertisement', 10 ) );
    }

    /**
     * This method will return advertised days for a vendor subscription pack
     *
     * @param $pack_id
     *
     *
     * @return int -1 if no expire days, non-zero positive integer otherwise
     */
    public static function get_subscription_pack_expire_after_days( $pack_id ) {
        return intval( get_post_meta( $pack_id, '_sk_advertisement_validity', true ) );
    }

    /**
     * This method will return advertised product's expire after days for a vendor by subscription
     *
     * If Vendor Subscription module is active and vendor is assigned to any subscription plan, this method will return
     * subscription pack's expire after days, otherwise this method will return false
     *
     *
     * @param int $vendor_id
     *
     * @return int -1 if no expire days, non-zero positive integer otherwise, false if not assigned
     */
    public static function get_expire_after_days_by_vendor_subscription( $vendor_id ) {
        // check if vendor subscription module is enabled and user is under a subscription
        $subscription = static::check_subscription_status_for_vendor( $vendor_id );

        // if no subscription is found, return global advertised expire after days count
        if ( false === $subscription ) {
            return 0;
        }

        // if subscription is found, return subscription pack product count
        return static::get_subscription_pack_expire_after_days( $subscription->get_id() );
    }

    /**
     * Check whether subscription module is enabled or not
     *
     *
     * @return bool
     */
    public static function has_vendor_subscription_module() {
        // don't confused with product_subscription, id for vendor subscription module is product_subscription
        return sk_ext()->module->is_active( 'product_subscription' ) && 'on' === sk_get_option( 'enable_pricing', 'sk_product_subscription' );
    }

    /**
     * This method will check if vendor is under any subscription pack
     *
     *
     * @param int $vendor_id
     *
     * @return bool|\SK\Modules\Subscription\SubscriptionPack
     */
    public static function check_subscription_status_for_vendor( $vendor_id = 0 ) {
        if ( empty( $vendor_id ) ) {
            return false;
        }

        // check if subscription module is enabled and advertisement is active for subscription
        if ( ! static::has_vendor_subscription_module() || ! static::is_enabled_for_vendor_subscription() ) {
            return false;
        }

        // check if user is under any subscription pack
        $subscription = sk()->vendor->get( $vendor_id )->subscription;

        if ( ! $subscription instanceof \SK\Modules\Subscription\SubscriptionPack ) {
            return false;
        }

        // check if subscription is enabled for subscription pack
        if ( ! static::is_advertisement_enabled_for_subscription_pack( $subscription->get_id() ) ) {
            return false;
        }

        return $subscription;
    }

    /**
     * This method will check if cart contain advertisement product
     *
     *
     * @return bool
     */
    public static function has_product_advertisement_in_cart() {
        if ( ! WC()->cart ) {
            return false;
        }

        foreach ( WC()->cart->get_cart() as $item ) {
            if ( isset( $item['sk_product_advertisement'] ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * This method will check if cart contain advertisement product
     *
     *
     * @return bool
     */
    public static function has_product_advertisement_in_order( $order ) {
        // check if we get
        if ( ! $order instanceof \WC_Abstract_Order && is_numeric( $order ) ) {
            // get order object from order_id
            $order = wc_get_order( $order );
        }

        if ( ! $order instanceof \WC_Abstract_Order ) {
            return false;
        }

        foreach ( $order->get_items() as $item ) {
            if ( $item->get_meta( 'sk_advertisement_product_id' ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all order IDs that contain advertisement products.
     *
     * This method traverses all orders and checks if any order item has the
     * 'sk_advertisement_product_id' meta key, following the same logic as
     * has_product_advertisement_in_order() but for all orders at once.
     *
     *
     * @return array Array of order IDs
     */
    public static function get_advertisement_order_ids(): array {
        global $wpdb;

        $subquery       = new SqlQuery();
        $hpos_enabled   = OrderUtil::is_hpos_enabled();
        $items_table    = $wpdb->prefix . 'woocommerce_order_items';
        $itemmeta_table = $wpdb->prefix . 'woocommerce_order_itemmeta';

        // Build SELECT clause.
        $subquery->add_sql_clause( 'select', 'DISTINCT items.order_id' );

        // Build FROM clause.
        $subquery->add_sql_clause( 'from', "{$items_table} AS items" );

        // Build JOIN clauses.
        $subquery->add_sql_clause(
            'join',
            "INNER JOIN {$itemmeta_table} AS itemmeta ON items.order_item_id = itemmeta.order_item_id"
        );

        if ( $hpos_enabled ) {
            $orders_table = $wpdb->prefix . 'wc_orders';
            $subquery->add_sql_clause(
                'join',
                "INNER JOIN {$orders_table} AS orders ON items.order_id = orders.id"
            );
        } else {
            $subquery->add_sql_clause(
                'join',
                "INNER JOIN {$wpdb->posts} AS posts ON items.order_id = posts.ID"
            );
        }

        // Build WHERE clauses.
        $subquery->add_sql_clause(
            'where',
            $wpdb->prepare( 'AND items.order_item_type = %s', 'line_item' )
        );

        $subquery->add_sql_clause(
            'where',
            $wpdb->prepare( 'AND itemmeta.meta_key = %s', 'sk_advertisement_product_id' )
        );

        if ( ! $hpos_enabled ) {
            $subquery->add_sql_clause(
                'where',
                "AND posts.post_type = 'shop_order'"
            );
        }

        // Get the final query and execute.
        $query                   = $subquery->get_query_statement();
        $advertisement_order_ids = $wpdb->get_col( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        /**
         * Filter the list of order IDs that contain advertisement products.
         *
         *
         * @param array $advertisement_order_ids Array of order IDs.
         */
        return apply_filters( 'sk_product_advertisement_order_ids', $advertisement_order_ids );
    }

    /**
     * @param \WC_Abstract_Order $order
     *
     *
     * @return array
     */
    public static function get_advertisement_data_from_order( \WC_Abstract_Order $order ) {
        $data = [];

        foreach ( $order->get_items() as $item ) {
            if ( $item->get_meta( 'sk_advertisement_product_id' ) ) {
                $data['product_id']         = (int) $item->get_meta( 'sk_advertisement_product_id' );
                $data['advertisement_cost'] = (float) $item->get_meta( 'sk_advertisement_cost' );
                $data['expires_after_days'] = (int) $item->get_meta( 'sk_advertisement_expire_after_days' );
                break;
            }
        }

        return $data;
    }

    /**
     * This method will return formatted expire after days text
     *
     *
     * @param int $expire_after_days
     *
     * @return string
     */
    public static function format_expire_after_days_text( $expire_after_days ) {
        if ( in_array( intval( $expire_after_days ), [ -1, 0 ], true ) ) {
            return __( 'Unlimited days', 'sk' );
        }
        // translators: 1) expire after day count
        return sprintf( _n( '%s day', '%s days', $expire_after_days, 'sk' ), number_format_i18n( $expire_after_days ) );
    }

    /**
     * This method will return formatted expire after date as localized string
     *
     *
     * @param int $expires_at
     *
     * @return string
     */
    public static function get_formatted_expire_date( $expires_at ) {
        if ( 0 === intval( $expires_at ) ) {
            return __( 'Unlimited', 'sk' );
        }
        return sk_format_date( $expires_at );
    }

    /**
     * This method will return formatted expire after date as localized string
     *
     *
     * @param int $remaining_slot
     *
     * @return string
     */
    public static function get_formatted_remaining_slot_count( $remaining_slot ) {
        if ( -1 === intval( $remaining_slot ) ) {
            return __( 'Unlimited', 'sk' );
        }
        return $remaining_slot;
    }

    /**
     * This method will return option key for advertisement base product
     *
     *
     * @return string
     */
    public static function get_advertisement_base_product_option_key() {
        return 'sk_advertisement_product_id';
    }

    /**
     * Create advertisement product
     *
     *
     * @return int
     */
    public static function get_advertisement_base_product() {
        // get advertisement product id from option table
        $base_product_id = (int) get_option( static::get_advertisement_base_product_option_key(), 0 );
        $product = wc_get_product( $base_product_id );
        if ( $product ) {
            // temporary adding this code to set sold individually to true, will remove this after some time
            if ( ! $product->is_sold_individually() ) {
                $product->set_sold_individually( true );
                $product->save();
            }
            return $base_product_id;
        }
        return 0;
    }

    /**
     * This method will return vendor id if called from single store page
     *
     *
     * @return bool|int
     */
    public static function get_vendor_from_single_store_page() {
        //todo: move this function to sk lite
        $custom_store_url = sk_get_option( 'custom_store_url', 'sk_general', 'store' );
        $store_name       = get_query_var( $custom_store_url );

        if ( ! empty( $store_name ) ) {
            $store_user = get_user_by( 'slug', $store_name );

            // no user found
            if ( ! $store_user ) {
                return false;
            }

            // Bell out for Vendor Stuff extensions
            if ( ! is_super_admin( $store_user->ID ) && user_can( $store_user->ID, 'vendor_staff' ) ) {
                return false;
            }

            // check if the user is seller
            if ( ! sk_is_user_seller( $store_user->ID ) ) {
                return false;
            }

            return $store_user->ID;
        }

        return false;
    }

    /**
     * Mark product as featured
     *
     *
     * @param int|\WC_Product $product
     * @param bool $featured
     *
     * @return void
     */
    public static function make_product_featured( $product, $featured = true ) {
        if ( ! $product instanceof \WC_Product && is_numeric( $product ) ) {
            $product = wc_get_product( $product );
        }

        if ( ! $product ) {
            return;
        }

        $product->set_featured( $featured );
        $product->save();
    }

    /**
     * Check if product has been advertised
     *
     *
     * @param int $product_id
     *
     * @return bool
     */
    public static function is_product_advertised( $product_id ) {
        if ( ! $product_id ) {
            return false;
        }

        $manager = new Manager();
        $advertised_products = $manager->all(
            [
                'product_id' => $product_id,
                'status'     => 1,
                'per_page'   => -1,
                'return'     => 'count',
            ]
        );

        return $advertised_products > 0;
    }

    /**
     * Get advertisement data by product
     *
     *
     * @param int|null $product
     *
     * @return array
     */
    public static function get_advertisement_data_by_product( $product ) {
        // get product object
        if ( ! $product instanceof \WC_Product ) {
            $product = wc_get_product( $product );
        }

        if ( empty( $product ) ) {
            return [];
        }

        $vendor_id = sk_get_vendor_by_product( $product, true );

        if ( ! $vendor_id ) {
            return [];
        }

        $already_advertised              = false;
        $can_advertise_for_free          = false;
        $expire_date                     = '';
        $subscription_status             = static::check_subscription_status_for_vendor( $vendor_id );
        $global_remaining_slot           = static::get_available_advertisement_slot_count();
        $remaining_slot                  = $global_remaining_slot;
        $subscription_remaining_slot     = $subscription_status ? static::get_available_advertisement_slot_count_by_vendor_subscription( $vendor_id ) : 0;
        $listing_price                   = static::get_advertisement_cost();
        $expires_after_days              = static::get_expire_after_days();
        $subscription_expires_after_days = $subscription_status ? static::get_expire_after_days_by_vendor_subscription( $vendor_id ) : 0;

        // check if product already advertised
        $manager = new Manager();
        $data = $manager->all(
            [
                'product_id' => $product->get_id(),
                'status'     => 1,
                'per_page'   => 1,
                'return'     => 'all',
            ]
        );
        if ( ! empty( $data ) && ! is_wp_error( $data ) ) {
            $already_advertised = true;
            $expire_date = static::get_formatted_expire_date( $data['expires_at'] );
        }

        /**
         * 1. per product purchase is enabled, with or without subscription on top
         * 2. only subscription is enabled
         * 3. neither is enabled, global defaults apply
         */
        if ( static::is_per_product_advertisement_enabled() ) {
            // an advertisement cost of 0 means vendors advertise at no cost
            if ( empty( $listing_price ) ) {
                $can_advertise_for_free = true;
            }

            // we will give priority to subscription slot and expire days
            if ( false !== $subscription_status && abs( $subscription_remaining_slot ) > 0 ) {
                $expires_after_days     = $subscription_expires_after_days;
                $remaining_slot         = $subscription_remaining_slot;
                $can_advertise_for_free = true;
            }
        } elseif ( static::is_enabled_for_vendor_subscription() ) {
            // check if user can advertise this product for free
            if ( false !== $subscription_status && abs( $subscription_remaining_slot ) > 0 ) {
                $can_advertise_for_free = true;
                $expires_after_days     = $subscription_expires_after_days;
                $remaining_slot         = $subscription_remaining_slot;
            } else {
                $remaining_slot = 0;
            }
        }

        //todo: return this as object
        return [
            'vendor_id'                       => $vendor_id,
            'product_id'                      => $product->get_id(),
            'subscription_status'             => $subscription_status,
            'remaining_slot'                  => $remaining_slot,
            'global_remaining_slot'           => $global_remaining_slot,
            'subscription_remaining_slot'     => $subscription_remaining_slot,
            'listing_price'                   => $listing_price,
            'expires_after_days'              => $expires_after_days,
            'subscription_expires_after_days' => $subscription_expires_after_days,
            'already_advertised'              => $already_advertised,
            'can_advertise_for_free'          => $can_advertise_for_free,
            'expire_date'                     => $expire_date,
            'post_status'                     => $product->get_status(),
        ];
    }

    /**
     * Get advertisement data and validate
     *
     *
     * @param int $product_id
     * @param int $vendor_id
     *
     * @return array|WP_Error
     */
    public static function get_advertisement_data_for_insert( $product_id, $vendor_id ) {
        $advertisement_data = static::get_advertisement_data_by_product( $product_id );

        if ( empty( $advertisement_data ) ) {
            return new WP_Error( 'invalid_product', __( 'No product found with given product ID. Please check your input.', 'sk' ) );
        }

        // check if product status is publish
        if ( 'publish' !== $advertisement_data['post_status'] ) {
            return new WP_Error( 'invalid_product', __( 'You can not advertise this product. Products need to be published before you can advertise.', 'sk' ) );
        }

        // check if product is belong to given vendor id
        if ( ! $advertisement_data['vendor_id'] || intval( $vendor_id ) !== $advertisement_data['vendor_id'] ) {
            return new WP_Error( 'invalid_vendor', __( 'Product id does not belong to given vendor. Please check your input', 'sk' ) );
        }

        // check advertisement already exists in database, this is to prevent duplicate entry
        if ( $advertisement_data['already_advertised'] ) {
            return new WP_Error( 'invalid_product', __( 'Advertisement for this product is already going on. Please select another product.', 'sk' ) );
        }

        // check we've got slot left for advertisement
        if ( empty( $advertisement_data['remaining_slot'] ) ) {
            return new WP_Error( 'empty_slot', __( 'There are no advertisement slots available at this moment.', 'sk' ) );
        }

        return $advertisement_data;
    }

    /**
     * This method will create advertisement base product
     *
     *
     * @return void
     */
    public static function create_advertisement_base_product() {
        // ! Check if WooCommerce is active, we need to check this because sk can be enabled without wooCommerce
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // get advertisement product id from option table
        $product_id = (int) get_option( static::get_advertisement_base_product_option_key(), false );
        if ( $product_id ) {
            $product = wc_get_product( $product_id );
            if ( $product ) {
                return;
            }
        }

        // create a new post
        $post = [
            'post_content' => 'This is SK advertisement payment product, do not delete.',
            'post_status'  => 'publish',
            'post_title'   => 'Product Advertisement Payment',
            'post_parent'  => '',
            'post_type'    => 'product',
        ];

        /* Create post */
        $post_id = wp_insert_post( $post );

        if ( is_wp_error( $post_id ) ) {
            return;
        }

        // try catch block used here just to get rid of phpcs errors
        try {
            // convert post into product
            $product = new \WC_Product_Simple();
            $product->set_id( $post_id );
            $product->set_catalog_visibility( 'hidden' );
            $product->set_virtual( true );
            $product->set_price( 0 );
            $product->set_regular_price( 0 );
            $product->set_sale_price( 0 );
            $product->set_manage_stock( false );
            $product->set_sold_individually( true );
            $product->save();

            update_option( static::get_advertisement_base_product_option_key(), $product->get_id() );
        } catch ( Exception $exception ) {
            return;
        }
    }

    /**
     * Purchase product advertisement.
     *
     *
     * @param int $product_id
     *
     * @return array|Exception
     *
     * @throws Exception
     */
    public static function purchase_advertisement( $product_id ) {
        try {
            // check permission, don't let vendor staff view this section
            if ( ! current_user_can( 'skdar' ) ) {
                throw new Exception( __( 'You do not have permission to use this action.', 'sk' ), 400 );
            }

            // check if purchasing advertisement settings is enabled
            if ( ! static::is_per_product_advertisement_enabled() && ! static::is_enabled_for_vendor_subscription() ) {
                throw new Exception( __( 'Purchasing advertisement is restricted by admin.', 'sk' ), 403 );
            }

            // get advertisement data
            $advertisement_data = static::get_advertisement_data_for_insert( $product_id, sk_get_current_user_id() );

            if ( is_wp_error( $advertisement_data ) ) {
                throw new Exception( $advertisement_data->get_error_message(), 400 );
            }

            // validate user can advertise product
            if ( false !== $advertisement_data['can_advertise_for_free'] ) {
                // prepare item for database
                $args = [
                    'product_id'         => $advertisement_data['product_id'],
                    'created_via'        => false !== $advertisement_data['subscription_status'] && ! empty( $advertisement_data['subscription_remaining_slot'] ) ? 'subscription' : 'free', // possible values are order, admin, subscription, free
                    'price'              => 0,
                    'expires_after_days' => $advertisement_data['expires_after_days'],
                    'status'             => 1, // 1 for active, 2 for inactive
                ];

                $manager  = new Manager();
                $inserted = $manager->insert( $args );

                if ( is_wp_error( $inserted ) ) {
                    throw new Exception( $inserted->get_error_message(), 400 );
                }

                return [
                    'message'       => __( 'Product has been successfully advertised.', 'sk' ),
                    'free_purchase' => true,
                ];
            }

            // Add advertisement product to cart
            $advertisement_product_id = static::get_advertisement_base_product();
            if ( ! is_numeric( $advertisement_product_id ) ) {
                throw new Exception( __( 'Invalid base advertisement product id. Please contact with site admin.', 'sk' ), 400 );
            }

            $advertisement_product = wc_get_product( $advertisement_product_id );
            if ( ! $advertisement_product ) {
                throw new Exception( __( 'Invalid base advertisement product found. Please contact with site admin.', 'sk' ), 400 );
            }

            if ( $advertisement_product->get_status() !== 'publish' ) {
                throw new Exception( __( 'Base advertisement product status is not published. Please contact with site admin.', 'sk' ), 400 );
            }

            /*
             * It is possible for the cart to be unavailable in some cases,
             * specially while requesting API from third party agent like PostMan.
             * To avoid any inconsistency, we need to load the cart manually if not exists.
             */
            if ( ! WC()->cart ) {
                wc_load_cart();
            }

            // Add  product to cart
            WC()->cart->empty_cart();
            $cart_item_data = [
                'sk_product_advertisement'           => true,
                'sk_advertisement_product_id'        => $product_id,
                'sk_advertisement_cost'              => $advertisement_data['listing_price'],
                'sk_advertisement_expire_after_days' => $advertisement_data['expires_after_days'],
            ];

            $added = WC()->cart->add_to_cart( $advertisement_product_id, 1, '', '', $cart_item_data ); // phpcs:ignore

            if ( $added ) {
                return [
                    'message'       => __( 'Product has been added to your cart.', 'sk' ),
                    'free_purchase' => false,
                ];
            }

            throw new Exception( __( 'Something went wrong.', 'sk' ), 400 );
        } catch ( Exception $e ) {
            return new WP_Error( 'sk-error-purchase-product-advertisement', $e->getMessage(), [ 'status' => $e->getCode() ] );
        }
    }
}
