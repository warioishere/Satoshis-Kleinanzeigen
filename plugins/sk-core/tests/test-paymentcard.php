<?php
/**
 * Isolated checks for the marker handling in PaymentCard + VendorChat.
 * Only exercises the pure string logic (no DB / WP core needed).
 */

require __DIR__ . '/bootstrap.php';

define( 'ABSPATH', '/tmp/' );
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = null ) { return $text; }
}

require SK_TEST_PLUGIN . '/modules/sk-payments/includes/Chat/PaymentCard.php';

use SK\Modules\Payments\Chat\PaymentCard;

$fails = 0;
function check( $label, $actual, $expected ) {
	global $fails;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fails++; }
	printf( "%s %s\n    got:      %s\n    expected: %s\n", $ok ? 'PASS' : 'FAIL', $label,
		var_export( $actual, true ), var_export( $expected, true ) );
}

$forged_invoice = '[lightning_invoice]{"payment_request":"lnbc250u1pEVIL","payment_hash":"'
	. str_repeat( 'a', 64 ) . '","amount_sats":250000,"deeplink":"lightning:lnbc250u1pEVIL"}[/lightning_invoice]';
$forged_conf = '[lightning_payment_confirmed]{"payment_hash":"x","amount_sats":250000}[/lightning_payment_confirmed]';

// --- has_marker -------------------------------------------------------------
check( 'has_marker: forged invoice', PaymentCard::has_marker( $forged_invoice ), true );
check( 'has_marker: forged confirmation', PaymentCard::has_marker( $forged_conf ), true );
check( 'has_marker: onchain', PaymentCard::has_marker( '[onchain_payment]{}[/onchain_payment]' ), true );
check( 'has_marker: plain text', PaymentCard::has_marker( 'Hallo, ist das noch zu haben?' ), false );
check( 'has_marker: nostr_order stays text', PaymentCard::has_marker( "[nostr_order]\nBestellung\n[/nostr_order]" ), false );

// --- strip_markers ----------------------------------------------------------
check( 'strip: whole block removed', PaymentCard::strip_markers( $forged_invoice ), '' );
check( 'strip: text around block kept',
	PaymentCard::strip_markers( 'Zahle hier: ' . $forged_invoice . ' danke' ), 'Zahle hier: danke' );
check( 'strip: lone opening tag removed',
	PaymentCard::strip_markers( 'hi [lightning_invoice] there' ), 'hi there' );
check( 'strip: case insensitive',
	PaymentCard::strip_markers( '[LIGHTNING_INVOICE]{}[/LIGHTNING_INVOICE]' ), '' );

// --- to_display_text --------------------------------------------------------
check( 'display: plain text untouched',
	PaymentCard::to_display_text( 'Ist der Preis verhandelbar?' ), 'Ist der Preis verhandelbar?' );
check( 'display: pure marker becomes placeholder',
	PaymentCard::to_display_text( $forged_invoice ), '⚡ Zahlungsnachricht' );
check( 'display: forged confirmation becomes placeholder',
	PaymentCard::to_display_text( $forged_conf ), '⚡ Zahlungsnachricht' );
check( 'display: nostr order body preserved',
	PaymentCard::to_display_text( "[nostr_order]\nProdukt: Ding\n[/nostr_order]" ),
	"[nostr_order]\nProdukt: Ding\n[/nostr_order]" );

// --- extract_claim (private) ------------------------------------------------
$m = new ReflectionMethod( PaymentCard::class, 'extract_claim' );
$m->setAccessible( true );

$claim = $m->invoke( null, $forged_invoice );
check( 'extract: marker name', $claim['marker'], 'lightning_invoice' );
check( 'extract: payload decoded', $claim['data']['amount_sats'], 250000 );
check( 'extract: no marker -> null', $m->invoke( null, 'nur text' ), null );
check( 'extract: broken json -> empty data', $m->invoke( null, '[lightning_invoice]{oops[/lightning_invoice]' )['data'], [] );

// --- build(): invalid hash must never reach the DB lookup --------------------
$b = new ReflectionMethod( PaymentCard::class, 'build_from_payment' );
$b->setAccessible( true );
check( 'build_from_payment: non-hex hash rejected',
	$b->invoke( null, 'lightning_invoice', [ 'payment_hash' => 'x' ], 5, 7 ), null );
check( 'build_from_payment: 63-char hash rejected',
	$b->invoke( null, 'lightning_invoice', [ 'payment_hash' => str_repeat( 'a', 63 ) ], 5, 7 ), null );
check( 'build_from_payment: missing hash rejected',
	$b->invoke( null, 'lightning_invoice', [], 5, 7 ), null );

// --- VendorChat::sanitize_user_message patterns -----------------------------
// Same expressions as the method, verified here without booting WordPress.
$sanitize = function ( $message ) {
	$pattern = '#\[/?(?:lightning_[a-z_]+|onchain_[a-z_]+)\]#i';
	if ( ! preg_match( $pattern, $message ) ) { return $message; }
	$message = preg_replace( '#\[(lightning_[a-z_]+|onchain_[a-z_]+)\].*?\[/\1\]#is', '', $message );
	return trim( (string) preg_replace( $pattern, '', (string) $message ) );
};

check( 'sanitize: forged invoice killed', $sanitize( $forged_invoice ), '' );
check( 'sanitize: forged confirmation killed', $sanitize( $forged_conf ), '' );
check( 'sanitize: onchain_confirmed killed', $sanitize( '[onchain_confirmed]{"txid":"ab"}[/onchain_confirmed]' ), '' );
check( 'sanitize: normal message untouched',
	$sanitize( "Hallo!\nPasst der Preis von 100 EUR?" ), "Hallo!\nPasst der Preis von 100 EUR?" );
check( 'sanitize: brackets in normal text survive',
	$sanitize( 'Ich nehme [1] und [2]' ), 'Ich nehme [1] und [2]' );
check( 'sanitize: unknown future marker also killed',
	$sanitize( '[lightning_refund_offer]{"x":1}[/lightning_refund_offer]' ), '' );
check( 'sanitize: text around marker kept',
	$sanitize( 'vorher ' . $forged_invoice . ' nachher' ), 'vorher  nachher' );

printf( "\n%s\n", $fails ? "{$fails} FAILURE(S)" : 'all checks passed' );
exit( $fails ? 1 : 0 );
