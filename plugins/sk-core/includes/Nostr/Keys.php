<?php

namespace SK\Core\Nostr;

defined( 'ABSPATH' ) || exit;

/**
 * Nostr keys: one place for what used to be repeated in every module.
 *
 * Every function takes what it is given — hex in either case, npub, nsec,
 * a "nostr:" prefix — and answers with lowercase 64-character hex, or ''
 * when the input is not a key. Nothing here throws; the library's
 * ValueError on an unusable key used to take whole settings pages down.
 *
 * The marketplace's own key lives in the wp-config constant or the
 * Auto Poster option, possibly as an nsec. Only the private half is stored
 * anywhere; the public half is derived here, once per request.
 */
final class Keys {

    const HEX64  = '/^[0-9a-f]{64}$/';
    const HEX128 = '/^[0-9a-f]{128}$/';

    /** @var string|null Request cache of the marketplace public key. */
    private static ?string $marketplace_pubkey = null;

    /** Lowercase 64-hex? */
    public static function is_hex( $value ): bool {
        return is_string( $value ) && (bool) preg_match( self::HEX64, $value );
    }

    /** A 128-hex signature? */
    public static function is_signature( $value ): bool {
        return is_string( $value ) && (bool) preg_match( self::HEX128, $value );
    }

    /**
     * Any spelling of a key to lowercase hex: hex in either case, npub or
     * nsec (bech32), with or without a "nostr:" prefix. '' when it is none.
     */
    public static function to_hex( $value ): string {
        if ( ! is_string( $value ) ) {
            return '';
        }

        $value = trim( preg_replace( '/^nostr:/i', '', trim( $value ) ) );

        if ( preg_match( '/^[0-9a-fA-F]{64}$/', $value ) ) {
            return strtolower( $value );
        }

        if ( preg_match( '/^n(pub|sec)1[02-9ac-hj-np-z]+$/i', $value ) && class_exists( '\swentel\nostr\Key\Key' ) ) {
            try {
                $hex = strtolower( (string) ( new \swentel\nostr\Key\Key() )->convertToHex( strtolower( $value ) ) );

                return preg_match( self::HEX64, $hex ) ? $hex : '';
            } catch ( \Throwable $e ) {
                return '';
            }
        }

        return '';
    }

    /** An npub (or hex) to hex; '' unless it is a public key spelling. */
    public static function npub_to_hex( $value ): string {
        $value = is_string( $value ) ? trim( preg_replace( '/^nostr:/i', '', trim( $value ) ) ) : '';

        if ( 0 === stripos( $value, 'nsec' ) ) {
            return '';
        }

        return self::to_hex( $value );
    }

    /** An nsec (or hex) to hex; '' unless it is a private key spelling. */
    public static function nsec_to_hex( $value ): string {
        $value = is_string( $value ) ? trim( $value ) : '';

        if ( 0 === stripos( $value, 'npub' ) ) {
            return '';
        }

        return self::to_hex( $value );
    }

    /** Hex public key to npub, '' on failure. */
    public static function to_npub( string $hex ): string {
        $hex = self::to_hex( $hex );

        if ( '' === $hex || ! class_exists( '\swentel\nostr\Key\Key' ) ) {
            return '';
        }

        try {
            return (string) ( new \swentel\nostr\Key\Key() )->convertPublicKeyToBech32( $hex );
        } catch ( \Throwable $e ) {
            return '';
        }
    }

    /** Hex private key to nsec, '' on failure. */
    public static function to_nsec( string $hex ): string {
        $hex = self::to_hex( $hex );

        if ( '' === $hex || ! class_exists( '\swentel\nostr\Key\Key' ) ) {
            return '';
        }

        try {
            return (string) ( new \swentel\nostr\Key\Key() )->convertPrivateKeyToBech32( $hex );
        } catch ( \Throwable $e ) {
            return '';
        }
    }

    /** The public key of a private key (hex or nsec), lowercase hex or ''. */
    public static function pubkey_of( $privkey ): string {
        $hex = self::nsec_to_hex( $privkey );

        if ( '' === $hex || ! class_exists( '\swentel\nostr\Key\Key' ) ) {
            return '';
        }

        try {
            $pub = strtolower( (string) ( new \swentel\nostr\Key\Key() )->getPublicKey( $hex ) );

            return preg_match( self::HEX64, $pub ) ? $pub : '';
        } catch ( \Throwable $e ) {
            return '';
        }
    }

    /** A fresh key pair, both halves as hex. */
    public static function generate(): array {
        $key  = new \swentel\nostr\Key\Key();
        $priv = strtolower( (string) $key->generatePrivateKey() );

        return [ 'priv' => $priv, 'pub' => strtolower( (string) $key->getPublicKey( $priv ) ) ];
    }

    /**
     * The marketplace's private key as hex, or ''. The wp-config constant
     * wins, then the Auto Poster's resolver (its option and the
     * `nap_nostr_private_key` filter), then the option directly.
     */
    public static function marketplace_privkey(): string {
        if ( defined( 'NAP_NOSTR_PRIVKEY' ) && NAP_NOSTR_PRIVKEY ) {
            $hex = self::nsec_to_hex( (string) NAP_NOSTR_PRIVKEY );

            if ( '' !== $hex ) {
                return $hex;
            }
        }

        if ( function_exists( 'nap_resolve_private_key' ) ) {
            $hex = self::nsec_to_hex( (string) nap_resolve_private_key() );

            if ( '' !== $hex ) {
                return $hex;
            }
        }

        $opts = get_option( 'nap_nostr_options', [] );

        return self::nsec_to_hex( is_array( $opts ) ? (string) ( $opts['private_key'] ?? '' ) : '' );
    }

    /** The marketplace's public key as hex, or ''. Cached for the request. */
    public static function marketplace_pubkey(): string {
        if ( null === self::$marketplace_pubkey ) {
            self::$marketplace_pubkey = self::pubkey_of( self::marketplace_privkey() );
        }

        return self::$marketplace_pubkey;
    }

    /** Forget the cached marketplace key (after the setting changed). */
    public static function forget_marketplace(): void {
        self::$marketplace_pubkey = null;
    }
}
