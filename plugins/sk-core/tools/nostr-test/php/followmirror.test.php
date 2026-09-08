<?php
/**
 * FollowMirror and RelayReader: a list is rebuilt only when a relay answered
 * and none has one; forged lists are ignored; dead relays publish nothing.
 *
 * Needs the mock relay running (see run.sh). SK_TEST_GENERATED is a user
 * whose key this site holds (default 610 on staging); its list on the mock
 * relay is whatever the test writes, nothing public is touched.
 */
require __DIR__ . '/bootstrap.php';

use SK\Core\Nostr\Events;
use SK\Core\Nostr\Relays;
use SK\Modules\Reputation\FollowMirror;

$generated = (int) ( getenv( 'SK_TEST_GENERATED' ) ?: 610 );
$vendor    = str_repeat( 'b', 63 ) . '2';

// ── verified(): the four ways an event can fail ───────────────────────────
$kp   = sk_test_keypair();
$good = sk_test_sign( $kp['priv'], 3, [ [ 'p', str_repeat( 'c', 64 ), 'wss://r', 'pet' ] ], '{"wss://r":{"read":true,"write":true}}', 1700000000 );

sk_check_eq( Events::verify( $good, 3, [ $kp['pub'] ] ), true, 'verify(): signed event of the right kind and author' );
sk_check_eq( Events::verify( $good, 0 ), false, 'verify(): wrong kind rejected' );
sk_check_eq( Events::verify( $good, 3, [ str_repeat( 'a', 64 ) ] ), false, 'verify(): wrong author rejected' );
$tampered = $good; $tampered['content'] .= 'x';
sk_check_eq( Events::verify( $tampered ), false, 'verify(): tampered content rejected' );

// ── Dead relays: nothing answers, nothing is published ────────────────────
$GLOBALS['sk_test_relay'] = "ws://127.0.0.1:1\nws://127.0.0.1:2";
$answered = null;
$latest   = Relays::latest( 3, [ $kp['pub'] ], $answered );
sk_check_eq( [ count( $latest ), $answered ], [ 0, 0 ], 'latest(): dead relays give no events and answered = 0' );
sk_check_eq( sk_call_private( FollowMirror::class, 'apply', $generated, [ $vendor ], [] ), 'no-answer', 'apply(): dead relays -> no-answer, nothing published' );

// ── Mock relay: forged newer list ignored, real one chosen ─────────────────
$GLOBALS['sk_test_relay'] = getenv( 'MOCK_RELAY' ) ?: 'ws://127.0.0.1:18080';
$forged = $good; $forged['created_at'] = 1700009000; $forged['tags'] = [ [ 'p', str_repeat( 'e', 64 ) ] ]; $forged['sig'] = str_repeat( '0', 128 );
sk_test_relay_events( [ $forged, $good ] );

$answered = null;
$latest   = Relays::latest( 3, [ $kp['pub'] ], $answered );
sk_check_eq( $answered, 1, 'latest(): the mock relay answered' );
sk_check_eq( $latest[ $kp['pub'] ]['created_at'] ?? null, 1700000000, 'latest(): the signed list wins over the forged newer one' );
sk_check_eq( $latest[ $kp['pub'] ]['tags'][0] ?? null, [ 'p', str_repeat( 'c', 64 ), 'wss://r', 'pet' ], 'latest(): petname and relay hint kept' );

// ── Answering relay without a list: created for add, nothing for remove ───
$mirrors = FollowMirror::mirrors( $generated );
sk_check( $mirrors, "user {$generated} has a generated identity this site holds" );

if ( $mirrors ) {
    sk_test_relay_events( [] );

    sk_check_eq( sk_call_private( FollowMirror::class, 'apply', $generated, [ $vendor ], [] ), 'published', 'apply(add): answering relay, no list -> a list is created and published (to the mock)' );
    sk_check_eq( sk_call_private( FollowMirror::class, 'apply', $generated, [], [ $vendor ] ), 'unchanged', 'apply(remove): no list -> nothing published' );

    $log = array_slice( file( getenv( 'MOCK_LOG' ) ?: dirname( __DIR__ ) . '/relay.jsonl' ), -20 );
    $published = array_values( array_filter( array_map( fn( $l ) => json_decode( $l, true ), $log ), fn( $m ) => 'EVENT' === ( $m['in'] ?? '' ) ) );
    $ev = $published ? end( $published )['payload'][0] : null;
    sk_check( $ev && 3 === $ev['kind'] && [ [ 'p', $vendor ] ] === $ev['tags'], 'published list has exactly the one added key', json_encode( $ev['tags'] ?? null ) );
    sk_check( $ev && Events::verify( $ev, 3 ), 'published list carries a valid signature' );
}

sk_check_eq( count( $GLOBALS['sk_test_cron'] ), 0, 'no cron event was scheduled during apply()' );
sk_test_done();
