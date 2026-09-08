<?php

namespace SK\Core\Trust;

use SK\Core\Nostr\Events;
use SK\Core\Nostr\Keys;

defined( 'ABSPATH' ) || exit;

/**
 * Which Nostr key a vendor may be shown under — the reading side.
 *
 * A key only counts once its holder has proven control of it towards this
 * site. Nothing is typed in for that: the proof comes from what happens
 * anyway — a Nostr login (the signed NIP-98 request, which is what puts
 * the key on the user in the first place), a key this site generated and
 * holds for the vendor, or a one-time signature the browser extension
 * gives silently in the vendor's dashboard. An npub merely typed into the
 * store settings is a claim, not a key, until that signature exists.
 *
 * Everything here only reads. The binding event — how it is built,
 * checked, stored, published and revoked — lives in KeyBinding.
 */
class VendorKey {

    /** JSON of the signed binding event (kind 30078, see KeyBinding). */
    const BINDING_META = 'sk_nostr_binding';

    /** The bound key alone, for uniqueness lookups. */
    const BOUND_META = 'sk_nostr_bound_pubkey';

    /** JSON of the last verified NIP-98 login event. */
    const LOGIN_PROOF_META = 'sk_nostr_login_proof';

    /** Relays that accepted the binding, and how often publishing was tried. */
    const RELAYS_META   = 'sk_nostr_binding_relays';
    const ATTEMPTS_META = 'sk_nostr_binding_attempts';

    const BINDING_KIND = 30078;
    const LOGIN_KIND   = 27235;

    /** @var array<int, string> */
    private static array $bound_cache = [];

    /** @var array<string, int>|null pubkey => user id, every proven key on the site */
    private static ?array $all_bound = null;

    /** The site a binding is for — the d tag of the event. */
    public static function site(): string {
        return strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
    }

    /**
     * The key the vendor claims, proven or not: the login/generated key on
     * the user, otherwise the npub typed into the store settings.
     */
    public static function candidate( int $vendor_id ): string {
        $hex = strtolower( (string) get_user_meta( $vendor_id, 'nostr_public_key', true ) );

        if ( Keys::is_hex( $hex ) ) {
            return $hex;
        }

        $settings = get_user_meta( $vendor_id, 'sk_profile_settings', true );

        return is_array( $settings ) ? Keys::npub_to_hex( (string) ( $settings['nostr'] ?? '' ) ) : '';
    }

    /**
     * A private key this site holds for the vendor: a generated identity,
     * or the marketplace key for the platform account. Hex, or null.
     */
    public static function held_private_key( int $vendor_id ): ?string {
        if ( sk_module_active( 'sk_auth' ) && class_exists( 'SK\Modules\Auth\NostrIdentity' ) ) {
            $priv = Keys::nsec_to_hex( (string) \SK\Modules\Auth\NostrIdentity::get_private_key( $vendor_id ) );

            if ( '' !== $priv ) {
                return $priv;
            }
        }

        if ( sk_module_active( 'sk_nostr_market' )
            && class_exists( 'SK\Modules\NostrMarket\Bridge\ChatBridge' )
            && \SK\Modules\NostrMarket\Bridge\ChatBridge::is_platform_account( $vendor_id ) ) {
            $priv = Keys::marketplace_privkey();

            if ( '' !== $priv ) {
                return $priv;
            }
        }

        return null;
    }

    /**
     * The key that counts for this vendor, lowercase hex, or '' when no
     * proven key exists. Never returns a merely claimed key. Reads only.
     */
    public static function bound( int $vendor_id ): string {
        if ( $vendor_id <= 0 ) {
            return '';
        }

        if ( ! isset( self::$bound_cache[ $vendor_id ] ) ) {
            self::$bound_cache[ $vendor_id ] = self::resolve( $vendor_id );
        }

        return self::$bound_cache[ $vendor_id ];
    }

    private static function resolve( int $vendor_id ): string {
        $held = self::held_private_key( $vendor_id );

        if ( null !== $held ) {
            $pub = Keys::pubkey_of( $held );

            if ( '' !== $pub ) {
                return $pub;
            }
        }

        // The key on the user is written only by a verified Nostr login or
        // by generating an identity — never from an unsigned claim.
        $login_key = strtolower( (string) get_user_meta( $vendor_id, 'nostr_public_key', true ) );

        if ( Keys::is_hex( $login_key ) ) {
            return $login_key;
        }

        // A typed npub counts once its holder has signed the binding.
        $candidate = self::candidate( $vendor_id );
        $binding   = self::binding( $vendor_id );

        if ( '' !== $candidate && $binding && $binding['pubkey'] === $candidate ) {
            return $candidate;
        }

        return '';
    }

    /**
     * Every proven key on the site, pubkey => user id, once per request.
     * The platform account is included when it has a key.
     *
     * @return array<string, int>
     */
    public static function all_bound(): array {
        if ( null !== self::$all_bound ) {
            return self::$all_bound;
        }

        global $wpdb;

        $ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key IN ('nostr_public_key', %s) AND meta_value <> ''",
            self::BOUND_META
        ) );

        $ids[] = class_exists( 'SK\Modules\NostrMarket\Bridge\ChatBridge' ) ? \SK\Modules\NostrMarket\Bridge\ChatBridge::PLATFORM_USER_ID : 1;

        $out = [];

        foreach ( array_unique( array_map( 'intval', $ids ) ) as $id ) {
            $key = self::bound( $id );

            if ( '' !== $key && ! isset( $out[ $key ] ) ) {
                $out[ $key ] = $id;
            }
        }

        self::$all_bound = $out;

        return $out;
    }

    /**
     * The user a key is proven for, or 0. A key can belong to one account.
     */
    public static function holder_of( string $pubkey ): int {
        return (int) ( self::all_bound()[ Keys::to_hex( $pubkey ) ] ?? 0 );
    }

    /**
     * How the bound key is proven, for the trust page and the admin view.
     *
     * @return array{type: string, pubkey: string, event: ?array, relays: string[]}|null
     */
    public static function proof( int $vendor_id ): ?array {
        $pub = self::bound( $vendor_id );

        if ( '' === $pub ) {
            return null;
        }

        $binding = self::binding( $vendor_id );
        $relays  = get_user_meta( $vendor_id, self::RELAYS_META, true );

        if ( $binding && $binding['pubkey'] === $pub ) {
            return [
                'type'   => null !== self::held_private_key( $vendor_id ) ? 'held' : 'binding',
                'pubkey' => $pub,
                'event'  => $binding,
                'relays' => is_array( $relays ) ? $relays : [],
            ];
        }

        $proof = self::login_proof( $vendor_id );

        if ( $proof && $proof['pubkey'] === $pub ) {
            return [
                'type'   => 'login',
                'pubkey' => $pub,
                'event'  => $proof,
                'relays' => [],
            ];
        }

        // The key on the user without a kept event: it came from a Nostr
        // login before login events were kept, or from an administrator.
        // Not called a login — nothing here proves which it was.
        return [
            'type'   => null !== self::held_private_key( $vendor_id ) ? 'held' : 'linked',
            'pubkey' => $pub,
            'event'  => null,
            'relays' => [],
        ];
    }

    /** The stored binding event as an array, if it is one for this site. */
    public static function binding( int $vendor_id ): ?array {
        $event = Events::decode( get_user_meta( $vendor_id, self::BINDING_META, true ) );

        if ( ! $event || self::BINDING_KIND !== (int) ( $event['kind'] ?? 0 ) || Events::tag( $event, 'd' ) !== self::site() ) {
            return null;
        }

        $event['pubkey'] = strtolower( (string) ( $event['pubkey'] ?? '' ) );

        return Keys::is_hex( $event['pubkey'] ) ? $event : null;
    }

    /** The stored login event as an array. */
    public static function login_proof( int $vendor_id ): ?array {
        $event = Events::decode( get_user_meta( $vendor_id, self::LOGIN_PROOF_META, true ) );

        if ( ! $event || self::LOGIN_KIND !== (int) ( $event['kind'] ?? 0 ) ) {
            return null;
        }

        $event['pubkey'] = strtolower( (string) ( $event['pubkey'] ?? '' ) );

        return Keys::is_hex( $event['pubkey'] ) ? $event : null;
    }

    /**
     * Whether the vendor's claimed key still lacks a proof the browser
     * could supply. Decides if the binding script is loaded.
     */
    public static function needs_binding( int $vendor_id ): bool {
        if ( null !== self::held_private_key( $vendor_id ) ) {
            return false;
        }

        return '' !== self::candidate( $vendor_id ) && '' === self::bound( $vendor_id );
    }

    /** Drop what this request remembered about one vendor (after a write). */
    public static function forget( int $vendor_id ): void {
        unset( self::$bound_cache[ $vendor_id ] );
        self::$all_bound = null;
    }
}
