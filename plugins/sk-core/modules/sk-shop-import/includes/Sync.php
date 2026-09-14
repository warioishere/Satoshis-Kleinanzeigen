<?php

namespace SK\Modules\ShopImport;

defined( 'ABSPATH' ) || exit;

/**
 * Keep an imported catalog in step with the shop it came from.
 *
 * A one-off import ages: prices move, articles sell out, new ones appear.
 * Once a night the shop is asked again and the listings are brought up to
 * date — the same path the manual import takes, so nothing behaves
 * differently just because a cron started it.
 *
 * It only ever touches listings that are already here. A dealer with a
 * quota smaller than their shop picked what to bring over; a nightly run
 * that imported everything would undo that choice and walk straight past
 * the quota. What is new in the shop is only counted and reported, so the
 * dealer can decide.
 *
 * What is gone in the shop is set to draft, never deleted. A shop that
 * answers oddly for one night would otherwise wipe out a catalogue, and a
 * draft can be brought back by hand.
 *
 * It never publishes either: a listing the dealer drafted stays drafted.
 * The run can therefore not raise the number of published listings, and
 * the pack limit holds without the run having to know about it.
 *
 * Only the largest package, and only after the dealer switches it on: the
 * run fetches from an outside host and writes to their listings.
 */
final class Sync {

    const HOOK = 'sk_shop_import_sync';

    /** User meta: the dealer wants the nightly run. */
    const META_ON = '_sk_import_sync';

    /** User meta: report of the last run, for the dashboard. */
    const META_LAST = '_sk_import_sync_last';

    /** The rung of the ladder this belongs to, see Variants::LADDER. */
    const PACK_GROUP = 'wal';

    /** Shops per run, so one night's cron stays bounded. */
    const SHOPS_PER_RUN = 3;

    /** Seconds one run may take before it stops and leaves the rest. */
    const TIME_BUDGET = 90;

    public function __construct() {
        add_action( self::HOOK, [ __CLASS__, 'run' ] );

        if ( ! wp_next_scheduled( self::HOOK ) ) {
            wp_schedule_event( time() + 2 * HOUR_IN_SECONDS, 'daily', self::HOOK );
        }
    }

    /**
     * Does this package include the nightly run?
     */
    public static function pack_allows( int $pack_id ): bool {
        return Variants::from_group( $pack_id, self::PACK_GROUP );
    }

    public static function allowed( int $vendor_id = 0 ): bool {
        $vendor_id = $vendor_id ?: get_current_user_id();

        if ( ! $vendor_id ) {
            return false;
        }

        return self::pack_allows( (int) get_user_meta( $vendor_id, 'product_package_id', true ) );
    }

    /**
     * Is the nightly run switched on for this dealer, and may it run?
     */
    public static function active( int $vendor_id ): bool {
        return self::allowed( $vendor_id )
            && '1' === (string) get_user_meta( $vendor_id, self::META_ON, true )
            && Dealer::may_import( $vendor_id )
            && '' !== (string) get_user_meta( $vendor_id, DashboardPage::META_FETCH_URL, true );
    }

    /**
     * Dealers whose catalog is due, oldest run first.
     *
     * @return int[]
     */
    public static function due(): array {
        $ids = get_users(
            [
                'meta_key'   => self::META_ON, // phpcs:ignore WordPress.DB.SlowDBQuery
                'meta_value' => '1',           // phpcs:ignore WordPress.DB.SlowDBQuery
                'fields'     => 'ID',
                'number'     => 100,
            ]
        );

        $ids = array_values( array_filter( array_map( 'intval', $ids ), [ __CLASS__, 'active' ] ) );

        usort( $ids, static function ( int $a, int $b ): int {
            $one = (array) get_user_meta( $a, self::META_LAST, true );
            $two = (array) get_user_meta( $b, self::META_LAST, true );

            return ( (int) ( $one['at'] ?? 0 ) ) <=> ( (int) ( $two['at'] ?? 0 ) );
        } );

        return array_slice( $ids, 0, self::SHOPS_PER_RUN );
    }

    /**
     * The nightly pass.
     *
     * @return array<int,array>
     */
    public static function run(): array {
        $started = time();
        $reports = [];

        foreach ( self::due() as $vendor_id ) {
            if ( time() - $started > self::TIME_BUDGET ) {
                break;
            }

            $reports[ $vendor_id ] = self::shop( $vendor_id );
        }

        return $reports;
    }

    /**
     * One dealer's catalog.
     *
     * @return array{at:int,fetched:int,updated:int,drafted:int,fresh:int,error:string}
     */
    public static function shop( int $vendor_id ): array {
        $report = [ 'at' => time(), 'fetched' => 0, 'updated' => 0, 'drafted' => 0, 'fresh' => 0, 'error' => '' ];

        $shop = (string) get_user_meta( $vendor_id, DashboardPage::META_FETCH_URL, true );
        $catalog = Source::fetch( $shop );

        if ( is_wp_error( $catalog ) ) {
            $report['error'] = $catalog->get_error_message();
            update_user_meta( $vendor_id, self::META_LAST, $report );

            return $report;
        }

        $items = 'woo' === $catalog['source']
            ? Woo::build( $catalog['products'] )
            : Shopify::build( $catalog['products'] );

        if ( empty( $items ) ) {
            $report['error'] = __( 'Der Shop hat nichts geliefert.', 'sk-core' );
            update_user_meta( $vendor_id, self::META_LAST, $report );

            return $report;
        }

        $report['fetched'] = count( $items );

        $here  = self::listings( $vendor_id );
        $known = [];

        foreach ( $items as $item ) {
            $key = (string) ( $item['key'] ?? '' );

            if ( '' !== $key && isset( $here[ $vendor_id . ':' . $key ] ) ) {
                $known[] = $item;
            }
        }

        $report['fresh'] = count( $items ) - count( $known );

        if ( ! empty( $known ) ) {
            /*
             * No fresh images on a nightly run: a catalog of a few hundred
             * articles would pull that many files every night, and the
             * pictures are the part that hardly ever changes.
             */
            $result = Importer::run(
                $known,
                [
                    'vendor_id'    => $vendor_id,
                    'currency'     => Settings::currency( $vendor_id )['currency'] ?? 'EUR',
                    'default_cat'  => Settings::default_category( $vendor_id ),
                    'category_map' => Settings::category_map( $vendor_id ),
                    'image_cap'    => 0,
                    'status'       => 'keep',
                    'source'       => 'sync',
                ]
            );

            $report['updated'] = (int) $result['updated'];
        }

        $report['drafted'] = self::draft_missing( $here, $items, $vendor_id );

        update_user_meta( $vendor_id, self::META_LAST, $report );

        return $report;
    }

    /**
     * The listings this dealer holds that came from an import, by key.
     *
     * @return array<string,int> key => post id
     */
    private static function listings( int $vendor_id ): array {
        $ids = get_posts(
            [
                'post_type'      => 'product',
                'post_status'    => [ 'publish', 'draft', 'pending' ],
                'author'         => $vendor_id,
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_key'       => Importer::META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery
            ]
        );

        $map = [];

        foreach ( $ids as $id ) {
            $key = (string) get_post_meta( (int) $id, Importer::META_KEY, true );

            if ( '' !== $key ) {
                $map[ $key ] = (int) $id;
            }
        }

        return $map;
    }

    /**
     * Listings whose article is no longer in the shop go to draft.
     *
     * Only listings an import created are touched — anything the dealer
     * typed in here has no import key and stays where it is.
     *
     * @param array<string,int> $here  Imported listings by key.
     * @param array<int,array>  $items What the shop currently offers.
     */
    private static function draft_missing( array $here, array $items, int $vendor_id ): int {
        $seen = [];

        foreach ( $items as $item ) {
            $key = (string) ( $item['key'] ?? '' );

            if ( '' !== $key ) {
                $seen[ $vendor_id . ':' . $key ] = true;
            }
        }

        if ( empty( $seen ) ) {
            return 0;
        }

        $drafted = 0;

        foreach ( $here as $key => $id ) {
            if ( isset( $seen[ $key ] ) || 'publish' !== get_post_status( $id ) ) {
                continue;
            }

            wp_update_post( [ 'ID' => $id, 'post_status' => 'draft' ] );
            $drafted++;
        }

        return $drafted;
    }
}
