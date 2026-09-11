<?php
/**
 * Nip05 — an address counts only when its domain vouches for the vendor's key.
 *
 * The lookup goes to the domain's /.well-known/nostr.json. Here the network is
 * faked: one domain answers with the right key, one with a different key, one
 * without the name, one not at all.
 */

namespace {

	require __DIR__ . '/bootstrap.php';

	defined( 'ABSPATH' ) || define( 'ABSPATH', '/tmp/' );
	defined( 'DAY_IN_SECONDS' ) || define( 'DAY_IN_SECONDS', 86400 );

	class WP_Error {
		public $code; public $message;
		public function __construct( $code = '', $msg = '' ) { $this->code = $code; $this->message = $msg; }
	}
	function is_wp_error( $t ) { return $t instanceof \WP_Error; }

	$GLOBALS['user_meta'] = [];
	function get_user_meta( $id, $key = '', $single = false ) { return $GLOBALS['user_meta'][ $id ][ $key ] ?? ''; }
	function update_user_meta( $id, $key, $value ) { $GLOBALS['user_meta'][ $id ][ $key ] = $value; return true; }
	function get_userdata( $id ) { return (object) [ 'user_nicename' => 'vendor' . $id ]; }

	$GLOBALS['KEY']   = str_repeat( 'ab', 32 );
	$GLOBALS['OTHER'] = str_repeat( 'cd', 32 );

	$GLOBALS['requests'] = 0;
	function wp_safe_remote_get( $url, $args = [] ) {
		$GLOBALS['requests']++;
		$q = [];
		parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $q );
		$n = $q['name'] ?? '';

		if ( strpos( $url, 'https://iris.example/.well-known/nostr.json' ) === 0 ) {
			return [ 'code' => 200, 'body' => json_encode( [ 'names' => [ 'Mario' => $GLOBALS['KEY'] ] ] ) ];
		}
		if ( strpos( $url, 'https://wrong.example/' ) === 0 ) {
			return [ 'code' => 200, 'body' => json_encode( [ 'names' => [ $n => $GLOBALS['OTHER'] ] ] ) ];
		}
		if ( strpos( $url, 'https://empty.example/' ) === 0 ) {
			return [ 'code' => 200, 'body' => json_encode( [ 'names' => [] ] ) ];
		}
		return new \WP_Error( 'http', 'unreachable' );
	}
	function wp_remote_retrieve_response_code( $r ) { return is_array( $r ) ? $r['code'] : 0; }
	function wp_remote_retrieve_body( $r ) { return is_array( $r ) ? $r['body'] : ''; }
}

// The bound key of the one vendor in this test.
namespace SK\Core\Trust {
	class VendorKey {
		public static function bound( int $user_id ): string { return $user_id === 1 ? $GLOBALS['KEY'] : ''; }
		public static function site(): string { return 'sk.example'; }
	}
}

// Vendor 3 has an identity this site generated.
namespace SK\Modules\Auth {
	class NostrIdentity {
		public static function has_identity( int $user_id ): bool { return $user_id === 3; }
	}
}

namespace {

	require SK_TEST_PLUGIN . '/includes/Nostr/Nip05.php';

	use SK\Core\Nostr\Nip05;

	$KEY   = $GLOBALS['KEY'];
	$fails = 0;
	function check( $label, $actual, $expected ) {
		global $fails;
		$ok = $actual === $expected;
		if ( ! $ok ) { $fails++; }
		printf( "%-6s %-50s got=%-14s expected=%s\n", $ok ? 'PASS' : 'FAIL', $label,
			var_export( $actual, true ), var_export( $expected, true ) );
	}

	// --- verify() -----------------------------------------------------------
	check( 'domain vouches for the key', Nip05::verify( 'mario@iris.example', $KEY ), true );
	check( 'name is matched without regard to case', Nip05::verify( 'MARIO@iris.example', $KEY ), true );
	check( 'domain names a different key', Nip05::verify( 'mario@wrong.example', $KEY ), false );
	check( 'domain does not know the name', Nip05::verify( 'mario@empty.example', $KEY ), false );
	check( 'domain unreachable', Nip05::verify( 'mario@down.example', $KEY ), false );
	check( 'no at sign', Nip05::verify( 'mario', $KEY ), false );
	check( 'bad key', Nip05::verify( 'mario@iris.example', 'nokey' ), false );
	check( 'bad domain', Nip05::verify( 'mario@not a domain', $KEY ), false );

	// --- check() stores the verdict and does not ask twice -------------------
	$GLOBALS['user_meta'][1]['nip05'] = 'mario@iris.example';
	$GLOBALS['requests'] = 0;
	$v = Nip05::check( 1 );
	check( 'verdict ok', $v['ok'] ?? null, true );
	check( 'one request made', $GLOBALS['requests'], 1 );
	Nip05::check( 1 );
	check( 'stored verdict reused', $GLOBALS['requests'], 1 );
	check( 'verified() reports the address', Nip05::verified( 1 ), 'mario@iris.example' );

	// --- a changed address is checked again ----------------------------------
	$GLOBALS['user_meta'][1]['nip05'] = 'mario@wrong.example';
	check( 'old verdict does not carry over', Nip05::verified( 1 ), null );
	$v = Nip05::check( 1 );
	check( 'new address checked', $GLOBALS['requests'], 2 );
	check( 'and refused', $v['ok'], false );
	check( 'nothing verified now', Nip05::verified( 1 ), null );

	// --- an old verdict is renewed ------------------------------------------
	$GLOBALS['user_meta'][1]['nip05'] = 'mario@iris.example';
	$GLOBALS['user_meta'][1][ Nip05::META ] = [ 'address' => 'mario@iris.example', 'ok' => true, 'at' => time() - 8 * DAY_IN_SECONDS ];
	Nip05::check( 1 );
	check( 'stale verdict renewed', $GLOBALS['requests'], 3 );

	// --- no key, no check ---------------------------------------------------
	$GLOBALS['user_meta'][2]['nip05'] = 'x@iris.example';
	check( 'vendor without a bound key', Nip05::check( 2 ), null );
	check( 'no request for them', $GLOBALS['requests'], 3 );

	// --- shown(): the verified address, else this site's for a generated identity
	check( 'shown: verified address wins', Nip05::shown( 1 ), 'mario@iris.example' );
	check( 'shown: nothing for a foreign key without verdict', Nip05::shown( 2 ), null );
	check( 'shown: site address for a generated identity', Nip05::shown( 3 ), 'vendor3@sk.example' );

	printf( "\n%s\n", $fails ? "{$fails} FAILURE(S)" : 'all checks passed' );
	exit( $fails ? 1 : 0 );
}
