<?php
/**
 * SK\Core\Nostr\Keys and Events: the one place keys are parsed and events
 * are signed and verified. Pure functions, no relay needed.
 */
require __DIR__ . '/bootstrap.php';

use SK\Core\Nostr\Events;
use SK\Core\Nostr\Keys;
use SK\Core\Nostr\ReportTypes;

// ── Keys ──────────────────────────────────────────────────────────────────
$kp   = Keys::generate();
$npub = Keys::to_npub( $kp['pub'] );
$nsec = Keys::to_nsec( $kp['priv'] );

sk_check( Keys::is_hex( $kp['pub'] ) && Keys::is_hex( $kp['priv'] ), 'generate(): both halves are lowercase hex' );
sk_check( 0 === strpos( $npub, 'npub1' ) && 0 === strpos( $nsec, 'nsec1' ), 'to_npub()/to_nsec() produce bech32' );
sk_check_eq( Keys::npub_to_hex( $npub ), $kp['pub'], 'npub_to_hex() round trip' );
sk_check_eq( Keys::npub_to_hex( 'nostr:' . $npub ), $kp['pub'], 'npub_to_hex() strips nostr: prefix' );
sk_check_eq( Keys::npub_to_hex( strtoupper( $kp['pub'] ) ), $kp['pub'], 'npub_to_hex() lowercases hex' );
sk_check_eq( Keys::npub_to_hex( $nsec ), '', 'npub_to_hex() refuses an nsec' );
sk_check_eq( Keys::nsec_to_hex( $nsec ), $kp['priv'], 'nsec_to_hex() round trip' );
sk_check_eq( Keys::nsec_to_hex( $npub ), '', 'nsec_to_hex() refuses an npub' );
sk_check_eq( Keys::pubkey_of( $kp['priv'] ), $kp['pub'], 'pubkey_of() from hex' );
sk_check_eq( Keys::pubkey_of( $nsec ), $kp['pub'], 'pubkey_of() from nsec (the Live config shape)' );
sk_check_eq( Keys::pubkey_of( 'garbage' ), '', 'pubkey_of() garbage -> empty, no exception' );
sk_check_eq( Keys::to_hex( 'x" onmouseover="1' ), '', 'to_hex() rejects non-keys' );

$mp = Keys::marketplace_pubkey();
sk_check( Keys::is_hex( $mp ), 'marketplace_pubkey() derived on this install' );
sk_check_eq( \SK\Modules\Auth\NostrSettings::marketplace_pubkey(), $mp, 'NostrSettings::marketplace_pubkey() agrees' );
if ( class_exists( 'SK\Modules\NostrMarket\EventSender' ) ) {
    sk_check_eq( \SK\Modules\NostrMarket\EventSender::get_pubkey(), $mp, 'EventSender::get_pubkey() agrees' );
    sk_check_eq( \SK\Modules\NostrMarket\EventSender::get_privkey(), Keys::marketplace_privkey(), 'EventSender::get_privkey() agrees' );
}

// ── Events ────────────────────────────────────────────────────────────────
$ev = Events::sign( 1, 'hällo / "quotes" \\ back', [ [ 'p', $kp['pub'], 'wss://r', 'pet' ], [ 't', 'x' ] ], $kp['priv'], 1700000000 );

sk_check_eq( [ $ev['kind'], $ev['created_at'], $ev['pubkey'] ], [ 1, 1700000000, $kp['pub'] ], 'sign(): kind, created_at, pubkey as given' );
sk_check_eq( Events::id( $ev ), $ev['id'], 'id() matches the library id' );
sk_check_eq( Events::verify( $ev ), true, 'verify(): array' );
sk_check_eq( Events::verify( (object) $ev ), true, 'verify(): object' );
sk_check_eq( Events::verify( wp_json_encode( $ev ) ), true, 'verify(): JSON string' );
sk_check_eq( Events::verify( $ev, 1, [ strtoupper( $kp['pub'] ) ] ), true, 'verify(): kind and author (any case) match' );
sk_check_eq( Events::verify( $ev, 3 ), false, 'verify(): wrong kind' );
sk_check_eq( Events::verify( $ev, 1, [ str_repeat( 'a', 64 ) ] ), false, 'verify(): wrong author' );
$t = $ev; $t['content'] .= 'x';
sk_check_eq( Events::verify( $t ), false, 'verify(): tampered content' );
$t = $ev; $t['sig'] = str_repeat( '0', 128 );
sk_check_eq( Events::verify( $t ), false, 'verify(): bad signature' );
$t = $ev; $t['tags'][] = [ 1 ];
sk_check_eq( Events::verify( $t ), false, 'verify(): non-string tag' );
sk_check_eq( Events::verify( null ), false, 'verify(): null' );
sk_check_eq( Events::verify( 'not json' ), false, 'verify(): garbage string' );
sk_check_eq( Events::verify( [ 'id' => 'x' ] ), false, 'verify(): incomplete' );

$signed_with_nsec = Events::sign( 1, 'a', [], $nsec, 1700000001 );
sk_check_eq( $signed_with_nsec['pubkey'], $kp['pub'], 'sign() accepts an nsec' );
try {
    Events::sign( 1, 'a', [], 'nokey' );
    sk_check( false, 'sign() throws on an unusable key' );
} catch ( \RuntimeException $e ) {
    sk_check( true, 'sign() throws on an unusable key' );
}

sk_check_eq( Events::tag( $ev, 'p' ), $kp['pub'], 'tag() first value' );
sk_check_eq( Events::tag( $ev, 'zzz' ), '', 'tag() missing -> empty' );
sk_check_eq( Events::tag_values( $ev, 'p', true ), [ $kp['pub'] ], 'tag_values() hex only' );
$mixed = [ 'tags' => [ [ 'p', 'nothex' ], [ 'p', strtoupper( $kp['pub'] ) ], [ 'e', $kp['pub'] ] ] ];
sk_check_eq( Events::tag_values( $mixed, 'p', true ), [ $kp['pub'] ], 'tag_values() drops non-hex, lowercases' );
sk_check_eq( Events::tag_values( $mixed, 'p' ), [ 'nothex', strtoupper( $kp['pub'] ) ], 'tag_values() raw keeps everything' );

$stored  = Events::encode_for_meta( $ev );
$decoded = Events::decode( wp_unslash( $stored ) );
sk_check_eq( $decoded, $ev, 'encode_for_meta()/decode() round trip through the meta API unslash' );
sk_check_eq( Events::verify( $decoded ), true, 'decoded event still verifies (ü survived)' );
sk_check_eq( Events::decode( '' ), null, 'decode() empty -> null' );

$obj = Events::to_object( $ev );
sk_check_eq( $obj->getId(), $ev['id'], 'to_object() carries the id' );
sk_check_eq( $obj->verify(), true, 'to_object() verifies in the library' );

// ── Report types ──────────────────────────────────────────────────────────
sk_check_eq( ReportTypes::is_counted( 'Spam' ), true, 'is_counted() case-insensitive' );
sk_check_eq( ReportTypes::is_counted( 'nudity' ), false, 'is_counted() nudity not counted' );
sk_check_eq( array_keys( ReportTypes::labels() ), ReportTypes::COUNTED, 'labels() cover exactly the counted types' );
sk_check_eq( ReportTypes::label( 'constructor' ), 'constructor', 'label() unknown -> itself' );

// ── RelayPublisher accepts arrays: an event that reaches no relay is reported, not thrown ──
$GLOBALS['sk_test_relay'] = 'ws://127.0.0.1:1';
$r = \SK\Modules\Auth\RelayPublisher::publish( $ev, [ 'ws://127.0.0.1:1' ] );
sk_check_eq( [ $r['accepted'], array_keys( $r['rejected'] ) ], [ [], [ 'ws://127.0.0.1:1' ] ], 'RelayPublisher::publish(array) runs and reports the dead relay' );
$r = \SK\Modules\Auth\RelayPublisher::publish( 'nonsense', [ 'ws://127.0.0.1:1' ] );
sk_check_eq( $r['rejected']['ws://127.0.0.1:1'] ?? '', 'not an event', 'RelayPublisher::publish(garbage) rejects without dialling' );

sk_test_done();
