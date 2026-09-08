<?php

namespace SK\Modules\Reputation;

use SK\Core\Trust\VendorKey;

defined( 'ABSPATH' ) || exit;

/**
 * Internal store follows, mirrored into the Nostr contact list of users
 * whose key this site generated and holds.
 *
 * Following a store here is a fact about the follower. For a user with an
 * SK-made identity the site keeps their profile anyway, so it may also
 * keep their contact list (kind 3): follow a store, and the vendor's
 * proven key is added; unfollow, and it is removed. From then on the
 * follow shows up in everyone's graph signal like any other, and can be
 * checked on the relays like any other. Users who brought their own key
 * are left alone — their list is theirs.
 *
 * Only proven vendor keys are ever added (see VendorKey). Nothing is
 * removed that this site did not add on request; the daily sync only
 * adds what is missing, so a follow made before the vendor proved their
 * key is caught up later.
 */
class FollowMirror {

    const CRON_HOOK = 'sk_reputation_mirror_follow';
    const SYNC_HOOK = 'sk_reputation_mirror_sync';

    public function __construct() {
        add_action( 'sk_follow_store_toggle_status', [ __CLASS__, 'on_toggle' ], 10, 3 );
        add_action( self::CRON_HOOK, [ __CLASS__, 'mirror' ], 10, 3 );
        add_action( self::SYNC_HOOK, [ __CLASS__, 'sync_all' ] );

        if ( ! wp_next_scheduled( self::SYNC_HOOK ) ) {
            self::schedule();
        }
    }

    public static function schedule(): void {
        if ( ! wp_next_scheduled( self::SYNC_HOOK ) ) {
            wp_schedule_event( time() + 2 * HOUR_IN_SECONDS, 'daily', self::SYNC_HOOK );
        }
    }

    public static function unschedule(): void {
        wp_clear_scheduled_hook( self::SYNC_HOOK );
    }

    /** Whether this site keeps the user's contact list. */
    public static function mirrors( int $user_id ): bool {
        if ( ! sk_module_active( 'sk_auth' ) || ! class_exists( 'SK\Modules\Auth\NostrIdentity' ) ) {
            return false;
        }

        if ( 'generated' !== get_user_meta( $user_id, 'sk_nostr_identity_source', true ) ) {
            return false;
        }

        return null !== \SK\Modules\Auth\NostrIdentity::get_private_key( $user_id );
    }

    /**
     * The follow button was used. The relay work happens off the request.
     */
    public static function on_toggle( $vendor_id, $follower_id, $status ): void {
        $vendor_id   = (int) $vendor_id;
        $follower_id = (int) $follower_id;

        if ( $vendor_id === $follower_id || ! self::mirrors( $follower_id ) ) {
            return;
        }

        $vendor_key = VendorKey::bound( $vendor_id );

        if ( '' === $vendor_key ) {
            return;
        }

        $op   = 'following' === $status ? 'add' : 'remove';
        $args = [ $follower_id, $vendor_key, $op ];

        if ( ! wp_next_scheduled( self::CRON_HOOK, $args ) ) {
            wp_schedule_single_event( time() + 5, self::CRON_HOOK, $args );
        }
    }

    /**
     * Cron: add or remove one key in the user's contact list and publish.
     */
    public static function mirror( $user_id, $vendor_key, $op ): void {
        $user_id    = (int) $user_id;
        $vendor_key = strtolower( (string) $vendor_key );

        if ( ! self::mirrors( $user_id ) || ! preg_match( '/^[0-9a-f]{64}$/', $vendor_key ) ) {
            return;
        }

        self::apply( $user_id, 'add' === $op ? [ $vendor_key ] : [], 'remove' === $op ? [ $vendor_key ] : [] );
    }

    /**
     * Cron, daily: every mirrored user gets the keys of the stores they
     * follow here that are still missing from their list.
     */
    public static function sync_all(): void {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT f.follower_id, f.vendor_id
             FROM {$wpdb->prefix}sk_follow_store_followers f
             INNER JOIN {$wpdb->usermeta} m ON m.user_id = f.follower_id AND m.meta_key = 'sk_nostr_identity_source' AND m.meta_value = 'generated'
             WHERE f.unfollowed_at IS NULL"
        );

        $wanted = [];

        foreach ( $rows as $row ) {
            $key = VendorKey::bound( (int) $row->vendor_id );

            if ( '' !== $key && (int) $row->vendor_id !== (int) $row->follower_id ) {
                $wanted[ (int) $row->follower_id ][] = $key;
            }
        }

        foreach ( $wanted as $user_id => $keys ) {
            if ( self::mirrors( $user_id ) ) {
                self::apply( $user_id, array_unique( $keys ), [] );
            }
        }
    }

    /**
     * Fetch the user's newest contact list, change it, sign, publish.
     * Every tag the list already has is kept, including petnames and
     * relay hints on p tags; the content (legacy relay list) too.
     *
     * @param string[] $add
     * @param string[] $remove
     */
    private static function apply( int $user_id, array $add, array $remove ): void {
        $pubkey = strtolower( (string) \SK\Modules\Auth\NostrIdentity::get_public_key( $user_id ) );

        if ( ! preg_match( '/^[0-9a-f]{64}$/', $pubkey ) ) {
            return;
        }

        $current = RelayReader::latest( 3, [ $pubkey ] )[ $pubkey ] ?? null;
        $tags    = is_array( $current['tags'] ?? null ) ? $current['tags'] : [];
        $content = is_string( $current['content'] ?? null ) ? $current['content'] : '';

        $have = [];

        foreach ( $tags as $tag ) {
            if ( is_array( $tag ) && 'p' === ( $tag[0] ?? '' ) ) {
                $have[ strtolower( (string) ( $tag[1] ?? '' ) ) ] = 1;
            }
        }

        $changed = false;

        foreach ( $add as $key ) {
            if ( ! isset( $have[ $key ] ) && $key !== $pubkey ) {
                $tags[]       = [ 'p', $key ];
                $have[ $key ] = 1;
                $changed      = true;
            }
        }

        if ( ! empty( $remove ) ) {
            $before = count( $tags );
            $tags   = array_values( array_filter( $tags, static function ( $tag ) use ( $remove ) {
                return ! ( is_array( $tag ) && 'p' === ( $tag[0] ?? '' ) && in_array( strtolower( (string) ( $tag[1] ?? '' ) ), $remove, true ) );
            } ) );
            $changed = $changed || count( $tags ) !== $before;
        }

        if ( ! $changed ) {
            return;
        }

        // A list that does not exist on the relays yet may only be created
        // for an addition; removing from nothing publishes nothing.
        if ( null === $current && empty( $add ) ) {
            return;
        }

        $report = null;
        $id     = \SK\Modules\Auth\NostrIdentity::publish( $user_id, 3, $content, $tags, $report );

        if ( null === $id ) {
            error_log( '[SK Reputation] contact list for user ' . $user_id . ' not accepted: ' . wp_json_encode( $report['rejected'] ?? [] ) );
        }
    }
}
