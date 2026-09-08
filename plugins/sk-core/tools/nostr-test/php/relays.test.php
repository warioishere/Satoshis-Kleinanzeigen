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
sk_check_eq( \SK\Modules\Auth\RelayPublisher::publish( $ev, [ $mock ] )['accepted'], [ $mock ], 'RelayPublisher facade still publishes' );

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

sk_test_done();
