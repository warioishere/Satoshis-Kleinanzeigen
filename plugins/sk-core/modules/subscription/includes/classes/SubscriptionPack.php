<?php

namespace SK\Modules\Subscription;

use SK\Modules\Subscription\Abstracts\VendorSubscription;
use SK\Core\ProductCategory\Categories;

defined( 'ABSPATH' ) || exit;

/**
 * Subscription Pack Class
 */
class SubscriptionPack extends VendorSubscription {
    /**
     * Hold Pack ID
     *
     * @var integer
     */
    public $pack_id = 0;

    /**
     * Constructor method
     *
     * @param int $id
     * @param int $vendor_id
     *
     * @return void
     */
    public function __construct( $id = null, $vendor_id = null ) {
        if ( $id ) {
            $this->pack_id = $id;
        }

        if ( $vendor_id ) {
            $this->vendor_id = $vendor_id;
        }
    }

    /**
     * Get vendor id
     *
     * @return int
     */
    public function get_vendor() {
        return $this->vendor_id;
    }

    /**
     * Get the all the subscription packages
     *
     * @param array $args
     *
     * @return object
     */
    public function all( $args = [] ) {
        return $this->get_packages( $args );
    }

    /**
     * Get all subscription packages
     *
     * @param array $args
     *
     * @return object
     */
    public function get_packages( $args = [] ) {
        $defaults = [
            'post_type' => 'product',
            'post_status' => 'publish',
            'tax_query' => [
                [
                    'taxonomy' => 'product_type',
                    'field'    => 'slug',
                    'terms'    => 'product_pack',
                ],
            ],
            'posts_per_page' => -1,
            'orderby'        => 'menu_order title',
            'order'          => 'ASC',
        ];

        $args = wp_parse_args( $args, $defaults );

        return new \WP_Query( apply_filters( 'dps_get_subscription_pack_arg', $args ) );
    }

    /**
     * Get individiual pack id (ei: sk->subscription->get( $pack_id )->pack_details())
     *
     * @param $pack_id
     *
     * @return $this instance
     */
    public function get( $pack_id ) {
        $this->pack_id = $pack_id;

        return $this;
    }

    /**
     * Get object ID
     *
     * @return int $pack_id
     */
    public function get_id() {
        return $this->pack_id;
    }

    /**
     * Get allowed product types against a subscription pack
     *
     * @return array|empty array on failure
     */
    public function get_allowed_product_types() {
        $types = [];

        if ( $this->get_id() ) {
            $types = get_post_meta( $this->get_id(), 'sk_subscription_allowed_product_types', true );
        }

        return $types ? $types : [];
    }

    /**
     * Get allowed categories against a subscription pack
     *
     * @return array|empty array on failure
     */
    public function get_allowed_product_categories() {
        $categories = [];

        if ( $this->get_id() ) {
            $categories = get_post_meta( $this->get_id(), '_vendor_allowed_categories', true );

            if ( empty( $categories ) ) {
                return $categories;
            }

            $category       = new Categories();
            $all_category   = $category->get_all_categories( true );
            $child_cate = [];

            // We are looping through all allowed categories and including it's child categories.
            foreach ( $categories as $cat_id ) {
                if ( ! isset( $all_category[ $cat_id ] ) || 0 !== intval( $all_category[ $cat_id ]['parent_id'] ) ) {
                    continue;
                }
                $child_cate = array_merge( $child_cate, $category->get_children( $cat_id ) );
            }

            $categories = array_unique( array_merge( $categories, $child_cate ) );
        }

        return $categories;
    }

    /**
     * Is gallary image upload restricted against a subscription pack
     *
     * @return boolean
     */
    public function is_gallery_image_upload_restricted() {
        $restricted = get_post_meta( $this->get_id(), '_enable_gallery_restriction', true );

        return 'yes' === $restricted ? true : false;
    }

    /**
     * Gallary image upload count
     *
     * @return int
     */
    public function gallery_image_upload_count() {
        $count = get_post_meta( $this->get_id(), '_gallery_image_restriction_count', true );

        return ! empty( $count ) ? intval( $count ) : -1;
    }






    /**
     * Work out when a freshly bought pack should end.
     *
     * A pack validity of 0 means the pack never expires.
     *
     * @return string
     */
    public function get_product_pack_end_date() {
        $pack_validity = absint( $this->get_pack_valid_days() ); //_pack_validity

        if ( 0 === $pack_validity ) {
            return 'unlimited';
        }

        try {
            return sk_current_datetime()->modify( "+{$pack_validity} days" )->format( 'Y-m-d H:i:s' );
        } catch ( \Exception $exception ) {
            return 'unlimited';
        }
    }

    /**
     * Get subscription end date
     *
     * @return string|null
     */
    public function subscription_end_date() {
        return apply_filters(
            'sk_get_vendor_subscription_end_date',
            $this->get_pack_end_date(),
            $this
        );
    }

    /**
     * Get number of products against a subscripton pack
     *
     * @return int
     */
    public function get_number_of_products() {
        return get_post_meta( $this->get_id(), '_no_of_product', true );
    }

    /**
     * Get subscription product instance
     *
     * @return \WC_Product|null|false
     */
    public function get_product() {
        return wc_get_product( $this->get_id() );
    }

    /**
     * Get subscirption pack title
     *
     * @return string
     */
    public function get_package_title() {
        $package = $this->get_product();

        return $package ? apply_filters( 'sk_vendor_subscription_package_title', $package->get_title(), $package ) : '';
    }

    /**
     * Get valid days of a subscription pack
     *
     * @return int
     */
    public function get_pack_valid_days() {
        return get_post_meta( $this->get_id(), '_pack_validity', true );
    }





    /**
     * Get subscription pack price
     *
     * @return float
     */
    public function get_price() {
        $package = $this->get_product();

        return $package ? $package->get_price() : 0;
    }


    /**
     * Activate the subscription after purchase
     *
     * This method doesn't check if user is currently on a subscription, so remember this while using this method.
     *
     * @param \WC_Order $order
     *
     *
     * @return void
     *
     * @throws \Exception
     */
    public function activate_subscription( \WC_Order $order ) {
        $product_pack = $this->get_product();
        $pack_id      = $product_pack->get_id();
        $user_id      = $order->get_customer_id();

        if ( ! $product_pack || 'product_pack' !== $product_pack->get_type() ) {
            return;
        }

        update_user_meta( $user_id, 'can_post_product', '1' );
        update_user_meta( $user_id, 'product_package_id', $pack_id );

        //number of products
        update_user_meta( $user_id, 'product_no_with_pack', get_post_meta( $product_pack->get_id(), '_no_of_product', true ) );
        update_user_meta( $user_id, 'product_pack_startdate', sk_current_datetime()->format( 'Y-m-d H:i:s' ) );
        update_user_meta( $user_id, 'product_order_id', $order->get_id() );
        update_user_meta( $user_id, 'product_pack_enddate', $this->get_product_pack_end_date() );
        update_user_meta( $user_id, 'sk_has_active_cancelled_subscrption', false );

        do_action( 'sk_vendor_purchased_subscription', $user_id );
    }

    /**
     * Temporary suspend a subscription till provided date
     *
     * @param string $enddate Time string formatted as Y-m-d H:i:s
     *
     *
     * @return bool
     */
    public function suspend_subscription( $enddate ) {
        if ( empty( $this->get_vendor() ) || empty( $this->get_id() ) ) {
            return false;
        }

        // store old enddate into another meta
        $cancelled_pack_enddate = get_user_meta( $this->get_vendor(), 'product_pack_enddate', true );
        update_user_meta( $this->get_vendor(), 'cancelled_product_pack_enddate', $cancelled_pack_enddate );

        // set product pack enddate
        update_user_meta( $this->get_vendor(), 'product_pack_enddate', $enddate );

        // set active cancel subscription status
        $this->set_active_cancelled_subscription();

        return true;
    }

    /**
     * Reactivate suspended subscription
     *
     *
     * @return bool
     */
    public function reactivate_subscription() {
        if ( empty( $this->get_vendor() ) || empty( $this->get_id() ) ) {
            return false;
        }
        // get old product pack enddate
        $previous_pack_enddate = get_user_meta( $this->get_vendor(), 'cancelled_product_pack_enddate', true );
        if ( ! empty( $previous_pack_enddate ) ) {
            update_user_meta( $this->get_vendor(), 'product_pack_enddate', $previous_pack_enddate );
        } else {
            update_user_meta( $this->get_vendor(), 'product_pack_enddate', $this->get_product_pack_end_date() );
        }

        // set can_post_product to 1
        update_user_meta( $this->get_vendor(), 'can_post_product', '1' );

        // reset subscription cancelled status
        $this->reset_active_cancelled_subscription();

        return true;
    }
}
