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

// A ping is answered; until it is, answered() says so.
sk_check_eq( [ $b->ping(), $b->answered() ], [ true, false ], 'ping(): sent, pong outstanding' );
$drain( [ 'b' => $b ], 0.4 );
sk_check_eq( $b->answered(), true, 'pump(): the pong came back' );
sk_check_eq( $d->ping(), false, 'ping() on a closed session is false' );

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

// ── NIP-42: the mock refuses kind 4 until the challenge is answered ──────
$box    = sk_test_keypair();
$dm     = [ [ 'kinds' => [ 4 ], '#p' => [ $box['pub'] ] ] ];
$got_dm = [];

$c = Relays::session( $mock, [ 'timeout' => 5, 'verify' => false, 'auth_privkey' => $box['priv'] ] );
sk_check_eq( $c->open(), true, 'auth: session with a key opens' );
sk_check( is_string( $c->subscribe( $dm, function ( $e ) use ( &$got_dm ) { $got_dm[] = $e['content']; } ) ), 'auth: DM subscription sent' );
$drain( [ 'c' => $c ], 0.8 ); // challenge, refusal, answer, OK, re-sent REQ, EOSE
sk_check_eq( $c->subscribed(), 1, 'auth: the refused subscription is kept and re-sent' );
$secret = sk_test_sign( sk_test_keypair()['priv'], 4, [ [ 'p', $box['pub'] ] ], 'secret', time() );
Relays::publish( $secret, [ $mock ] );
$drain( [ 'c' => $c ], 0.5 );
sk_check_eq( $got_dm, [ 'secret' ], 'auth: after the answer the private kind is delivered' );
$c->close();

$n = Relays::session( $mock, [ 'timeout' => 5, 'verify' => false ] );
$n->open();
$n->subscribe( $dm, function () {} );
$drain( [ 'n' => $n ], 0.5 );
sk_check_eq( $n->subscribed(), 0, 'auth: without a key the refused subscription is dropped' );
$n->close();

// The poll's request() on the same relay, same key; the DM is a stored event now.
sk_test_relay_events( [ $secret ] );
$p   = Relays::session( $mock, [ 'timeout' => 5, 'verify' => false, 'auth_privkey' => $box['priv'] ] );
$p->open();
$hit = 0;
$r   = $p->request( $dm, function () use ( &$hit ) { $hit++; } );
sk_check_eq( [ $r['eose'], $hit ], [ true, 1 ], 'auth: request() answers the challenge, re-sends and reads the stored DM' );
$p->close();

// Several mailboxes in one filter: an auth relay serves the auth key's only.
// With own filters the subscription is narrowed to it, without them dropped.
$other  = sk_test_keypair();
$both   = [ [ 'kinds' => [ 4 ], '#p' => [ $box['pub'], $other['pub'] ] ] ];
$got_dm = [];
$w = Relays::session( $mock, [ 'timeout' => 5, 'verify' => false, 'auth_privkey' => $box['priv'] ] );
$w->open();
$w->subscribe( $both, function ( $e ) use ( &$got_dm ) { $got_dm[] = $e['content']; }, [ [ 'kinds' => [ 4 ], '#p' => [ $box['pub'] ] ] ] );
$drain( [ 'w' => $w ], 0.8 ); // refused, answer, OK, re-sent, refused again, narrowed, EOSE
sk_check_eq( $w->subscribed(), 1, 'auth: refused again after the answer, the subscription is narrowed and kept' );
Relays::publish( sk_test_sign( sk_test_keypair()['priv'], 4, [ [ 'p', $other['pub'] ] ], 'not ours', time() ), [ $mock ] );
Relays::publish( sk_test_sign( sk_test_keypair()['priv'], 4, [ [ 'p', $box['pub'] ] ], 'ours', time() ), [ $mock ] );
$drain( [ 'w' => $w ], 0.5 );
// 'secret' is the stored DM to the same mailbox from above.
sk_check_eq( $got_dm, [ 'secret', 'ours' ], 'auth: the narrowed subscription delivers the auth key\'s mailbox only' );
$w->close();

$x = Relays::session( $mock, [ 'timeout' => 5, 'verify' => false, 'auth_privkey' => $box['priv'] ] );
$x->open();
$x->subscribe( $both, function () {} );
$drain( [ 'x' => $x ], 0.8 );
sk_check_eq( $x->subscribed(), 0, 'auth: refused again and nothing to narrow to, the subscription is dropped' );
$x->close();

// The relay refuses the answer itself (its address does not match the
// relay tag, as relay.damus.io does today): nothing waits for it.
$bad = $mock . '/elsewhere';
$r   = Relays::session( $bad, [ 'timeout' => 5, 'verify' => false, 'auth_privkey' => $box['priv'] ] );
$r->open();
$r->subscribe( $dm, function () {} );
$drain( [ 'r' => $r ], 0.8 );
sk_check_eq( $r->subscribed(), 0, 'auth refused by the relay: the held subscription is dropped' );
$r->close();
$r = Relays::session( $bad, [ 'timeout' => 5, 'verify' => false, 'auth_privkey' => $box['priv'] ] );
$r->open();
$t0  = microtime( true );
$res = $r->request( $dm, function () {} );
sk_check( ! $res['eose'] && microtime( true ) - $t0 < 2, 'auth refused by the relay: request() gives up at once', sprintf( '%.2fs eose=%d', microtime( true ) - $t0, (int) $res['eose'] ) );
$r->close();

// A subscription the relay closes for another reason is gone, and counted
// as lost so the worker dials again; an auth refusal is not.
$l = Relays::session( $mock, [ 'timeout' => 5, 'verify' => false ] );
$l->open();
$l->subscribe( array_fill( 0, 11, $filter[0] ), function () {} );
$l->subscribe( $filter, function () {} );
$drain( [ 'l' => $l ], 0.5 );
sk_check_eq( [ $l->subscribed(), $l->lost() ], [ 1, 1 ], 'lost(): a subscription closed with "too many filters" counts, the other is held' );
$l->close();
sk_check_eq( [ $x->lost(), $r->lost(), $l->lost() ], [ 0, 0, 0 ], 'lost(): auth refusals do not count, close() resets' );

// A mark another caller set (a poll dialling the same relay) is not the
// session's to lift.
$m = Relays::session( $mock, [ 'timeout' => 5, 'verify' => false ] );
$m->open();
$m->subscribe( $filter, function () {} );
Relays::mark_attempt( $mock );
$m->close();
sk_check_eq( Relays::stalled( $mock ), true, 'close(): leaves a mark it did not set' );
Relays::clear_attempt( $mock );

sk_check_eq( Relays::wants_auth( 'ERROR: auth-required: requested filter requires authentication' ), true, 'wants_auth(): the damus wording counts' );
sk_check_eq( Relays::wants_auth( 'error: too many filters' ), false, 'wants_auth(): another error does not' );

sk_test_done();
