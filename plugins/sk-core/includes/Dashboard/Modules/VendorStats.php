<?php

namespace SK\Core\Dashboard\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * What a vendor's listings actually do.
 *
 * Views and contact clicks have been counted all along, but only the
 * operator could see them. Without them a vendor has no way of telling a
 * listing nobody finds from one people look at and walk away from — the
 * first needs a better title, the second a better price or picture.
 *
 * Deliberately for everyone, not a package feature: the numbers cost us
 * nothing and a private seller needs them just as much.
 */
class VendorStats {

    /** Listings in the table below the figures. */
    const TOP_LISTINGS = 5;

    /** Days the contact figure covers. */
    const WINDOW_DAYS = 30;

    public function __construct() {
        // After the welcome box, which sits on the same hook at 10.
        add_action( 'sk_dashboard_before_widgets', [ $this, 'render' ], 20 );
    }

    public function render(): void {
        $vendor_id = sk_get_current_user_id();

        if ( ! $vendor_id || ! function_exists( 'sk_is_user_seller' ) || ! sk_is_user_seller( $vendor_id ) ) {
            return;
        }

        $stats = self::collect( $vendor_id );

        // Nothing listed yet: the empty table would say nothing the
        // onboarding notices do not already say better.
        if ( 0 === $stats['listings'] ) {
            return;
        }

        sk_get_template_part( 'dashboard/vendor-stats', '', $stats );
    }

    /**
     * @return array{listings:int,drafts:int,allowed:?int,views:int,contacts:int,window:int,top:array<int,array>}
     */
    public static function collect( int $vendor_id ): array {
        $mine = self::listing_ids( $vendor_id );

        $views    = self::views( $mine['all'] );
        $from     = gmdate( 'Y-m-d', time() - ( self::WINDOW_DAYS - 1 ) * DAY_IN_SECONDS );
        $to       = gmdate( 'Y-m-d' );
        $contacts = [];
        $total    = 0;

        if ( class_exists( \SK\Modules\ContactClicks\Stats::class ) ) {
            $contacts = \SK\Modules\ContactClicks\Stats::vendor_by_product( $vendor_id, $from, $to );
            $total    = \SK\Modules\ContactClicks\Stats::vendor_totals( $vendor_id, $from, $to )['clicks'];
        }

        return [
            'listings' => count( $mine['published'] ),
            'drafts'   => count( $mine['all'] ) - count( $mine['published'] ),
            'allowed'  => self::allowed( $vendor_id ),
            'views'    => array_sum( $views ),
            'contacts' => $total,
            'window'   => self::WINDOW_DAYS,
            'top'      => self::top( $mine['published'], $views, $contacts ),
        ];
    }

    /**
     * @return array{all:int[],published:int[]}
     */
    private static function listing_ids( int $vendor_id ): array {
        $all = get_posts(
            [
                'post_type'      => 'product',
                'post_status'    => [ 'publish', 'draft', 'pending' ],
                'author'         => $vendor_id,
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
            ]
        );

        $all       = array_map( 'intval', $all );
        $published = array_values( array_filter( $all, static fn( $id ) => 'publish' === get_post_status( $id ) ) );

        return [ 'all' => $all, 'published' => $published ];
    }

    /**
     * Views per listing, in one query rather than one per listing.
     *
     * @param int[] $ids
     *
     * @return array<int,int>
     */
    private static function views( array $ids ): array {
        global $wpdb;

        if ( empty( $ids ) ) {
            return [];
        }

        $in   = implode( ',', array_map( 'intval', $ids ) );
        $rows = $wpdb->get_results(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- ids are cast to int above.
            "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'pageview' AND post_id IN ({$in})",
            ARRAY_A
        );

        $out = [];

        foreach ( (array) $rows as $r ) {
            $out[ (int) $r['post_id'] ] = (int) $r['meta_value'];
        }

        return $out;
    }

    /**
     * How many listings the package allows, or null when unlimited.
     */
    private static function allowed( int $vendor_id ): ?int {
        $pack = (int) get_user_meta( $vendor_id, 'product_package_id', true );

        if ( ! $pack ) {
            return null;
        }

        $count = (int) get_post_meta( $pack, '_no_of_product', true );

        return -1 === $count || $count <= 0 ? null : $count;
    }

    /**
     * The most seen listings, contacts alongside.
     *
     * Ordered by views: that is the number the vendor can act on, and a
     * listing seen often but never contacted is exactly the one worth
     * looking at.
     *
     * @param int[]           $ids
     * @param array<int,int>  $views
     * @param array<int,int>  $contacts
     *
     * @return array<int,array{id:int,title:string,url:string,views:int,contacts:int}>
     */
    private static function top( array $ids, array $views, array $contacts ): array {
        $rows = [];

        foreach ( $ids as $id ) {
            $rows[] = [
                'id'       => $id,
                'title'    => get_the_title( $id ),
                'url'      => (string) get_permalink( $id ),
                'views'    => (int) ( $views[ $id ] ?? 0 ),
                'contacts' => (int) ( $contacts[ $id ] ?? 0 ),
            ];
        }

        usort( $rows, static fn( $a, $b ) => [ $b['views'], $b['contacts'] ] <=> [ $a['views'], $a['contacts'] ] );

        return array_slice( $rows, 0, self::TOP_LISTINGS );
    }
}
