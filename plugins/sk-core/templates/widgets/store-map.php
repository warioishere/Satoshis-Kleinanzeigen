<?php
/**
 * SK Seller Widget Map Content
 *
 *
 */

if ( empty( $map_location ) ) {
    return;
}

?>
    <div class="location-container">
        <div id="sk-store-location"></div>
    </div>
<?php

$source = sk_get_option( 'map_api_source', 'sk_appearance', 'google_maps' );

$location  = explode( ',', $map_location );
$longitude = ! empty( $location[1] ) ? $location[1] : 90.40714300000002;
$latitude  = ! empty( $location[0] ) ? $location[0] : 23.709921;

if ( 'mapbox' === $source ) {
    $access_token = sk_get_option( 'mapbox_access_token', 'sk_appearance', null );

    if ( ! $access_token ) {
        esc_html_e( 'Mapbox Access Token not found', 'sk-core' );

        return;
    }

    sk_get_template_part(
        'widgets/store-map-mapbox', '', [
            'map_location' => $map_location,
            'access_token' => $access_token,
            'location'     => [
                'longitude' => $longitude,
                'latitude'  => $latitude,
                'zoom'      => 10,
            ],
        ]
    );
} else {
    sk_get_template_part(
        'widgets/store-map-google-maps', '', [
            'map_location' => $map_location,
            'location'     => [
                'longitude' => $longitude,
                'latitude'  => $latitude,
                'zoom'      => 15,
            ],
        ]
    );
}
