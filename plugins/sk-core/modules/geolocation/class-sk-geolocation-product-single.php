<?php

/**
 * Geolocation Module Product Tab in product single page
 *
 */
class SK_Geolocation_Product_Single {

    /**
     * Class constructor
     *
     *
     * @return void
     */
    public function __construct() {
        add_filter( 'woocommerce_product_tabs', array( $this, 'add_tab' ) );
        add_action( 'sk_geo_product_location_tab_data', array( $this, 'add_product_location_data' ), 30 );
    }

    /**
     * Add Product Location tab in product single page
     *
     *
     * @param array $tabs
     *
     * @return array
     */
    public function add_tab( $tabs ) {
        $show_tab = sk_get_option( 'show_product_location_in_wc_tab', 'sk_geolocation', 'on' );

        if ( 'on' !== $show_tab ) {
            return $tabs;
        }

        // Don't add the tab if the product has no real address.
        global $product;
        if ( $product ) {
            $address = $product->get_meta( 'sk_geo_address', true );
            if ( empty( $address ) || 'Dhaka' === trim( $address ) ) {
                return $tabs;
            }
        }

        $tabs['geolocation'] = array(
            'title'    => __( 'Location', 'sk-core' ),
            'priority' => 90,
            'callback' => array( $this, 'location_tab' )
        );

        return $tabs;
    }

    /**
     * Location tab callback
     *
     * Prints google map with product location
     *
     *
     * @return void
     */
    public function location_tab() {
        printf( '<h2>%s</h2>', __( 'Product Location', 'sk-core' ) );

        sk_geo_product_location();

        do_action( 'sk_geo_product_location_tab_data' );
    }

    /**
     * Add product location data in product single page
     *
     * @todo This should be a reusable function
     *
     *
     * @return void
     */
    public function add_product_location_data() {
        global $product;

        $latitude  = $product->get_meta( 'sk_geo_latitude', true );
        $longitude = $product->get_meta( 'sk_geo_longitude', true );
        $address   = $product->get_meta( 'sk_geo_address', true );

        // Don't show map if no real address has been set by the vendor.
        if ( empty( $address ) || 'Dhaka' === trim( $address ) ) {
            return;
        }

        $args = array(
            'id'                  => $product->get_id(),
            'sk_geo_latitude'  => $latitude,
            'sk_geo_longitude' => $longitude,
            'sk_geo_address'   => $address,
            'info'                => null,
        );

        sk_geo_get_template( 'item-geolocation-data', $args );
    }
}
