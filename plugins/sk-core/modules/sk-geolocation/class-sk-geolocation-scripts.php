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
        wp_register_script( 'sk-geo-locations-map', SK_GEOLOCATION_ASSETS . '/js/sk-geolocation-locations-map-mapbox' . $this->suffix . '.js', array( 'jquery' ), SK_CORE_VERSION, true );

        wp_register_script( 'sk-geo-filters-store-lists', SK_GEOLOCATION_ASSETS . '/js/sk-geolocation-filters' . $this->suffix . '.js', array( 'jquery', 'sk-maps', 'sk-mapbox-suggestions' ), SK_GEOLOCATION_VERSION, true );
        wp_register_script( 'sk-geo-filters', SK_GEOLOCATION_ASSETS . '/js/sk-geolocation-filters' . $this->suffix . '.js', array( 'jquery', 'sk-maps', 'sk-mapbox-suggestions' ), SK_CORE_VERSION, true );

        // Address lookups go through our own endpoint, so the scripts need the
        // route and a nonce instead of the Mapbox token.
        $geocode = [
            'url'   => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( \SK_Geolocation_Geocode::NONCE ),
        ];

        wp_localize_script( 'sk-geo-filters-store-lists', 'SkGeoGeocode', $geocode );
        wp_localize_script( 'sk-geo-filters', 'SkGeoGeocode', $geocode );
        wp_localize_script( 'sk-geolocation', 'SkGeoGeocode', $geocode );
    }
}
