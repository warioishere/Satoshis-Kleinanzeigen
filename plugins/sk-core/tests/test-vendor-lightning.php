<?php
/**
 * Settings::has_lightning — who can actually be paid over Lightning.
 *
 * Every account gets v/<id>@<host> from this site, published in its Nostr
 * profile and synced back into the shop settings. That address is minted
 * through the wallet the vendor connected, so on its own it is dead: counting
 * it as a payment route produced zap buttons and Lightning purchases that could
 * never succeed.
 */

require __DIR__ . '/bootstrap.php';

defined( 'ABSPATH' ) || define( 'ABSPATH', '/tmp/' );

const TEST_HOST = 'staging.satoshiskleinanzeigen.space';

function home_url( $path = '' ) { return 'https://' . TEST_HOST . $path; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function add_action( ...$a ) {}
function add_filter( ...$a ) {}

// The site-wide wallet switch. Flipped by one of the checks below.
$GLOBALS['wallet_switch'] = 'on';
function sk_get_option( $key, $group = '', $default = '' ) {
	return $key === 'wallet_connections' ? $GLOBALS['wallet_switch'] : $default;
}

// Vendors, by what they have stored.
$GLOBALS['vendors'] = [
	1 => [ 'lightning_nwc' => true ],                                   // wallet connected
	2 => [ 'lightning_lndhub' => true ],                                // other wallet
	3 => [ 'lightning_address' => 'wario@getalby.com', 'lightning_lud21' => true ], // own address, provable
	4 => [ 'lightning_address' => 'v/4@' . TEST_HOST ],                 // ours, no wallet
	5 => [ 'lightning_address' => 'v/5@' . TEST_HOST, 'lightning_nwc' => true ], // ours, with wallet
	6 => [ 'lightning_address' => 'someshop@' . TEST_HOST ],            // ours by slug, no wallet
	7 => [],                                                            // nothing at all
	// Mirrored from a Nostr profile, but its wallet cannot confirm a payment.
	10 => [ 'lightning_address' => 'shop@wos.example', 'lightning_lud21' => false ],
	// Stored before the flag existed: not yet checked.
	11 => [ 'lightning_address' => 'shop@alt.example' ],
	8 => [ 'btc_address' => 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq' ],
];

// The xpub lives in its own meta key, not in the settings array.
$GLOBALS['xpubs'] = [ 9 => 'zpub6rFR7y4Q2AijBEqTUquhVz398htDFrtymD9xYYfG1m4wAcvPhXNfE3EfH1r1ADqtfSdVCToUG868RvUUkgDKf31mGDtKsAYz2oz2AGutZYs' ];

function get_user_meta( $user_id, $key = '', $single = false ) {
	if ( $key === 'sk_xpub' ) {
		return $GLOBALS['xpubs'][ $user_id ] ?? '';
	}
	return $GLOBALS['vendors'][ $user_id ] ?? [];
}

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

// --- who can be paid --------------------------------------------------------
check( 'NWC connected', Settings::has_lightning( 1 ), true );
check( 'LNDHub connected', Settings::has_lightning( 2 ), true );
check( 'own address elsewhere', Settings::has_lightning( 3 ), true );
check( 'our address, no wallet', Settings::has_lightning( 4 ), false );
check( 'our address, wallet connected', Settings::has_lightning( 5 ), true );
check( 'our address by slug, no wallet', Settings::has_lightning( 6 ), false );
check( 'nothing stored', Settings::has_lightning( 7 ), false );

// A sale needs a payment that can be proven; a zap does not, and reads the
// address directly.
check( 'address that cannot prove a payment', Settings::has_lightning( 10 ), false );
check( 'address not checked yet', Settings::has_lightning( 11 ), false );
check( 'provable is reported as such', Settings::address_is_provable( 3 ), true );
check( 'unprovable is reported as such', Settings::address_is_provable( 10 ), false );

// --- the switch still wins --------------------------------------------------
// Off has to reach the getters too, not just the has_… methods: a caller that
// asks for the address directly can mint an invoice from it, and that is what
// the switch exists to stop.
check( 'switch on: address handed out', Settings::get_lightning_address( 3 ), 'wario@getalby.com' );
check( 'switch on: onchain address handed out', Settings::get_btc_address( 8 ), 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq' );
check( 'switch on: xpub seen', Settings::has_xpub( 9 ), true );

$GLOBALS['wallet_switch'] = 'off';
check( 'switch off: NWC', Settings::has_lightning( 1 ), false );
check( 'switch off: foreign address', Settings::has_lightning( 3 ), false );
check( 'switch off: no address handed out', Settings::get_lightning_address( 3 ), '' );
check( 'switch off: no onchain address', Settings::get_btc_address( 8 ), '' );
check( 'switch off: xpub not seen', Settings::has_xpub( 9 ), false );
check( 'switch off: onchain reports nothing', Settings::has_onchain( 8 ), false );
$GLOBALS['wallet_switch'] = 'on';

// --- which addresses are ours ----------------------------------------------
check( 'v/<id> on our host', Settings::is_local_address( 'v/4@' . TEST_HOST ), true );
check( 'slug on our host', Settings::is_local_address( 'someshop@' . TEST_HOST ), true );
check( 'our host, other case', Settings::is_local_address( 'v/4@' . strtoupper( TEST_HOST ) ), true );
check( 'foreign host', Settings::is_local_address( 'wario@getalby.com' ), false );
check( 'our host as a suffix', Settings::is_local_address( 'x@' . TEST_HOST . '.evil.example' ), false );
check( 'our host as a subdomain', Settings::is_local_address( 'x@mail.' . TEST_HOST ), false );
check( 'no at sign', Settings::is_local_address( TEST_HOST ), false );
check( 'empty', Settings::is_local_address( '' ), false );

printf( "\n%s\n", $fails ? "{$fails} FAILURE(S)" : 'all checks passed' );
exit( $fails ? 1 : 0 );
