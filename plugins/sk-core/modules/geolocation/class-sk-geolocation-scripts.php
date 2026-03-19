<?php

/**
 * Geolocation Module Scripts
 *
 */
class SK_Geolocation_Scripts {

    /**
    * @var string
     */
    private $suffix = '';

    /**
     * Class constructor
     *
     *
     * @return void
     */
    public function __construct() {

        add_action( 'wp', array( $this, 'register_styles' ) );
        add_action( 'wp', array( $this, 'register_scripts' ) );

        add_filter( 'sk_google_maps_script_query_args', array( $this, 'add_gmap_script_query_args' ) );
    }

    /**
     * Register module styles
     *
     *
     * @return void
     */
    public function register_styles() {
        wp_register_style( 'sk-geo-locations-map', SK_GEOLOCATION_ASSETS . '/js/sk-geolocation-locations-map' . $this->suffix . '.css', array(), SK_CORE_VERSION );
        wp_register_style( 'sk-geo-filters', SK_GEOLOCATION_ASSETS . '/js/sk-geolocation-filters' . $this->suffix . '.css', array(), SK_CORE_VERSION );
    }

    /**
     * Register module scripts
     *
     *
     * @return void
     */
    public function register_scripts() {
        $source = sk_get_option( 'map_api_source', 'sk_appearance', 'google_maps' );

        $js_src = ( 'mapbox' === $source ) ? '/js/sk-geolocation-locations-map-mapbox' . $this->suffix . '.js' : '/js/sk-geolocation-locations-map-google-maps' . $this->suffix . '.js';

        wp_register_script( 'sk-geo-locations-map', SK_GEOLOCATION_ASSETS . $js_src, array( 'jquery' ), SK_CORE_VERSION, true );

        wp_register_script( 'sk-geo-filters-store-lists', SK_GEOLOCATION_ASSETS . '/js/sk-geolocation-filters' . $this->suffix . '.js', array( 'jquery', 'sk-maps', 'sk-mapbox-suggestions' ), SK_GEOLOCATION_VERSION, true );
        wp_register_script( 'sk-geo-filters', SK_GEOLOCATION_ASSETS . '/js/sk-geolocation-filters' . $this->suffix . '.js', array( 'jquery', 'sk-maps', 'sk-mapbox-suggestions' ), SK_CORE_VERSION, true );
    }

    /**
     * Add google map script url query args
     *
     * Geolocation module requires 'places' library for autocomple feature
     *
     *
     * @param array $query_args
     */
    public function add_gmap_script_query_args( $query_args ) {
        $query_args['libraries'] = 'places';

        return $query_args;
    }
}
