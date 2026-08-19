<?php
namespace SK\Modules\ProductAdvertisement\Frontend;

use SK\Modules\ProductAdvertisement\Helper;
use SK\Modules\ProductAdvertisement\Manager;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class Order
 *
 *
 */
class Order {
    /**
     * Order constructor.
     *
     */
    public function __construct() {
        // replace advertisement meta text with formatted text
        add_filter( 'woocommerce_order_item_display_meta_key', [ $this, 'hide_order_item_meta_key' ], 999, 3 );
        add_filter( 'woocommerce_order_item_display_meta_value', [ $this, 'hide_order_item_meta_value' ], 999, 3 );

        // store advertisement metas
        add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'store_advertisement_line_item_metas' ], 10, 3 );

        // after payment complete
        add_action( 'woocommerce_payment_complete', [ $this, 'process_advertisement_order' ], 10, 1 );

        // after order status changed
        add_action( 'woocommerce_order_status_changed', [ $this, 'process_order_status_changed' ], 10, 3 );

        // Modifies Mangopay payin data.
        add_filter( 'sk_mangopay_payin_data', [ $this, 'modify_mangopay_payin_data' ] );
    }

    /**
     * Insert advertisement into database after order status has been completed.
     *
     *
     * @param int    $order_id of the $order_id .
     * @param string $old_status old status of the order.
     * @param string $new_status this is new status of the order.
     */
    public function process_order_status_changed( $order_id, $old_status, $new_status ) {
        if ( $old_status === $new_status ) {
            return;
        }

        if ( 'completed' !== $new_status ) {
            return;
        }

        // add advertisement data into database
        $this->insert_advertisement( $order_id );
    }

    /**
     * Insert advertisement into database after order payment status has been completed
     *
     *
     * @param int $order_id
     *
     * @return void
     */
    public function process_advertisement_order( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order instanceof \WC_Abstract_Order ) {
            return;
        }

        if ( ! Helper::has_product_advertisement_in_order( $order ) ) {
            return;
        }

        // add advertisement data into database
        $this->insert_advertisement( $order_id );
    }

    /**
     * This method will insert advertisement record into database
     *
     *
     * @param int $order_id
     *
     * @return void
     */
    protected function insert_advertisement( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! Helper::has_product_advertisement_in_order( $order ) ) {
            return;
        }

        // get advertisement data from order
        $advertisement_data = Helper::get_advertisement_data_from_order( $order );
        if ( empty( $advertisement_data ) ) {
            return;
        }

        // check advertisement already exists in database, this is to prevent duplicate entry
        if ( Helper::is_product_advertised( $advertisement_data['product_id'] ) ) {
            return;
        }

        // prepare item for database
        $args = [
            'product_id'         => $advertisement_data['product_id'],
            'created_via'        => 'order',      // possible values are order,admin,subscription,free
            'order_id'           => $order->get_id(),
            'price'              => $advertisement_data['advertisement_cost'],
            'expires_after_days' => $advertisement_data['expires_after_days'],
            'status'             => 1,       // 1 for active, 2 for inactive
        ];

        // finally insert advertisement
        $manager = new Manager();
        $inserted = $manager->insert( $args );
    }

    /**
     * Stores advertisement cost and expire date in the line item meta.
     *
     *
     * @param \WC_Order_Item_Product $line_item     The line item added to the order.
     * @param string                 $cart_item_key The key of the cart item being added to the cart.
     * @param array                  $cart_item     The cart item data.
     */
    public static function store_advertisement_line_item_metas( $line_item, $cart_item_key, $cart_item ) {
        if ( isset( $cart_item['sk_product_advertisement'] ) ) {
            $line_item->add_meta_data( 'sk_advertisement_product_id', $cart_item['sk_advertisement_product_id'] );
            $line_item->add_meta_data( 'sk_advertisement_cost', $cart_item['sk_advertisement_cost'] );
            $line_item->add_meta_data( 'sk_advertisement_expire_after_days', $cart_item['sk_advertisement_expire_after_days'] );
        }
    }

    /**
     * Hide meta key in the order.
     *
     *
     * @param  string $display_key of the key.
     * @param  object $meta for the meta data.
     * @param  array $item array.
     *
     * @return string
     */
    public function hide_order_item_meta_key( $display_key, $meta, $item ) {
        switch ( $display_key ) {
            case 'sk_advertisement_cost':
                $display_key = __( 'Advertisement Listing Price', 'sk' );
                break;

            case 'sk_advertisement_expire_after_days':
                $display_key = __( 'Expires In Days', 'sk' );
                break;

            case 'sk_advertisement_product_id':
                $display_key = __( 'Product Name', 'sk' );
                break;
        }

        return $display_key;
    }

    /**
     * Hide meta key in the order.
     *
     *
     * @param  mixed $display_value for the display item.
     * @param  object $meta data of the order.
     * @param  array $item item array.
     *
     * @return string
     */
    public function hide_order_item_meta_value( $display_value, $meta, $item ) {
        switch ( $meta->key ) {
            case 'sk_advertisement_cost':
                $display_value = wc_price( $display_value );
                break;

            case 'sk_advertisement_expire_after_days':
                $display_value = Helper::format_expire_after_days_text( $display_value );
                break;

            case 'sk_advertisement_product_id':
                $title         = esc_html( get_the_title( $display_value ) );
                $permalink     = esc_url( get_the_permalink( $display_value ) );
                $display_value = sprintf( '<a href="%1$s">%2$s</a>', $permalink, $title );
                break;
        }

        return $display_value;
    }

    /**
     * Modifies payin data for Mangopay while purchasing advertisement.
     *
     *
     * @param array $payin_data
     *
     * @return bool
     */
    public function modify_mangopay_payin_data( $payin_data ) {
        if ( empty( $payin_data['order_id'] ) ) {
            return $payin_data;
        }

        $order = wc_get_order( $payin_data['order_id'] );

        if ( ! $order ) {
            return $payin_data;
        }

        if ( ! Helper::has_product_advertisement_in_order( $order ) ) {
            return $payin_data;
        }

        /*
         * We cannot pass the full amount as fees.
         * So the amount for the marketplace will be 1 cent less than the payin amount.
         */
        $payin_data['fees'] = $payin_data['amount'] - 1;

        return $payin_data;
    }
}
