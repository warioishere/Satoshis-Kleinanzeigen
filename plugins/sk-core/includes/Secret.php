<?php

namespace SK\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Every secret this plugin stores at rest goes through here.
 *
 * Wallet connections (NWC, LNDHub), the vendor xpub and the Nostr private keys
 * we hold for generated identities all used to carry their own encryption. Two
 * different schemes with two different keys meant one of them was always the
 * weaker one, and nobody could say which values were safe.
 *
 * The scheme is AES-256-GCM, which authenticates the ciphertext, so tampering
 * is detected instead of silently producing a different plaintext. The key is
 * derived from wp_salt('auth') via HMAC over a purpose string, so a value
 * stored for one purpose cannot be moved into another purpose's field.
 *
 * Nothing that was written before this class has to be re-entered: every older
 * scheme is still readable (see open()), and a value is rewritten in the
 * current form the first time it is read through from_user_meta()/from_option().
 */
class Secret {

    /** Marks a GCM payload. The ":" cannot appear in base64, so old values are unambiguous. */
    const PREFIX = 'skv2:';

    const CIPHER  = 'aes-256-gcm';
    const IV_LEN  = 12;
    const TAG_LEN = 16;

    /** Wallet connections and the xpub. */
    const WALLET = 'wallet';

    /** Private keys of Nostr identities this site generated. */
    const NOSTR = 'nostr';

    /** The marketplace's own Nostr key, when it is kept in an option. */
    const MARKETPLACE = 'marketplace';

    /**
     * Key namespaces per purpose, newest first.
     *
     * New values are written under the first entry, reads try every entry.
     * NEVER remove or edit an entry that may still be in the database: the
     * string goes into the key derivation, so changing it makes those values
     * unreadable. Add a new one in front instead — reads upgrade by themselves.
     */
    const CONTEXTS = [
        self::WALLET => [
            'sk-core/wallet-secret/v2',
            // Written while the wallet layer still lived in the sk_payments module.
            'sk-payments/wallet-secret/v2',
        ],
        self::NOSTR  => [
            'sk-core/nostr-identity/v2',
        ],
        self::MARKETPLACE => [
            'sk-core/nostr-marketplace/v2',
        ],
    ];

    /**
     * Encrypt a secret for storage.
     *
     * @param string $purpose One of the class constants; see CONTEXTS.
     *
     * @return string Storable ciphertext, or '' if encryption failed — callers
     *                must not persist an empty result over a real secret.
     */
    public static function encrypt( string $plaintext, string $purpose = self::WALLET ): string {
        if ( $plaintext === '' ) {
            return '';
        }

        $iv  = random_bytes( self::IV_LEN );
        $tag = '';

        $context    = self::contexts( $purpose )[0];
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            self::key( $context ),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $context,
            self::TAG_LEN
        );

        if ( $ciphertext === false ) {
            error_log( '[SK Core] Verschlüsselung eines Secrets fehlgeschlagen (' . $purpose . ').' );
            return '';
        }

        return self::PREFIX . base64_encode( $iv . $tag . $ciphertext );
    }

    /**
     * Decrypt a stored secret, whichever scheme wrote it.
     *
     * @return string Plaintext, or '' if the value is missing, tampered with or
     *                was encrypted under a different salt.
     */
    public static function decrypt( string $stored, string $purpose = self::WALLET ): string {
        return self::open( $stored, $purpose )[0];
    }

    /**
     * Is this value stored in anything other than the current scheme?
     */
    public static function needs_upgrade( string $stored, string $purpose = self::WALLET ): bool {
        return trim( $stored ) !== '' && ! self::open( $stored, $purpose )[1];
    }

    /**
     * Read a secret from user meta, rewriting an older form in place.
     */
    public static function from_user_meta( int $user_id, string $meta_key, string $purpose = self::WALLET ): string {
        $stored = (string) get_user_meta( $user_id, $meta_key, true );

        if ( $stored === '' ) {
            return '';
        }

        list( $plaintext, $current ) = self::open( $stored, $purpose );

        if ( $plaintext !== '' && ! $current ) {
            $upgraded = self::encrypt( $plaintext, $purpose );
            if ( $upgraded !== '' ) {
                update_user_meta( $user_id, $meta_key, $upgraded );
            }
        }

        return $plaintext;
    }

    /**
     * Read a secret from an option, rewriting an older form in place.
     */
    public static function from_option( string $option_name, string $purpose = self::WALLET ): string {
        $stored = (string) get_option( $option_name, '' );

        if ( $stored === '' ) {
            return '';
        }

        list( $plaintext, $current ) = self::open( $stored, $purpose );

        if ( $plaintext !== '' && ! $current ) {
            $upgraded = self::encrypt( $plaintext, $purpose );
            if ( $upgraded !== '' ) {
                update_option( $option_name, $upgraded );
            }
        }

        return $plaintext;
    }

    /**
     * Open a stored value under every scheme this purpose has ever used.
     *
     * @return array{0:string,1:bool} Plaintext (or ''), and whether it was
     *                                already stored in the current form.
     */
    private static function open( string $stored, string $purpose ): array {
        $stored = trim( $stored );

        if ( $stored === '' ) {
            return [ '', true ];
        }

        if ( strpos( $stored, self::PREFIX ) === 0 ) {
            $payload = substr( $stored, strlen( self::PREFIX ) );

            foreach ( self::contexts( $purpose ) as $i => $context ) {
                $plaintext = self::decrypt_gcm( $payload, $context );

                if ( $plaintext !== '' ) {
                    return [ $plaintext, $i === 0 ];
                }
            }

            return [ '', true ];
        }

        // Before GCM, both purposes used unauthenticated AES-256-CBC as
        // base64(iv . ciphertext) — only the key differed.
        foreach ( self::legacy_cbc_keys( $purpose ) as $key ) {
            $plaintext = self::decrypt_legacy_cbc( $stored, $key );

            if ( $plaintext !== '' ) {
                return [ $plaintext, false ];
            }
        }

        return [ '', false ];
    }

    private static function decrypt_gcm( string $payload, string $context ): string {
        $raw = base64_decode( $payload, true );

        if ( $raw === false || strlen( $raw ) < self::IV_LEN + self::TAG_LEN ) {
            return '';
        }

        $iv         = substr( $raw, 0, self::IV_LEN );
        $tag        = substr( $raw, self::IV_LEN, self::TAG_LEN );
        $ciphertext = substr( $raw, self::IV_LEN + self::TAG_LEN );

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            self::key( $context ),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $context
        );

        return is_string( $plaintext ) ? $plaintext : '';
    }

    /**
     * Read a value written by the old AES-256-CBC scheme.
     */
    private static function decrypt_legacy_cbc( string $stored, string $key ): string {
        $data = base64_decode( $stored, true );

        if ( $data === false || strlen( $data ) <= 16 ) {
            return '';
        }

        $plaintext = openssl_decrypt(
            substr( $data, 16 ),
            'aes-256-cbc',
            $key,
            OPENSSL_RAW_DATA,
            substr( $data, 0, 16 )
        );

        return is_string( $plaintext ) ? $plaintext : '';
    }

    /**
     * The CBC keys this purpose was written with before GCM.
     *
     * The wallet passed the salt itself, the Nostr identities its SHA-256
     * digest. Both are kept so no stored value becomes unreadable. The
     * marketplace key was never encrypted at all — it lay in the option as
     * plain text, and that case is handled where it is read.
     */
    private static function legacy_cbc_keys( string $purpose ): array {
        if ( $purpose === self::MARKETPLACE ) {
            return [];
        }

        if ( $purpose === self::NOSTR ) {
            return [ hash( 'sha256', wp_salt( 'auth' ), true ) ];
        }

        return [ wp_salt( 'auth' ) ];
    }

    /** @return string[] */
    private static function contexts( string $purpose ): array {
        return self::CONTEXTS[ $purpose ] ?? self::CONTEXTS[ self::WALLET ];
    }

    /**
     * 32-byte key derived from the WordPress auth salt and the namespace.
     */
    private static function key( string $context ): string {
        return hash_hmac( 'sha256', $context, wp_salt( 'auth' ), true );
    }
}
