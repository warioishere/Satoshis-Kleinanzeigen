<?php
/**
 * SK\Core\Nostr\Relays: list, breaker, fetch/query/latest with
 * verification, publish with OK handling, and the foreign-URL guard.
 * Needs the mock relay running (see run.sh).
 */
require __DIR__ . '/bootstrap.php';

use SK\Core\Nostr\Events;
use SK\Core\Nostr\Relays;

$mock = getenv( 'MOCK_RELAY' ) ?: 'ws://127.0.0.1:18080';
$dead = 'ws://127.0.0.1:1';

// ── list() ────────────────────────────────────────────────────────────────
sk_check_eq( Relays::list(), [ $mock ], 'list() reads the relay option (filtered to the mock here)' );
sk_check_eq( \SK\Modules\Auth\NostrIdentity::get_relays(), Relays::list(), 'NostrIdentity::get_relays() delegates' );
$GLOBALS['sk_test_relay'] = "wss://a.example, ws://b.example\nhttps://not-a-relay wss://a.example";
sk_check_eq( Relays::list(), [ 'wss://a.example', 'ws://b.example' ], 'list() splits on newline/comma/space, keeps ws(s) only, deduplicates' );
$GLOBALS['sk_test_relay'] = $mock;

// ── fetch(): verification and EOSE ────────────────────────────────────────
$kp     = sk_test_keypair();
$good   = sk_test_sign( $kp['priv'], 1, [ [ 't', 'x' ] ], 'good', 1700000000 );
$forged = $good; $forged['content'] = 'forged'; $forged['created_at'] = 1700000001; $forged['id'] = Events::id( $forged );
sk_test_relay_events( [ $good, $forged ] );

$r = Relays::fetch( $mock, [ [ 'kinds' => [ 1 ], 'authors' => [ $kp['pub'] ] ] ] );
sk_check_eq( [ count( $r['events'] ), $r['eose'] ], [ 1, true ], 'fetch(): the forged event is dropped, EOSE seen' );
sk_check_eq( $r['events'][0]['content'] ?? null, 'good', 'fetch(): the signed event survives' );

$r = Relays::fetch( $mock, [ [ 'kinds' => [ 1 ], 'authors' => [ $kp['pub'] ] ] ], [ 'verify' => false ] );
sk_check_eq( count( $r['events'] ), 2, 'fetch(verify=false): raw events for callers that verify themselves' );

$r = Relays::fetch( $dead, [ [ 'kinds' => [ 1 ] ] ] );
sk_check_eq( [ $r['events'], $r['eose'] ], [ [], false ], 'fetch(): dead relay -> nothing and no EOSE' );

// ── breaker ───────────────────────────────────────────────────────────────
Relays::mark_attempt( $mock );
sk_check_eq( Relays::stalled( $mock ), true, 'mark_attempt() sets the breaker' );
$r = Relays::fetch( $mock, [ [ 'kinds' => [ 1 ] ] ] );
sk_check_eq( $r['eose'], false, 'fetch(): a stalled relay is not dialled' );
Relays::clear_attempt( $mock );
sk_check_eq( Relays::stalled( $mock ), false, 'clear_attempt() lifts it' );
Relays::fetch( $mock, [ [ 'kinds' => [ 1 ] ] ] );
sk_check_eq( Relays::stalled( $mock ), false, 'a completed fetch leaves no breaker mark behind' );

// ── query() and latest() ──────────────────────────────────────────────────
$q = Relays::query( [ [ 'kinds' => [ 1 ], 'authors' => [ $kp['pub'] ] ] ], [ $mock, $mock, $dead ] );
sk_check_eq( [ count( $q['events'] ), $q['answered'] ], [ 1, 2 ], 'query(): deduplicated by id, answered counts EOSE per relay' );

$older = sk_test_sign( $kp['priv'], 3, [ [ 'p', str_repeat( 'a', 64 ) ] ], '', 1700000000 );
$newer = sk_test_sign( $kp['priv'], 3, [ [ 'p', str_repeat( 'b', 64 ) ] ], '', 1700000500 );
$other = sk_test_keypair();
$otherlist = sk_test_sign( $other['priv'], 3, [], '', 1700000900 );
sk_test_relay_events( [ $older, $newer, $otherlist ] );
$l = Relays::latest( 3, [ strtoupper( $kp['pub'] ) ], $answered, [ $mock ] );
sk_check_eq( $l[ $kp['pub'] ]['created_at'] ?? null, 1700000500, 'latest(): newest per author, author given in upper case' );
sk_check_eq( isset( $l[ $other['pub'] ] ), false, 'latest(): only the asked-for authors' );
sk_check_eq( $answered, 1, 'latest(): answered relays counted' );

// ── publish() ─────────────────────────────────────────────────────────────
$ev = sk_test_sign( $kp['priv'], 1, [], 'publish me' );
$p  = Relays::publish( $ev, [ $mock, $dead ] );
sk_check_eq( $p['accepted'], [ $mock ], 'publish(): the mock says OK' );
sk_check_eq( array_keys( $p['rejected'] ), [ $dead ], 'publish(): the dead relay is reported, not thrown' );
$log = array_map( fn( $l ) => json_decode( $l, true ), file( getenv( 'MOCK_LOG' ) ?: dirname( __DIR__ ) . '/relay.jsonl' ) );
$sent = array_values( array_filter( $log, fn( $m ) => 'EVENT' === ( $m['in'] ?? '' ) && ( $m['payload'][0]['id'] ?? '' ) === $ev['id'] ) );
sk_check_eq( count( $sent ), 1, 'publish(): exactly one EVENT frame reached the mock' );
sk_check_eq( Relays::publish( 'garbage', [ $mock ] )['rejected'][ $mock ] ?? '', 'not an event', 'publish(): garbage rejected without dialling' );
// ── session(): several requests on one connection ─────────────────────────
$s = Relays::session( $mock, [ 'timeout' => 5 ] );
sk_check_eq( $s->open(), true, 'session(): opens' );
sk_check_eq( Relays::stalled( $mock ), true, 'session(): breaker marked while open' );
$got = [];
$r1  = $s->request( [ [ 'kinds' => [ 3 ], 'authors' => [ $kp['pub'] ] ] ], function ( $e ) use ( &$got ) { $got[] = $e['created_at']; } );
sk_check_eq( [ $r1['eose'], $r1['count'], $r1['stopped'] ], [ true, 2, false ], 'session request 1: both signed lists, EOSE' );
$r2 = $s->request( [ [ 'kinds' => [ 3 ], 'authors' => [ $other['pub'] ] ] ], function ( $e ) { return false; } );
sk_check_eq( [ $r2['eose'], $r2['count'], $r2['stopped'] ], [ false, 1, true ], 'session request 2: callback stopped after the first event' );
$s->close();
sk_check_eq( Relays::stalled( $mock ), false, 'session(): breaker cleared on close' );
$dead_s = Relays::session( $dead, [ 'timeout' => 2 ] );
sk_check_eq( $dead_s->open(), false, 'session(): dead relay does not open' );
sk_check_eq( $dead_s->request( [ [ 'kinds' => [ 1 ] ] ], function () {} )['eose'], false, 'session(): request on a closed session is a no-op' );

// ── is_public_url() ───────────────────────────────────────────────────────
foreach ( [
    'wss://relay.damus.io'          => true,
    'wss://relay.damus.io/'         => true,
    'ws://relay.damus.io'           => false,
    'wss://127.0.0.1'               => false,
    'wss://localhost'               => false,
    'wss://relay.local'             => false,
    'wss://[::1]'                   => false,
    'wss://user:pw@relay.damus.io'  => false,
    'wss://relay.damus.io/?x=1'     => false,
    'wss://does-not-exist.example'  => false,
    'not a url'                     => false,
] as $url => $expected ) {
    sk_check_eq( Relays::is_public_url( $url ), $expected, 'is_public_url(' . $url . ')' );
}

// NIP-42: the mock serves a private kind to the authed mailbox. With the
// key, fetch() answers the challenge and reads; when the relay refuses
// the answer (its address does not match the relay tag, as relay.damus.io
// does today), fetch() gives up at once and the relay is noted as one no
// kind 10050 may name.
$box    = sk_test_keypair();
$secret = sk_test_sign( sk_test_keypair()['priv'], 4, [ [ 'p', $box['pub'] ] ], 'secret', 1700000600 );
$dm     = [ [ 'kinds' => [ 4 ], '#p' => [ $box['pub'] ] ] ];
sk_test_relay_events( [ $secret ] );
$r = Relays::fetch( $mock, $dm, [ 'verify' => false, 'auth_privkey' => $box['priv'] ] );
sk_check_eq( [ $r['eose'], count( $r['events'] ) ], [ true, 1 ], 'fetch(): answers the NIP-42 challenge and reads the private kind' );
sk_check_eq( Relays::auth_refused( $mock ), false, 'auth_refused(): a served request leaves no note' );
$bad = $mock . '/elsewhere';
$t0  = microtime( true );
$r   = Relays::fetch( $bad, $dm, [ 'verify' => false, 'auth_privkey' => $box['priv'], 'timeout' => 5 ] );
sk_check( ! $r['eose'] && microtime( true ) - $t0 < 2, 'fetch(): gives up at once when the relay refuses the auth answer', sprintf( '%.2fs', microtime( true ) - $t0 ) );
sk_check_eq( Relays::auth_refused( $bad ), true, 'auth_refused(): the refusing relay is noted' );
delete_transient( 'sk_relay_auth_refused_' . md5( $bad ) );

sk_test_done();
