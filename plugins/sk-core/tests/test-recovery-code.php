<?php
/**
 * RecoveryCode: only the hash is stored, a code opens exactly its own
 * account exactly once, a new code kills the old one, typing variants
 * are accepted and anything that is not a code is rejected.
 */

require __DIR__ . '/bootstrap.php';

defined( 'DAY_IN_SECONDS' ) || define( 'DAY_IN_SECONDS', 86400 );

// User meta in memory: [ user_id => [ key => value ] ].
$GLOBALS['sk_test_meta'] = [];

function get_user_meta( $user_id, $key, $single = false ) {
	return $GLOBALS['sk_test_meta'][ $user_id ][ $key ] ?? '';
}
function update_user_meta( $user_id, $key, $value ) {
	$GLOBALS['sk_test_meta'][ $user_id ][ $key ] = $value;
	return true;
}
function delete_user_meta( $user_id, $key ) {
	unset( $GLOBALS['sk_test_meta'][ $user_id ][ $key ] );
	return true;
}
function get_users( $args ) {
	$found = [];
	foreach ( $GLOBALS['sk_test_meta'] as $id => $meta ) {
		if ( ( $meta[ $args['meta_key'] ] ?? null ) === $args['meta_value'] ) {
			$found[] = $id;
		}
	}
	return array_slice( $found, 0, $args['number'] );
}

require SK_TEST_PLUGIN . '/modules/sk-auth/includes/RecoveryCode.php';

use SK\Modules\Auth\RecoveryCode;

$fails = 0;
function check( $label, $actual, $expected ) {
	global $fails;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fails++; }
	printf( "%-6s %-55s got=%-28s expected=%s\n", $ok ? 'PASS' : 'FAIL', $label, var_export( $actual, true ), var_export( $expected, true ) );
}

$alice = 7;
$bob   = 8;

$code = RecoveryCode::generate( $alice );
check( 'format: 4 groups of 5 from the alphabet', (bool) preg_match( '/^[2-9A-HJKMNP-Z]{5}(-[2-9A-HJKMNP-Z]{5}){3}$/', $code ), true );
check( 'code itself is not stored', in_array( $code, $GLOBALS['sk_test_meta'][ $alice ], true ) || in_array( str_replace( '-', '', $code ), $GLOBALS['sk_test_meta'][ $alice ], true ), false );
check( 'two codes differ', RecoveryCode::generate( $bob ) !== $code, true );

check( 'unknown code', RecoveryCode::redeem( 'AAAAA-AAAAA-AAAAA-AAAAA' ), 0 );
check( 'too short', RecoveryCode::redeem( substr( $code, 0, 22 ) ), 0 );
check( 'misread characters (0/O/1/I/L)', RecoveryCode::redeem( '0OIL1-AAAAA-AAAAA-AAAAA' ), 0 );
check( 'empty', RecoveryCode::redeem( '' ), 0 );

$old  = $code;
$code = RecoveryCode::generate( $alice );
check( 'a new code invalidates the old one', RecoveryCode::redeem( $old ), 0 );

check( 'lower case with spaces opens the account', RecoveryCode::redeem( ' ' . strtolower( str_replace( '-', ' ', $code ) ) . ' ' ), $alice );
check( 'used up afterwards', RecoveryCode::redeem( $code ), 0 );
check( 'no code left on the account', RecoveryCode::has_code( $alice ), false );
check( "bob's code still there", RecoveryCode::has_code( $bob ), true );

printf( "\n%s\n", $fails ? "{$fails} FAILURE(S)" : 'all checks passed' );
exit( $fails ? 1 : 0 );
