<?php
/**
 * SK\Core\Nostr\RelaySession held open: subscribe(), wait() across several
 * sessions, pump() with and without verification, breaker lifted while a
 * subscription is held. Needs the mock relay running (see run.sh).
 */
require __DIR__ . '/bootstrap.php';

use SK\Core\Nostr\Events;
use SK\Core\Nostr\Relays;
use SK\Core\Nostr\RelaySession;

$mock = getenv( 'MOCK_RELAY' ) ?: 'ws://127.0.0.1:18080';
$dead = 'ws://127.0.0.1:1';

sk_test_relay_events( [] );

$kp  = sk_test_keypair();
$got = [ 'a' => [], 'b' => [] ];

$a = Relays::session( $mock, [ 'timeout' => 5, 'verify' => true ] );
$b = Relays::session( $mock, [ 'timeout' => 5, 'verify' => false ] );
$d = Relays::session( $dead, [ 'timeout' => 2 ] );

$filter = [ [ 'kinds' => [ 1 ], 'authors' => [ $kp['pub'] ] ] ];

sk_check_eq( [ $a->open(), $d->open() ], [ true, false ], 'a session opens, the dead relay does not' );
sk_check_eq( Relays::stalled( $mock ), true, 'breaker marked after the dial' );
sk_check_eq( $b->open(), false, 'a second dial while the breaker is marked is refused' );
sk_check( is_string( $a->subscribe( $filter, function ( $e ) use ( &$got ) { $got['a'][] = $e['content']; } ) ), 'subscribe(): returns an id' );
sk_check_eq( Relays::stalled( $mock ), false, 'breaker lifted once a subscription is held' );
sk_check_eq( $b->open(), true, 'the second session opens now' );
sk_check( is_string( $b->subscribe( $filter, function ( $e ) use ( &$got ) { $got['b'][] = $e['content']; } ) ), 'subscribe(): second session too' );
sk_check_eq( $d->subscribe( $filter, function () {} ), null, 'subscribe() on a closed session is null' );

// The stored-events phase ends with EOSE on each session; drain it.
$drain = function ( array $sessions, float $for ) {
    $until = microtime( true ) + $for;
    while ( microtime( true ) < $until ) {
        foreach ( RelaySession::wait( $sessions, 0.2 ) as $s ) {
            $s->pump();
        }
    }
};
$drain( [ 'a' => $a, 'b' => $b, 'd' => $d ], 0.6 );
sk_check_eq( $got, [ 'a' => [], 'b' => [] ], 'nothing delivered before anything is published' );
sk_check_eq( RelaySession::wait( [ 'a' => $a, 'b' => $b, 'd' => $d ], 0.2 ), [], 'wait(): quiet sessions and a closed one give nothing' );

// A signed event published through a third connection reaches both.
$t0   = microtime( true );
$good = sk_test_sign( $kp['priv'], 1, [], 'live', time() );
sk_check_eq( Relays::publish( $good, [ $mock ] )['accepted'], [ $mock ], 'publish(): mock took the event' );

$ready = RelaySession::wait( [ 'a' => $a, 'b' => $b ], 2.0 );
sk_check( isset( $ready['a'] ) || isset( $ready['b'] ), 'wait(): a session became readable', 'keys ' . implode( ',', array_keys( $ready ) ) );
$drain( [ 'a' => $a, 'b' => $b ], 0.5 );
sk_check_eq( $got, [ 'a' => [ 'live' ], 'b' => [ 'live' ] ], 'pump(): the pushed event reached both callbacks' );
sk_check( microtime( true ) - $t0 < 1.5, 'delivery took under 1.5 s', sprintf( '%.3fs', microtime( true ) - $t0 ) );

// A forged event: dropped where verification is on, passed where it is off.
$forged = $good; $forged['content'] = 'forged'; $forged['created_at']++; $forged['id'] = Events::id( $forged );
Relays::publish( $forged, [ $mock ] );
$drain( [ 'a' => $a, 'b' => $b ], 0.5 );
sk_check_eq( $got, [ 'a' => [ 'live' ], 'b' => [ 'live', 'forged' ] ], 'pump(): verify=true drops the forged event, verify=false hands it over' );

// A message for another subscription (or none) is ignored.
$other = sk_test_sign( sk_test_keypair()['priv'], 1, [], 'other', time() );
Relays::publish( $other, [ $mock ] );
$drain( [ 'a' => $a, 'b' => $b ], 0.5 );
sk_check_eq( count( $got['b'] ), 2, 'pump(): an event outside the filter is not delivered' );

// One session closed: wait() leaves it out, the other still works.
$a->close();
sk_check_eq( [ $a->is_open(), $a->subscribed() ], [ false, 0 ], 'close(): open flag and subscriptions gone' );
Relays::publish( sk_test_sign( $kp['priv'], 1, [], 'after-close', time() + 2 ), [ $mock ] );
$ready = RelaySession::wait( [ 'a' => $a, 'b' => $b ], 2.0 );
sk_check_eq( array_keys( $ready ), [ 'b' ], 'wait(): only the open session is returned' );
$drain( [ 'b' => $b ], 0.3 );
sk_check_eq( end( $got['b'] ), 'after-close', 'pump(): the open session keeps receiving' );
sk_check_eq( $a->pump(), false, 'pump() on a closed session is false' );
$b->close();

sk_test_done();
