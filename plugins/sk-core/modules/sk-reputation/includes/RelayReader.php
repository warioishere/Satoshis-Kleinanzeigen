<?php

namespace SK\Modules\Reputation;

defined( 'ABSPATH' ) || exit;

/**
 * Reading from the site's relays, for cron jobs only.
 *
 * One REQ per call, events until EOSE or the timeout, guarded by the same
 * circuit breaker as every other relay call in sk-core: a relay that took
 * a process down is skipped for a while. Nothing here may run while a page
 * renders.
 */
class RelayReader {

    const TIMEOUT    = 10;
    const MAX_EVENTS = 2000;

    /** @return string[] */
    public static function relays(): array {
        if ( sk_module_active( 'sk_auth' ) && class_exists( 'SK\Modules\Auth\NostrIdentity' ) ) {
            return \SK\Modules\Auth\NostrIdentity::get_relays();
        }

        return [];
    }

    /**
     * @return array<int, array> Raw events, unverified.
     */
    public static function req( string $relay, array $filters, int $timeout = self::TIMEOUT, int $max = self::MAX_EVENTS ): array {
        return self::fetch( $relay, $filters, $timeout, $max )['events'];
    }

    /**
     * One REQ, and whether the relay actually finished answering it.
     *
     * An empty result means two different things: the relay has nothing,
     * or the relay never answered (unreachable, stalled, timed out). A
     * caller about to publish a replacement for something it did not find
     * must tell them apart — hence `eose`, true only when the relay closed
     * the subscription itself with EOSE (or CLOSED).
     *
     * @return array{events: array<int, array>, eose: bool} Raw events, unverified.
     */
    public static function fetch( string $relay, array $filters, int $timeout = self::TIMEOUT, int $max = self::MAX_EVENTS ): array {
        $none = [ 'events' => [], 'eose' => false ];

        if ( ! class_exists( '\WebSocket\Client' ) ) {
            return $none;
        }

        $breaker = class_exists( 'SK\Modules\Auth\RelayPublisher' );

        if ( $breaker && \SK\Modules\Auth\RelayPublisher::stalled( $relay ) ) {
            return $none;
        }

        if ( $breaker ) {
            \SK\Modules\Auth\RelayPublisher::mark_attempt( $relay );
        }

        $events = [];
        $eose   = false;
        $sub    = bin2hex( random_bytes( 8 ) );

        try {
            $client = new \WebSocket\Client( $relay );
            $client->setTimeout( $timeout );
            $client->text( wp_json_encode( array_merge( [ 'REQ', $sub ], $filters ) ) );

            $deadline = microtime( true ) + $timeout;

            while ( microtime( true ) < $deadline && count( $events ) < $max ) {
                $data = json_decode( $client->receive()->getContent(), true );

                if ( ! is_array( $data ) || ( $data[1] ?? '' ) !== $sub ) {
                    continue;
                }

                if ( 'EOSE' === $data[0] || 'CLOSED' === $data[0] ) {
                    $eose = true;
                    break;
                }

                if ( 'EVENT' === $data[0] && is_array( $data[2] ?? null ) && is_string( $data[2]['id'] ?? null ) ) {
                    $events[] = $data[2];
                }
            }

            $client->close();
        } catch ( \Throwable $e ) {
            // A silent or unreachable relay contributes nothing.
        }

        if ( $breaker ) {
            \SK\Modules\Auth\RelayPublisher::clear_attempt( $relay );
        }

        return [ 'events' => $events, 'eose' => $eose ];
    }

    /**
     * Whether an event is what it claims: well-formed, id recomputed,
     * signature valid, and — when given — of the expected kind and from
     * one of the expected authors. Relays check signatures on the way in,
     * but nothing here relies on a relay being honest.
     *
     * @param string[]|null $authors Lowercase hex keys the event must come from.
     */
    public static function verified( $event, ?int $kind = null, ?array $authors = null ): bool {
        return \SK\Core\Nostr\Events::verify( $event, $kind, $authors );
    }

    /**
     * The newest replaceable event per author across all relays, each
     * verified against its signature and the author it was asked for.
     *
     * @param string[] $authors
     * @param int|null $answered Set to the number of relay requests that
     *                           reached EOSE. Zero means nobody answered,
     *                           which is not the same as nobody having it.
     * @return array<string, array> author => event
     */
    public static function latest( int $kind, array $authors, ?int &$answered = null ): array {
        $latest   = [];
        $answered = 0;
        $authors  = array_values( array_unique( array_map( 'strtolower', array_map( 'strval', $authors ) ) ) );

        foreach ( array_chunk( $authors, 100 ) as $chunk ) {
            foreach ( self::relays() as $relay ) {
                $result = self::fetch( $relay, [ [ 'kinds' => [ $kind ], 'authors' => $chunk ] ] );

                if ( $result['eose'] ) {
                    $answered++;
                }

                foreach ( $result['events'] as $event ) {
                    if ( ! self::verified( $event, $kind, $chunk ) ) {
                        continue;
                    }

                    $author = strtolower( $event['pubkey'] );

                    if ( ! isset( $latest[ $author ] ) || $latest[ $author ]['created_at'] < $event['created_at'] ) {
                        $latest[ $author ] = $event;
                    }
                }
            }
        }

        return $latest;
    }
}
