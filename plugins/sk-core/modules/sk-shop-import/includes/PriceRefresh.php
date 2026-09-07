<?php

namespace SK\Modules\ShopImport;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps the Sats prices of fiat listings up to date.
 *
 * A shop prices in euros. If the Sats amount computed at import time just
 * stayed put, a 169-euro item would end up visibly mispriced after a few
 * percent of rate movement. The fiat amount is the source of truth, the
 * Sats amount gets refreshed to match.
 */
final class PriceRefresh {

    const HOOK = 'sk_shop_import_refresh_prices';

    /** Per run, so a large catalog doesn't blow past the cron's time budget. */
    const BATCH = 200;

    public function __construct() {
        add_action( self::HOOK, [ __CLASS__, 'run' ] );

        if ( ! wp_next_scheduled( self::HOOK ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK );
        }
    }

    /**
     * @return array{checked:int,updated:int}
     */
    public static function run(): array {
        $result = [ 'checked' => 0, 'updated' => 0 ];

        $ids = get_posts(
            [
                'post_type'      => 'product',
                'post_status'    => [ 'publish', 'draft' ],
                'posts_per_page' => self::BATCH,
                'fields'         => 'ids',
                'meta_key'       => Importer::META_FIAT,
                'orderby'        => 'modified',
                'order'          => 'ASC',
            ]
        );

        foreach ( $ids as $id ) {
            $result['checked']++;

            $fiat     = (float) get_post_meta( $id, Importer::META_FIAT, true );
            $currency = (string) get_post_meta( $id, Importer::META_CURRENCY, true ) ?: 'EUR';

            if ( $fiat <= 0 ) {
                continue;
            }

            $sats = Rate::to_sats( $fiat, $currency );
            if ( is_wp_error( $sats ) ) {
                // Rate source unavailable: better the old price than none at all.
                break;
            }

            $current = (int) get_post_meta( $id, '_price', true );
            if ( $current !== (int) $sats ) {
                update_post_meta( $id, '_regular_price', (string) $sats );
                update_post_meta( $id, '_price', (string) $sats );
                $result['updated']++;
            }

            self::refresh_variants( (int) $id, $currency );
        }

        return $result;
    }

    /**
     * Refresh the variants too — otherwise instant checkout would later be
     * correct for the main variant but not for the rest.
     */
    private static function refresh_variants( int $post_id, string $currency ): void {
        $variants = get_post_meta( $post_id, Importer::META_VARIANTS, true );
        if ( ! is_array( $variants ) || empty( $variants ) ) {
            return;
        }

        $changed = false;
        foreach ( $variants as &$variant ) {
            $fiat = (float) ( $variant['price'] ?? 0 );
            if ( $fiat <= 0 ) {
                continue;
            }

            $sats = Rate::to_sats( $fiat, (string) ( $variant['currency'] ?? $currency ) );
            if ( is_wp_error( $sats ) ) {
                continue;
            }

            if ( (int) ( $variant['sats'] ?? 0 ) !== (int) $sats ) {
                $variant['sats'] = (int) $sats;
                $changed         = true;
            }
        }
        unset( $variant );

        if ( $changed ) {
            update_post_meta( $post_id, Importer::META_VARIANTS, $variants );
        }
    }

    public static function unschedule(): void {
        $next = wp_next_scheduled( self::HOOK );
        if ( $next ) {
            wp_unschedule_event( $next, self::HOOK );
        }
    }
}
