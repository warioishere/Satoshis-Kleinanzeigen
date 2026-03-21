<?php

namespace SK\Modules\NostrMarket;

use swentel\nostr\Event\Event;
use swentel\nostr\Sign\Sign;
use swentel\nostr\Relay\Relay;
use swentel\nostr\Message\EventMessage;

defined( 'ABSPATH' ) || exit;

/**
 * Nostr Event sender — signs and publishes events to relays.
 * Shared by StallManager, ProductPublisher, and ProductDeleter.
 */
class EventSender {

    /**
     * Create, sign, and send a Nostr event.
     *
     * @param int    $kind    Event kind (30017, 30018, 5, etc.)
     * @param string $content Event content (JSON string for NIP-15).
     * @param array  $tags    Array of tag arrays.
     * @return string|null     Event ID on success, null on failure.
     */
    public static function send( int $kind, string $content, array $tags ): ?string {
        $privkey = self::get_privkey();
        if ( ! $privkey ) {
            error_log( '[SK Nostr Market] Kein Private Key konfiguriert.' );
            return null;
        }

        $relays = self::get_relays();
        if ( empty( $relays ) ) {
            error_log( '[SK Nostr Market] Keine Relays konfiguriert.' );
            return null;
        }

        if ( ! class_exists( '\swentel\nostr\Event\Event' ) ) {
            error_log( '[SK Nostr Market] Nostr PHP Library nicht gefunden.' );
            return null;
        }

        try {
            $event = new Event();
            $event->setKind( $kind );
            $event->setContent( $content );

            foreach ( $tags as $tag ) {
                $event->addTag( $tag );
            }

            $signer = new Sign();
            $signer->signEvent( $event, $privkey );

            $event_id = $event->getId();
            $sent_any = false;

            foreach ( $relays as $relay_url ) {
                try {
                    $msg   = new EventMessage( $event );
                    $relay = new Relay( $relay_url );
                    if ( method_exists( $relay, 'setTimeout' ) ) {
                        $relay->setTimeout( 3 );
                    }
                    $relay->setMessage( $msg );
                    $result = $relay->send();
                    if ( $result !== false ) {
                        $sent_any = true;
                    }
                } catch ( \Exception $e ) {
                    error_log( "[SK Nostr Market] Relay {$relay_url} error: " . $e->getMessage() );
                }
            }

            return $sent_any ? $event_id : null;

        } catch ( \Exception $e ) {
            error_log( '[SK Nostr Market] Event error: ' . $e->getMessage() );
            return null;
        }
    }

    /**
     * Send a Kind 5 deletion event referencing another event.
     */
    public static function delete( string $event_id ): bool {
        $result = self::send( 5, '', [ [ 'e', $event_id ] ] );
        return $result !== null;
    }

    /**
     * Get the Nostr private key (reuses Auto Poster's key).
     */
    public static function get_privkey(): ?string {
        // Priority: wp-config constant → Auto Poster setting → filter.
        if ( defined( 'NAP_NOSTR_PRIVKEY' ) && preg_match( '/^[0-9a-fA-F]{64}$/', NAP_NOSTR_PRIVKEY ) ) {
            return NAP_NOSTR_PRIVKEY;
        }

        if ( function_exists( 'nap_resolve_private_key' ) ) {
            $key = nap_resolve_private_key();
            return $key ?: null;
        }

        // Fallback: read from Auto Poster options directly.
        $opts = get_option( 'nap_nostr_options', [] );
        $key  = $opts['private_key'] ?? '';
        if ( preg_match( '/^[0-9a-fA-F]{64}$/', $key ) ) {
            return $key;
        }

        return null;
    }

    /**
     * Get the Nostr public key derived from the private key.
     */
    public static function get_pubkey(): ?string {
        $privkey = self::get_privkey();
        if ( ! $privkey ) {
            return null;
        }

        try {
            $key = new \swentel\nostr\Key\Key();
            return $key->getPublicKey( $privkey );
        } catch ( \Exception $e ) {
            return null;
        }
    }

    /**
     * Get relay URLs.
     * Uses own config if set, falls back to Auto Poster relays.
     */
    public static function get_relays(): array {
        // Own relays from settings.
        $own = sk_get_option( 'sk_nostr_market_relays', 'sk_nostr_market', '' );
        if ( ! empty( trim( $own ) ) ) {
            return self::parse_relays( $own );
        }

        // Fallback to Auto Poster relays.
        if ( function_exists( 'nap_get_relays' ) ) {
            return nap_get_relays();
        }

        $opts   = get_option( 'nap_nostr_options', [] );
        $relays = $opts['relays'] ?? "wss://relay.nostr.band\nwss://nos.lol";
        return self::parse_relays( $relays );
    }

    private static function parse_relays( string $raw ): array {
        $lines  = preg_split( '/[\n,\s]+/', $raw );
        $relays = [];
        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( preg_match( '#^wss?://#i', $line ) ) {
                $relays[] = $line;
            }
        }
        return $relays;
    }
}
