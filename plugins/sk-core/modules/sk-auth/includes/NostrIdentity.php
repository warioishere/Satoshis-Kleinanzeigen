<?php

namespace SK\Modules\Auth;

use swentel\nostr\Event\Event;
use swentel\nostr\Sign\Sign;
use swentel\nostr\Key\Key;
use swentel\nostr\Relay\Relay;
use swentel\nostr\Message\EventMessage;

defined( 'ABSPATH' ) || exit;

/**
 * Nostr Identity Manager — generates, stores and uses Nostr keypairs per user.
 *
 * Every vendor can have a Nostr identity (secp256k1 keypair).
 * Private keys are stored encrypted (AES-256-CBC) in user meta.
 * Public keys are stored unencrypted for fast lookup.
 */
class NostrIdentity {

    private static $encryption_method = 'aes-256-cbc';

    /**
     * Register hooks for profile sync.
     */
    public static function init_hooks() {
        add_action( 'sk_store_profile_saved', [ __CLASS__, 'on_store_profile_saved' ], 30, 1 );
    }

    /**
     * Re-publish Kind 0 when vendor updates store settings.
     */
    public static function on_store_profile_saved( int $store_id ) {
        if ( ! self::has_identity( $store_id ) ) {
            return;
        }
        // Defer to shutdown to not slow down the settings save.
        register_shutdown_function( [ __CLASS__, 'publish_profile' ], $store_id );
    }

    /**
     * Create a new Nostr identity for a user.
     * Generates keypair, stores encrypted privkey + pubkey, publishes Kind 0 profile.
     *
     * @return string Public key (hex).
     */
    public static function create_for_user( int $user_id ): string {
        $key     = new Key();
        $privkey = $key->generatePrivateKey();
        $pubkey  = $key->getPublicKey( $privkey );

        // Store encrypted private key.
        update_user_meta( $user_id, 'sk_nostr_private_key', self::encrypt( $privkey ) );
        // Store public key (compatible with existing nostr_public_key field).
        update_user_meta( $user_id, 'nostr_public_key', $pubkey );
        // Mark source.
        update_user_meta( $user_id, 'sk_nostr_identity_source', 'generated' );

        // Publish Kind 0 profile.
        self::publish_profile( $user_id );

        return $pubkey;
    }

    /**
     * Delete a generated Nostr identity — removes private key, public key,
     * and identity source. User can later re-link via browser extension
     * (NIP-07) through the auth-connector dashboard.
     */
    public static function delete_for_user( int $user_id ): void {
        delete_user_meta( $user_id, 'sk_nostr_private_key' );
        delete_user_meta( $user_id, 'nostr_public_key' );
        delete_user_meta( $user_id, 'sk_nostr_identity_source' );
    }

    /**
     * Check if user has a Nostr identity (with private key we control).
     */
    public static function has_identity( int $user_id ): bool {
        return ! empty( get_user_meta( $user_id, 'sk_nostr_private_key', true ) );
    }

    /**
     * Check if user has any Nostr public key (generated or imported via extension).
     */
    public static function has_pubkey( int $user_id ): bool {
        return ! empty( get_user_meta( $user_id, 'nostr_public_key', true ) );
    }

    /**
     * Get user's public key (hex).
     */
    public static function get_public_key( int $user_id ): string {
        return get_user_meta( $user_id, 'nostr_public_key', true ) ?: '';
    }

    /**
     * Get user's private key (decrypted hex). Returns null if no generated identity.
     */
    public static function get_private_key( int $user_id ): ?string {
        $encrypted = get_user_meta( $user_id, 'sk_nostr_private_key', true );
        if ( empty( $encrypted ) ) {
            return null;
        }
        return self::decrypt( $encrypted );
    }

    /**
     * Get user's npub (bech32 public key).
     */
    public static function get_npub( int $user_id ): string {
        $pubkey = self::get_public_key( $user_id );
        if ( empty( $pubkey ) ) {
            return '';
        }
        $key = new Key();
        return $key->convertPublicKeyToBech32( $pubkey );
    }

    /**
     * Get user's nsec (bech32 private key). Only for generated identities.
     */
    public static function get_nsec( int $user_id ): string {
        $privkey = self::get_private_key( $user_id );
        if ( empty( $privkey ) ) {
            return '';
        }
        $key = new Key();
        return $key->convertPrivateKeyToBech32( $privkey );
    }

    /**
     * Sign an Event with the user's private key.
     */
    public static function sign_event( int $user_id, Event $event ): bool {
        $privkey = self::get_private_key( $user_id );
        if ( ! $privkey ) {
            return false;
        }

        $signer = new Sign();
        $signer->signEvent( $event, $privkey );
        return true;
    }

    /**
     * Build, sign and publish an event to relays with the user's key.
     *
     * @return string|null Event ID if successful.
     */
    public static function publish( int $user_id, int $kind, string $content, array $tags = [] ): ?string {
        $privkey = self::get_private_key( $user_id );
        if ( ! $privkey ) {
            return null;
        }

        $event = new Event();
        $event->setKind( $kind );
        $event->setContent( $content );
        foreach ( $tags as $tag ) {
            $event->addTag( $tag );
        }

        $signer = new Sign();
        $signer->signEvent( $event, $privkey );

        $relays  = self::get_relays();
        $sent    = false;

        foreach ( $relays as $relay_url ) {
            try {
                $msg   = new EventMessage( $event );
                $relay = new Relay( $relay_url );
                if ( method_exists( $relay, 'setTimeout' ) ) {
                    $relay->setTimeout( 3 );
                }
                $relay->setMessage( $msg );
                $result = $relay->send();
                if ( false !== $result ) {
                    $sent = true;
                }
            } catch ( \Throwable $e ) {
                // Log but continue to next relay.
                error_log( '[NostrIdentity] Relay send failed (' . $relay_url . '): ' . $e->getMessage() );
            }
        }

        return $sent ? $event->getId() : null;
    }

    /**
     * Publish Kind 0 profile event for user.
     */
    public static function publish_profile( int $user_id ): ?string {
        $store_info = function_exists( 'sk_get_store_info' ) ? sk_get_store_info( $user_id ) : [];
        $user       = get_userdata( $user_id );
        $domain     = wp_parse_url( home_url(), PHP_URL_HOST );

        $profile = [
            'name'    => $store_info['store_name'] ?? ( $user ? $user->display_name : '' ),
            'about'   => $store_info['store_description'] ?? '',
            'picture' => get_user_meta( $user_id, 'nostr_avatar', true ) ?: '',
            'banner'  => $store_info['banner'] ?? '',
            'website' => function_exists( 'sk_get_store_url' ) ? sk_get_store_url( $user_id ) : '',
            'lud16'   => 'v/' . $user_id . '@' . $domain,
            'nip05'   => ( $user ? $user->user_nicename : $user_id ) . '@' . $domain,
        ];

        // Remove empty values.
        $profile = array_filter( $profile );

        return self::publish( $user_id, 0, wp_json_encode( $profile, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
    }

    /**
     * Get configured relays.
     */
    public static function get_relays(): array {
        $option = get_option( 'nostr_login_relays', "wss://purplepag.es\nwss://relay.nostr.band\nwss://relay.primal.net\nwss://relay.damus.io" );
        // Support both newline and comma separated.
        $relays = preg_split( '/[\n,]+/', $option );
        return array_values( array_filter( array_map( 'trim', $relays ) ) );
    }

    // ── Encryption ──

    private static function encrypt( string $data ): string {
        $key    = hash( 'sha256', wp_salt( 'auth' ), true );
        $iv_len = openssl_cipher_iv_length( self::$encryption_method );
        $iv     = openssl_random_pseudo_bytes( $iv_len );
        $cipher = openssl_encrypt( $data, self::$encryption_method, $key, OPENSSL_RAW_DATA, $iv );
        return base64_encode( $iv . $cipher );
    }

    private static function decrypt( string $data ): string {
        $key    = hash( 'sha256', wp_salt( 'auth' ), true );
        $raw    = base64_decode( $data );
        $iv_len = openssl_cipher_iv_length( self::$encryption_method );
        $iv     = substr( $raw, 0, $iv_len );
        $cipher = substr( $raw, $iv_len );
        return openssl_decrypt( $cipher, self::$encryption_method, $key, OPENSSL_RAW_DATA, $iv ) ?: '';
    }
}
