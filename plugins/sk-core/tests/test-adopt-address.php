<?php
/**
 * Settings::adopt_discovered_address — a Lightning address found on a vendor's
 * Nostr profile goes into the shop settings.
 *
 * Three paths find one: the Nostr login, the relay sync and the zap lookup.
 * They all decide it here, under the same conditions the form applies: the
 * address must be reachable and able to prove a payment (LUD-21), otherwise a
 * sale through it could never be settled. What the vendor typed themselves is
 * never overwritten, and an address of ours is no address at all.
 */

require __DIR__ . '/bootstrap.php';

defined( 'ABSPATH' ) || define( 'ABSPATH', '/tmp/' );

function home_url( $path = '' ) { return 'https://staging.satoshiskleinanzeigen.space' . $path; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function add_action( ...$a ) {}
function add_filter( ...$a ) {}
function sk_get_option( $key, $group = '', $default = '' ) {
	return $key === 'wallet_connections' ? $GLOBALS['wallet_switch'] : $default;
}
$GLOBALS['wallet_switch'] = 'on';

$GLOBALS['user_meta'] = [];
function get_user_meta( $id, $key = '', $single = false ) { return $GLOBALS['user_meta'][ $id ][ $key ] ?? ''; }
function update_user_meta( $id, $key, $value ) { $GLOBALS['user_meta'][ $id ][ $key ] = $value; return true; }

class WP_Error {
	public $code; public $message;
	public function __construct( $code = '', $msg = '' ) { $this->code = $code; $this->message = $msg; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $t, $d = '' ) { return $t; }

// --- The network, faked -----------------------------------------------------
// Two hosts: one that can prove a payment, one that cannot.
$GLOBALS['calls'] = 0;
function wp_safe_remote_get( $url, $args = [] ) {
	$GLOBALS['calls']++;

	// The invoice callbacks first: their host names contain the ones below.
	if ( strpos( $url, 'weissnix.example/cb' ) !== false ) {
		// An invoice, but no way to ask later whether it was paid.
		return [ 'code' => 200, 'body' => wp_json_encode( [ 'pr' => 'lnbc10n1p' . str_repeat( 'q', 40 ) ] ) ];
	}
	if ( strpos( $url, 'weiss.example/cb' ) !== false ) {
		return [ 'code' => 200, 'body' => wp_json_encode( [ 'pr' => 'lnbc10n1p' . str_repeat( 'q', 40 ), 'verify' => 'https://weiss.example/verify/1' ] ) ];
	}
	if ( strpos( $url, 'weissnix.example' ) !== false ) {
		return [ 'code' => 200, 'body' => wp_json_encode( [ 'callback' => 'https://weissnix.example/cb', 'minSendable' => 1000 ] ) ];
	}
	if ( strpos( $url, 'weiss.example' ) !== false ) {
		return [ 'code' => 200, 'body' => wp_json_encode( [ 'callback' => 'https://weiss.example/cb', 'minSendable' => 1000 ] ) ];
	}

	return new WP_Error( 'http', 'unreachable' );
}
function wp_remote_retrieve_response_code( $r ) { return is_array( $r ) ? $r['code'] : 0; }
function wp_remote_retrieve_body( $r ) { return is_array( $r ) ? $r['body'] : ''; }
function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f ); }

require SK_TEST_PLUGIN . '/includes/Wallet/LNURL/Resolver.php';
require SK_TEST_PLUGIN . '/includes/Wallet/Settings.php';

use SK\Core\Wallet\Settings;

$fails = 0;
function check( $label, $actual, $expected ) {
	global $fails;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fails++; }
	printf( "%-6s %-52s got=%-10s expected=%s\n", $ok ? 'PASS' : 'FAIL', $label,
		var_export( $actual, true ), var_export( $expected, true ) );
}
function stored( $id ) { return (string) ( $GLOBALS['user_meta'][ $id ]['sk_profile_settings']['lightning_address'] ?? '' ); }

$good = 'shop@weiss.example';     // can prove a payment
$bad  = 'shop@weissnix.example';  // cannot

// --- The empty field is filled ----------------------------------------------
check( 'address with proof is adopted', Settings::adopt_discovered_address( 1, $good ), true );
check( 'it is in the field', stored( 1 ), $good );
check( 'and marked as provable', $GLOBALS['user_meta'][1]['sk_profile_settings']['lightning_lud21'], true );

// --- Without LUD-21 it stays out --------------------------------------------
check( 'address without proof is refused', Settings::adopt_discovered_address( 2, $bad ), false );
check( 'field stays empty', stored( 2 ), '' );

// --- What the vendor typed themselves wins ----------------------------------
$GLOBALS['user_meta'][3]['sk_profile_settings'] = [ 'lightning_address' => 'eigene@example.org' ];
check( 'own address is not overwritten', Settings::adopt_discovered_address( 3, $good ), false );
check( 'own address still there', stored( 3 ), 'eigene@example.org' );

// --- One of ours counts as no address ---------------------------------------
$GLOBALS['user_meta'][4]['sk_profile_settings'] = [ 'lightning_address' => 'v/4@staging.satoshiskleinanzeigen.space' ];
check( 'our own address is replaced', Settings::adopt_discovered_address( 4, $good ), true );
check( 'the real one is in the field now', stored( 4 ), $good );

// --- An address of ours is never adopted ------------------------------------
check( 'our address is not adopted', Settings::adopt_discovered_address( 5, 'v/5@staging.satoshiskleinanzeigen.space' ), false );
check( 'nonsense is not adopted', Settings::adopt_discovered_address( 5, 'kein schluessel' ), false );
check( 'empty is not adopted', Settings::adopt_discovered_address( 5, '' ), false );
check( 'field stays empty', stored( 5 ), '' );

// --- Nothing happens twice --------------------------------------------------
$before = $GLOBALS['calls'];
check( 'the same address again does nothing', Settings::adopt_discovered_address( 1, $good ), false );
check( 'and costs no request', $GLOBALS['calls'], $before );

// --- Mirroring: the profile is the source -----------------------------------
// A change the vendor made in a Nostr client replaces what stands here.
$GLOBALS['user_meta'][7]['sk_profile_settings'] = [ 'lightning_address' => 'alt@example.org', 'lightning_lud21' => true ];
check( 'a change on Nostr replaces the address', Settings::adopt_discovered_address( 7, $good, true ), true );
check( 'the new address is in the field', stored( 7 ), $good );

// Without proof it is still taken over, but marked as unusable for a sale.
$GLOBALS['user_meta'][8]['sk_profile_settings'] = [ 'lightning_address' => 'alt@example.org', 'lightning_lud21' => true ];
check( 'an address without proof is mirrored too', Settings::adopt_discovered_address( 8, $bad, true ), true );
check( 'it is in the field', stored( 8 ), $bad );
check( 'but marked as not provable', $GLOBALS['user_meta'][8]['sk_profile_settings']['lightning_lud21'], false );

// Mirroring never brings one of ours back.
check( 'mirroring does not adopt our own address',
	Settings::adopt_discovered_address( 7, 'v/7@staging.satoshiskleinanzeigen.space', true ), false );
check( 'the real address survives', stored( 7 ), $good );

// --- The site-wide switch stops it ------------------------------------------
$GLOBALS['wallet_switch'] = 'off';
check( 'switch off: nothing is adopted', Settings::adopt_discovered_address( 6, $good ), false );
check( 'field stays empty', stored( 6 ), '' );
$GLOBALS['wallet_switch'] = 'on';

printf( "\n%s\n", $fails ? "{$fails} FAILURE(S)" : 'all checks passed' );
exit( $fails ? 1 : 0 );
