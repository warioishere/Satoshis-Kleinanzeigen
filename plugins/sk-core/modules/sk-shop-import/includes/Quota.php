<?php

namespace SK\Modules\ShopImport;

defined( 'ABSPATH' ) || exit;

/**
 * Checks before import whether the pack is enough.
 *
 * Dealers are deliberately NOT exempt from the quota: anyone wanting to
 * upload 28 items sees immediately what a bigger pack is good for. This
 * is the most effective sales trigger the import brings with it.
 */
final class Quota {

    /**
     * How many more listings may this vendor create?
     *
     * @return int|null null = unlimited or undeterminable
     */
    public static function remaining( int $vendor_id ): ?int {
        if ( ! class_exists( '\SK\Modules\Subscription\Helper' ) ) {
            return null;
        }

        $remaining = \SK\Modules\Subscription\Helper::get_vendor_remaining_products( $vendor_id );

        if ( $remaining === '' || $remaining === null || $remaining === false ) {
            return null;
        }

        // Unlimited packs report a text string depending on configuration.
        if ( ! is_numeric( $remaining ) ) {
            return null;
        }

        return max( 0, (int) $remaining );
    }

    /**
     * Is the quota enough for this many listings?
     *
     * @return array{ok:bool,remaining:?int,needed:int,missing:int}
     */
    public static function check( int $vendor_id, int $needed ): array {
        $remaining = self::remaining( $vendor_id );

        if ( $remaining === null ) {
            return [ 'ok' => true, 'remaining' => null, 'needed' => $needed, 'missing' => 0 ];
        }

        $missing = max( 0, $needed - $remaining );

        return [
            'ok'        => $missing === 0,
            'remaining' => $remaining,
            'needed'    => $needed,
            'missing'   => $missing,
        ];
    }

    /**
     * Packs that are enough for this many listings — cheapest first.
     *
     * @return array<int,array{id:int,name:string,price:int,products:int,days:int}>
     */
    public static function packs_for( int $needed ): array {
        global $wpdb;

        $ids = $wpdb->get_col(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_pack_validity'"
        );

        $packs = [];
        foreach ( $ids as $id ) {
            $post = get_post( (int) $id );
            if ( ! $post || $post->post_status !== 'publish' ) {
                continue;
            }

            $products = (int) get_post_meta( $id, '_no_of_product', true );
            if ( $products < $needed ) {
                continue;
            }

            $packs[] = [
                'id'       => (int) $id,
                'name'     => $post->post_title,
                'price'    => (int) get_post_meta( $id, '_price', true ),
                'products' => $products,
                'days'     => (int) get_post_meta( $id, '_pack_validity', true ),
            ];
        }

        usort( $packs, static fn( $a, $b ) => $a['price'] <=> $b['price'] );

        return $packs;
    }

    /**
     * Do listings stay online after the pack expires?
     *
     * Controls the notice in the upgrade dialog — and it has to be
     * accurate, or it's a promise the platform doesn't keep.
     */
    public static function listings_stay_online(): bool {
        $option = get_option( 'sk_product_subscription', [] );

        return ( $option['product_status_after_end'] ?? '' ) === 'publish';
    }
}
