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
     * Id and signature are verified here, not left to the relay. The input
     * comes from a browser; without the check an arbitrary id could be
     * written into the product meta.
     *
     * @param array $signed_event
     * @return string|null Ereigniskennung, wenn ein Relay es angenommen hat.
     */
    public static function send_signed( array $signed_event ): ?string {
        $event = self::event_from_array( $signed_event );

        if ( null === $event ) {
            return null;
        }

        $event_id = $event->getId();
        $relays   = self::get_relays();

        if ( empty( $relays ) ) {
            error_log( '[SK Nostr Market] Keine Relays konfiguriert.' );
            return null;
        }

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
    }

    /**
     * Build an Event from raw data as a browser sends it.
     *
     * The id has to match the content and the signature has to match the
     * pubkey. The library checks both: the id is the SHA-256 of the
     * canonical form, the signature is Schnorr over the id.
     *
     * @param array $raw
     */
    private static function event_from_array( array $raw ): ?Event {
        $hex64 = '/^[0-9a-f]{64}$/';

        if ( ! isset( $raw['id'], $raw['pubkey'], $raw['sig'], $raw['kind'], $raw['created_at'] ) ) {
            return null;
        }

        if ( ! is_string( $raw['id'] ) || ! preg_match( $hex64, $raw['id'] )
            || ! is_string( $raw['pubkey'] ) || ! preg_match( $hex64, $raw['pubkey'] )
            || ! is_string( $raw['sig'] ) || ! preg_match( '/^[0-9a-f]{128}$/', $raw['sig'] )
            || ! is_int( $raw['kind'] ) || ! is_int( $raw['created_at'] ) ) {
            return null;
        }

        $raw['content'] = isset( $raw['content'] ) && is_string( $raw['content'] ) ? $raw['content'] : '';
        $raw['tags']    = isset( $raw['tags'] ) && is_array( $raw['tags'] ) ? $raw['tags'] : [];

        try {
            $event = new Event();

            if ( ! $event->verify( (object) $raw ) ) {
                error_log( '[SK Nostr Market] Signed event ' . $raw['id'] . ' failed verification.' );
                return null;
            }

            $event->populate( (object) $raw );

            return $event;
        } catch ( \Throwable $e ) {
            error_log( '[SK Nostr Market] Signed event unusable: ' . $e->getMessage() );
            return null;
        }
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
     * Get the Nostr private key (reuses Auto Poster's key), always as hex.
     *
     * Der Schluessel darf als nsec hinterlegt sein — auf Live ist er das.
     * Sign::signEvent() wandelt das selbst um, Key::getPublicKey() nicht: dort
     * warf ein nsec einen ValueError. Deshalb wird hier einmal zentral
     * normalisiert, damit jeder Aufrufer Hex bekommt.
     */
    public static function get_privkey(): ?string {
        // Priority: wp-config constant → Auto Poster setting → filter.
        if ( defined( 'NAP_NOSTR_PRIVKEY' ) && NAP_NOSTR_PRIVKEY ) {
            $key = self::to_hex( (string) NAP_NOSTR_PRIVKEY );

            if ( null !== $key ) {
                return $key;
            }
        }

        if ( function_exists( 'nap_resolve_private_key' ) ) {
            $key = nap_resolve_private_key();

            if ( $key ) {
                return self::to_hex( (string) $key );
            }
        }

        // Fallback: read from Auto Poster options directly.
        $opts = get_option( 'nap_nostr_options', [] );

        return self::to_hex( (string) ( $opts['private_key'] ?? '' ) );
    }

    /**
     * Einen Schluessel auf 64 Hex-Zeichen bringen, oder null.
     */
    private static function to_hex( string $key ): ?string {
        $key = trim( $key );

        if ( preg_match( '/^[0-9a-fA-F]{64}$/', $key ) ) {
            return strtolower( $key );
        }

        if ( 0 === strpos( $key, 'nsec' ) && class_exists( '\swentel\nostr\Key\Key' ) ) {
            try {
                $hex = ( new \swentel\nostr\Key\Key() )->convertToHex( $key );

                if ( preg_match( '/^[0-9a-fA-F]{64}$/', (string) $hex ) ) {
                    return strtolower( (string) $hex );
                }
            } catch ( \Throwable $e ) {
                error_log( '[SK Nostr Market] nsec liess sich nicht umwandeln: ' . $e->getMessage() );
            }
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

        /*
         * \Throwable, nicht \Exception: die Bibliothek wirft bei einem
         * unbrauchbaren Schluessel einen ValueError, und der ist ein Error.
         * Er lief deshalb bis in die Einstellungsseite durch und riss sie mit.
         */
        try {
            $key = new \swentel\nostr\Key\Key();
            return $key->getPublicKey( $privkey );
        } catch ( \Throwable $e ) {
            error_log( '[SK Nostr Market] Pubkey liess sich nicht ableiten: ' . $e->getMessage() );
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
