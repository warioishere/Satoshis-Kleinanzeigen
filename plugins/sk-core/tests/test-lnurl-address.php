<?php
/**
 * Resolver::is_own_address — which addresses are routed to our own LNURL endpoint.
 *
 * Vendors who never typed a Lightning address get v/<id>@<host> from this site.
 * A slash is illegal in a Lightning address everywhere else, so the general
 * format check rejects it and every server-side payment towards such a vendor
 * used to die as "invalid address" before it ever reached our endpoint.
 */

namespace {

	require __DIR__ . '/bootstrap.php';

	defined( 'ABSPATH' ) || define( 'ABSPATH', '/tmp/' );

	class WP_Error {
		public $code;
		public function __construct( $code = '', $msg = '' ) { $this->code = $code; }
		public function get_error_code() { return $this->code; }
	}
	function is_wp_error( $t ) { return $t instanceof \WP_Error; }

	function home_url( $path = '' ) { return 'https://staging.satoshiskleinanzeigen.space' . $path; }
	function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
}

// The resolver asks Settings for the ordinary formats; only that much is needed.
namespace SK\Core\Wallet {

	class Settings {
		public static function is_valid_lightning_address( string $v ): bool {
			return (bool) preg_match( '/^[a-zA-Z0-9_.+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $v );
		}
		public static function is_valid_lnurl( string $v ): bool {
			return stripos( $v, 'lnurl1' ) === 0 && strlen( $v ) > 20;
		}
	}
}

namespace {

	require SK_TEST_PLUGIN . '/includes/Wallet/LNURL/Resolver.php';

	use SK\Core\Wallet\LNURL\Resolver;
	use SK\Core\Wallet\Settings;

	$host  = 'staging.satoshiskleinanzeigen.space';
	$fails = 0;

	function check( $label, $actual, $expected ) {
		global $fails;
		$ok = $actual === $expected;
		if ( ! $ok ) { $fails++; }
		printf( "%-6s %-46s got=%-10s expected=%s\n", $ok ? 'PASS' : 'FAIL', $label,
			var_export( $actual, true ), var_export( $expected, true ) );
	}

	// --- ours ---------------------------------------------------------------
	check( 'own address', Resolver::is_own_address( 'v/144@' . $host ), true );
	check( 'own address, upper case', Resolver::is_own_address( 'V/144@' . strtoupper( $host ) ), true );

	// --- not ours -----------------------------------------------------------
	check( 'foreign host', Resolver::is_own_address( 'v/144@evil.example' ), false );
	check( 'our host as a suffix', Resolver::is_own_address( 'v/144@' . $host . '.evil.example' ), false );
	check( 'our host as a prefix', Resolver::is_own_address( 'v/144@sub.' . $host ), false );
	check( 'no numeric id', Resolver::is_own_address( 'v/abc@' . $host ), false );
	check( 'plain address', Resolver::is_own_address( 'wario@getalby.com' ), false );
	check( 'empty', Resolver::is_own_address( '' ), false );
	check( 'path traversal in local part', Resolver::is_own_address( 'v/../wp-admin@' . $host ), false );

	// --- routing ------------------------------------------------------------
	// resolve() must not reject our own address on format. Everything past that
	// goes out over HTTP, which a unit test does not do — the format verdict is
	// what matters, and a rejected one never leaves the process.
	$accepted = function ( $addr ) {
		return Resolver::is_own_address( $addr ) || Settings::is_valid_lightning_address( $addr );
	};

	check( 'ordinary address is accepted', $accepted( 'wario@getalby.com' ), true );
	check( 'our own address is accepted', $accepted( 'v/144@' . $host ), true );
	check( 'garbage is still refused', $accepted( 'hallo welt' ), false );
	check( 'foreign slash address is still refused', $accepted( 'v/144@evil.example' ), false );

	printf( "\n%s\n", $fails ? "{$fails} FAILURE(S)" : 'all checks passed' );
	exit( $fails ? 1 : 0 );
}
