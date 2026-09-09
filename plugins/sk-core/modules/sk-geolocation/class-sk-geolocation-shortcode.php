<?php

/**
 * Geolocation Module Shortcodes
 *
 */
class SK_Geolocation_Shortcode {

    /**
     * Class constructor
     *
     *
     * @return void
     */
    public function __construct() {
        add_shortcode( 'sk-geolocation-filter-form', array( $this, 'shortcode' ) );
        add_filter( 'sk_button_shortcodes', array( $this, 'add_to_sk_shortcode_menu' ) );
    }

    /**
     * Geolocation Shortcode
     *
     *
     * @param array $attrs
     *
     * @return string
     */
    public function shortcode( $attrs ) {
        $defaults = array(
            'scope' => '',
            'display' => 'inline',
        );

        $attrs = shortcode_atts( $defaults, $attrs );

        ob_start();
        sk_geo_filter_form( $attrs['scope'], $attrs['display'] );
        return ob_get_clean();
    }

    /**
     * Add Geolocation shortcode
     *
     *
     * @param array $shortcodes
     *
     * @return array
     */
    public function add_to_sk_shortcode_menu( $shortcodes ) {
        $shortcodes['sk-geolocation-filter-form'] = array(
            'title'   => __( 'Geolocation Filter Form', 'sk-core' ),
            'content' => '[sk-geolocation-filter-form]'
        );

        return $shortcodes;
    }
}
