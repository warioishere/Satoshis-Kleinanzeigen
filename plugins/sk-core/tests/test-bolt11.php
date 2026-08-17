<?php
/**
 * Bolt11Parser::get_amount_msats — BOLT-11 spec test vectors + QR payload guards.
 */

require __DIR__ . '/bootstrap.php';

define( 'ABSPATH', '/tmp/' );

class WP_Error {
	public $code;
	public function __construct( $code = '', $msg = '' ) { $this->code = $code; }
	public function get_error_code() { return $this->code; }
}
function is_wp_error( $t ) { return $t instanceof WP_Error; }

require SK_TEST_PLUGIN . '/modules/sk-payments/includes/LNURL/Bolt11Parser.php';

use SK\Modules\Payments\LNURL\Bolt11Parser;

$fails = 0;
function check( $label, $actual, $expected ) {
	global $fails;
	$got = $actual instanceof WP_Error ? 'WP_Error:' . $actual->get_error_code() : $actual;
	$ok  = $got === $expected;
	if ( ! $ok ) { $fails++; }
	printf( "%-6s %-46s got=%-24s expected=%s\n", $ok ? 'PASS' : 'FAIL', $label,
		var_export( $got, true ), var_export( $expected, true ) );
}

// Data part only needs to exist; the amount lives in the HRP.
$tail = '1' . str_repeat( 'q', 200 );

// --- BOLT-11 spec vectors ---------------------------------------------------
check( '2500u = 250k sats', Bolt11Parser::get_amount_msats( 'lnbc2500u' . $tail ), 250000000 );
check( '20m = 2M sats', Bolt11Parser::get_amount_msats( 'lnbc20m' . $tail ), 2000000000 );
check( '9678785340p (spec vector)', Bolt11Parser::get_amount_msats( 'lnbc9678785340p' . $tail ), 967878534 );
check( '1u = 100 sats', Bolt11Parser::get_amount_msats( 'lnbc1u' . $tail ), 100000 );
check( '1n = 0.1 sats', Bolt11Parser::get_amount_msats( 'lnbc1n' . $tail ), 100 );
check( '1 (whole BTC)', Bolt11Parser::get_amount_msats( 'lnbc1' . $tail ), 100000000000 );

// --- networks ---------------------------------------------------------------
check( 'testnet lntb20m', Bolt11Parser::get_amount_msats( 'lntb20m' . $tail ), 2000000000 );
check( 'regtest lnbcrt500u', Bolt11Parser::get_amount_msats( 'lnbcrt500u' . $tail ), 50000000 );
check( 'signet lnsb100u', Bolt11Parser::get_amount_msats( 'lnsb100u' . $tail ), 10000000 );

// --- rejections -------------------------------------------------------------
check( 'no amount (open invoice)', Bolt11Parser::get_amount_msats( 'lnbc' . $tail ), 'WP_Error:bolt11_no_amount' );
check( 'sub-msat pico amount', Bolt11Parser::get_amount_msats( 'lnbc1p' . $tail ), 'WP_Error:bolt11_sub_msat' );
check( 'unknown network', Bolt11Parser::get_amount_msats( 'lnxx100u' . $tail ), 'WP_Error:bolt11_hrp' );
// No "1" at all -> no bech32 separator, rejected before the HRP is parsed.
check( 'not an invoice', Bolt11Parser::get_amount_msats( 'not-an-invoice' ), 'WP_Error:bolt11_invalid' );
check( 'garbage with separator', Bolt11Parser::get_amount_msats( 'evil1payload' ), 'WP_Error:bolt11_hrp' );
check( 'no separator', Bolt11Parser::get_amount_msats( 'lnbc2500u' ), 'WP_Error:bolt11_invalid' );
check( 'bad multiplier', Bolt11Parser::get_amount_msats( 'lnbc100x' . $tail ), 'WP_Error:bolt11_hrp' );

// --- case + prefix handling -------------------------------------------------
check( 'uppercase invoice', Bolt11Parser::get_amount_msats( 'LNBC2500U' . strtoupper( $tail ) ), 250000000 );
check( 'lightning: prefix', Bolt11Parser::get_amount_msats( 'lightning:lnbc2500u' . $tail ), 250000000 );

// --- the check create_invoice performs -------------------------------------
$requested_sats = 250000;
check( 'matches requested amount',
	Bolt11Parser::get_amount_msats( 'lnbc2500u' . $tail ) === $requested_sats * 1000, true );
check( 'mismatch is detected (vendor asks 10x)',
	Bolt11Parser::get_amount_msats( 'lnbc25m' . $tail ) === $requested_sats * 1000, false );

// --- QR endpoint payload validation (same expressions as get_qr) ------------
$is_bolt11 = function ( $d ) { return (bool) preg_match( '/^ln[a-z0-9]{20,}$/i', $d ); };
$is_bip21  = function ( $d ) { return (bool) preg_match( '/^bitcoin:[a-zA-Z0-9]{20,90}(?:\?[A-Za-z0-9=&.\-_%]*)?$/', $d ); };

check( 'qr accepts bolt11', $is_bolt11( 'lnbc2500u' . $tail ), true );
check( 'qr accepts bech32 bip21',
	$is_bip21( 'bitcoin:bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq?amount=0.00250000' ), true );
check( 'qr accepts base58 bip21',
	$is_bip21( 'bitcoin:1BvBMSEYstWetqTFn5Au4m4GFg7xJaNVN2?amount=0.001' ), true );
check( 'qr rejects http url', $is_bolt11( 'http://evil.tld/x' ) || $is_bip21( 'http://evil.tld/x' ), false );
check( 'qr rejects javascript uri',
	$is_bolt11( 'javascript:alert(1)' ) || $is_bip21( 'javascript:alert(1)' ), false );
check( 'qr rejects html payload',
	$is_bolt11( '<img src=x onerror=alert(1)>' ) || $is_bip21( '<img src=x>' ), false );
check( 'qr rejects arbitrary text', $is_bolt11( 'hallo welt' ) || $is_bip21( 'hallo welt' ), false );

printf( "\n%s\n", $fails ? "{$fails} FAILURE(S)" : 'all checks passed' );
exit( $fails ? 1 : 0 );
