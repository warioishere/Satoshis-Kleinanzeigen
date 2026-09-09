<?php

/**
 * Geolocation Module Product Location Widget
 *
 */
class SK_Geolocation_Widget_Product_Location extends WP_Widget {

    /**
     * Instance key to keep track of the widget inside widget container in sk-lite
     *
     *
     * @var string
     */
    const INSTANCE_KEY = 'geolocation__SK_Geolocation_Widget_Product_Location'; // Naming Structure: {module_slug}__{ClassName}

    /**
     * Class constructor
     *
     *
     * @return void
     */
    public function __construct() {
        $widget_ops = array(
            'classname'   => 'sk-geolocation-widget-product-location',
            'description' => __( 'Show product geolocation informations in single page', 'sk-core' ),
        );

        parent::__construct( 'sk-geolocation-widget-product-location', __( 'SK: Product Location', 'sk-core' ), $widget_ops );
    }

    /**
     * Widget settings form in widget settings
     *
     *
     * @param array $instance
     *
     * @return void
     */
    public function form( $instance ) {
        $instance = wp_parse_args( (array) $instance, array(
            'title' => __( 'Product Location', 'sk-core' ),
        ) );

        $args = array(
            'title_id'   => $this->get_field_id( 'title' ),
            'title_name' => $this->get_field_name( 'title' ),
            'title'      => $instance['title'],
        );

        sk_geo_get_template( 'widget-product-location', $args );
    }

    /**
     * Update or save widget settings
     *
     *
     * @param array $new_instance
     * @param array $old_instance
     *
     * @return array
     */
    public function update( $new_instance, $old_instance ) {
        $title = empty( $new_instance['title'] ) ? __( 'Product Location', 'sk-core' ) : $new_instance['title'];

        return array(
            'title' => $title,
        );
    }

    /**
     * Display widget in frontend
     *
     *
     * @param array $args
     * @param array $instance
     *
     * @return void
     */
    public function widget( $args, $instance ) {
        if ( ! is_product() ) {
            return;
        }

        $show_tab = sk_get_option( 'show_product_location_in_wc_tab', 'sk_geolocation', 'on' );

        if ( 'on' === $show_tab ) {
            return;
        }

        extract( $args, EXTR_SKIP );

        echo $before_widget;

        $title = isset( $instance['title'] ) ? apply_filters( 'widget_title', $instance['title'] ) : '';
        $title = empty( $title ) ? __( 'Product Location', 'sk-core' ) : $title;

        echo $args['before_title'] . $title . $args['after_title'];

        sk_geo_product_location();

        $this->add_product_location_data();

        echo $after_widget;
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
