<?php

namespace SK\Core\Shipping;

/**
 * Class ShippingHelper
 *
 */
class Helper {

    /**
     * Checks if the "Mark as Received" feature is enabled for customers.
     *
     * This method verifies whether the shipment tracking and "Mark as Received"
     * features are enabled in the admin settings.
     *
     *
     * @return bool True if both shipment tracking and "Mark as Received" features are enabled, false otherwise.
     */
    public static function is_mark_as_received_allowed_for_customers(): bool {
        // Retrieve the shipment tracking and mark as received settings.
        $allow_shipment_tracking = sk_get_option( 'enabled', 'sk_shipping_status_setting', 'off' );
        $allow_mark_as_received  = sk_get_option( 'allow_mark_received', 'sk_shipping_status_setting', 'off' );

        // Return true if both settings are enabled, otherwise return false.
        return ( $allow_shipment_tracking === 'on' && $allow_mark_as_received === 'on' );
    }

    /**
     * Check shipment complete availability for orders.
     *
     *
     * @param \WC_Order $order
     *
     * @return bool
     */
    public static function is_order_fully_shipped( $order ): bool {
        // Check $order is an instance of wc order.
        if ( ! $order instanceof \WC_Order ) {
            return false;
        }

        // Check mark received allow for customers.
        if ( ! self::is_mark_as_received_allowed_for_customers() ) {
            return false;
        }

        // Check the order is fully shipped to customer.
        $is_order_shipped = sk_ext()->shipment->is_order_shipped( $order );
        if ( ! $is_order_shipped ) {
            return $is_order_shipped;
        }

        $order_id      = $order->get_id();
        $is_available  = true;
        $shipment_info = sk_ext()->shipment->get_shipping_tracking_info( $order_id );

        // Retrieve the marked received order meta.
        $order_marked_as_received = (array) $order->get_meta( '_sk_order_marked_received' );

        foreach ( $shipment_info as $shipment ) {
            if ( $shipment->shipping_status === 'ss_cancelled' ) {
                continue;
            }

            if ( ! in_array( (int) $shipment->id, $order_marked_as_received, true ) ) {
                $is_available = false;
                break;
            }
        }

        return $is_available;
    }

    /**
     * Check the order receiving complete from customer.
     *
     *
     * @param int $order_id
     * @param int $shipment_id
     *
     * @return bool
     */
    public static function is_order_marked_as_received( $order_id, $shipment_id ) {
        if ( ! $order_id || ! $shipment_id ) {
            return false;
        }

        // Check mark received allow for customers.
        if ( ! self::is_mark_as_received_allowed_for_customers() ) {
            return false;
        }

        $order = wc_get_order( $order_id ); // Collect order instance via id.

        // Retrieve the marked received order meta.
        $order_marked_as_received = (array) $order->get_meta( '_sk_order_marked_received' );

        return in_array( (int) $shipment_id, $order_marked_as_received, true );
    }
}
