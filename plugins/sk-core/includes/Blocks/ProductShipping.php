<?php

namespace SK\Core\Blocks;

use WC_Product;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * SK Product Shipping Block data class.
 *
 * 
 */
class ProductShipping {

    /**
     * Block section name.
     *
     *
     * @var string
     */
    public $section;

    /**
     * Product instance.
     *
     *
     * @var WC_Product|null
     */
    private $product;

    /**
     * WP Rest request instance.
     *
     *
     * @var WP_Rest_Request|null
     */
    private $request;

    /**
     * Constructor class.
     *
     */
    public function __construct() {
        $this->section = 'shipping_tax';
        $this->product = null;
        $this->request = null;

        // Configuration
        add_filter( 'sk_get_product_block_configurations', [ $this, 'get_block_configurations' ] );

        // Set and Get
        add_filter( 'sk_rest_get_product_variable_block_data', [ $this, 'get_variable_block_data' ], 10, 3 );
        add_filter( 'sk_rest_get_product_block_data', [ $this, 'get_block_data' ], 10, 3 );
        add_action( 'sk_rest_insert_product_object', [ $this, 'set_block_data' ], 10, 3 );
    }

    /**
     * Get product block configurations.
     *
     *
     * @param $configuration array
     *
     * @return array
     */
    public function get_block_configurations( $configuration = [] ) {
        // Shipping related configurations.
        $sk_shipping_option  = get_option( 'woocommerce_sk_product_shipping_settings' );
        $is_shipping_disabled   = false;
        $sk_shipping_enabled = ( isset( $sk_shipping_option['enabled'] ) ) ? $sk_shipping_option['enabled'] : 'yes';

        if ( 'sell_digital' === sk_ext()->digital_product->get_selling_product_type() ) {
            $is_shipping_disabled = true;
        }

        $configuration[ $this->section ] = [
            'wc_shipping_enabled'    => get_option( 'woocommerce_calc_shipping' ) === 'yes' ? true : false,
            'wc_tax_enabled'         => get_option( 'woocommerce_calc_taxes' ) === 'yes' ? true : false,
            'is_shipping_disabled'   => $is_shipping_disabled,
            'sk_shipping_enabled' => 'yes' === $sk_shipping_enabled ? true : false,
            'store_shipping_enabled' => get_user_meta( get_current_user_id(), '_dps_shipping_enable', true ) === 'yes' ? true : false,
            'weight_placeholder'     => sprintf(
                // translators: 1) Woocommerce weight unit name
                __( 'weight (%s)', 'sk' ), get_option( 'woocommerce_weight_unit' )
            ),
            'length_placeholder'     => sprintf(
                // translators: 1) Woocommerce length unit name
                __( 'length (%s)', 'sk' ), get_option( 'woocommerce_dimension_unit' )
            ),
            'width_placeholder'      => sprintf(
                // translators: 1) Woocommerce width unit name
                __( 'width (%s)', 'sk' ), get_option( 'woocommerce_dimension_unit' )
            ),
            'height_placeholder'     => sprintf(
                // translators: 1) Woocommerce height unit name
                __( 'height (%s)', 'sk' ), get_option( 'woocommerce_dimension_unit' )
            ),
            'shipping_classes'       => WC()->shipping->get_shipping_classes(),
            'processing_times'       => sk_get_shipping_processing_times(),
            'tax_statuses'           => [
                'taxable'   => __( 'Taxable', 'sk' ),
                'shipping'  => __( 'Shipping only', 'sk' ),
                'none'      => _x( 'None', 'Tax status', 'sk' ),
            ],
            'tax_classes'            => sk_ext()->products->get_tax_class_option(),
        ];

        return $configuration;
    }

    /**
     * Get product shipping classes formatted for select2.
     *
     *
     * @return array
     */
    private function get_product_shipping_classes() {
        $shipping = WC()->shipping->get_shipping_classes();
        $classes  = [];

        if ( ! empty( $shipping ) ) {
            foreach ( $shipping as $shipping ) {
                $classes[ $shipping->slug ] = $shipping->name;
            }
        }

        return $classes;
    }

    /**
     * Get product-shipping data.
     *
     *
     * @param array      $block
     * @param WC_Product $product
     * @param string     $context
     *
     * @return array
     */
    public function get_block_data( array $block, $product, string $context ) {
        if ( ! $product instanceof WC_Product ) {
            return $block;
        }

        $user_id                 = sk_get_current_user_id();
        $_additional_price       = $product->get_meta( '_additional_price', true, $context );
        $_additional_qty         = $product->get_meta( '_additional_qty', true, $context );
        $_processing_time        = $product->get_meta( '_dps_processing_time', true, $context );

        $dps_shipping_type_price = get_user_meta( $user_id, '_dps_shipping_type_price', true );
        $dps_additional_qty      = get_user_meta( $user_id, '_dps_additional_qty', true );
        $dps_pt                  = get_user_meta( $user_id, '_dps_pt', true );

        $block[ $this->section ] = [
            '_disable_shipping' => 'yes' === $product->get_meta( '_disable_shipping' ),
            '_weight'           => $product->get_weight( $context ),
            '_length'           => $product->get_length( $context ),
            '_width'            => $product->get_width( $context ),
            '_height'           => $product->get_height( $context ),
            'shipping_class'    => $product->get_shipping_class(),

            // Shipping Override fields than the store default.
            '_overwrite_shipping'  => 'yes' === $product->get_meta( '_overwrite_shipping', true, $context ),
            '_additional_price'    => $_additional_price ? $_additional_price : $dps_shipping_type_price,
            '_additional_qty'      => $_additional_qty ? $_additional_qty : $dps_additional_qty,
            '_dps_processing_time' => $_processing_time ? $_processing_time : $dps_pt,

            // Tax
            '_tax_status' => $product->get_tax_status( $context ),
            '_tax_class'  => $product->get_tax_class( $context ),
        ];

        return $block;
    }

    /**
     * Returns variable block data.
     *
     *
     * @param array  $block
     * @param        $product
     * @param string $context
     *
     * @return array
     */
    public function get_variable_block_data( array $block, $product, string $context ): array {
		$data = $this->get_block_data( $block, $product, $context );

        $updated_array = [];

        foreach ( $data[ $this->section ] as $key => $value ) {
            if ( isset( $key[0] ) && $key[0] === '_' ) {
                $new_key = substr( $key, 1 );
            } else {
                $new_key = $key;
            }
            $updated_array[ $new_key ] = $value;
        }

        $data[ $this->section ] = $updated_array;

        return $data;
    }

    /**
     * Save product-shipping data.
     *
     *
     * @param WC_Product      $product  Inserted object.
     * @param WP_REST_Request $request  Request object.
     *
     * @return void
     */
    public function set_block_data( $product, $request ) {
        if ( ! $product instanceof WC_Product ) {
            return;
        }

        $this->product = $product;
        $this->request = $request;

        // Set dimensions for variable and shippable products.
        $this->set_dimensions();

        // Set shipping disable or enable.
        $this->product->update_meta_data( '_disable_shipping', ( isset( $request['_disable_shipping'] ) && $request['_disable_shipping'] ) ? 'yes' : 'no' );

        // Override store's default shipping data for this product.
        $this->override_store_default();

        // Set tax status and class.
        $this->product->set_tax_class( isset( $request['_tax_class'] ) ? $request['_tax_class'] : '' );
        $this->product->set_tax_status( isset( $request['_tax_status'] ) ? $request['_tax_status'] : '' );

        $this->set_shipping_class();
        $this->product->save();
    }

    /**
     * Set product dimensions and return product instance.
     *
     *
     * @return void
     */
    private function set_dimensions() {
        if ( $this->product->is_virtual() ) {
            $this->set_virtual_product_dimensions();
        }

        $this->set_shippable_product_dimensions();
    }

    /**
     * Set virtual product dimensions.
     *
     *
     * @return void
     */
    private function set_virtual_product_dimensions() {
        $this->product->set_weight( '' );
        $this->product->set_length( '' );
        $this->product->set_width( '' );
        $this->product->set_height( '' );
    }

    /**
     * Set shippable product dimensions.
     *
     *
     * @return void
     */
    private function set_shippable_product_dimensions() {
        if ( isset( $this->request['_weight'] ) ) {
            $this->product->set_weight( ( '' === $this->request['_weight'] ) ? '' : wc_format_decimal( $this->request['_weight'] ) );
        }

        if ( isset( $this->request['_length'] ) ) {
            $this->product->set_length( ( '' === $this->request['_length'] ) ? '' : wc_format_decimal( $this->request['_length'] ) );
        }

        if ( isset( $this->request['_width'] ) ) {
            $this->product->set_width( ( '' === $this->request['_width'] ) ? '' : wc_format_decimal( $this->request['_width'] ) );
        }

        if ( isset( $this->request['_height'] ) ) {
            $this->product->set_height( ( '' === $this->request['_height'] ) ? '' : wc_format_decimal( $this->request['_height'] ) );
        }
    }

    /**
     * Override store's default shipping data for this product.
     *
     *
     * @return void
     */
    private function override_store_default() {
        if ( isset( $this->request['_overwrite_shipping'] ) && ( 'yes' === $this->request['_overwrite_shipping'] || $this->request['_overwrite_shipping'] ) ) {
            $this->product->update_meta_data( '_overwrite_shipping', 'yes' );
        } else {
            $this->product->update_meta_data( '_overwrite_shipping', 'no' );
        }

        $this->product->update_meta_data( '_additional_price', isset( $this->request['_additional_price'] ) ? $this->request['_additional_price'] : '' );
        $this->product->update_meta_data( '_additional_qty', isset( $this->request['_additional_qty'] ) ? $this->request['_additional_qty'] : '' );
        $this->product->update_meta_data( '_dps_processing_time', isset( $this->request['_dps_processing_time'] ) ? $this->request['_dps_processing_time'] : '' );
    }

    /**
     * Set product shipping class.
     *
     *
     * @return void
     */
    private function set_shipping_class() {
        $class_slug   = $this->request['shipping_class'];
        $product_type = $this->product->get_type();
        $term         = get_term_by( 'slug', $class_slug, 'product_shipping_class' );

        if ( empty( $term ) || is_wp_error( $term ) || 'external' !== $product_type ) {
            return;
        }

        $product_shipping_class = absint( $term->term_id );
        wp_set_object_terms( $this->product->get_id(), $product_shipping_class, 'product_shipping_class' );
    }
}
