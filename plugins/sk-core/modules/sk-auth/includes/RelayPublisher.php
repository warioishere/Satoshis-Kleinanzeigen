<?php

namespace SK\Modules\Auth;

use swentel\nostr\EventInterface;
use swentel\nostr\Message\EventMessage;

defined( 'ABSPATH' ) || exit;

/**
 * Sends a signed event to relays and reports which of them accepted it.
 *
 * The library's Relay::send() reads exactly one message and its response
 * object calls everything but an explicit OK-false a success. A relay that
 * answers NOTICE, an AUTH challenge or nothing at all therefore counted as
 * "accepted", and the client waited the default 60 seconds for a silent
 * one. Here the answer is read until the relay says OK for this very event
 * id, within a short timeout, and only OK-true counts.
 */
class RelayPublisher {

    /** Seconds per relay: connect, send and wait for the OK. */
    const TIMEOUT = 5;

    /** How long a relay is left alone after an attempt that never returned. */
    const STALL_SKIP = 6 * HOUR_IN_SECONDS;

    /**
     * @param EventInterface|array $event  Signed event, as the library's
     *                                     object or as the plain array the
     *                                     rest of the plugin works with.
     * @param string[]       $relays       Relay URLs.
     * @param string|null    $auth_privkey Key to answer a NIP-42 challenge
     *                                     with — normally the key that
     *                                     signed the event. Without one a
     *                                     fresh throwaway key answers, which
     *                                     is all a gift wrap can offer: its
     *                                     own signing key is random and the
     *                                     real sender must stay out of it.
     * @return array{accepted: string[], rejected: array<string, string>}
     *               URLs that accepted, and URL => reason for the rest.
     */
    public static function publish( $event, array $relays, ?string $auth_privkey = null ): array {
        $accepted = [];
        $rejected = [];

        if ( is_array( $event ) ) {
            $event = \SK\Core\Nostr\Events::to_object( $event );
        }

        if ( ! $event instanceof EventInterface ) {
            foreach ( $relays as $url ) {
                $rejected[ $url ] = 'not an event';
            }

            return compact( 'accepted', 'rejected' );
        }

        if ( ! class_exists( '\WebSocket\Client' ) ) {
            foreach ( $relays as $url ) {
                $rejected[ $url ] = 'websocket client missing';
            }

            return compact( 'accepted', 'rejected' );
        }

        $payload  = ( new EventMessage( $event ) )->generate();
        $event_id = $event->getId();

        foreach ( $relays as $url ) {
            if ( self::stalled( $url ) ) {
                $rejected[ $url ] = 'skipped: an earlier attempt never returned';
                continue;
            }

            self::mark_attempt( $url );

            $result = self::send_one( $url, $payload, $event_id, $auth_privkey );

            self::clear_attempt( $url );

            if ( true === $result ) {
                $accepted[] = $url;
            } else {
                $rejected[ $url ] = $result;
                error_log( '[RelayPublisher] ' . $url . ' did not accept ' . substr( $event_id, 0, 12 ) . ': ' . $result );
            }
        }

        return compact( 'accepted', 'rejected' );
    }

    // ── The circuit breaker, for every place that dials a relay ───────────

    /**
     * Is this relay left alone after an attempt that never came back?
     */
    public static function stalled( string $url ): bool {
        return (bool) get_transient( self::breaker_key( $url ) );
    }

    /**
     * Note an attempt before it starts.
     *
     * Not every failure comes back as a failure: a relay has taken the
     * whole PHP process down mid-connection, and such an attempt never
     * reaches the line that clears this. The marker outlives the crash, so
     * the next caller skips that relay instead of dying with it. Publishing
     * had this from the start; reading (the DM poll, profile and relay-list
     * lookups) dialled the same relays without it.
     */
    public static function mark_attempt( string $url ): void {
        set_transient( self::breaker_key( $url ), 1, self::STALL_SKIP );
    }

    /**
     * The attempt came back, whatever it brought.
     */
    public static function clear_attempt( string $url ): void {
        delete_transient( self::breaker_key( $url ) );
    }

    private static function breaker_key( string $url ): string {
        return 'sk_relay_stalled_' . md5( $url );
    }

    // ── NIP-42: answering a relay's AUTH challenge ────────────────────────

    /**
     * Answer a NIP-42 challenge: a kind 22242 naming the relay and the
     * challenge, signed with the given key, sent as ["AUTH", event].
     *
     * A relay that guards its inbox — the DM inbox relays Amethyst users
     * list are the common case — answers every REQ and EVENT with
     * "auth-required" until this is done. Before, such a relay cost the
     * full timeout per attempt and took nothing.
     *
     * @return string The id of the auth event, so its OK can be told apart.
     */
    public static function answer_challenge( \WebSocket\Client $client, string $relay_url, string $challenge, string $privkey ): string {
        $auth = \SK\Core\Nostr\Events::sign( 22242, '', [ [ 'relay', $relay_url ], [ 'challenge', $challenge ] ], $privkey );

        $client->text( '["AUTH",' . wp_json_encode( $auth, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . ']' );

        return (string) $auth['id'];
    }

    /**
     * A key for challenges when the caller has none to offer.
     */
    public static function throwaway_key(): string {
        return \SK\Core\Nostr\Keys::generate()['priv'];
    }

    /**
     * Does a relay's reason say it wants NIP-42 first?
     */
    public static function wants_auth( string $reason ): bool {
        return 0 === strpos( $reason, 'auth-required' );
    }

    /**
     * Send to one relay and wait for its verdict.
     *
     * NOTICE is read and skipped: some relays send it before the OK, and it
     * says nothing about whether the event was stored. An AUTH challenge is
     * answered, and an event the relay refused with "auth-required" is sent
     * once more after the relay has accepted the answer.
     *
     * @return true|string True when accepted, otherwise the reason.
     */
    private static function send_one( string $url, string $payload, string $event_id, ?string $auth_privkey = null ) {
        $client = null;
        $notice = '';

        // NIP-42 state for this connection.
        $auth_id = null;   // id of the answer sent, or null
        $authed  = false;  // the relay accepted the answer
        $resend  = false;  // the event was refused for lack of auth
        $resent  = false;  // it went out a second time

        try {
            $client = new \WebSocket\Client( $url );
            $client->setTimeout( self::TIMEOUT );
            $client->text( $payload );

            $deadline = microtime( true ) + self::TIMEOUT;

            while ( microtime( true ) < $deadline ) {
                $data = json_decode( $client->receive()->getContent(), true );

                if ( ! is_array( $data ) || ! isset( $data[0] ) ) {
                    continue;
                }

                switch ( $data[0] ) {
                    case 'AUTH':
                        if ( null === $auth_id && is_string( $data[1] ?? null ) && '' !== $data[1] ) {
                            $auth_id = self::answer_challenge( $client, $url, $data[1], $auth_privkey ?: self::throwaway_key() );
                        }
                        continue 2;

                    case 'OK':
                        if ( null !== $auth_id && ( $data[1] ?? '' ) === $auth_id ) {
                            $authed = ! empty( $data[2] );

                            if ( $authed && $resend && ! $resent ) {
                                $client->text( $payload );
                                $resent = true;
                            }

                            continue 2;
                        }

                        if ( ( $data[1] ?? '' ) !== $event_id ) {
                            continue 2;
                        }

                        if ( ! empty( $data[2] ) ) {
                            $client->disconnect();

                            return true;
                        }

                        $reason = (string) ( $data[3] ?? '' );

                        // Refused only for lack of auth: once the relay has
                        // taken our answer, the event goes out again.
                        if ( self::wants_auth( $reason ) && ! $resent && null !== $auth_id ) {
                            $resend = true;

                            if ( $authed ) {
                                $client->text( $payload );
                                $resent = true;
                            }

                            continue 2;
                        }

                        $client->disconnect();

                        return $reason ?: 'rejected without reason';

                    case 'NOTICE':
                        $notice = (string) ( $data[1] ?? '' );
                        continue 2;

                    case 'CLOSED':
                        $client->disconnect();

                        return 'closed: ' . (string) ( $data[2] ?? '' );

                    default:
                        // Anything else: keep waiting for the OK.
                        continue 2;
                }
            }

            $client->disconnect();

            return 'no OK within ' . self::TIMEOUT . 's' . ( $notice ? ' (notice: ' . $notice . ')' : '' );
        } catch ( \Throwable $e ) {
            if ( $client ) {
                try {
                    $client->disconnect();
                } catch ( \Throwable $ignored ) {
                    // Already gone.
                }
            }

            return $e->getMessage() . ( $notice ? ' (notice: ' . $notice . ')' : '' );
        }
    }
}
