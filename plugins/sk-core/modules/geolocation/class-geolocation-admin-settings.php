<?php

/**
 * Geolocation Admin Settings
 *
 */
class SK_Geolocation_Admin_Settings {

    /**
     * Class constructor
     *
     *
     * @return void
     */
    public function __construct() {
        add_filter( 'sk_settings_sections', array( $this, 'add_settings_section' ) );
        add_filter( 'sk_settings_fields', array( $this, 'add_settings_fields' ) );
    }

    /**
     * Add admin settings section
     *
     *
     * @param array $sections
     *
     * @return array
     */
    public function add_settings_section( $sections ) {
        $sections['sk_geolocation'] = [
            'id'                   => 'sk_geolocation',
            'title'                => __( 'Geolocation', 'sk' ),
            'icon_url'             => SK_GEOLOCATION_ASSETS . '/images/geolocation.svg',
            'description'          => __( 'Store Location Setup', 'sk' ),
            'document_link'        => 'https://sk.co/docs/wordpress/modules/sk-geolocation/',
            'settings_title'       => __( 'Geolocation Settings', 'sk' ),
            'settings_description' => __( 'You can configure your store location settings and access configuration for vendor store from this settings menu.', 'sk' ),
        ];

        return $sections;
    }

    /**
     * Add admin settings fields
     *
     *
     * @param array $settings_fields
     *
     * @return array
     */
    public function add_settings_fields( $settings_fields ) {
        $settings_fields['sk_geolocation'] = [
            'show_locations_map' => [
                'name'    => 'show_locations_map',
                'label'   => __( 'Location Map Position', 'sk' ),
                'type'    => 'radio',
                'default' => 'top',
                'tooltip' => __( 'Choose where to place the Location Map of your store.', 'sk' ),
                'options' => [
                    'top'   => __( 'Top', 'sk' ),
                    'left'  => __( 'Left', 'sk' ),
                    'right' => __( 'Right', 'sk' ),
                ],
            ],
            'show_location_map_pages' => [
                'name'    => 'show_location_map_pages',
                'label'   => __( 'Show Map', 'sk' ),
                'desc'    => __( 'Select where want to show the map only', 'sk' ),
                'type'    => 'radio',
                'default' => 'all',
                'tooltip' => __( 'Select which pages to display the store map.', 'sk' ),
                'options' => [
                    'all'           => __( 'Both', 'sk' ),
                    'store_listing' => __( 'Store Listing', 'sk' ),
                    'shop'          => __( 'Shop Page', 'sk' ),
                ],
            ],
            'show_filters_before_locations_map' => [
                'name'    => 'show_filters_before_locations_map',
                'label'   => __( 'Show Filters Before Location Map', 'sk' ),
                'desc'    => __( 'Yes', 'sk' ),
                'type'    => 'switcher',
                'default' => 'on',
            ],
            'show_product_location_in_wc_tab' => [
                'name'    => 'show_product_location_in_wc_tab',
                'label'   => __( 'Product Location Tab', 'sk' ),
                'desc'    => __( 'Show location tab in single product page', 'sk' ),
                'type'    => 'switcher',
                'default' => 'on',
            ],
            'distance_unit' => [
                'name'    => 'distance_unit',
                'label'   => __( 'Radius Search - Unit', 'sk' ),
                'type'    => 'radio',
                'default' => 'km',
                'tooltip' => __( 'Set the unit measurement for map radius.', 'sk' ),
                'options' => [
                    'km'    => __( 'Kilometers', 'sk' ),
                    'miles' => __( 'Miles', 'sk' ),
                ],
            ],
            'distance_min' => [
                'name'    => 'distance_min',
                'label'   => __( 'Radius Search - Minimum Distance', 'sk' ),
                'desc'    => __( 'Set minimum distance for radius search.', 'sk' ),
                'type'    => 'number',
                'min'     => 0,
                'default' => 0,
                'tooltip' => __( 'Set the minimum unit distance of the radius.', 'sk' ),
            ],
            'distance_max' => [
                'name'    => 'distance_max',
                'label'   => __( 'Radius Search - Maximum Distance', 'sk' ),
                'desc'    => __( 'Set maximum distance for radius search.', 'sk' ),
                'type'    => 'number',
                'min'     => 1,
                'default' => 10,
                'tooltip' => __( 'Set the maximum unit distance of the radius.', 'sk' ),
            ],
            'map_zoom'     => [
                'name'          => 'map_zoom',
                'label'         => __( 'Map Zoom Level', 'sk' ),
                'desc'          => __( 'To zoom in increase the number, to zoom out decrease the number.', 'sk' ),
                'type'          => 'number',
                'min'           => 1,
                'max'           => 18,
                'default'       => 11,
            ],
            'location' => [
                'name'    => 'location',
                'label'   => __( 'Default Location', 'sk' ),
                'desc'    => __( 'In case the searched store is not found, the default location will be set on the map.', 'sk' ),
                'type'    => 'gmap',
                'default' => [
                    'latitude'  => 23.709921,
                    'longitude' => 90.40714300000002,
                    'address'   => __( 'Dhaka', 'sk' ),
                    'zoom'      => 10,
                ],
            ],
        ];

        return $settings_fields;
    }
}
