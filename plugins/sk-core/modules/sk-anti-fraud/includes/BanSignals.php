<?php

namespace SK\Modules\AntiFraud;

defined( 'ABSPATH' ) || exit;

/**
 * Durable identifiers of banned accounts.
 *
 * Browser fingerprints are useless against a Tor user — Tor Browser is hardened
 * to make everyone look alike. What a scammer cannot hide is how he gets paid
 * and how he stays reachable: wallet, npub, Lightning address, Telegram handle.
 * Those are frozen when an account is banned and matched on every login, so a
 * returning scammer surfaces even from a fresh account and a fresh IP.
 *
 * Signals survive deletion of the account they came from — that is the whole
 * point, since deleting a vendor used to erase every trace.
 */
final class BanSignals {

    /** User meta: account is banned. */
    const META_BANNED = 'sk_banned';

    /** User meta: matched signals from the last check. */
    const META_FLAGGED = 'sk_flagged_signals';

    /** Signal types and how they read from a user. */
    const TYPES = [
        'lnurl_node'        => 'LNURL-Node',
        'nostr'             => 'Nostr-npub',
        'lightning_address' => 'Lightning-Adresse',
        'telegram'          => 'Telegram',
        'email'             => 'E-Mail',
        'phone'             => 'Telefon',
        'twitter'           => 'X / Twitter',
        'btc_address'       => 'Bitcoin-Adresse',
    ];

    public static function table(): string {
        global $wpdb;

        return $wpdb->prefix . 'sk_banned_signals';
    }

    // ── Collecting ─────────────────────────────────────────────────────────────

    /**
     * Every durable identifier we can read off a user.
     *
     * @return array<string,string> type => value
     */
    public static function collect( int $user_id ): array {
        $user = get_userdata( $user_id );

        if ( ! $user ) {
            return [];
        }

        $signals = [
            'lnurl_node'  => (string) get_user_meta( $user_id, 'lnurl-auth-bjm-id', true ),
            'nostr'       => (string) get_user_meta( $user_id, 'nostr_public_key', true ),
            'btc_address' => (string) get_user_meta( $user_id, 'sk_btc_address', true ),
            'email'       => (string) $user->user_email,
        ];

        $profile = get_user_meta( $user_id, 'sk_profile_settings', true );

        if ( is_array( $profile ) ) {
            $signals['lightning_address'] = (string) ( $profile['lightning_address'] ?? '' );
            $signals['telegram']          = (string) ( $profile['telegram'] ?? '' );
            $signals['twitter']           = (string) ( $profile['twitter'] ?? '' );

            $phone = $profile['phone_number'] ?? ( $profile['phone'] ?? '' );
            // The profile stores 'no'/'yes' in 'phone' when it is a visibility flag.
            $signals['phone'] = in_array( $phone, [ 'no', 'yes' ], true ) ? '' : (string) $phone;
        }

        return array_filter( array_map( [ __CLASS__, 'normalize' ], $signals ) );
    }

    /**
     * Comparable form — the same handle typed with @ or in caps must match.
     */
    public static function normalize( $value ): string {
        $value = mb_strtolower( trim( (string) $value ) );
        $value = ltrim( $value, '@' );

        // Placeholder addresses are not identifying.
        if ( '' === $value || preg_match( '/@(nostr|btc|lightning)\.local$/', $value ) ) {
            return '';
        }

        return mb_substr( $value, 0, 100 );
    }

    // ── Banning ────────────────────────────────────────────────────────────────

    /**
     * Freeze a user's identifiers, then take everything offline.
     *
     * @return array{signals:int,listings:int}
     */
    public static function ban( int $user_id, string $note = '' ): array {
        $stored = 0;

        foreach ( self::collect( $user_id ) as $type => $value ) {
            if ( self::add( $type, $value, $user_id ) ) {
                $stored++;
            }
        }

        update_user_meta( $user_id, self::META_BANNED, 1 );

        $listings = Suspension::suspend( $user_id, 'banned' . ( $note ? ': ' . $note : '' ) );

        return [ 'signals' => $stored, 'listings' => $listings ];
    }

    public static function unban( int $user_id ): int {
        self::remove_by_user( $user_id );
        delete_user_meta( $user_id, self::META_BANNED );
        delete_user_meta( $user_id, self::META_FLAGGED );

        return Suspension::unsuspend( $user_id );
    }

    public static function is_banned( int $user_id ): bool {
        return (bool) get_user_meta( $user_id, self::META_BANNED, true );
    }

    // ── Storage ────────────────────────────────────────────────────────────────

    public static function add( string $type, string $value, int $user_id = 0 ): bool {
        global $wpdb;

        $value = self::normalize( $value );

        if ( '' === $value || ! isset( self::TYPES[ $type ] ) ) {
            return false;
        }

        $exists = $wpdb->get_var( $wpdb->prepare(
            'SELECT id FROM ' . self::table() . ' WHERE signal_type = %s AND signal_value = %s',
            $type,
            $value
        ) );

        if ( $exists ) {
            return false;
        }

        return (bool) $wpdb->insert( self::table(), [
            'banned_user_id' => $user_id,
            'signal_type'    => $type,
            'signal_value'   => $value,
            'banned_at'      => current_time( 'mysql' ),
        ], [ '%d', '%s', '%s', '%s' ] );
    }

    public static function remove( int $id ): bool {
        global $wpdb;

        return (bool) $wpdb->delete( self::table(), [ 'id' => $id ], [ '%d' ] );
    }

    public static function remove_by_user( int $user_id ): void {
        global $wpdb;

        $wpdb->delete( self::table(), [ 'banned_user_id' => $user_id ], [ '%d' ] );
    }

    /**
     * @return object[]
     */
    public static function all(): array {
        global $wpdb;

        return (array) $wpdb->get_results(
            'SELECT * FROM ' . self::table() . ' ORDER BY banned_at DESC, id DESC'
        );
    }

    // ── Matching ───────────────────────────────────────────────────────────────

    /**
     * Which of this user's identifiers are on the ban list?
     *
     * @return array[] { type, value, banned_user_id }
     */
    public static function match( int $user_id ): array {
        global $wpdb;

        $matches = [];

        foreach ( self::collect( $user_id ) as $type => $value ) {
            $row = $wpdb->get_row( $wpdb->prepare(
                'SELECT * FROM ' . self::table() . ' WHERE signal_type = %s AND signal_value = %s LIMIT 1',
                $type,
                $value
            ) );

            if ( ! $row ) {
                continue;
            }

            // Don't flag the banned account against its own signals.
            if ( (int) $row->banned_user_id === $user_id ) {
                continue;
            }

            $matches[] = [
                'type'           => $type,
                'value'          => $value,
                'banned_user_id' => (int) $row->banned_user_id,
            ];
        }

        return $matches;
    }
}
