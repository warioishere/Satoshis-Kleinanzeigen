<?php

namespace SK\Modules\NostrMarket;

defined( 'ABSPATH' ) || exit;

/**
 * Manages NIP-15 Stall events (Kind 30017).
 * Each vendor gets one stall, created on first product publish.
 */
class StallManager {

    const META_KEY = '_sk_nostr_stall_event_id';

    /**
     * Ensure a vendor has a stall on Nostr. Creates one if missing.
     *
     * @param int $vendor_id WordPress user ID.
     * @return string|null   Stall event ID, or null on failure.
     */
    public static function ensure_stall( int $vendor_id ): ?string {
        $existing = get_user_meta( $vendor_id, self::META_KEY, true );
        if ( ! empty( $existing ) ) {
            return $existing;
        }

        return self::publish_stall( $vendor_id );
    }

    /**
     * Publish or update a vendor's stall.
     */
    public static function publish_stall( int $vendor_id ): ?string {
        $store_info = function_exists( 'sk_get_store_info' ) ? sk_get_store_info( $vendor_id ) : [];
        $user       = get_userdata( $vendor_id );

        $store_name = $store_info['store_name'] ?? ( $user ? $user->display_name : 'Store #' . $vendor_id );

        // Vendor location from store profile.
        $city    = $store_info['address']['city'] ?? '';
        $country = $store_info['address']['country'] ?? '';
        $location_parts = array_filter( [ $city, $country ] );
        $location = implode( ', ', $location_parts );

        // Build description: bio + location.
        $store_desc = '';
        if ( ! empty( $store_info['vendor_biography'] ) ) {
            $store_desc = wp_strip_all_tags( $store_info['vendor_biography'] );
        }
        if ( $location ) {
            $store_desc = ( $store_desc ? $store_desc . "\n\n" : '' ) . 'Standort: ' . $location;
        }

        $currency = sk_get_option( 'sk_nostr_market_currency', 'sk_nostr_market', 'sat' );

        // Shipping regions: use vendor country if set, otherwise global setting.
        $regions = sk_get_option( 'sk_nostr_market_shipping_regions', 'sk_nostr_market', 'EU,CH' );
        $region_list = array_map( 'trim', explode( ',', $regions ) );
        if ( $country && ! in_array( $country, $region_list, true ) ) {
            array_unshift( $region_list, $country );
        }

        $stall_id = 'vendor-' . $vendor_id;

        $stall_data = [
            'id'          => $stall_id,
            'name'        => $store_name,
            'description' => $store_desc,
            'currency'    => $currency,
            'shipping'    => [
                [
                    'id'      => 'pickup',
                    'name'    => 'Abholung' . ( $city ? ' in ' . $city : '' ),
                    'cost'    => 0,
                    'regions' => [ $country ?: '*' ],
                ],
                [
                    'id'      => 'shipping',
                    'name'    => 'Versand',
                    'cost'    => 0,
                    'regions' => $region_list,
                ],
            ],
        ];

        $content = wp_json_encode( $stall_data );

        $tags = [
            [ 'd', $stall_id ],
        ];

        $event_id = EventSender::send( 30017, $content, $tags );

        if ( $event_id ) {
            update_user_meta( $vendor_id, self::META_KEY, $event_id );
        }

        return $event_id;
    }
}
