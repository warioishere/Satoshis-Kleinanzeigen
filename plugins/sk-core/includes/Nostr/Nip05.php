<?php

namespace SK\Core\Nostr;

defined( 'ABSPATH' ) || exit;

/**
 * NIP-05: does a name@domain really belong to a key?
 *
 * The domain answers under /.well-known/nostr.json which key it vouches for
 * under that name. The address only counts once that answer matches the
 * vendor's bound key. The lookup is a request to an outside host, so it runs
 * from the relay sync and the result is stored; a page render only reads it.
 */
final class Nip05 {

    /** User meta: [ 'address' => string, 'ok' => bool, 'at' => int ]. */
    const META = 'sk_nip05_check';

    /** A stored verdict is renewed after this long. */
    const MAX_AGE = 7 * DAY_IN_SECONDS;

    /**
     * Ask the domain whether the name maps to this key.
     */
    public static function verify( string $address, string $pubkey ): bool {
        $pubkey = strtolower( trim( $pubkey ) );

        if ( ! preg_match( '/^[0-9a-f]{64}$/', $pubkey ) ) {
            return false;
        }

        $at = strrpos( $address, '@' );

        if ( false === $at ) {
            return false;
        }

        $name   = strtolower( substr( $address, 0, $at ) );
        $domain = strtolower( substr( $address, $at + 1 ) );

        if ( ! preg_match( '/^[a-z0-9_.+-]+$/', $name ) || ! preg_match( '/^[a-z0-9.-]+\.[a-z]{2,}$/', $domain ) ) {
            return false;
        }

        $response = wp_safe_remote_get(
            'https://' . $domain . '/.well-known/nostr.json?name=' . rawurlencode( $name ),
            [ 'timeout' => 8, 'headers' => [ 'Accept' => 'application/json' ] ]
        );

        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! is_array( $body ) || ! is_array( $body['names'] ?? null ) ) {
            return false;
        }

        // Names are matched without regard to case, as clients do.
        foreach ( $body['names'] as $listed => $key ) {
            if ( strtolower( (string) $listed ) === $name ) {
                return is_string( $key ) && strtolower( $key ) === $pubkey;
            }
        }

        return false;
    }

    /**
     * Verify the address in the vendor's profile and remember the verdict.
     *
     * Runs again when the address changed or the verdict is older than
     * MAX_AGE; otherwise the stored one is returned without a request.
     *
     * @return array{address:string,ok:bool,at:int}|null Null without an address or key.
     */
    public static function check( int $user_id, bool $force = false ): ?array {
        $address = trim( (string) get_user_meta( $user_id, 'nip05', true ) );
        $pubkey  = class_exists( '\SK\Core\Trust\VendorKey' ) ? \SK\Core\Trust\VendorKey::bound( $user_id ) : '';

        if ( '' === $address || '' === $pubkey ) {
            return null;
        }

        $stored = get_user_meta( $user_id, self::META, true );

        if ( ! $force && is_array( $stored )
            && strcasecmp( (string) ( $stored['address'] ?? '' ), $address ) === 0
            && (int) ( $stored['at'] ?? 0 ) > time() - self::MAX_AGE ) {
            return $stored;
        }

        $verdict = [
            'address' => $address,
            'ok'      => self::verify( $address, $pubkey ),
            'at'      => time(),
        ];

        update_user_meta( $user_id, self::META, $verdict );

        return $verdict;
    }

    /**
     * Would check() go out to the network for this vendor right now?
     *
     * Lets a cron pass spread the requests instead of doing them all at once.
     */
    public static function needs_check( int $user_id ): bool {
        $address = trim( (string) get_user_meta( $user_id, 'nip05', true ) );

        if ( '' === $address ) {
            return false;
        }

        $stored = get_user_meta( $user_id, self::META, true );

        return ! is_array( $stored )
            || strcasecmp( (string) ( $stored['address'] ?? '' ), $address ) !== 0
            || (int) ( $stored['at'] ?? 0 ) <= time() - self::MAX_AGE;
    }

    /**
     * The vendor's verified address, or null when there is none.
     *
     * Reads only: a verdict for an address that is no longer in the profile
     * does not count.
     */
    public static function verified( int $user_id ): ?string {
        $address = trim( (string) get_user_meta( $user_id, 'nip05', true ) );
        $stored  = get_user_meta( $user_id, self::META, true );

        if ( '' === $address || ! is_array( $stored ) || empty( $stored['ok'] ) ) {
            return null;
        }

        return strcasecmp( (string) ( $stored['address'] ?? '' ), $address ) === 0 ? $address : null;
    }
}
