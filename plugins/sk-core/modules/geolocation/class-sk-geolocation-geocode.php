<?php

defined( 'ABSPATH' ) || exit;

/**
 * Server-side geocoding proxy.
 *
 * Address lookups used to run in the visitor's browser with the site's Mapbox
 * token in the page. That handed the token to anyone who viewed the source,
 * spent our quota one keystroke at a time, and sent every typed address plus
 * the visitor's IP straight to Mapbox.
 *
 * The lookup happens here instead: the token stays on the server, repeated
 * queries are answered from cache, and the endpoint is bounded.
 *
 * The map itself still needs a browser token to draw tiles — that one cannot
 * be proxied and remains in SkGeo.
 */
class SK_Geolocation_Geocode {

    const NONCE  = 'sk_geo_geocode';
    const CACHE  = 12 * HOUR_IN_SECONDS;
    const MAX_PER_MINUTE = 30;

    public function __construct() {
        add_action( 'wp_ajax_sk_geo_geocode', [ $this, 'handle' ] );
        add_action( 'wp_ajax_nopriv_sk_geo_geocode', [ $this, 'handle' ] );
    }

    public function handle() {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! $this->rate_allows() ) {
            wp_send_json_error( [ 'message' => __( 'Zu viele Anfragen.', 'sk-core' ) ] );
        }

        $token = sk_get_option( 'mapbox_access_token', 'sk_appearance', '' );

        if ( empty( $token ) ) {
            wp_send_json_error( [ 'message' => __( 'Geocoding nicht konfiguriert.', 'sk-core' ) ] );
        }

        $reverse = isset( $_POST['lat'], $_POST['lng'] );
        $query   = $this->read_query();

        if ( '' === $query ) {
            wp_send_json_success( [ 'features' => [] ] );
        }

        $cache_key = 'sk_geo_gc_' . md5( $query );
        $cached    = get_transient( $cache_key );

        if ( false !== $cached ) {
            wp_send_json_success( [ 'features' => $cached, 'cached' => true ] );
        }

        $args = [ 'access_token' => $token ];

        // Mapbox rejects 'limit' on a reverse lookup unless a single type is
        // given, and 'autocomplete' means nothing there.
        if ( ! $reverse ) {
            $args['autocomplete'] = 'true';
            $args['limit']        = 5;
        }

        $url = add_query_arg(
            $args,
            'https://api.mapbox.com/geocoding/v5/mapbox.places/' . rawurlencode( $query ) . '.json'
        );

        // The token is URL-restricted in the Mapbox account, which is exactly
        // how a public token should be set up — but it means Mapbox checks the
        // Referer. A server request has none, so we send our own site URL.
        $response = wp_remote_get( $url, [
            'timeout' => 6,
            'headers' => [
                'Accept'  => 'application/json',
                'Referer' => home_url( '/' ),
            ],
        ] );

        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            wp_send_json_error( [ 'message' => __( 'Ort konnte nicht aufgelöst werden.', 'sk-core' ) ] );
        }

        $body     = json_decode( wp_remote_retrieve_body( $response ), true );
        $features = $this->trim_features( $body['features'] ?? [] );

        set_transient( $cache_key, $features, self::CACHE );

        wp_send_json_success( [ 'features' => $features ] );
    }

    /**
     * Either a text search or "lng,lat" for a reverse lookup.
     */
    private function read_query(): string {
        $lng = isset( $_POST['lng'] ) ? sk_geo_float_val( wp_unslash( $_POST['lng'] ) ) : null;
        $lat = isset( $_POST['lat'] ) ? sk_geo_float_val( wp_unslash( $_POST['lat'] ) ) : null;

        if ( null !== $lng && null !== $lat && ( $lng || $lat ) ) {
            if ( abs( $lat ) > 90 || abs( $lng ) > 180 ) {
                return '';
            }

            return $lng . ',' . $lat;
        }

        $search = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';

        return mb_substr( trim( $search ), 0, 120 );
    }

    /**
     * Only what the address picker actually renders leaves the server.
     */
    private function trim_features( array $features ): array {
        $out = [];

        foreach ( array_slice( $features, 0, 5 ) as $feature ) {
            if ( empty( $feature['place_name'] ) || empty( $feature['geometry']['coordinates'] ) ) {
                continue;
            }

            $out[] = [
                'place_name' => (string) $feature['place_name'],
                'geometry'   => [
                    'type'        => 'Point',
                    'coordinates' => [
                        (float) $feature['geometry']['coordinates'][0],
                        (float) $feature['geometry']['coordinates'][1],
                    ],
                ],
            ];
        }

        return $out;
    }

    private function rate_allows(): bool {
        $ip    = function_exists( 'sk_get_client_ip' ) ? sk_get_client_ip() : '';
        $key   = 'sk_geo_gc_rate_' . md5( $ip !== '' ? $ip : 'unknown' );
        $count = (int) get_transient( $key );

        if ( $count >= self::MAX_PER_MINUTE ) {
            return false;
        }

        set_transient( $key, $count + 1, MINUTE_IN_SECONDS );

        return true;
    }
}
