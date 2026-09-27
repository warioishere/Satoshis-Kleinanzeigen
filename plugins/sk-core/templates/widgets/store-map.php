<?php
/**
 * SK Seller Widget Map Content
 *
 *
 */

// No map without a location the vendor chose: an empty profile stores ","
// and must not fall back to any place.
$location  = explode( ',', (string) ( $map_location ?? '' ) );
$latitude  = trim( $location[0] ?? '' );
$longitude = trim( $location[1] ?? '' );

if ( ! is_numeric( $latitude ) || ! is_numeric( $longitude ) ) {
    return;
}

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
