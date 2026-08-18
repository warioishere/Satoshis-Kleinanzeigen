<?php

namespace SK\Modules\Geolocation;

class Module {

    /**
     * Checks admin has set google map api key
     *
     *
     * @var bool
     */
    public $has_map_api_key = false;

    /**
     * Class constructor
     *
     *
     * @return void
     */
    public function __construct() {
        $sk_appearance = get_option( 'sk_appearance', array() );

        if ( ! empty( $sk_appearance['gmap_api_key'] ) && 'google_maps' === $sk_appearance['map_api_source'] ) {
            $this->has_map_api_key = true;
        } elseif ( ! empty( $sk_appearance['mapbox_access_token'] ) && 'mapbox' === $sk_appearance['map_api_source'] ) {
            $this->has_map_api_key = true;
            add_action( 'wp_footer', array( $this, 'render_mapbox_script' ), 30 );
        }

        $this->define_constants();
        $this->includes();
        $this->hooks();
        $this->instances();

        add_action( 'sk_activated_module_geolocation', array( $this, 'activate' ) );
    }

    /**
     * Module constants
     *
     *
     * @return void
     */
    private function define_constants() {
        define( 'SK_GEOLOCATION_VERSION', SK_CORE_VERSION );
        define( 'SK_GEOLOCATION_PATH', __DIR__ );
        define( 'SK_GEOLOCATION_URL', plugins_url( '', __FILE__ ) );
        define( 'SK_GEOLOCATION_ASSETS', SK_GEOLOCATION_URL . '/assets' );
        define( 'SK_GEOLOCATION_VIEWS', SK_GEOLOCATION_PATH . '/views' );
    }

    /**
     * Add action and filter hooks
     *
     *
     * @return void
     */
    private function hooks() {
        if ( $this->has_map_api_key ) {
            add_action( 'init', array( $this, 'register_scripts' ) );
            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
            add_action( 'sk_widgets', array( $this, 'register_widget' ) );
            add_action( 'sk_new_seller_created', array( $this, 'set_default_geolocation_data' ), 35 );
            add_action( 'woocommerce_product_import_inserted_product_object', array( $this, 'set_product_geo_location_meta_on_import' ), 10, 2 );
            add_action( 'sk_store_profile_saved', array( $this, 'handle_store_profile_saved' ), 10, 3 );
        } else {
            add_filter( 'sk_admin_notices', [ $this, 'admin_notices' ] );
        }
    }

    /**
     * Include module related files
     *
     *
     * @return void
     */
    private function includes() {
        require_once SK_GEOLOCATION_PATH . '/functions.php';
        require_once SK_GEOLOCATION_PATH . '/class-geolocation-admin-settings.php';

        if ( $this->has_map_api_key ) {
            require_once SK_GEOLOCATION_PATH . '/class-sk-geolocation-scripts.php';
            require_once SK_GEOLOCATION_PATH . '/class-sk-geolocation-shortcode.php';
            require_once SK_GEOLOCATION_PATH . '/class-sk-geolocation-widget-filters.php';
            require_once SK_GEOLOCATION_PATH . '/class-sk-geolocation-widget-product-location.php';
            require_once SK_GEOLOCATION_PATH . '/class-sk-geolocation-vendor-dashboard.php';
            require_once SK_GEOLOCATION_PATH . '/class-sk-geolocation-vendor-query.php';
            require_once SK_GEOLOCATION_PATH . '/class-sk-geolocation-vendor-view.php';
            require_once SK_GEOLOCATION_PATH . '/class-sk-geolocation-product-query.php';
            require_once SK_GEOLOCATION_PATH . '/class-sk-geolocation-product-view.php';
            require_once SK_GEOLOCATION_PATH . '/class-sk-geolocation-product-single.php';
            require_once SK_GEOLOCATION_PATH . '/class-sk-geolocation-product-import.php';
            require_once SK_GEOLOCATION_PATH . '/class-sk-geolocation-country-filter.php';
            require_once SK_GEOLOCATION_PATH . '/class-sk-geolocation-full-markers.php';
            require_once SK_GEOLOCATION_PATH . '/class-sk-geolocation-geocode.php';
        }
    }

    /**
     * Create module related class instances
     *
     *
     * @return void
     */
    private function instances() {
        new \SK_Geolocation_Admin_Settings();

        if ( $this->has_map_api_key ) {
            new \SK_Geolocation_Scripts();
            new \SK_Geolocation_Shortcode();
            new \SK_Geolocation_Vendor_Dashboard();
            new \SK_Geolocation_Vendor_Query();
            new \SK_Geolocation_Vendor_View();
            new \SK_Geolocation_Product_Query();
            new \SK_Geolocation_Product_View();
            new \SK_Geolocation_Product_Single();
            new \SK_Geolocation_Product_Import();
            new BlockData();
            new \SK_Geolocation_Country_Filter();
            \SK_Geolocation_Full_Markers::bootstrap();
            new \SK_Geolocation_Geocode();
        }
    }

    /**
     * Run upon module activation
     *
     *
     * @return void
     */
    public function activate() {
        $item = apply_filters(
            'sk_geolocation_activate_item',
            [
                'updating' => 'vendors',
                'paged'    => 1,
            ]
        );

        $this->push_to_queue_processor( $item );
    }

    /**
     * Handle store profile saved event.
     *
     *
     * @param int   $vendor_id           The vendor user ID
     * @param array $sk_settings      The vendor's updated SK settings
     * @param array $prev_sk_settings The vendor's previous SK settings
     *
     * @return void
     */
    public function handle_store_profile_saved( $vendor_id, $sk_settings, $prev_sk_settings ) {
        $prev_location    = sanitize_text_field( wp_unslash( $prev_sk_settings['location'] ?? '' ) );
        $current_location = sanitize_text_field( wp_unslash( $sk_settings['location'] ?? '' ) );

        // If the location is not changed, return early.
        if ( $prev_location === $current_location ) {
            return;
        }

        $item = apply_filters(
            'sk_geolocation_store_profile_saved_item',
            [
                'updating'  => 'vendor_products',
                'vendor_id' => $vendor_id,
                'paged'     => 1,
            ],
            $vendor_id
        );

        // Push the item to the queue.
        $this->push_to_queue_processor( $item );
    }

    /**
     * Reusable method to handle push-to-queue processing
     *
     *
     * @param array $item Queue item with updating type and other parameters
     *
     * @return void
     */
    protected function push_to_queue_processor( $item ) {
        // return if sk plugin is not active
        if ( ! function_exists( 'sk' ) ) {
            return;
        }
        $updater_file = SK_GEOLOCATION_PATH . '/class-sk-geolocation-update-location-data.php';

        include_once $updater_file;
        $processor = new \SK_Geolocation_Update_Location_Data();

        $processor->push_to_queue( $item );
        $processor->save()->dispatch();
    }

    public function register_scripts() {
        [ $suffix, $version ] = sk_get_script_suffix_and_version();

        wp_register_style( 'sk-geolocation', SK_GEOLOCATION_ASSETS . '/js/geolocation' . $suffix . '.css', array(), $version );

        wp_register_script( 'sk-geolocation', SK_GEOLOCATION_ASSETS . '/js/sk-geolocation-product-editor-mapbox.js', array( 'jquery', 'sk-maps' ), $version, true );

    }

    /**
     * Enqueue module scripts
     *
     *
     * @return void
     */
    public function enqueue_scripts() {
        global $wp;
        if (
            is_shop()
            || sk_is_store_listing()
            || is_product_category()
            || is_product_tag()
            || ( isset( $wp->query_vars['products'] ) && isset( $_GET['action'] ) && 'edit' === sanitize_text_field( wp_unslash( $_GET['action'] ) ) ) //phpcs:ignore
            || ( isset( $wp->query_vars['booking'] ) && ( ( 'edit' === $wp->query_vars['booking'] ) || ( 'new-product' === $wp->query_vars['booking'] ) ) )
            || ( isset( $wp->query_vars['auction'] ) && isset( $_GET['action'] ) && 'edit' === sanitize_text_field( wp_unslash( $_GET['action'] ) ) ) //phpcs:ignore
        ) {
            wp_enqueue_style( 'sk-geolocation' );
            wp_enqueue_script( 'sk-geolocation' );
        }

        if ( sk_is_store_listing() ) {
            wp_enqueue_script( 'sk-geo-filters-store-lists' );
            wp_enqueue_style( 'sk-geo-filters' );
        }
    }

    /**
     * Register module widgets
     *
     *
     * @param array $widgets List of widgets to be registered
     *
     * @return array
     */
    public function register_widget( array $widgets ): array {
        $widgets[ \SK_Geolocation_Widget_Filters::INSTANCE_KEY ] = \SK_Geolocation_Widget_Filters::class;
        $widgets[ \SK_Geolocation_Widget_Product_Location::INSTANCE_KEY ] = \SK_Geolocation_Widget_Product_Location::class;
        return $widgets;
    }

    /**
     * Show admin notices
     *
     *
     * @param array $notices
     *
     * @return array
     */
    public function admin_notices( $notices ) {
        $notices[] = [
            'type'        => 'alert',
            'title'       => __( 'SK Geolocation module is almost ready!', 'sk' ),
            'description' => __( 'SK <strong> Geolocation Module</strong> requires Google Map API Key or Mapbox Access Token. Please set your API Key or Token in <strong>SK Admin Settings > Appearance</strong>.', 'sk' ),
            'priority'    => 10,
            'actions'     => [
                [
                    'type'   => 'primary',
                    'text'   => __( 'Go to Settings', 'sk' ),
                    'action'  => add_query_arg( array( 'page' => 'sk#/settings' ), admin_url( 'admin.php' ) ),
                ],
            ],
        ];

        return $notices;
    }

    /**
     * Show mapbox some extra scripts only for RTL
     *
     *
     * @return void
     */
    public function render_mapbox_script() {
        if ( is_rtl() ) {
            ?>
            <style type="text/css">
                .mapboxgl-map {
                    text-align: inherit;
                }
            </style>
            <?php
        }
    }

    /**
     * Geolocation data add when new seller
     *
     *
     * @param $user_id
     */
    public function set_default_geolocation_data( $user_id ) {
        $default_locations = sk_get_option( 'location', 'sk_geolocation' );

        if ( ! is_array( $default_locations ) || empty( $default_locations ) ) {
            $default_locations = array(
                'latitude'  => '',
                'longitude' => '',
                'address'   => '',
            );
        }

        update_user_meta( $user_id, 'sk_geo_latitude', $default_locations['latitude'] );
        update_user_meta( $user_id, 'sk_geo_longitude', $default_locations['longitude'] );
        update_user_meta( $user_id, 'sk_geo_public', 1 );
        update_user_meta( $user_id, 'sk_geo_address', $default_locations['address'] );

        $sk_settings   = get_user_meta( $user_id, 'sk_profile_settings', true );
        $default_location = '';

        if ( ! empty( $default_locations['latitude'] ) && ! empty( $default_locations['longitude'] ) ) {
            $default_location = $default_locations['latitude'] . ',' . $default_locations['longitude'];
        }

        $sk_settings['location']     = $default_location;
        $sk_settings['find_address'] = $default_locations['address'];

        update_user_meta( $user_id, 'sk_profile_settings', $sk_settings );
    }

    /**
     * Set product geo location meta information on product import
     *
     *
     * @param WC_Product $product
     * @param array $csv_line_item   product line item data
     *
     * @return array $product
     */
    public function set_product_geo_location_meta_on_import( $product, $csv_line_item ) {
        if ( substr( wp_get_referer(), 0, strlen( get_admin_url() ) ) === get_admin_url() ) {
            return;
        }

        if ( ! is_a( $product, 'WC_Product' ) ) {
            return;
        }

        $need_to_add_geo_data = false;

        // check if we are inserting a product, in that case, insert geo location data
        if ( empty( $csv_line_item['id'] ) ) {
            $need_to_add_geo_data = true;
        }

        // check if geo location meta exists
        if ( false === $need_to_add_geo_data && ! empty( $csv_line_item['meta_data'] ) ) {
            $meta_data = array_column( $csv_line_item['meta_data'], 'value', 'key' );
            $sk_geo_meta = [ 'sk_geo_latitude', 'sk_geo_longitude' ];

            foreach ( $sk_geo_meta as $meta_key ) {
                if ( array_key_exists( $meta_key, $meta_data ) && empty( $meta_data[ $meta_key ] ) ) {
                    // if meta key exists and is empty, we need to insert geo data
                    $need_to_add_geo_data = true;
                    break;
                }
            }
        }

        if ( ! $need_to_add_geo_data ) {
            return;
        }

        $user_id = get_post_field( 'post_author', $product->get_id() );

        //initialize vendor geo location if available
        $sk_geo_latitude  = get_user_meta( $user_id, 'sk_geo_latitude', true );
        $sk_geo_longitude = get_user_meta( $user_id, 'sk_geo_longitude', true );
        $sk_geo_address   = get_user_meta( $user_id, 'sk_geo_address', true );

        // No coordinates on the vendor means the listing has no location. It
        // used to inherit the module default here, which put listings on the
        // map in places nobody had chosen.
        if ( empty( $sk_geo_latitude ) || empty( $sk_geo_longitude ) ) {
            delete_post_meta( $product->get_id(), 'sk_geo_latitude' );
            delete_post_meta( $product->get_id(), 'sk_geo_longitude' );
            delete_post_meta( $product->get_id(), 'sk_geo_address' );

            return;
        }

        update_post_meta( $product->get_id(), 'sk_geo_latitude', $sk_geo_latitude );
        update_post_meta( $product->get_id(), 'sk_geo_longitude', $sk_geo_longitude );
        update_post_meta( $product->get_id(), 'sk_geo_public', 1 );
        update_post_meta( $product->get_id(), 'sk_geo_address', $sk_geo_address );
    }
}
