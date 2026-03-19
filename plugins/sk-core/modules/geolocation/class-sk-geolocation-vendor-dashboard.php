<?php

/**
 * Vendor dashboard functionalities
 *
 */
class SK_Geolocation_Vendor_Dashboard {

    /**
     * Class constructor
     *
     *
     * @return void
     */
    public function __construct() {
        add_action( 'sk_store_profile_saved', array( $this, 'save_vendor_geodata' ), 10, 2 );
        add_action( 'sk_product_edit_after_main', array( $this, 'add_product_editor_options' ) );
        add_action( 'sk_new_product_added', array( $this, 'update_product_settings' ) );
        add_action( 'sk_product_updated', array( $this, 'update_product_settings' ) );
        add_action( 'sk_update_auction_product', array( $this, 'update_product_settings' ), 10, 2 );
        add_action( 'woocommerce_process_product_meta', array( $this, 'update_product_settings' ) );
    }

    /**
     * Use store settings option
     *
     *
     * @param int $post_id
     *
     * @return string
     */
    public function use_store_settings( $post_id ) {
        $use_store_settings = get_post_meta( $post_id, '_sk_geolocation_use_store_settings', true );

        if ( empty( $use_store_settings ) || 'yes' === $use_store_settings ) {
            return 'yes';
        } else {
            return 'no';
        }
    }

    /**
     * Save vendor geodata
     *
     *
     * @param int   $store_id
     * @param array $sk_settings
     *
     * @return void
     */
    public function save_vendor_geodata( $store_id, $sk_settings ) {
        if ( isset( $sk_settings['location'] ) && isset( $sk_settings['find_address'] ) ) {
            $location = explode( ',', $sk_settings['location'] );

            if ( 2 !== count( $location ) ) {
                return;
            }

            $old_latitude  = get_user_meta( $store_id, 'sk_geo_latitude', true );
            $old_longitude = get_user_meta( $store_id, 'sk_geo_longitude', true );

            $new_latitude  = $location[0];
            $new_longitude = $location[1];

            update_user_meta( $store_id, 'sk_geo_latitude', $new_latitude );
            update_user_meta( $store_id, 'sk_geo_longitude', $new_longitude );
            update_user_meta( $store_id, 'sk_geo_public', 1 );
            update_user_meta( $store_id, 'sk_geo_address', $sk_settings['find_address'] );

            if ( ( $old_latitude == $new_latitude ) && ( $old_longitude == $new_longitude ) ) {
                return;
            }

            $updater_file = SK_GEOLOCATION_PATH . '/class-sk-geolocation-update-product-location-data.php';
            include_once $updater_file;

            $processor = new SK_Geolocation_Update_Product_Location_Data();

            $item = array(
                'vendor_id' => $store_id,
                'paged'     => 1,
            );

            $processor->push_to_queue( $item );
            $processor->dispatch_process();
        }
    }

    /**
     * Add product editor options/settings
     *
     *
     * @param int $post_id
     *
     * @return void
     */
    public function add_product_editor_options( $post_id ) {
        if ( $post_id instanceof WP_Post ) {
            $post_id = $post_id->ID;
        }

        $args = sk_geo_get_product_data( $post_id );
        $args['post_id'] = $post_id;

        sk_geo_get_template( 'product-editor-options', $args );
    }

    /**
     * Update product settings
     *
     *
     * @param int $post_id
     *
     * @return void
     */
    public function update_product_settings( $post_id ) {
        $store_id            = ! empty( $_POST['sk_product_author_override'] ) ? intval( $_POST['sk_product_author_override'] ) : sk_get_current_user_id();
        $sk_geo_latitude  = get_user_meta( $store_id, 'sk_geo_latitude', true );
        $sk_geo_longitude = get_user_meta( $store_id, 'sk_geo_longitude', true );
        $sk_geo_public    = get_user_meta( $store_id, 'sk_geo_public', true );
        $sk_geo_address   = get_user_meta( $store_id, 'sk_geo_address', true );
        $use_store_settings  = 'yes';

        if ( ! empty( $_POST['_sk_geolocation_use_store_settings'] ) ) {
            $use_store_settings = 'no' === sanitize_text_field( wp_unslash( $_POST['_sk_geolocation_use_store_settings'] ) ) ? 'no' : 'yes';

            update_post_meta( $post_id, '_sk_geolocation_use_store_settings', $use_store_settings );
        } else {
            $use_store_settings = $this->use_store_settings( $post_id );
        }

        if ( 'yes' !== $use_store_settings ) {
            $sk_geo_latitude_post  = get_post_meta( $post_id, 'sk_geo_latitude', true );
            $sk_geo_longitude_post = get_post_meta( $post_id, 'sk_geo_longitude', true );
            $sk_geo_address_post   = get_post_meta( $post_id, 'sk_geo_address', true );

            $sk_geo_latitude  = ! empty( $_POST['_sk_geolocation_product_sk_geo_latitude'] ) ? $_POST['_sk_geolocation_product_sk_geo_latitude'] : $sk_geo_latitude_post;
            $sk_geo_longitude = ! empty( $_POST['_sk_geolocation_product_sk_geo_longitude'] ) ? $_POST['_sk_geolocation_product_sk_geo_longitude'] : $sk_geo_longitude_post;
            $sk_geo_address   = ! empty( $_POST['_sk_geolocation_product_sk_geo_address'] ) ? $_POST['_sk_geolocation_product_sk_geo_address'] : $sk_geo_address_post;
        }

        update_post_meta( $post_id, 'sk_geo_latitude', $sk_geo_latitude );
        update_post_meta( $post_id, 'sk_geo_longitude', $sk_geo_longitude );
        update_post_meta( $post_id, 'sk_geo_public', $sk_geo_public );
        update_post_meta( $post_id, 'sk_geo_address', $sk_geo_address );
    }
}
