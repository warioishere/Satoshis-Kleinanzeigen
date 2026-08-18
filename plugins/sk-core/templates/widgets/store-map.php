<?php
/**
 * SK Seller Widget Map Content
 *
 *
 */

if ( empty( $map_location ) ) {
    return;
}

$location  = explode( ',', $map_location );
$longitude = ! empty( $location[1] ) ? $location[1] : 90.40714300000002;
$latitude  = ! empty( $location[0] ) ? $location[0] : 23.709921;

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
