<?php
/**
 * Suspension — vendor suspension with independent sources.
 *
 * Before, anti-fraud and commission enforcement each had their own half of this
 * with separate meta and separate product lists, so paying a commission
 * republished listings that anti-fraud had taken offline. These checks pin down
 * that lifting one source cannot bring a vendor back while another still holds.
 */

require __DIR__ . '/bootstrap.php';

// --- WordPress stubs --------------------------------------------------------
$GLOBALS['usermeta'] = [];
$GLOBALS['posts']    = [];   // id => [ 'author' => int, 'status' => string ]

function get_user_meta( $id, $key, $single = false ) { return $GLOBALS['usermeta'][ $id ][ $key ] ?? ''; }
function update_user_meta( $id, $key, $v ) { $GLOBALS['usermeta'][ $id ][ $key ] = $v; return true; }
function delete_user_meta( $id, $key ) { unset( $GLOBALS['usermeta'][ $id ][ $key ] ); return true; }
function metadata_exists( $type, $id, $key ) { return isset( $GLOBALS['usermeta'][ $id ][ $key ] ); }
function current_time( $type = 'mysql' ) { return gmdate( 'Y-m-d H:i:s' ); }
function is_wp_error( $t ) { return false; }

function get_posts( array $args ) {
	$out = [];
	foreach ( $GLOBALS['posts'] as $id => $p ) {
		if ( $p['author'] === (int) $args['author'] && $p['status'] === $args['post_status'] ) {
			$out[] = $id;
		}
	}
	return $out;
}
function get_post_status( $id ) { return $GLOBALS['posts'][ $id ]['status'] ?? false; }
function wp_update_post( array $data, $wp_error = false ) {
	$id = (int) $data['ID'];
	if ( ! isset( $GLOBALS['posts'][ $id ] ) ) { return 0; }
	$GLOBALS['posts'][ $id ]['status'] = $data['post_status'];
	return $id;
}
function get_post( $id ) { return isset( $GLOBALS['posts'][ $id ] ) ? (object) [ 'ID' => $id ] : null; }
function get_users( array $args ) {
	$out = [];
	foreach ( $GLOBALS['usermeta'] as $uid => $meta ) {
		if ( isset( $meta[ $args['meta_key'] ] ) ) {
			$out[] = ( ( $args['fields'] ?? 'all' ) === 'ID' ) ? $uid : (object) [ 'ID' => $uid ];
		}
	}
	return $out;
}

require SK_TEST_PLUGIN . '/includes/Vendor/Suspension.php';

use SK\Core\Vendor\Suspension;

$fails = 0;
function check( $label, $actual, $expected ) {
	global $fails;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fails++; }
	printf( "%-6s %-58s got=%-20s expected=%s\n", $ok ? 'PASS' : 'FAIL', $label,
		var_export( $actual, true ), var_export( $expected, true ) );
}

function reset_fixture() {
	$GLOBALS['usermeta'] = [];
	$GLOBALS['posts'] = [
		101 => [ 'author' => 7, 'status' => 'publish' ],
		102 => [ 'author' => 7, 'status' => 'publish' ],
		103 => [ 'author' => 7, 'status' => 'draft' ],    // vendor drafted this one themselves
		201 => [ 'author' => 9, 'status' => 'publish' ],
	];
}

const VENDOR = 7;

// --- Single source ----------------------------------------------------------
reset_fixture();
check( 'not suspended initially', Suspension::is_suspended( VENDOR ), false );

$drafted = Suspension::suspend( VENDOR, Suspension::SOURCE_ANTI_FRAUD, 'ban_signal_match' );
check( 'suspending drafts the published listings', $drafted, 2 );
check( 'listing 101 is draft', $GLOBALS['posts'][101]['status'], 'draft' );
check( 'listing 102 is draft', $GLOBALS['posts'][102]['status'], 'draft' );
check( 'other vendor untouched', $GLOBALS['posts'][201]['status'], 'publish' );
check( 'vendor is suspended', Suspension::is_suspended( VENDOR ), true );
check( 'suspended by anti-fraud', Suspension::is_suspended_by( VENDOR, Suspension::SOURCE_ANTI_FRAUD ), true );
check( 'not suspended by commission', Suspension::is_suspended_by( VENDOR, Suspension::SOURCE_COMMISSION ), false );

$restored = Suspension::unsuspend( VENDOR, Suspension::SOURCE_ANTI_FRAUD );
check( 'lifting restores what it drafted', $restored, 2 );
check( 'listing 101 published again', $GLOBALS['posts'][101]['status'], 'publish' );
check( 'self-drafted listing stays draft', $GLOBALS['posts'][103]['status'], 'draft' );
check( 'no longer suspended', Suspension::is_suspended( VENDOR ), false );

// --- Two sources: the interaction that used to be broken --------------------
reset_fixture();
Suspension::suspend( VENDOR, Suspension::SOURCE_ANTI_FRAUD, 'fraud' );
$second = Suspension::suspend( VENDOR, Suspension::SOURCE_COMMISSION, 'unpaid' );

check( 'second source drafts nothing more', $second, 0 );
check( 'both sources recorded', count( Suspension::sources( VENDOR ) ), 2 );

$restored = Suspension::unsuspend( VENDOR, Suspension::SOURCE_COMMISSION );
check( 'paying the commission restores NOTHING', $restored, 0 );
check( 'listing 101 stays offline', $GLOBALS['posts'][101]['status'], 'draft' );
check( 'vendor stays suspended', Suspension::is_suspended( VENDOR ), true );
check( 'commission source gone', Suspension::is_suspended_by( VENDOR, Suspension::SOURCE_COMMISSION ), false );
check( 'fraud source remains', Suspension::is_suspended_by( VENDOR, Suspension::SOURCE_ANTI_FRAUD ), true );

// Only when the last source goes does the vendor come back.
$restored = Suspension::unsuspend( VENDOR, Suspension::SOURCE_ANTI_FRAUD );
check( 'last source restores the listings', $restored, 2 );
check( 'listing 101 published again', $GLOBALS['posts'][101]['status'], 'publish' );
check( 'fully unsuspended', Suspension::is_suspended( VENDOR ), false );
check( 'drafted list cleaned up', isset( $GLOBALS['usermeta'][ VENDOR ][ Suspension::META_DRAFTED ] ), false );

// --- Order does not matter --------------------------------------------------
reset_fixture();
Suspension::suspend( VENDOR, Suspension::SOURCE_COMMISSION, 'unpaid' );
Suspension::suspend( VENDOR, Suspension::SOURCE_ANTI_FRAUD, 'fraud' );
Suspension::unsuspend( VENDOR, Suspension::SOURCE_ANTI_FRAUD );
check( 'reverse order: still offline', $GLOBALS['posts'][101]['status'], 'draft' );
check( 'reverse order: still suspended', Suspension::is_suspended( VENDOR ), true );

// --- Idempotence ------------------------------------------------------------
reset_fixture();
Suspension::suspend( VENDOR, Suspension::SOURCE_ANTI_FRAUD, 'a' );
Suspension::suspend( VENDOR, Suspension::SOURCE_ANTI_FRAUD, 'b' );
check( 'same source twice yields one entry', count( Suspension::sources( VENDOR ) ), 1 );
check( 'drafted list not overwritten by the repeat',
	count( (array) $GLOBALS['usermeta'][ VENDOR ][ Suspension::META_DRAFTED ] ), 2 );

check( 'lifting an unknown source is harmless', Suspension::unsuspend( VENDOR, 'nope' ), 0 );
check( 'still suspended after that', Suspension::is_suspended( VENDOR ), true );
check( 'user 0 cannot be suspended', Suspension::suspend( 0, Suspension::SOURCE_COMMISSION ), 0 );
check( 'empty source is rejected', Suspension::suspend( VENDOR, '' ), 0 );

// --- Migration from the old single-flag anti-fraud meta ---------------------
reset_fixture();
$GLOBALS['posts'][101]['status'] = 'draft';
$GLOBALS['posts'][102]['status'] = 'draft';
$GLOBALS['usermeta'][ VENDOR ] = [
	Suspension::LEGACY_SUSPENDED => 1,
	Suspension::LEGACY_REASON    => 'banned: old case',
	Suspension::LEGACY_SINCE     => '2026-01-01 10:00:00',
	Suspension::LEGACY_DRAFTED   => [ 101, 102 ],
];

check( 'legacy suspension is recognised', Suspension::is_suspended( VENDOR ), true );
check( 'legacy maps to the anti-fraud source',
	Suspension::is_suspended_by( VENDOR, Suspension::SOURCE_ANTI_FRAUD ), true );
check( 'legacy reason carried over',
	Suspension::sources( VENDOR )[ Suspension::SOURCE_ANTI_FRAUD ]['reason'], 'banned: old case' );
check( 'legacy flag removed', isset( $GLOBALS['usermeta'][ VENDOR ][ Suspension::LEGACY_SUSPENDED ] ), false );
check( 'legacy drafted list carried over, so lifting restores',
	Suspension::unsuspend( VENDOR, Suspension::SOURCE_ANTI_FRAUD ), 2 );

// --- Admin listing ----------------------------------------------------------
reset_fixture();
Suspension::suspend( VENDOR, Suspension::SOURCE_ANTI_FRAUD, 'fraud' );
Suspension::suspend( VENDOR, Suspension::SOURCE_COMMISSION, 'unpaid' );
$rows = Suspension::get_suspended();
check( 'one row for the suspended vendor', count( $rows ), 1 );
check( 'row names both sources', count( $rows[0]['sources'] ), 2 );
check( 'row lists the drafted products', count( $rows[0]['products'] ), 2 );

printf( "\n%s\n", $fails ? "{$fails} FAILURE(S)" : 'all checks passed' );
exit( $fails ? 1 : 0 );
