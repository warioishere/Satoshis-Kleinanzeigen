<?php

namespace SK\Modules\NostrMarket;

use swentel\nostr\Event\Event;
use swentel\nostr\Sign\Sign;
use swentel\nostr\Relay\Relay;
use swentel\nostr\Message\EventMessage;

defined( 'ABSPATH' ) || exit;

/**
 * Nostr Event sender — signs and publishes events to relays.
 * Shared by ProductPublisher and ProductDeleter.
 */
class EventSender {

    /**
     * Create, sign, and send a Nostr event.
     *
     * @param int    $kind    Event kind (30402 listing, 5 deletion, 4 DM).
     * @param string $content Event content.
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

                    if ( self::relay_accepted( $result ) ) {
                        $sent_any = true;
                    } else {
                        error_log( "[SK Nostr Market] Relay {$relay_url} lehnte Event {$event_id} ab: " . self::relay_message( $result ) );
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
     * Ein bereits signiertes Ereignis an die Relays geben.
     *
     * Fuer Inserate, die der Anbieter selbst im Browser signiert hat: den
     * Schluessel bekommen wir dabei nie zu sehen, nur das fertige Ereignis.
     * Verteilt wird es ueber dieselbe Schleife und dieselbe Erfolgspruefung
     * wie alles andere.
     *
     * @param array $signed_event
     * @return string|null Ereigniskennung, wenn ein Relay es angenommen hat.
     */
    public static function send_signed( array $signed_event ): ?string {
        $event_id = (string) ( $signed_event['id'] ?? '' );

        if ( '' === $event_id ) {
            return null;
        }

        $relays = self::get_relays();

        if ( empty( $relays ) ) {
            error_log( '[SK Nostr Market] Keine Relays konfiguriert.' );
            return null;
        }

        $sent_any = false;

        foreach ( $relays as $relay_url ) {
            try {
                $msg   = new \swentel\nostr\Message\EventMessage( (object) $signed_event );
                $relay = new Relay( $relay_url );

                if ( method_exists( $relay, 'setTimeout' ) ) {
                    $relay->setTimeout( 3 );
                }

                $relay->setMessage( $msg );
                $result = $relay->send();

                if ( self::relay_accepted( $result ) ) {
                    $sent_any = true;
                } else {
                    error_log( "[SK Nostr Market] Relay {$relay_url} lehnte Event {$event_id} ab: " . self::relay_message( $result ) );
                }
            } catch ( \Exception $e ) {
                error_log( "[SK Nostr Market] Relay {$relay_url} error: " . $e->getMessage() );
            }
        }

        return $sent_any ? $event_id : null;
    }

    /**
     * Hat das Relay das Ereignis wirklich angenommen?
     *
     * Relay::send() liefert immer ein Objekt, nie false — die alte Pruefung
     * "!== false" wertete deshalb auch ein ablehnendes Relay als Erfolg, und
     * das Inserat galt als veroeffentlicht, obwohl es niemand genommen hatte.
     *
     * Nur ein ausdrueckliches isSuccess=false zaehlt als Ablehnung; alles
     * Unerwartete gilt als angenommen, damit nichts doppelt gesendet wird.
     * Dieselbe Pruefung steht im Auto Poster, wo der Fehler zuerst auffiel.
     *
     * @param mixed $response
     */
    private static function relay_accepted( $response ): bool {
        if ( is_object( $response ) && property_exists( $response, 'isSuccess' ) ) {
            return (bool) $response->isSuccess;
        }

        return $response !== false;
    }

    /**
     * Lesbarer Grund einer Ablehnung, fuers Protokoll.
     *
     * @param mixed $response
     */
    private static function relay_message( $response ): string {
        if ( is_object( $response ) && property_exists( $response, 'message' ) && $response->message !== '' ) {
            return (string) $response->message;
        }

        return 'kein Grund genannt';
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
