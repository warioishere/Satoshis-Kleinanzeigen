<?php

/**
 * Shows location maps for WC Products
 *
 */
class SK_Geolocation_Product_View {

    /**
     * Map location
     *
     * Possible values: top, left, right
     *
     *
     * @var string
     */
    private $map_location = 'top';

    /**
     * Class constructor
     *
     *
     * @return void
     */
    public function __construct() {
        $this->map_location = sk_get_option( 'show_locations_map', 'sk_geolocation', 'top' );

        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'woocommerce_before_shop_loop', array( $this, 'start_column_layout' ), 1 );
        add_action( 'woocommerce_no_products_found', array( $this, 'start_column_layout' ), 1 );
        add_action( 'woocommerce_after_main_content', array( $this, 'end_column_layout' ), 1 );
        add_action( 'woocommerce_before_shop_loop', array( $this, 'before_shop_loop' ) );
        add_action( 'woocommerce_no_products_found', array( $this, 'before_shop_loop' ), 9 );
        add_action( 'woocommerce_after_shop_loop_item', array( $this, 'after_shop_loop_item' ) );
    }

    /**
     * Enqueue locations map scripts in WC shop page
     *
     *
     * @return void
     */
    public function enqueue_scripts() {
        if ( ! $this->is_geolocation_show_on_shop_page() ) {
            return;
        }

        sk_geo_enqueue_locations_map();
    }

    /**
     * Include locations map template in WC shop page when layout is set to left or right
     *
     *
     * @return void
     */
    public function start_column_layout() {
        if ( ! $this->is_geolocation_show_on_shop_page() ) {
            return;
        }

        if ( 'right' === $this->map_location ) {
            echo '<div class="sk-geolocation-row sk-geolocation-map-right"><div class="sk-geolocation-col-7">';
        } elseif ( 'left' === $this->map_location ) {
            echo '<div class="sk-geolocation-row sk-geolocation-map-left"><div class="sk-geolocation-col-5">';

            sk_geo_get_template( 'map', array( 'layout' => 'left' ) );

            echo '</div><div class="sk-geolocation-col-7">';
        }
    }

    /**
     * Include locations map template in WC shop page when layout is set to left or right
     *
     *
     * @return void
     */
    public function end_column_layout() {
        if ( ! $this->is_geolocation_show_on_shop_page() ) {
            return;
        }

        if ( 'right' === $this->map_location ) {
            echo '</div><div class="sk-geolocation-col-5">';

            sk_geo_get_template( 'map', array( 'layout' => 'right' ) );

            echo '</div>'; // .row

        } elseif ( 'left' === $this->map_location ) {
            echo '</div>';  // .row
        }
    }

    /**
     * Include locations map template in WC shop page
     *
     *
     * @return void
     */
    public function before_shop_loop() {
        if ( ! $this->is_geolocation_show_on_shop_page() ) {
            return;
        }

        $show_filters = sk_get_option( 'show_filters_before_locations_map', 'sk_geolocation', 'on' );

        if ( 'on' === $show_filters ) {
            sk_geo_filter_form( 'product' );
        }

        if ( 'top' === $this->map_location ) {
            sk_geo_get_template( 'map', array( 'layout' => 'top' ) );
        }
    }

    /**
     * Include geolocation data for every product
     *
     *
     * @return void
     */
    public function after_shop_loop_item() {
        if ( ! $this->is_geolocation_show_on_shop_page() ) {
            return;
        }

        global $post, $product;

        if ( empty( $post->sk_geo_latitude ) || empty( $post->sk_geo_longitude ) ) {
            return;
        }

        $image_src = wp_get_attachment_image_src( $product->get_image_id() );

        if ( ! empty( $image_src[0] ) ) {
            $image = $image_src[0];
        } else {
            $image = wc_placeholder_img_src();
        }

        $info_window_data = array(
            'title'   => $post->post_title,
            'link'    => get_permalink( $post->ID ),
            'image'   => $image,
            'address' => $post->sk_geo_address,
        );

        /**
         * Filter to modify product data for map marker info window
         *
         *
         * @param array      $info_window_data
         * @param WP_Post    $post
         * @param WC_Product $product
         */
        $info = apply_filters( 'sk_geolocation_info_product', $info_window_data, $post, $product );

        $args = array(
            'id'                  => $post->ID,
            'sk_geo_latitude'  => $post->sk_geo_latitude,
            'sk_geo_longitude' => $post->sk_geo_longitude,
            'sk_geo_address'   => $post->sk_geo_address,
            'info'                => wp_json_encode( $info ),
        );

        sk_geo_get_template( 'item-geolocation-data', $args );
    }

    /**
     * Is geolocation show on shop page
     *
     *
     * @return bool
     */
    public function is_geolocation_show_on_shop_page() {
        $show_map_pages = sk_get_option( 'show_location_map_pages', 'sk_geolocation', 'shop' );

        if ( ( is_shop() || is_product_taxonomy() ) && ( 'shop' === $show_map_pages || 'all' === $show_map_pages ) ) {
            return true;
        }

        return false;
    }
}
