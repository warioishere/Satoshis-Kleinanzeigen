<?php
/**
 * AiCategorizer::api_key — the Claude API key is stored encrypted.
 *
 * It used to sit in the settings section as plain text. A key still lying
 * there is moved into the encrypted option on first read and blanked in the
 * section; a submitted key is encrypted before the section is saved; an empty
 * field leaves the stored key alone.
 */

require __DIR__ . '/bootstrap.php';

defined( 'ABSPATH' ) || define( 'ABSPATH', '/tmp/' );

$GLOBALS['sk_test_salt'] = 'k9Xq2!vLm4Zt7Rw0PbNc8FhJ1sYd6EuA3gTiOa5MnQrVzW+lKpB/eS-XyCfDhGjU';
function wp_salt( $scheme = 'auth' ) { return $GLOBALS['sk_test_salt']; }

$GLOBALS['options'] = [];
function get_option( $name, $default = false ) { return $GLOBALS['options'][ $name ] ?? $default; }
function update_option( $name, $value ) { $GLOBALS['options'][ $name ] = $value; return true; }
function delete_option( $name ) { unset( $GLOBALS['options'][ $name ] ); return true; }
function add_action( ...$a ) {}
function add_filter( ...$a ) {}
function __( $t, $d = '' ) { return $t; }

require SK_TEST_PLUGIN . '/includes/Secret.php';
require SK_TEST_PLUGIN . '/includes/Dashboard/Modules/AiCategorizer.php';

use SK\Core\Dashboard\Modules\AiCategorizer;
use SK\Core\Secret;

$fails = 0;
function check( $label, $actual, $expected ) {
	global $fails;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fails++; }
	$fmt = function ( $v ) { return is_string( $v ) && strlen( $v ) > 26 ? substr( $v, 0, 23 ) . '...' : var_export( $v, true ); };
	printf( "%-6s %-48s got=%-28s expected=%s\n", $ok ? 'PASS' : 'FAIL', $label, $fmt( $actual ), $fmt( $expected ) );
}
function stored() { return (string) ( $GLOBALS['options'][ AiCategorizer::KEY_OPTION ] ?? '' ); }
function in_section() { return (string) ( $GLOBALS['options']['sk_product_advertisement']['skai_api_key'] ?? '' ); }

$key = 'sk-ant-api03-' . str_repeat( 'x', 80 ) . 'AA';

// --- Nothing stored ---------------------------------------------------------
check( 'no key anywhere', AiCategorizer::api_key(), '' );

// --- A key still in the section as plain text -------------------------------
$GLOBALS['options']['sk_product_advertisement'] = [ 'skai_enabled' => 'on', 'skai_api_key' => $key ];

check( 'plain text key is read', AiCategorizer::api_key(), $key );
check( 'section no longer holds it', in_section(), '' );
check( 'other section values survive', $GLOBALS['options']['sk_product_advertisement']['skai_enabled'], 'on' );
check( 'stored encrypted', strpos( stored(), 'skv2:' ) === 0, true );
check( 'plain text is not in the stored value', strpos( stored(), 'sk-ant' ), false );

// --- Read again from the ciphertext -----------------------------------------
$cipher = stored();
check( 'second read returns the key', AiCategorizer::api_key(), $key );
check( 'no rewrite on the second read', stored(), $cipher );

// --- Saving the settings ----------------------------------------------------
$saved = AiCategorizer::strip_api_key( [ 'skai_enabled' => 'off', 'skai_api_key' => '' ], 'sk_product_advertisement' );
check( 'empty field keeps the stored key', AiCategorizer::api_key(), $key );
check( 'section saved without the key', $saved['skai_api_key'], '' );

$new   = 'sk-ant-api03-' . str_repeat( 'y', 80 ) . 'BB';
$saved = AiCategorizer::strip_api_key( [ 'skai_enabled' => 'on', 'skai_api_key' => $new ], 'sk_product_advertisement' );
check( 'new key replaces the old one', AiCategorizer::api_key(), $new );
check( 'section never carries the new key', $saved['skai_api_key'], '' );
check( 'other section is left alone', AiCategorizer::strip_api_key( [ 'skai_api_key' => 'zzz' ], 'sk_general' )['skai_api_key'], 'zzz' );

// --- Its own namespace ------------------------------------------------------
check( 'does not open as a wallet secret', Secret::decrypt( stored() ), '' );
check( 'does not open as a Nostr key', Secret::decrypt( stored(), Secret::NOSTR ), '' );
check( 'opens as an API key', Secret::decrypt( stored(), Secret::API_KEY ), $new );

printf( "\n%s\n", $fails ? "{$fails} FAILURE(S)" : 'all checks passed' );
exit( $fails ? 1 : 0 );
