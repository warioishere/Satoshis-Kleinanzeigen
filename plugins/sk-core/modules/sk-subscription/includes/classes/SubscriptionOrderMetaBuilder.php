<?php

namespace SK\Modules\Subscription;

use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Subscription Renewal Meta Builder.
 *
 */
class SubscriptionOrderMetaBuilder {

    /**
     * The Payment Method Name.
     *
     * @var string
     */
    private string $payment_method;

    /**
     * WC Order Object.
     *
     * @var WC_Order
     */
    private WC_Order $order;

    /**
     * Order Meta Data.
     *
     * @var array
     */
    private $order_meta = [];

    /**
     * Class Constructor.
     *
     * @param WC_Order $order          Order Object
     * @param string   $payment_method Payment Method
     *
     * @return void
     */
    public function __construct( $order, $payment_method = '' ) {
        $this->order          = $order;
        $this->payment_method = $payment_method;
    }

    /**
     * Set Capture ID.
     *
     *
     * @param string $capture_id Capture ID
     *
     * @return $this
     */
    public function set_capture_id( $capture_id ) {
        $this->order_meta[ "_sk_{$this->payment_method}_payment_capture_id" ] = $capture_id;
        return $this;
    }

    /**
     * Set Gateway Order ID.
     *
     *
     * @param int $gateway_order_id Gateway Order ID
     *
     * @return $this
     */
    public function set_gateway_order_id( $gateway_order_id ) {
        $this->order_meta[ "_sk_{$this->payment_method}_order_id" ] = $gateway_order_id;
        return $this;
    }

    /**
     * Set Create Order Debug ID.
     *
     *
     * @param int $debug_id Debug ID
     *
     * @return $this
     */
    public function set_create_order_debug_id( $debug_id ) {
        $this->order_meta[ "_sk_{$this->payment_method}_create_order_debug_id" ] = $debug_id;
        return $this;
    }

    /**
     * Set Gateway Charge ID.
     *
     *
     * @param string $charge_id Charge ID
     *
     * @return $this
     */
    public function set_gateway_charge_id( $charge_id ) {
        $payment_method_name = 'stripe_express' === $this->payment_method ? 'sk_stripe_express' : $this->payment_method;

        $this->order_meta[ "_{$payment_method_name}_subscription_charge_id" ] = $charge_id;
        return $this;
    }

    /**
     * Set Gateway Vendor Subscription ID.
     *
     *
     * @param int $subscription_id Subscription ID
     *
     * @return $this
     */
    public function set_gateway_vendor_subscription_id( $subscription_id ) {
        $payment_method_name = 'paypal' === $this->payment_method ? 'paypal_marketplace' : $this->payment_method;

        $this->order_meta[ "_sk_{$payment_method_name}_vendor_subscription_id" ] = $subscription_id;
        return $this;
    }

    /**
     * Set Redirect URL.
     *
     *
     * @param string $url Redirect URL
     *
     * @return $this
     */
    public function set_redirect_url( $url ) {
        $this->order_meta[ "_sk_{$this->payment_method}_redirect_url" ] = $url;
        return $this;
    }

    /**
     * Set Processing Fee.
     *
     *
     * @param float $fee Processing Fee
     *
     * @return $this
     */
    public function set_processing_fee( $fee ) {
        $processing_key = 'stripe_express' === $this->payment_method ? $this->payment_method : "{$this->payment_method}_payment_processing";

        $this->order_meta[ "_sk_{$processing_key}_fee" ] = $fee;
        return $this;
    }

    /**
     * Set Processing Currency.
     *
     *
     * @param string $currency Processing Currency
     *
     * @return $this
     */
    public function set_processing_currency( $currency ) {
        $this->order_meta[ "_sk_{$this->payment_method}_payment_processing_currency" ] = $currency;
        return $this;
    }

    /**
     * Set Gateway Fee.
     *
     *
     * @param float $fee Gateway Fee
     *
     * @return $this
     */
    public function set_gateway_fee( $fee ) {
        $this->order_meta[ 'sk_gateway_fee' ] = $fee;
        return $this;
    }

    /**
     * Set Gateway Fee Paid By.
     *
     *
     * @param string $paid_by Gateway Fee Paid By
     *
     * @return $this
     */
    public function set_gateway_fee_paid_by( $paid_by ) {
        $this->order_meta[ 'sk_gateway_fee_paid_by' ] = $paid_by;
        return $this;
    }

    /**
     * Set Shipping Fee Recipient.
     *
     *
     * @param string $receipent Shipping Fee Recipient
     *
     * @return $this
     */
    public function set_shipping_fee_recipient( $recipient ) {
        $this->order_meta[ 'shipping_fee_recipient' ] = $recipient;
        return $this;
    }

    /**
     * Set Tax Fee Recipient.
     *
     *
     * @param string $receipent Tax Fee Recipient
     *
     * @return $this
     */
    public function set_tax_fee_recipient( $recipient ) {
        $this->order_meta[ 'tax_fee_recipient' ] = $recipient;
        return $this;
    }

    /**
     * Set Vendor Subscription Order.
     *
     *
     * @param string $is_subscription_order Is Subscription Order
     *
     * @return $this
     */
    public function set_is_vendor_subscription_order( $is_subscription_order ) {
        $this->order_meta[ '_sk_vendor_subscription_order' ] = $is_subscription_order;
        return $this;
    }

    /**
     * Set Pack Validity Start Date.
     *
     *
     * @param string $start_date Validity Start Date
     *
     * @return $this
     */
    public function set_pack_validity_start_date( $start_date ) {
        $this->order_meta[ '_sk_subscription_pack_validity_start' ] = $start_date;
        return $this;
    }

    /**
     * Set Pack Validity End Date.
     *
     *
     * @param string $end_date Validity End Data
     *
     * @return $this
     */
    public function set_pack_validity_end_date( $end_date ) {
        $this->order_meta[ '_pack_validity' ] = $end_date;
        return $this;
    }

    /**
     * Set Pack Renewal Interval Count.
     *
     *
     * @param string $interval_count Renewal Interval Count
     *
     * @return $this
     */
    public function set_pack_renewal_interval_count( $interval_count ) {
        $this->order_meta[ '_sk_subscription_pack_renewal_interval_count' ] = $interval_count;
        return $this;
    }

    /**
     * Set Pack Renewal Interval Period.
     *
     *
     * @param string $interval_period Renewal Interval Period
     *
     * @return $this
     */
    public function set_pack_renewal_interval_period( $interval_period ) {
        $this->order_meta[ '_sk_subscription_pack_renewal_interval_period' ] = $interval_period;
        return $this;
    }

    /**
     * Set Number of Allowed Products.
     *
     *
     * @param int $number Number of Products
     *
     * @return $this
     */
    public function set_no_of_allowed_products( $number ) {
        $this->order_meta[ '_no_of_product' ] = $number;
        return $this;
    }

    /**
     * Build Order Meta Data Updater.
     *
     *
     * @return $this
     */
    public function build() {
        foreach ( $this->order_meta as $meta_key => $meta_value ) {
            $this->order->update_meta_data( $meta_key, $meta_value );
        }

        return $this;
    }

    /**
     * Save The Order.
     *
     *
     * @return void
     */
    public function save() {
        $this->order->save();
    }
}
