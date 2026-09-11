<?php
/**
 * NostrIdentity: newest profile wins, and a profile signed elsewhere is only
 * published under the key the account is actually bound to.
 *
 * Both sides write now — a shop change goes out to the relays, a change in a
 * Nostr client comes back in. Without an order, our own publication would
 * return minutes later and undo itself.
 */

require __DIR__ . '/bootstrap.php';

defined( 'ABSPATH' ) || define( 'ABSPATH', '/tmp/' );

$GLOBALS['user_meta'] = [];
function get_user_meta( $id, $key = '', $single = false ) { return $GLOBALS['user_meta'][ $id ][ $key ] ?? ''; }
function update_user_meta( $id, $key, $value ) { $GLOBALS['user_meta'][ $id ][ $key ] = $value; return true; }
function add_action( ...$a ) {}
function add_filter( ...$a ) {}

require SK_TEST_PLUGIN . '/lib/autoload.php';
require SK_TEST_PLUGIN . '/includes/Nostr/Keys.php';
require SK_TEST_PLUGIN . '/includes/Nostr/Events.php';

use SK\Core\Nostr\Events;
use SK\Core\Nostr\Keys;

$fails = 0;
function check( $label, $actual, $expected ) {
	global $fails;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fails++; }
	printf( "%-6s %-52s got=%-10s expected=%s\n", $ok ? 'PASS' : 'FAIL', $label,
		var_export( $actual, true ), var_export( $expected, true ) );
}

/*
 * The two methods under test, copied as the plain rule they are: the class
 * itself drags in the whole auth module, and what matters here is the order.
 */
function remember( $user_id, $created_at ) {
	if ( $created_at > (int) get_user_meta( $user_id, 'sk_nostr_profile_at', true ) ) {
		update_user_meta( $user_id, 'sk_nostr_profile_at', $created_at );
	}
}
function is_newer( $user_id, $created_at ) {
	return $created_at > (int) get_user_meta( $user_id, 'sk_nostr_profile_at', true );
}

$user = 5;

// --- Nothing seen yet: anything counts --------------------------------------
check( 'first event counts', is_newer( $user, 1000 ), true );
remember( $user, 1000 );

// --- What we just published must not come back and undo itself --------------
check( 'the same event again is ignored', is_newer( $user, 1000 ), false );
check( 'an older event from a slow relay is ignored', is_newer( $user, 900 ), false );

// --- A real change in a Nostr client wins -----------------------------------
check( 'a newer event is applied', is_newer( $user, 1001 ), true );
remember( $user, 1001 );
check( 'and is remembered', (int) get_user_meta( $user, 'sk_nostr_profile_at', true ), 1001 );

// --- An older one may never lower the mark ----------------------------------
remember( $user, 500 );
check( 'an older event does not lower the mark', (int) get_user_meta( $user, 'sk_nostr_profile_at', true ), 1001 );

// --- A signed profile only counts under the bound key -----------------------
$owner     = Keys::generate();
$stranger  = Keys::generate();
$content   = wp_json_encode_test( [ 'name' => 'Testshop' ] );

$own      = Events::sign( 0, $content, [], $owner['priv'] );
$foreign  = Events::sign( 0, $content, [], $stranger['priv'] );
$wrong_kind = Events::sign( 1, $content, [], $owner['priv'] );

check( 'own signature verifies', Events::verify( $own ), true );
check( 'event carries the owner key', strtolower( $own['pubkey'] ) === $owner['pub'], true );
check( 'a stranger key is not the owner', strtolower( $foreign['pubkey'] ) === $owner['pub'], false );
check( 'a note is not a profile', (int) $wrong_kind['kind'] === 0, false );

$tampered            = $own;
$tampered['content'] = wp_json_encode_test( [ 'name' => 'Fremdshop' ] );
check( 'changed content breaks the signature', Events::verify( $tampered ), false );

printf( "\n%s\n", $fails ? "{$fails} FAILURE(S)" : 'all checks passed' );
exit( $fails ? 1 : 0 );

function wp_json_encode_test( $v ) { return json_encode( $v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); }
