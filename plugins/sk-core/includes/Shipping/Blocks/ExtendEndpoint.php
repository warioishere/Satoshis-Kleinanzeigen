<?php

namespace SK\Core\Shipping\Blocks;

use Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema;

defined( 'ABSPATH' ) || exit();

/**
 * Extend checkout endpoint of StoreAPI
 *
 * @see https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce-blocks/docs/third-party-developers/extensibility/rest-api/available-endpoints-to-extend.md
 * @see https://github.com/woocommerce/woocommerce/tree/trunk/plugins/woocommerce-blocks/docs/third-party-developers/extensibility/rest-api
 *
 */
class ExtendEndpoint {
    /**
     * Stores Rest Extending instance.
     *
     * @var ExtendSchema
     */
    private static $extend;

    /**
     * Plugin Identifier, unique to each plugin.
     *
     * @var string
     */
    const IDENTIFIER = 'sk_shipping';

    /**
     * Bootstraps the class and hooks required data.
     *
     * @param ExtendSchema $extend_rest_api An instance of the ExtendSchema class.
     *
     */
    public static function init( ExtendSchema $extend_rest_api ) {
        self::$extend = $extend_rest_api;
        self::extend_store();
    }

    /**
     * Registers the actual data into each endpoint.
     *
     */
    public static function extend_store() {
        // Register into `checkout`
        self::$extend->register_endpoint_data(
            array(
                'endpoint'        => CartSchema::IDENTIFIER,
                'namespace'       => self::IDENTIFIER,
                'data_callback'   => array( self::class, 'extend_cart_data' ),
                'schema_callback' => array( self::class, 'extend_cart_schema' ),
                'schema_type'       => ARRAY_A,
            )
        );
    }

    /**
     * Register subscription product data into checkout endpoint.
     *
     *
     * @return array $item_data Registered data or empty array if condition is not satisfied.
     */
    public static function extend_cart_data() {
        $cart     = WC()->cart;
        $packages = $cart->get_shipping_packages();

        $messages = [];
        foreach ( $packages as $package ) {
            $store_name = '';
            $vendor_id = $package['seller_id'] ?? 0;

            if ( $vendor_id ) {
                $store_name = sk_get_store_info( $vendor_id )['store_name'];
            }

            $message = sk_ext()->shipping_hooks->display_free_shipping_remaining_amount_block( $package, $store_name );

            if ( $store_name !== $message ) {
                $messages[] = $message;
            }

            $message = sk_ext()->shipping_hooks->display_free_shipping_remaining_amount_for_vendor_shipping_block($package, $store_name,);

            if ( $store_name !== $message ) {
                $messages[] = $message;
            }
        }

        return [
            'messages' => $messages
        ];
    }

    /**
     * Register subscription product schema into checkout endpoint.
     *
     *
     * @return array Registered schema.
     */
    public static function extend_cart_schema() {
        return [
            'properties' => array(
                'messages' => array(
                    'type' => 'array',
                ),
            ),
        ];
    }
}
