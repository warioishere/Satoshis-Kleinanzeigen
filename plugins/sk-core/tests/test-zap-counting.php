<?php
/**
 * ZapStats::count_zap — a paid zap is counted once, wherever it was seen.
 *
 * Three paths learn about a zap: the browser handing in a receipt, the feed's
 * invoice lookup and the relay sync. The relay sync had no lock at all, so the
 * same receipt arriving from a second relay counted twice on the post while the
 * vendor's own total never moved.
 */

require __DIR__ . '/bootstrap.php';

defined( 'ABSPATH' ) || define( 'ABSPATH', '/tmp/' );
defined( 'HOUR_IN_SECONDS' ) || define( 'HOUR_IN_SECONDS', 3600 );

// --- WordPress meta, in memory ----------------------------------------------
$GLOBALS['user_meta'] = [];
$GLOBALS['post_meta'] = [];
$GLOBALS['posts']     = [];

function get_user_meta( $id, $key = '', $single = false ) { return $GLOBALS['user_meta'][ $id ][ $key ] ?? ''; }
function update_user_meta( $id, $key, $value ) { $GLOBALS['user_meta'][ $id ][ $key ] = $value; return true; }
function add_user_meta( $id, $key, $value, $unique = false ) {
	if ( $unique && isset( $GLOBALS['user_meta'][ $id ][ $key ] ) ) { return false; }
	$GLOBALS['user_meta'][ $id ][ $key ] = $value;
	return true;
}
function get_post_meta( $id, $key = '', $single = false ) { return $GLOBALS['post_meta'][ $id ][ $key ] ?? ''; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['post_meta'][ $id ][ $key ] = $value; return true; }
function add_post_meta( $id, $key, $value, $unique = false ) {
	if ( $unique && isset( $GLOBALS['post_meta'][ $id ][ $key ] ) ) { return false; }
	$GLOBALS['post_meta'][ $id ][ $key ] = $value;
	return true;
}
function get_post( $id ) { return $GLOBALS['posts'][ $id ] ?? null; }
function add_action( ...$a ) {}
function add_filter( ...$a ) {}

require SK_TEST_PLUGIN . '/modules/sk-zaps/includes/ZapStats.php';

use SK\Modules\Zaps\ZapStats;

$fails = 0;
function check( $label, $actual, $expected ) {
	global $fails;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fails++; }
	printf( "%-6s %-52s got=%-10s expected=%s\n", $ok ? 'PASS' : 'FAIL', $label,
		var_export( $actual, true ), var_export( $expected, true ) );
}

function vendor_total( $id ) { return (int) ( $GLOBALS['user_meta'][ $id ][ ZapStats::SATS_META ] ?? 0 ); }
function post_total( $id ) { return (int) ( $GLOBALS['post_meta'][ $id ]['_sk_zap_total_sats'] ?? 0 ); }

$vendor = 7;
$other  = 8;
$post   = 100;
$GLOBALS['posts'][ $post ]  = (object) [ 'post_author' => $vendor, 'post_type' => 'sk_feed_post' ];
$GLOBALS['posts'][ 101 ]    = (object) [ 'post_author' => $other, 'post_type' => 'sk_feed_post' ];

$receipt_a = str_repeat( 'a', 64 );
$receipt_b = str_repeat( 'b', 64 );

// --- The first sighting counts ----------------------------------------------
$r = ZapStats::count_zap( $vendor, $receipt_a, 21, $post );
check( 'first sighting counts', $r['counted'], true );
check( 'vendor total after one zap', vendor_total( $vendor ), 21 );
check( 'post total after one zap', post_total( $post ), 21 );
check( 'post total is reported back', $r['post_total'], 21 );

// --- The same receipt from a second relay must not count again --------------
$r = ZapStats::count_zap( $vendor, $receipt_a, 21, $post );
check( 'second sighting does not count', $r['counted'], false );
check( 'vendor total unchanged', vendor_total( $vendor ), 21 );
check( 'post total unchanged', post_total( $post ), 21 );
check( 'post total still reported', $r['post_total'], 21 );

// --- A different zap does count ---------------------------------------------
ZapStats::count_zap( $vendor, $receipt_b, 100, $post );
check( 'a second, different zap counts', vendor_total( $vendor ), 121 );
check( 'post total follows', post_total( $post ), 121 );

// --- Someone else's post is not touched -------------------------------------
$r = ZapStats::count_zap( $vendor, str_repeat( 'c', 64 ), 50, 101 );
check( 'foreign post is not counted', post_total( 101 ), 0 );
check( 'foreign post reports nothing', $r['post_total'], null );
check( 'vendor total still moves', vendor_total( $vendor ), 171 );

// --- A zap without a post ---------------------------------------------------
$r = ZapStats::count_zap( $vendor, str_repeat( 'd', 64 ), 9 );
check( 'zap without a post counts for the vendor', vendor_total( $vendor ), 180 );
check( 'no post total to report', $r['post_total'], null );

// --- Unusable input ---------------------------------------------------------
check( 'no key, no count', ZapStats::count_zap( $vendor, 'nicht-hex', 10 )['counted'], false );
check( 'no amount, no count', ZapStats::count_zap( $vendor, str_repeat( 'e', 64 ), 0 )['counted'], false );
check( 'no vendor, no count', ZapStats::count_zap( 0, str_repeat( 'f', 64 ), 10 )['counted'], false );
check( 'total untouched by bad input', vendor_total( $vendor ), 180 );

printf( "\n%s\n", $fails ? "{$fails} FAILURE(S)" : 'all checks passed' );
exit( $fails ? 1 : 0 );
