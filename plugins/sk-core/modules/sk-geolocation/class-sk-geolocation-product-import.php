<?php

/**
 * SK_Geolocation_Product_Import
 *
 */
class SK_Geolocation_Product_Import {
    /**
     * Constructor method
     */
    public function __construct() {
        add_filter( 'woocommerce_product_importer_pre_expand_data', [ __CLASS__, 'format_geo_data' ] );
    }

    /**
     * Format geo data
     *
     *
     * @param array $data
     *
     * @return array
     */
    public static function format_geo_data( $data ) {
        if ( ! empty( $data['meta:sk_geo_latitude'] ) ) {
            $data['meta:sk_geo_latitude'] = sk_geo_float_val( $data['meta:sk_geo_latitude'] );
        }

        if ( ! empty( $data['meta:sk_geo_longitude'] ) ) {
            $data['meta:sk_geo_longitude'] = sk_geo_float_val( $data['meta:sk_geo_longitude'] );
        }

        return $data;
    }
}