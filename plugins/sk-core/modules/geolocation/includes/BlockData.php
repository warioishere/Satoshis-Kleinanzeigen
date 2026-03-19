<?php

namespace SK\Modules\Geolocation;

defined( 'ABSPATH' ) || exit;

use WC_Product;

/**
 * Block data handler.
 *
 */
class BlockData {

    /**
     * Block section name.
     *
     *
     * @var string
     */
    public $section;

    /**
     * Constructor class.
     *
     */
    public function __construct() {
        $this->section = 'geolocation';
        $this->hooks();
    }

    /**
     * Registers necessary hooks.
     *
     *
     * @return void
     */
    protected function hooks() {
        // Get and Set block
        add_filter( 'sk_rest_get_product_block_data', [ $this, 'get_product_block_data' ], 10, 3 );
        add_action( 'sk_rest_insert_product_object', [ $this, 'set_product_block_data' ], 10, 3 );
    }

    /**
     * Get eu compliance product data.
     *
     *
     * @param array      $block
     * @param WC_Product $product
     * @param string     $context
     *
     * @return array
     */
    public function get_product_block_data( array $block, $product, string $context ) {
        if ( ! $product instanceof WC_Product ) {
            return $block;
        }

        $block[ $this->section ] = sk_geo_get_product_data( $product->get_id() );

        return $block;
    }

    /**
     * Save order-min-max data after REST-API insert or update.
     *
     *
     * @param WC_Product      $product  Inserted object.
     * @param WP_REST_Request $request  Request object.
     * @param boolean         $creating True when creating object, false when updating.
     *
     * @return void
     */
    public function set_product_block_data( $product, $request, $creating = true ) {
        if ( ! $product instanceof WC_Product ) {
            return;
        }

        $store_id = ! empty( $request['sk_product_author_override'] )
            ? intval( $request['sk_product_author_override'] )
            : sk_get_current_user_id();

        $sk_geo_public = get_user_meta( $store_id, 'sk_geo_public', true );

        if ( ! empty( $request['use_store_settings'] ) ) {
            $use_store_settings = 'no' === $request['use_store_settings'] ? 'no' : 'yes';
            $product->update_meta_data( '_sk_geolocation_use_store_settings', $use_store_settings );
        } else {
            $use_store_settings = 'no' === $product->get_meta( '_sk_geolocation_use_store_settings', true ) ? 'no' : 'yes';
        }

        if ( 'yes' !== $use_store_settings ) {
            $sk_geo_latitude = ! empty( $request['sk_geo_latitude'] )
                ? $request['sk_geo_latitude']
                : $product->get_meta( 'sk_geo_latitude' );

            $sk_geo_longitude = ! empty( $request['sk_geo_longitude'] )
                ? $request['sk_geo_longitude']
                : $product->get_meta( 'sk_geo_longitude' );

            $sk_geo_address = ! empty( $request['sk_geo_address'] )
                ? $request['sk_geo_address']
                : $product->get_meta( 'sk_geo_address' );
        } else {
            $sk_geo_latitude  = get_user_meta( $store_id, 'sk_geo_latitude', true );
            $sk_geo_longitude = get_user_meta( $store_id, 'sk_geo_longitude', true );
            $sk_geo_address   = get_user_meta( $store_id, 'sk_geo_address', true );
        }

        $product->update_meta_data( 'sk_geo_latitude', $sk_geo_latitude );
        $product->update_meta_data( 'sk_geo_longitude', $sk_geo_longitude );
        $product->update_meta_data( 'sk_geo_address', $sk_geo_address );
        $product->update_meta_data( 'sk_geo_public', $sk_geo_public );
        $product->save();
    }
}
