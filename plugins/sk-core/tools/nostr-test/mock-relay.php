<?php
/**
 * A local Nostr relay for tests. Nothing leaves the machine.
 *
 * Serves the events in an events file that match a REQ filter (kinds,
 * authors, #p, since, until, limit; newest first), then EOSE. Answers every
 * EVENT with OK true and pushes it to every open subscription it matches.
 * Logs what it receives, one JSON line per message. The
 * events file is re-read on every REQ, so a test can swap scenarios while
 * the relay runs.
 *
 *   php mock-relay.php --port=18080 --events=/path/events.json --log=/path/relay.jsonl
 *
 * Only connections from 127.0.0.1 are answered.
 */

if ( PHP_SAPI !== 'cli' ) {
    exit( 1 );
}

require dirname( __DIR__, 2 ) . '/lib/autoload.php';

$opts   = getopt( '', [ 'port::', 'events::', 'log::' ] );
$port   = (int) ( $opts['port'] ?? 18080 );
$events = (string) ( $opts['events'] ?? __DIR__ . '/events.json' );
$logf   = (string) ( $opts['log'] ?? __DIR__ . '/relay.jsonl' );
$log    = fopen( $logf, 'a' );

$server = new WebSocket\Server( $port );
$server->addMiddleware( new WebSocket\Middleware\CloseHandler() );
$server->addMiddleware( new WebSocket\Middleware\PingResponder() );

$server->onHandshake( function ( $server, $conn, $request, $response ) {
    if ( 0 !== strpos( (string) $conn->getRemoteName(), '127.0.0.1:' ) ) {
        $conn->close();
    }
} );

$server->onText( function ( $server, $conn, $message ) use ( $events, $log ) {
    $msg = json_decode( $message->getContent(), true );

    if ( ! is_array( $msg ) || ! isset( $msg[0] ) ) {
        return;
    }

    fwrite( $log, json_encode( [ 'in' => $msg[0], 'payload' => array_slice( $msg, 1 ) ] ) . "\n" );

    if ( 'REQ' === $msg[0] ) {
        $sub  = $msg[1];
        $list = json_decode( (string) @file_get_contents( $events ), true ) ?: [];

        usort( $list, static fn( $a, $b ) => ( $b['created_at'] ?? 0 ) <=> ( $a['created_at'] ?? 0 ) );

        foreach ( array_slice( $msg, 2 ) as $f ) {
            $sent = 0;

            foreach ( $list as $e ) {
                if ( ! mock_matches( $f, $e ) ) {
                    continue;
                }
                if ( isset( $f['limit'] ) && $sent >= $f['limit'] ) {
                    break;
                }

                $conn->text( json_encode( [ 'EVENT', $sub, $e ] ) );
                $sent++;
            }
        }

        $conn->text( json_encode( [ 'EOSE', $sub ] ) );

        // Kept open: whatever is published later and matches is pushed.
        $subs         = (array) $conn->getMeta( 'subs' );
        $subs[ $sub ] = array_slice( $msg, 2 );
        $conn->setMeta( 'subs', $subs );
    } elseif ( 'EVENT' === $msg[0] ) {
        $conn->text( json_encode( [ 'OK', $msg[1]['id'] ?? '', true, '' ] ) );

        foreach ( $server->getConnections() as $other ) {
            foreach ( (array) $other->getMeta( 'subs' ) as $sub => $filters ) {
                foreach ( $filters as $f ) {
                    if ( mock_matches( $f, $msg[1] ) ) {
                        $other->text( json_encode( [ 'EVENT', $sub, $msg[1] ] ) );
                        break;
                    }
                }
            }
        }
    } elseif ( 'CLOSE' === $msg[0] ) {
        $subs = (array) $conn->getMeta( 'subs' );
        unset( $subs[ $msg[1] ?? '' ] );
        $conn->setMeta( 'subs', $subs );
    }
} );

/** Does the event pass the filter (kinds, authors, #p, since, until)? */
function mock_matches( array $f, array $e ): bool {
    if ( isset( $f['kinds'] ) && ! in_array( $e['kind'], $f['kinds'], true ) ) {
        return false;
    }
    if ( isset( $f['authors'] ) && ! in_array( $e['pubkey'], $f['authors'], true ) ) {
        return false;
    }
    if ( isset( $f['#p'] ) ) {
        $hit = false;
        foreach ( (array) ( $e['tags'] ?? [] ) as $t ) {
            if ( 'p' === ( $t[0] ?? '' ) && in_array( $t[1] ?? '', $f['#p'], true ) ) {
                $hit = true;
            }
        }
        if ( ! $hit ) {
            return false;
        }
    }
    if ( isset( $f['since'] ) && $e['created_at'] < $f['since'] ) {
        return false;
    }
    if ( isset( $f['until'] ) && $e['created_at'] > $f['until'] ) {
        return false;
    }

    return true;
}

$server->start();
