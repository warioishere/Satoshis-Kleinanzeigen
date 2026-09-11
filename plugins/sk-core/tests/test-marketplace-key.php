<?php
/**
 * Keys::marketplace_privkey — the key the whole marketplace signs with.
 *
 * It used to sit in the option nap_nostr_options as plain text, readable in any
 * database dump. It is encrypted now, and a value still stored in the clear is
 * encrypted the first time it is read, so no operator has to act.
 */

require __DIR__ . '/bootstrap.php';

defined( 'ABSPATH' ) || define( 'ABSPATH', '/tmp/' );

$GLOBALS['sk_test_salt'] = 'k9Xq2!vLm4Zt7Rw0PbNc8FhJ1sYd6EuA3gTiOa5MnQrVzW+lKpB/eS-XyCfDhGjU';
function wp_salt( $scheme = 'auth' ) { return $GLOBALS['sk_test_salt']; }

$GLOBALS['options'] = [];
function get_option( $name, $default = false ) { return $GLOBALS['options'][ $name ] ?? $default; }
function update_option( $name, $value ) { $GLOBALS['options'][ $name ] = $value; return true; }
function apply_filters( $hook, $value ) { return $GLOBALS['filter_' . $hook ] ?? $value; }

require SK_TEST_PLUGIN . '/includes/Secret.php';
require SK_TEST_PLUGIN . '/includes/Nostr/Keys.php';

use SK\Core\Nostr\Keys;
use SK\Core\Secret;

$fails = 0;
function check( $label, $actual, $expected ) {
	global $fails;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fails++; }
	$fmt = function ( $v ) { return is_string( $v ) && strlen( $v ) > 26 ? substr( $v, 0, 23 ) . '...' : var_export( $v, true ); };
	printf( "%-6s %-46s got=%-28s expected=%s\n", $ok ? 'PASS' : 'FAIL', $label, $fmt( $actual ), $fmt( $expected ) );
}

function stored_key() {
	$o = $GLOBALS['options']['nap_nostr_options'] ?? [];
	return (string) ( $o['private_key'] ?? '' );
}

$privkey = '2cd4fc973ec2a5b025876f65c63c3997fb0d81d97f09a052435a08e55c405d1f';

// --- Nothing stored ---------------------------------------------------------
check( 'no option, no key', Keys::marketplace_privkey(), '' );

// --- A key still lying there in the clear -----------------------------------
$GLOBALS['options']['nap_nostr_options'] = [ 'relays' => [], 'private_key' => $privkey ];

check( 'plain text key is read', Keys::marketplace_privkey(), $privkey );
check( 'option is encrypted afterwards', strpos( stored_key(), 'skv2:' ) === 0, true );
check( 'plain text is gone from the option', strpos( stored_key(), $privkey ), false );
check( 'other option keys survive', array_key_exists( 'relays', $GLOBALS['options']['nap_nostr_options'] ), true );

// --- Reading it back out of the ciphertext ----------------------------------
$cipher = stored_key();
check( 'second read returns the same key', Keys::marketplace_privkey(), $privkey );
check( 'no rewrite on the second read', stored_key(), $cipher );

// --- The key namespace is its own -------------------------------------------
check( 'does not open as a user identity', Secret::decrypt( $cipher, Secret::NOSTR ), '' );
check( 'does not open as a wallet secret', Secret::decrypt( $cipher ), '' );
check( 'opens as the marketplace key', Secret::decrypt( $cipher, Secret::MARKETPLACE ), $privkey );

// --- Garbage stays untouched instead of being encrypted ---------------------
$GLOBALS['options']['nap_nostr_options'] = [ 'private_key' => 'kein schluessel' ];
check( 'garbage yields no key', Keys::marketplace_privkey(), '' );
check( 'garbage is left as it is', stored_key(), 'kein schluessel' );

// --- The filter is the last source ------------------------------------------
$GLOBALS['options']['nap_nostr_options'] = [];
$GLOBALS['filter_nap_nostr_private_key'] = $privkey;
check( 'filter is used when nothing is stored', Keys::marketplace_privkey(), $privkey );
check( 'filter value is not written to the option', stored_key(), '' );

printf( "\n%s\n", $fails ? "{$fails} FAILURE(S)" : 'all checks passed' );
exit( $fails ? 1 : 0 );
