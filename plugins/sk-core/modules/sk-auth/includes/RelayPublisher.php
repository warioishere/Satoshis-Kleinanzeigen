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

    /**
     * @param EventInterface $event  Signed event.
     * @param string[]       $relays Relay URLs.
     * @return array{accepted: string[], rejected: array<string, string>}
     *               URLs that accepted, and URL => reason for the rest.
     */
    public static function publish( EventInterface $event, array $relays ): array {
        $accepted = [];
        $rejected = [];

        if ( ! class_exists( '\WebSocket\Client' ) ) {
            foreach ( $relays as $url ) {
                $rejected[ $url ] = 'websocket client missing';
            }

            return compact( 'accepted', 'rejected' );
        }

        $payload  = ( new EventMessage( $event ) )->generate();
        $event_id = $event->getId();

        foreach ( $relays as $url ) {
            $result = self::send_one( $url, $payload, $event_id );

            if ( true === $result ) {
                $accepted[] = $url;
            } else {
                $rejected[ $url ] = $result;
                error_log( '[RelayPublisher] ' . $url . ' did not accept ' . substr( $event_id, 0, 12 ) . ': ' . $result );
            }
        }

        return compact( 'accepted', 'rejected' );
    }

    /**
     * Send to one relay and wait for its verdict.
     *
     * NOTICE and AUTH are read and skipped: some relays send them before the
     * OK, and neither says whether the event was stored.
     *
     * @return true|string True when accepted, otherwise the reason.
     */
    private static function send_one( string $url, string $payload, string $event_id ) {
        $client = null;
        $notice = '';

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
                    case 'OK':
                        if ( ( $data[1] ?? '' ) !== $event_id ) {
                            continue 2;
                        }

                        $client->disconnect();

                        return ! empty( $data[2] ) ? true : ( (string) ( $data[3] ?? '' ) ?: 'rejected without reason' );

                    case 'NOTICE':
                        $notice = (string) ( $data[1] ?? '' );
                        continue 2;

                    case 'CLOSED':
                        $client->disconnect();

                        return 'closed: ' . (string) ( $data[2] ?? '' );

                    default:
                        // AUTH challenges and anything else: keep waiting for the OK.
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
