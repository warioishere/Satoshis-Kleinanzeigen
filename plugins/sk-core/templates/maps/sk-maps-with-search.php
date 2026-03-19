<?php

$access_token = sk_get_option( 'mapbox_access_token', 'sk_appearance', null );

if ( ! $access_token ) {
    esc_html_e( 'Mapbox Access Token not found', 'sk-core' );
    return;
}

$map_id   = 'sk-maps-' . wp_rand();
$location = explode( ',', $map_location );

$map_address = ! empty( $map_address ) ? $map_address : '';

if ( empty( $map_location ) && function_exists( 'sk_geo_get_default_location' ) && ! empty( sk_geo_get_default_location() ) ) {
    $default_location = sk_geo_get_default_location();

    $map_address  = ! empty( $default_location['address'] ) ? $default_location['address'] : '';
    $longitude    = ! empty( $default_location['longitude'] ) ? $default_location['longitude'] : 10.0;
    $latitude     = ! empty( $default_location['latitude'] ) ? $default_location['latitude'] : 51.0;
    $map_location = $latitude . ',' . $longitude;
} else {
    $longitude = ! empty( $location[1] ) ? $location[1] : 10.0;
    $latitude  = ! empty( $location[0] ) ? $location[0] : 51.0;
}

sk_get_template(
    'maps/mapbox-with-search.php', array(
        'map_location' => $map_location,
        'map_address'  => $map_address,
        'access_token' => $access_token,
        'map_id'       => $map_id,
        'location'     => array(
            'address'   => $map_address,
            'longitude' => $longitude,
            'latitude'  => $latitude,
            'zoom'      => 12,
        ),
    )
);
