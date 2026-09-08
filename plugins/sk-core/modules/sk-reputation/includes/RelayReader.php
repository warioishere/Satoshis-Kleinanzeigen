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
        if ( ! class_exists( '\WebSocket\Client' ) ) {
            return [];
        }

        $breaker = class_exists( 'SK\Modules\Auth\RelayPublisher' );

        if ( $breaker && \SK\Modules\Auth\RelayPublisher::stalled( $relay ) ) {
            return [];
        }

        if ( $breaker ) {
            \SK\Modules\Auth\RelayPublisher::mark_attempt( $relay );
        }

        $events = [];
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

        return $events;
    }

    /**
     * The newest replaceable event per author across all relays.
     *
     * @param string[] $authors
     * @return array<string, array> author => event
     */
    public static function latest( int $kind, array $authors ): array {
        $latest = [];

        foreach ( array_chunk( array_values( $authors ), 100 ) as $chunk ) {
            foreach ( self::relays() as $relay ) {
                foreach ( self::req( $relay, [ [ 'kinds' => [ $kind ], 'authors' => $chunk ] ] ) as $event ) {
                    $author = strtolower( (string) ( $event['pubkey'] ?? '' ) );

                    if ( ! isset( $latest[ $author ] ) || $latest[ $author ]['created_at'] < $event['created_at'] ) {
                        $latest[ $author ] = $event;
                    }
                }
            }
        }

        return $latest;
    }
}
