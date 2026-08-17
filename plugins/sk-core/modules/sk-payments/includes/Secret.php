<?php

namespace SK\Modules\Payments;

defined( 'ABSPATH' ) || exit;

/**
 * Encryption for wallet secrets at rest: NWC and LNDHub connection strings and
 * the vendor xpub.
 *
 * Uses AES-256-GCM, which authenticates the ciphertext — tampering is detected
 * instead of silently producing a different plaintext. The key is derived from
 * wp_salt('auth') via HMAC and namespaced to this purpose, so these secrets do
 * not share a key with anything else deriving from the same salt.
 *
 * Values written before this class used unauthenticated AES-256-CBC with the
 * salt as the raw key. Those still decrypt (see decrypt_legacy_cbc) and are
 * re-encrypted to GCM the first time they are read, so nobody has to re-enter
 * their wallet connection.
 */
class Secret {

    /** Marks a GCM payload. The ":" cannot appear in base64, so old values are unambiguous. */
    const PREFIX = 'skv2:';

    const CIPHER  = 'aes-256-gcm';
    const IV_LEN  = 12;
    const TAG_LEN = 16;

    /** Key namespace, also used as GCM additional authenticated data. */
    const CONTEXT = 'sk-payments/wallet-secret/v2';

    /**
     * Encrypt a secret for storage.
     *
     * @return string Storable ciphertext, or '' if encryption failed — callers
     *                must not persist an empty result over a real secret.
     */
    public static function encrypt( string $plaintext ): string {
        if ( $plaintext === '' ) {
            return '';
        }

        $iv  = random_bytes( self::IV_LEN );
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            self::key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            self::CONTEXT,
            self::TAG_LEN
        );

        if ( $ciphertext === false ) {
            error_log( '[SK Payments] Verschlüsselung des Wallet-Secrets fehlgeschlagen.' );
            return '';
        }

        return self::PREFIX . base64_encode( $iv . $tag . $ciphertext );
    }

    /**
     * Decrypt a stored secret.
     *
     * @return string Plaintext, or '' if the value is missing, tampered with or
     *                was encrypted under a different salt.
     */
    public static function decrypt( string $stored ): string {
        $stored = trim( $stored );

        if ( $stored === '' ) {
            return '';
        }

        if ( strpos( $stored, self::PREFIX ) === 0 ) {
            return self::decrypt_gcm( substr( $stored, strlen( self::PREFIX ) ) );
        }

        return self::decrypt_legacy_cbc( $stored );
    }

    /**
     * Is this value still in the old, unauthenticated format?
     */
    public static function needs_upgrade( string $stored ): bool {
        $stored = trim( $stored );

        return $stored !== '' && strpos( $stored, self::PREFIX ) !== 0;
    }

    /**
     * Read a secret from user meta, upgrading legacy ciphertext in place.
     */
    public static function from_user_meta( int $user_id, string $meta_key ): string {
        $stored = (string) get_user_meta( $user_id, $meta_key, true );

        if ( $stored === '' ) {
            return '';
        }

        $plaintext = self::decrypt( $stored );

        if ( $plaintext !== '' && self::needs_upgrade( $stored ) ) {
            $upgraded = self::encrypt( $plaintext );
            if ( $upgraded !== '' ) {
                update_user_meta( $user_id, $meta_key, $upgraded );
            }
        }

        return $plaintext;
    }

    /**
     * Read a secret from an option, upgrading legacy ciphertext in place.
     */
    public static function from_option( string $option_name ): string {
        $stored = (string) get_option( $option_name, '' );

        if ( $stored === '' ) {
            return '';
        }

        $plaintext = self::decrypt( $stored );

        if ( $plaintext !== '' && self::needs_upgrade( $stored ) ) {
            $upgraded = self::encrypt( $plaintext );
            if ( $upgraded !== '' ) {
                update_option( $option_name, $upgraded );
            }
        }

        return $plaintext;
    }

    private static function decrypt_gcm( string $payload ): string {
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
            self::key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            self::CONTEXT
        );

        return is_string( $plaintext ) ? $plaintext : '';
    }

    /**
     * Read a value written by the old AES-256-CBC scheme.
     */
    private static function decrypt_legacy_cbc( string $stored ): string {
        $data = base64_decode( $stored, true );

        if ( $data === false || strlen( $data ) <= 16 ) {
            return '';
        }

        $plaintext = openssl_decrypt(
            substr( $data, 16 ),
            'aes-256-cbc',
            wp_salt( 'auth' ),
            OPENSSL_RAW_DATA,
            substr( $data, 0, 16 )
        );

        return is_string( $plaintext ) ? $plaintext : '';
    }

    /**
     * 32-byte key derived from the WordPress auth salt.
     */
    private static function key(): string {
        return hash_hmac( 'sha256', self::CONTEXT, wp_salt( 'auth' ), true );
    }
}
