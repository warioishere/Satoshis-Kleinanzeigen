<?php

namespace SK\Core\Vendor;

defined( 'ABSPATH' ) || exit;

/**
 * Suspending and un-suspending vendors.
 *
 * Suspending drafts every published listing and blocks publishing new ones (see
 * SuspensionGuard). The drafted IDs are recorded so that lifting a suspension
 * only republishes what it actually took offline — listings the vendor drafted
 * themselves stay drafted.
 *
 * A vendor can be suspended by more than one source at a time: anti-fraud and
 * unpaid commissions are independent reasons. Each source is tracked separately
 * and the vendor stays suspended while any of them remains, so paying a
 * commission cannot lift a fraud suspension.
 *
 * Lives in core because two modules need it. Before, anti-fraud and the
 * commission enforcement each had their own half of this, with separate meta and
 * separate product lists.
 */
final class Suspension {

    /** User meta: [ source => [ 'reason' => string, 'since' => mysql date ] ]. */
    const META_SOURCES = 'sk_suspended_sources';

    /** User meta: product IDs this suspension drafted. */
    const META_DRAFTED = 'sk_suspended_products';

    /** Legacy single-flag meta from the anti-fraud-only implementation. */
    const LEGACY_SUSPENDED = 'sk_auto_suspended';
    const LEGACY_REASON    = 'sk_auto_suspended_reason';
    const LEGACY_SINCE     = 'sk_auto_suspended_at';
    const LEGACY_DRAFTED   = 'sk_auto_suspended_products';

    /** Known sources, for readable labels. */
    const SOURCE_ANTI_FRAUD = 'anti_fraud';
    const SOURCE_COMMISSION = 'commission';

    public static function is_suspended( int $user_id ): bool {
        return ! empty( self::sources( $user_id ) );
    }

    /**
     * Active suspension sources of a vendor.
     *
     * @return array [ source => [ 'reason' => string, 'since' => string ] ]
     */
    public static function sources( int $user_id ): array {
        self::migrate_legacy( $user_id );

        $sources = get_user_meta( $user_id, self::META_SOURCES, true );

        return is_array( $sources ) ? $sources : [];
    }

    /**
     * Is the vendor suspended by this particular source?
     */
    public static function is_suspended_by( int $user_id, string $source ): bool {
        return isset( self::sources( $user_id )[ $source ] );
    }

    /**
     * Suspend a vendor for a given reason.
     *
     * Idempotent per source. Listings are only drafted the first time, so a
     * second source does not overwrite the record of what went offline.
     *
     * @param int    $user_id
     * @param string $source  One of the SOURCE_* constants.
     * @param string $reason  Free-text note for the admin view.
     * @return int Number of listings taken offline by this call.
     */
    public static function suspend( int $user_id, string $source, string $reason = '' ): int {
        if ( ! $user_id || $source === '' ) {
            return 0;
        }

        $sources = self::sources( $user_id );
        $first   = empty( $sources );

        $sources[ $source ] = [
            'reason' => $reason,
            'since'  => current_time( 'mysql' ),
        ];

        update_user_meta( $user_id, self::META_SOURCES, $sources );

        // Already offline — nothing more to draft, and the existing record of
        // drafted listings must be kept.
        if ( ! $first ) {
            return 0;
        }

        $product_ids = get_posts( [
            'author'         => $user_id,
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ] );

        $drafted = [];

        foreach ( $product_ids as $product_id ) {
            $result = wp_update_post( [ 'ID' => (int) $product_id, 'post_status' => 'draft' ], true );

            if ( ! is_wp_error( $result ) ) {
                $drafted[] = (int) $product_id;
            }
        }

        update_user_meta( $user_id, self::META_DRAFTED, $drafted );

        return count( $drafted );
    }

    /**
     * Lift one source. The vendor comes back online only when none remain.
     *
     * @param int    $user_id
     * @param string $source
     * @return int Number of listings put back online.
     */
    public static function unsuspend( int $user_id, string $source ): int {
        if ( ! $user_id || $source === '' ) {
            return 0;
        }

        $sources = self::sources( $user_id );

        unset( $sources[ $source ] );

        // Still suspended for another reason: keep everything offline.
        if ( ! empty( $sources ) ) {
            update_user_meta( $user_id, self::META_SOURCES, $sources );
            return 0;
        }

        $drafted  = (array) get_user_meta( $user_id, self::META_DRAFTED, true );
        $restored = 0;

        foreach ( $drafted as $product_id ) {
            $product_id = (int) $product_id;

            // Only touch what is still a draft — the vendor may have changed or
            // deleted it in the meantime.
            if ( 'draft' !== get_post_status( $product_id ) ) {
                continue;
            }

            $result = wp_update_post( [ 'ID' => $product_id, 'post_status' => 'publish' ], true );

            if ( ! is_wp_error( $result ) ) {
                $restored++;
            }
        }

        delete_user_meta( $user_id, self::META_SOURCES );
        delete_user_meta( $user_id, self::META_DRAFTED );

        return $restored;
    }

    /**
     * All currently suspended vendors, newest first.
     *
     * @return array[] { user, reason, since, sources, products }
     */
    public static function get_suspended(): array {
        $users = get_users( [
            'meta_key'     => self::META_SOURCES,
            'meta_compare' => 'EXISTS',
            'fields'       => 'all',
        ] );

        $rows = [];

        foreach ( $users as $user ) {
            $sources = self::sources( (int) $user->ID );

            if ( empty( $sources ) ) {
                continue;
            }

            $ids      = (array) get_user_meta( $user->ID, self::META_DRAFTED, true );
            $products = [];

            foreach ( $ids as $product_id ) {
                $post = get_post( (int) $product_id );

                if ( $post ) {
                    $products[] = $post;
                }
            }

            // Oldest source describes when this vendor went offline.
            $since  = '';
            $labels = [];
            foreach ( $sources as $key => $data ) {
                $labels[] = $key . ( ! empty( $data['reason'] ) ? ': ' . $data['reason'] : '' );
                $when     = (string) ( $data['since'] ?? '' );
                if ( $when !== '' && ( $since === '' || $when < $since ) ) {
                    $since = $when;
                }
            }

            $rows[] = [
                'user'     => $user,
                'reason'   => implode( ' | ', $labels ),
                'since'    => $since,
                'sources'  => $sources,
                'products' => $products,
            ];
        }

        usort( $rows, static function ( $a, $b ) {
            return strcmp( $b['since'], $a['since'] );
        } );

        return $rows;
    }

    /**
     * Carry a vendor over from the old single-flag anti-fraud meta.
     */
    private static function migrate_legacy( int $user_id ): void {
        if ( ! get_user_meta( $user_id, self::LEGACY_SUSPENDED, true ) ) {
            return;
        }

        $sources = get_user_meta( $user_id, self::META_SOURCES, true );

        if ( ! is_array( $sources ) ) {
            $sources = [];
        }

        $sources[ self::SOURCE_ANTI_FRAUD ] = [
            'reason' => (string) get_user_meta( $user_id, self::LEGACY_REASON, true ),
            'since'  => (string) get_user_meta( $user_id, self::LEGACY_SINCE, true ) ?: current_time( 'mysql' ),
        ];

        update_user_meta( $user_id, self::META_SOURCES, $sources );

        $drafted = get_user_meta( $user_id, self::LEGACY_DRAFTED, true );

        if ( is_array( $drafted ) && ! metadata_exists( 'user', $user_id, self::META_DRAFTED ) ) {
            update_user_meta( $user_id, self::META_DRAFTED, $drafted );
        }

        delete_user_meta( $user_id, self::LEGACY_SUSPENDED );
        delete_user_meta( $user_id, self::LEGACY_REASON );
        delete_user_meta( $user_id, self::LEGACY_SINCE );
        delete_user_meta( $user_id, self::LEGACY_DRAFTED );
    }
}
