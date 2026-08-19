<?php

namespace SK\Modules\Subscription;

use SK\Modules\Subscription\SubscriptionPack;
use SK\Core\Product\ProductCache;
use SK\Core\Traits\Singleton;
use SK\Modules\Subscription\HelperChangerProductStatus;

/**
 * DPS Helper Class
 */
class Helper {

    use Singleton;

    /**
     * Get a sellers remaining product count
     *
     * @param int $vendor_id
     *
     * @return int|bool integer number (could be zero), boolean true if module is deactivated or vendor can publish unlimited product
     */
    public static function get_vendor_remaining_products( $vendor_id ) {
        // vendor subscription module is deactivated, so return true
        if ( ! self::is_subscription_module_enabled() ) {
            return true;
        }

        $vendor = sk()->vendor->get( $vendor_id )->subscription;

        if ( ! $vendor ) {
            return 0;
        }

        $remaining_products = $vendor->get_remaining_products();

        // check if venddor can upload unlimited products
        if ( '-1' === $remaining_products ) {
            return true;
        }

        return $remaining_products;
    }

    /**
     * Get subscribed vendor count
     *
     *
     * @return int The count of subscribed vendors
     */
    public static function get_subscribed_vendor_count(): int {
        global $wpdb;

        // Check if a subscription module is enabled
        $enable_option = get_option( 'sk_product_subscription', [ 'enable_pricing' => 'off' ] );
        if ( ! isset( $enable_option['enable_pricing'] ) || $enable_option['enable_pricing'] !== 'on' ) {
            return 0;
        }

        // Get subscribed vendors count.
        $subscribed_vendors = $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id)
            FROM {$wpdb->usermeta}
            WHERE meta_key = 'product_package_id'
            AND meta_value != ''"
        );

        return (int) ( $subscribed_vendors ?? 0 );
    }

    /**
     * Work out how the given pack relates to the vendor's current subscription.
     *
     * Returns one of:
     *   'other'     the pack is not the one the vendor currently holds
     *   'unlimited' it is their pack and it never expires
     *   'active'    it is their pack and it is still within its validity
     *   'expired'   it is their pack but the validity has passed
     *
     *
     * @param int $pack_id
     * @param int $vendor_id Defaults to the current vendor.
     *
     * @return string
     */
    public static function get_pack_state( $pack_id, $vendor_id = 0 ) {
        // sk_get_current_user_id resolves to the vendor when a staff account is logged in
        $vendor_id = $vendor_id ? absint( $vendor_id ) : sk_get_current_user_id();

        if ( ! $vendor_id ) {
            return 'other';
        }

        if ( (int) get_user_meta( $vendor_id, 'product_package_id', true ) !== (int) $pack_id ) {
            return 'other';
        }

        $pack_end_date = self::get_pack_end_date( $vendor_id );

        if ( empty( $pack_end_date ) ) {
            return 'other';
        }

        if ( 'unlimited' === $pack_end_date ) {
            return 'unlimited';
        }

        $current_date = sk_current_datetime();

        try {
            $validation_date = $current_date->modify( $pack_end_date );
        } catch ( \Exception $e ) {
            // an unreadable date must not look like an expired pack
            self::log( 'Pack state check: unreadable pack end date for user #' . $vendor_id . ': ' . $e->getMessage() );
            return 'active';
        }

        if ( ! $validation_date ) {
            return 'active';
        }

        return $current_date > $validation_date ? 'expired' : 'active';
    }

    /**
     * Check if its vendor subscribed pack
     *
     * @param integer $product_id
     *
     * @return boolean
     */
    public static function is_vendor_subscribed_pack( $product_id ) {
        return in_array( self::get_pack_state( $product_id ), [ 'unlimited', 'active' ], true );
    }

    /**
     * Check package renew for seller
     *
     * @param integer $product_id
     *
     * @return boolean
     */
    public static function pack_renew_seller( $product_id ) {
        return 'expired' === self::get_pack_state( $product_id );
    }




    /**
     * Get a list of options of all the product types
     *
     * @return strings
     */
    public static function get_product_types_options() {
        $selected      = sk()->subscription->get( get_the_ID() )->get_allowed_product_types();
        $product_types = sk_get_product_types();
        $output        = '';

        if ( ! $product_types ) {
            return;
        }

        foreach ( $product_types as $value => $label ) {
            $output .= '<option value="' . esc_attr( $value ) . '" ';
            $output .= in_array( $value, $selected ) ? ' selected="selected"' : '';
            $output .= '>' . esc_html( $label ) . '</option>';
        }

        echo $output;
    }


    /**
     * Get vendor subscription pack id
     *
     * @return int|null on failure
     */
    public static function get_subscription_pack_id() {
        $user_id = sk_get_current_user_id();

        if ( ! $user_id || ! sk_is_user_seller( $user_id ) ) {
            return null;
        }

        $subscription_pack_id = get_user_meta( $user_id, 'product_package_id', true );

        if ( ! $subscription_pack_id ) {
            return null;
        }

        return $subscription_pack_id;
    }

    /**
     * Is gallary image upload restricted
     *
     * @return boolean
     */
    public static function is_gallery_image_upload_restricted() {
        return get_post_meta( self::get_subscription_pack_id(), '_enable_gallery_restriction', true );
    }

    /**
     * Get allowed product types of a vendor
     *
     * @return array|empty array on failure
     */
    public static function get_vendor_allowed_product_types() {
        $types  = [];
        $vendor = sk()->vendor->get( sk_get_current_user_id() )->subscription;

        if ( $vendor ) {
            $types = $vendor->get_allowed_product_types();
        }

        return $types ? $types : [];
    }

    /**
     * Get allowed product cateogories of a vendor
     *
     * @return array|empty array on failure
     */
    public static function get_vendor_allowed_product_categories() {
        $categories = [];

        $vendor = sk()->vendor->get( sk_get_current_user_id() )->subscription;

        if ( $vendor ) {
            $categories = $vendor->get_allowed_product_categories();
        }

        return $categories;
    }







    /**
     * Is subscription module is enabled
     *
     * @return boolean
     */
    public static function is_subscription_module_enabled() {
        $is_enabled = sk_get_option( 'enable_pricing', 'sk_product_subscription' );

        return 'on' === $is_enabled ? true : false;
    }

    /**
     * Is vendor subscription is enabled
     *
     *
     * @return bool
     */
    public static function is_vendor_subscription_enabled() {
        return 'on' === sk_get_option( 'enable_pricing', 'sk_product_subscription' );
    }

    /**
     * Is subscription is enalbed on registration
     *
     * @return boolean
     */
    public static function is_subscription_enabled_on_registration() {
        $is_enabled = sk_get_option( 'enable_subscription_pack_in_reg', 'sk_product_subscription' );

        return 'on' === $is_enabled ? true : false;
    }


    /**
     * Check is product is subscription or not
     *
     * @param integer $product_id
     *
     * @return boolean
     */
    public static function is_subscription_product( $product_id ) {
        $product = wc_get_product( $product_id );

        if ( $product && 'product_pack' === $product->get_type() ) {
            return true;
        }

        return false;
    }


    /**
     * Checks the cart to see if it contains a subscription product
     *
     * @return bool
     */
    public static function cart_contains_subscription() {
        $contains_subscription = false;

        if ( ! empty( WC()->cart->cart_contents ) ) {
            foreach ( WC()->cart->cart_contents as $cart_item ) {
                if ( self::is_subscription_product( $cart_item['product_id'] ) ) {
                    $contains_subscription = true;
                    break;
                }
            }
        }

        return $contains_subscription;
    }


    /**
     * Get subscription product from an order
     *
     *
     * @param \WC_Order $order
     *
     * @return \WC_Product|bool|null
     */
    public static function get_vendor_subscription_product_by_order( $order ) {
        if ( ! is_a( $order, 'WC_Abstract_Order' ) ) {
            $order = wc_get_order( $order );
        }

        if ( ! $order ) {
            return false;
        }

        foreach ( $order->get_items( 'line_item' ) as $item ) {
            $product = $item->get_product();

            if ( $product && 'product_pack' === $product->get_type() ) {
                return $product;
            }
        }

        return false;
    }

    /**
     * Check if the order is a subscription order
     *
     *
     * @param \WC_Order|int $order
     *
     * @return bool
     **/
    public static function is_vendor_subscription_order( $order ) {
        if ( ! is_a( $order, 'WC_Abstract_Order' ) ) {
            $order = wc_get_order( $order );
        }

        if ( ! $order ) {
            return false;
        }

        // check if  meta exists
        /**
         */
        if ( 'yes' === $order->get_meta( '_sk_vendor_subscription_order' ) || $order->meta_exists( '_pack_validity' ) ) {
            return true;
        }

        // check from order items
        $product = static::get_vendor_subscription_product_by_order( $order );

        return $product ? true : false;
    }

    /**
     * Removes all subscription products from the shopping cart.
     *
     * @return void
     */
    public static function remove_subscriptions_from_cart() {
        foreach ( WC()->cart->cart_contents as $cart_item_key => $cart_item ) {
            if ( self::is_subscription_product( $cart_item['product_id'] ) ) {
                WC()->cart->set_quantity( $cart_item_key, 0 );
            }
        }
    }

    /**
     * Helper function for loggin
     *
     * @param string $message
     */
    public static function log( $message ) {
        $message = sprintf( '[%s] %s: %s', date( 'd.m.Y h:i:s' ), __( 'SK Vendor Subscription: ', 'sk' ), $message );
        sk_log( $message );
    }

    /**
     * Delete Subscription pack
     *
     * @param integer $customer_id
     *
     * @return void
     */
    public static function delete_subscription_pack( $customer_id, $order_id ) {
        if ( absint( $order_id ) !== absint( get_user_meta( $customer_id, 'product_order_id', true ) ) ) {
            return;
        }

        /**
         */
        do_action( 'sk_subscription_cancelled', $customer_id, get_user_meta( $customer_id, 'product_package_id', true ), $order_id );

        delete_user_meta( $customer_id, 'product_order_id' );
        delete_user_meta( $customer_id, 'product_pack_enddate' );
        delete_user_meta( $customer_id, 'product_package_id' );
        delete_user_meta( $customer_id, 'product_no_with_pack' );
        delete_user_meta( $customer_id, 'product_pack_startdate' );
        delete_user_meta( $customer_id, 'can_post_product' );
        delete_user_meta( $customer_id, 'sk_has_active_cancelled_subscrption' );
        delete_user_meta( $customer_id, 'sk_vendor_subscription_cancel_email' );
        delete_user_meta( $customer_id, '_paypal_subscriber_ID' );

        // make product status draft after subscriptions is got cancelled.
        if ( self::check_vendor_has_existing_product( $customer_id ) ) {
            self::apply_product_status_after_end( $customer_id );
        }

        /**
         * Fires once every subscription meta of a vendor has been removed.
         *
         * Unlike sk_subscription_cancelled this runs after the cleanup, so a
         * listener can hand the vendor a replacement pack right away instead of
         * leaving them without one until their next visit.
         *
         * @param int $customer_id
         * @param int $order_id
         */
        do_action( 'sk_subscription_pack_deleted', $customer_id, $order_id );
    }

    /**
     * Check if a vendor has existing product
     *
     * @param int $user_id
     *
     * @return boolean
     */
    public static function check_vendor_has_existing_product( $user_id ) {
        self::log( 'Product exist check: As the package has expired of user #' . $user_id . ' we are checking if he has any product' );

        $product = wc_get_products(
            [
                'author' => $user_id,
                'status' => 'any',
                'limit'  => 1,
            ]
        );

        return ! empty( $product );
    }

    /**
     * Make product status publish
     *
     *
     * @param int $vendor_id
     *
     * @return void
     */
    public static function make_product_publish( $vendor_id ) {
        $product_status_changer = self::get_product_status_changer_bg_class();
        if ( null === $product_status_changer ) {
            return;
        }

        $product_status_changer->reset();
        $product_status_changer->set_vendor_id( $vendor_id );
        $product_status_changer->add_to_queue( 'revert', 'publish' );
    }

    /**
     * Apply the configured product status for vendors whose pack has ended.
     *
     * Despite what the old name (make_product_draft) suggested this does not
     * necessarily draft anything. It queues a background job that moves the
     * vendor's published and pending products to whatever the admin configured
     * under "product_status_after_end". On a site that keeps listings online
     * after expiry that setting is 'publish' and the job is a no-op. Drafts are
     * never touched, the queue only selects publish and pending products.
     *
     *
     * @param int $vendor_id
     *
     * @return void
     */
    public static function apply_product_status_after_end( $vendor_id ) {
        $product_status_changer = self::get_product_status_changer_bg_class();
        if ( null === $product_status_changer ) {
            return;
        }

        $status = sk_get_option( 'product_status_after_end', 'sk_product_subscription', 'draft' );
        $product_status_changer->reset();
        $product_status_changer->set_vendor_id( $vendor_id );
        $product_status_changer->add_to_queue( 'change_status', $status );
    }

    /**
     * Alert before 2 days end of subscription
     *
     * @return boolean
     */
    public static function alert_before_two_days( $user_id ) {
        // check if email already sent to client
        if ( 'yes' === get_user_meta( $user_id, 'sk_vendor_subscription_cancel_email', true ) ) {
            return false;
        }

        // if product pack end date is unlimited return false
        $pack_end_date_str = self::get_pack_end_date( $user_id );
        if ( 'unlimited' === $pack_end_date_str ) {
            return false;
        }

        $alert_days = (int) sk_get_option( 'no_of_days_before_mail', 'sk_product_subscription' );
        if ( $alert_days === 0 ) {
            $alert_days = 2;
        }

        if ( empty( $pack_end_date_str ) || ! strtotime( $pack_end_date_str ) ) {
            return false;
        }

        $current_date = sk_current_datetime();

        try {
            $alert_date = sk_current_datetime()->modify( $pack_end_date_str )->modify( "- $alert_days days" );
        } catch ( \Exception $e ) {
            self::log( 'Subscription alert check: could not parse pack end date for user #' . $user_id . ': ' . $e->getMessage() );
            return false;
        }

        if ( ! $alert_date ) {
            return false;
        }

        $current_date = $current_date->format( 'Y-m-d' );
        $alert_date = $alert_date->format( 'Y-m-d' );

        if ( $current_date >= $alert_date ) {
            return true;
        }
        return false;
    }

    /**
     * Get pack end date
     *
     * @return string
     */
    public static function get_pack_end_date( $vendor_id ) {
        return get_user_meta( $vendor_id, 'product_pack_enddate', true );
    }

    /**
     * Update can_post_product flag on subscripton expire
     *
     * @return boolean
     */
    public static function maybe_cancel_subscription( $vendor_id ) {
        $pack_end_date = self::get_pack_end_date( $vendor_id );
	    if ( 'unlimited' === $pack_end_date ) {
		    return false;
	    }

        /*
         * An unreadable end date must not cancel the plan. Cancelling deletes every
         * subscription meta and drafts the vendor's products, so an odd value would
         * silently wipe a paying vendor. Log it and leave the plan alone instead.
         */
        if ( empty( $pack_end_date ) || ! strtotime( $pack_end_date ) ) {
            self::log( 'Subscription validity check: unreadable pack end date for user #' . $vendor_id . ' (' . wp_json_encode( $pack_end_date ) . '), leaving the subscription untouched.' );
            return false;
        }

        $current_date = sk_current_datetime();

        try {
            $validation_date = $current_date->modify( $pack_end_date );
        } catch ( \Exception $e ) {
            self::log( 'Subscription validity check: could not parse pack end date for user #' . $vendor_id . ': ' . $e->getMessage() );
            return false;
        }

        if ( ! $validation_date ) {
            return false;
        }

        if ( $current_date > $validation_date ) {
            self::log( 'Subscription validity check ( ' . $current_date->format( 'Y-m-d' ) . ' ): checking subscription pack validity of user #' . $vendor_id . '. This users subscription pack will expire on ' . $validation_date->format( 'Y-m-d' ) );

            return true;
        }

        return false;
    }



    /**
     * Check wheter vendor is subscribed or not
     *
     *
     * @param int $vendor_id
     *
     * @return boolean
     */
    public static function vendor_has_subscription( $vendor_id ) {
        return get_user_meta( $vendor_id, 'product_package_id', true );
    }

    /**
     * Check wheter vendor can publish unlimited products or not
     *
     *
     * @param int $vendor_id
     *
     * @return boolean
     */
    public static function vendor_can_publish_unlimited_products( $vendor_id ) {
        return true === self::get_vendor_remaining_products( $vendor_id );
    }

    /**
     * Create New Order From Parent Order
     *
     *
     * @param \WC_Order  $parent_order The parent order to create a renewal order from.
     * @param null|float $order_total Order total for the renewal order. If null, the total will be copied from the parent order.
     *
     * @return \WC_Order|\WP_Error The renewal order object on success, or a WP_Error object on failure.
     */
    public static function create_renewal_order( $parent_order, $order_total = null ) {
        if ( ! is_a( $parent_order, 'WC_Abstract_Order' ) ) {
            $parent_order = wc_get_order( $parent_order );
        }

        // Early return if customer info is not found
        if ( ! $parent_order || ! $parent_order->get_customer_id() ) {
            return new \WP_Error( 'invalid_order', __( 'Invalid order or customer information not found.', 'sk' ) );
        }

        global $wpdb;

        try {
            $wpdb->query( 'START TRANSACTION' );

            $new_order = wc_create_order(
                [
                    'customer_id'   => $parent_order->get_customer_id(),
                    'customer_note' => $parent_order->get_customer_note(),
                    'created_via'   => 'vendor_subscription',
                    'parent'        => $parent_order->get_id(),
                ]
            );

            foreach ( $parent_order->get_items( [ 'line_item', 'fee' ] ) as $item ) {
                $item_type = $item->get_type();

                // Create order item on the renewal order
                $new_item_id = wc_add_order_item(
                    $new_order->get_id(), [
						'order_item_name' => $item->get_name(),
						'order_item_type' => $item_type,
					]
                );

                $new_item = $new_order->get_item( $new_item_id );

                // Clone the item's data
                $item_data = $item->get_data();

                unset( $item_data['id'] );
                unset( $item_data['order_id'] );

                $new_item->set_props( $item_data );

                $excluded_meta_keys = [
                    '_reduced_stock',
                    '_restock_refunded_items',
                ];

                /**
                 * Filter the meta keys that should be excluded when cloning order items for subscription renewals.
                 *
                 * This filter allows you to specify which meta keys should not be copied from the parent order
                 * to the renewal order when creating a subscription renewal. Use this to prevent copying
                 * sensitive or temporary data that shouldn't persist in renewal orders.
                 *
                 *
                 * @param array $excluded_meta_keys An array of meta keys to exclude from cloning.
                 *                                  Default: ['_reduced_stock', '_restock_refunded_items']
                 *
                 * @param \WC_Order_Item $item       The original order item being cloned.
                 * @param \WC_Order      $new_order  The new renewal order being created.
                 * @param \WC_Order      $old_order  The original parent order.
                 *
                 * @return array Modified array of meta keys to exclude from cloning.
                 */
                $meta_to_exclude = apply_filters( 'sk_subscription_renewal_exclude_meta', $excluded_meta_keys, $item, $new_order, $parent_order );

                foreach ( $item->get_meta_data() as $meta ) {
                    if ( ! in_array( $meta->key, $meta_to_exclude, true ) ) {
                        $new_item->add_meta_data( $meta->key, $meta->value );
                    }
                }

                $new_item->save();
            }

            // Recalculate totals
            $new_order->calculate_totals();

            // set billing address
            $new_order->set_billing_address_1( $parent_order->get_billing_address_1() );
            $new_order->set_billing_address_2( $parent_order->get_billing_address_2() );
            $new_order->set_billing_city( $parent_order->get_billing_city() );
            $new_order->set_billing_state( $parent_order->get_billing_state() );
            $new_order->set_billing_postcode( $parent_order->get_billing_postcode() );
            $new_order->set_billing_country( $parent_order->get_billing_country() );
            $new_order->set_billing_phone( $parent_order->get_billing_phone() );
            $new_order->set_billing_email( $parent_order->get_billing_email() );
            $new_order->set_billing_company( $parent_order->get_billing_company() );
            $new_order->set_billing_first_name( $parent_order->get_billing_first_name() );
            $new_order->set_billing_last_name( $parent_order->get_billing_last_name() );
            $new_order->set_customer_id( $parent_order->get_customer_id() );
            $new_order->set_customer_ip_address( $parent_order->get_customer_ip_address() );
            $new_order->set_customer_user_agent( $parent_order->get_customer_user_agent() );

            // copy payment gateway data
            $new_order->set_currency( $parent_order->get_currency() );
            $new_order->set_payment_method( $parent_order->get_payment_method() );
            $new_order->set_payment_method_title( $parent_order->get_payment_method_title() );

            // set order total
            if ( null !== $order_total ) {
                $new_order->set_total( $order_total );
            } else {
                $new_order->set_total( $parent_order->get_total( 'edit' ) );
            }

            // store vendor id
            $new_order->update_meta_data( '_sk_vendor_id', $parent_order->get_meta( '_sk_vendor_id' ) );
            $new_order->save();

            $new_order->save();
            // If we got here, the subscription was created without problems
            $wpdb->query( 'COMMIT' );

            return $new_order;
        } catch ( \Exception $e ) {
            // There was an error adding the subscription
            $wpdb->query( 'ROLLBACK' );

            return new \WP_Error( 'new-order-error', $e->getMessage() );
        }
    }

    /**
     * Inserts a new key/value after the key in the array.
     *
     * @param array  $needle    The array key to insert the element after
     * @param array  $haystack  An array to insert the element into
     * @param string $new_key   The key to insert
     * @param string $new_value An value to insert
     *
     * @return array new array if the $needle key exists, otherwise an unmodified $haystack
     */
    public static function array_insert_after( $needle, $haystack, $new_key, $new_value ) {
        if ( array_key_exists( $needle, $haystack ) ) {
            $new_array = [];

            foreach ( $haystack as $key => $value ) {
                $new_array[ $key ] = $value;

                if ( $key === $needle ) {
                    $new_array[ $new_key ] = $new_value;
                }
            }

            return $new_array;
        }

        return $haystack;
    }

    /**
     * Generates post edit link
     *
     *
     * @param integer $post_id
     *
     * @return string
     */
    public static function get_edit_post_link( $post_id = null ) {
        $post = get_post( $post_id );
        $link = '';

        if ( ! $post ) {
            return $link;
        }

        $post_type_object = get_post_type_object( $post->post_type );
        if ( ! $post_type_object ) {
            return $link;
        }

        if ( $post_type_object->_edit_link ) {
            $link = admin_url( sprintf( $post_type_object->_edit_link . '&action=edit', $post->ID ) );
        }

        return $link;
    }



    /**
     * Get subscription order by user_id
     *
     * @param $user_id
     *
     * @return false|\WC_Order|\WC_Order_Refund
     */
    public static function get_subscription_order( $user_id ) {
        $order_id = get_user_meta( $user_id, 'product_order_id', true );

        return wc_get_order( $order_id );
    }

    /**
     * Check if subscription packs are available
     *
     *
     * @return bool
     */
    public static function is_subscription_pack_available() {
        /**
         * @var $subscription_packs \WP_Query
         */
        $subscription_packs = sk()->subscription->all();

        return $subscription_packs->have_posts();
    }

    /**
     * Filter arguments for product list filtering.
     *
     *
     * @param array  $args
     * @param string $filter
     *
     * @return array
     */
    public static function filter_products_by_filter_by_other_helper( $args, $filter ) {
        if ( 'best_selling' === $filter ) {
            $args['orderby']  = 'meta_value_num';
            $args['meta_key'] = 'total_sales';

            self::set_default_tax_query( $args );
        }

        if ( 'top_rated' === $filter ) {
            self::set_default_tax_query( $args );

            add_filter( 'posts_clauses', [ 'WC_Shortcodes', 'order_by_rating_post_clauses' ] );
        }

        if ( 'featured' === $filter ) {
            self::set_default_tax_query( $args );

            $product_visibility_term_ids = wc_get_product_visibility_term_ids();
            $args['tax_query'][]         = [
                'taxonomy' => 'product_visibility',
                'field'    => 'term_taxonomy_id',
                'terms'    => $product_visibility_term_ids['featured'],
            ];
        }

        // Query for low stock products.
        if ( 'low_stock' === $filter ) {
            $low_stock_amount = get_option( 'woocommerce_notify_low_stock_amount', 1 );

            $args['meta_query'][] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                'relation' => 'AND',
                [
                    'key'     => '_stock_status',
                    'value'   => 'instock',
                    'compare' => '=',
                ],
                [
                    'key'     => '_manage_stock',
                    'value'   => 'yes',
                    'compare' => '=',
                ],
                [
                    'key'     => '_stock',
                    'value'   => $low_stock_amount,
                    'compare' => '<=',
                    'type'    => 'NUMERIC',
                ],
            ];
        }

        // Query for out of stock products.
        if ( 'out_of_stock' === $filter ) {
            $args['meta_query'][] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                'key'     => '_stock_status',
                'value'   => 'outofstock',
                'compare' => '=',
            ];
        }

        return apply_filters( 'sk_pro_vendor_product_filter_by_args', $args, $filter );
    }


    /**
     * Set default tax query
     *
     *
     * @param array $args
     *
     * @return array
     */
    public static function set_default_tax_query( $args ) {
        $product_visibility_term_ids = wc_get_product_visibility_term_ids();

        $args['tax_query'][] = [
            'taxonomy' => 'product_visibility',
            'field'    => 'term_taxonomy_id',
            'terms'    => is_search() ? $product_visibility_term_ids['exclude-from-search'] : $product_visibility_term_ids['exclude-from-catalog'],
            'operator' => 'NOT IN',
        ];

        return $args;
    }

    /**
     * Get subscription product status changer background process class
     *
     *
     * @return HelperChangerProductStatus|null
     */
    public static function get_product_status_changer_bg_class() {
        if ( ! class_exists( 'SK\Core\Abstracts\ProductStatusChanger' ) ) {
            return null;
        }
        return sk()->bg_process->subscription_product_status_changer;
    }

    /**
     * Get Subscription Orders by Vendor ID.
     *
     *
     * @return array Paginated subscription orders.
     */
    public static function get_paginated_subscription_orders_by_vendor_id( $vendor_id, $page = 1, $per_page = 20 ) {
        // Calculate offset for pagination.
        $offset = ( $page - 1 ) * $per_page;

        $args = [
            'customer_id' => $vendor_id,
            'paged'       => $page,
            'limit'       => $per_page,
            'offset'      => $offset,
            'meta_query'  => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                [
                    'relation' => 'OR',
                    [
                        'key'     => '_sk_vendor_subscription_order',
                        'value'   => 'yes',
                    ],
                ],
            ],
        ];

        // Get orders.
        $orders = wc_get_orders( $args );

        // Get total count via a separate ids-query — 'return=count' broke in WC
        // 10.x (returns a stdClass/array depending on backend), causing "array / int"
        // TypeError on the division below.
        $count_args            = $args;
        $count_args['return']  = 'ids';
        $count_args['limit']   = -1;
        $count_args['paged']   = 1;
        $count_args['offset']  = 0;
        $count                 = count( (array) wc_get_orders( $count_args ) );

        // Calculate total pages.
        $total_pages = $per_page > 0 ? (int) ceil( $count / $per_page ) : 1;

        // Return the orders along with pagination data
        return [
            'orders'       => $orders,
            'total_orders' => $count,
            'total_pages'  => $total_pages,
            'current_page' => $page,
            'per_page'     => $per_page,
        ];
    }
}

Helper::instance();
