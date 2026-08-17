<?php
/**
 * sk_get_client_ip() / sk_is_trusted_proxy() — proxy-header trust rules.
 *
 * SK_TRUSTED_PROXIES cannot be undefined once set, so the three configurations
 * run as separate child processes. Call without arguments to run all of them.
 */

require __DIR__ . '/bootstrap.php';

function sanitize_text_field( $s ) { return is_string( $s ) ? trim( strip_tags( $s ) ) : ''; }
function wp_unslash( $s ) { return is_string( $s ) ? stripslashes( $s ) : $s; }

$mode = $argv[1] ?? '';

// Runner: execute each mode in its own process.
if ( $mode === '' ) {
	$fails = 0;
	foreach ( [ 'heuristic', 'trusted', 'empty' ] as $m ) {
		printf( "\n### mode: %s\n", $m );
		passthru( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' ' . $m, $code );
		$fails += $code;
	}
	printf( "\n%s\n", $fails ? "{$fails} MODE(S) WITH FAILURES" : 'all modes passed' );
	exit( $fails ? 1 : 0 );
}

$src = file_get_contents(
	SK_TEST_PLUGIN . '/includes/functions.php'
);

foreach ( [ 'sk_get_client_ip', 'sk_is_trusted_proxy' ] as $fn ) {
	if ( ! preg_match( '/\nfunction ' . $fn . '\([^)]*\) \{.*?\n\}/s', $src, $m ) ) {
		exit( "could not extract {$fn}()\n" );
	}
	eval( $m[0] );
}

if ( $mode === 'trusted' ) {
	define( 'SK_TRUSTED_PROXIES', '203.0.113.9, 203.0.113.10' );
} elseif ( $mode === 'empty' ) {
	define( 'SK_TRUSTED_PROXIES', '' );
}

$fails = 0;
function check( $label, $actual, $expected ) {
	global $fails;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fails++; }
	printf( "%-6s %-52s got=%-22s expected=%s\n", $ok ? 'PASS' : 'FAIL', $label,
		var_export( $actual, true ), var_export( $expected, true ) );
}

function scenario( array $server ) {
	$_SERVER = $server;
	return sk_get_client_ip();
}

$forge = [ 'HTTP_X_FORWARDED_FOR' => '9.9.9.9', 'HTTP_CF_CONNECTING_IP' => '9.9.9.9', 'HTTP_X_REAL_IP' => '9.9.9.9' ];

// --- A directly connected client can never pick its own IP -------------------
check( 'direct client, no headers', scenario( [ 'REMOTE_ADDR' => '203.0.113.1' ] ), '203.0.113.1' );
check( 'direct client forging all three headers',
	scenario( [ 'REMOTE_ADDR' => '203.0.113.1' ] + $forge ), '203.0.113.1' );

// --- Degenerate input -------------------------------------------------------
check( 'no REMOTE_ADDR at all', scenario( [] ), '' );
check( 'invalid REMOTE_ADDR', scenario( [ 'REMOTE_ADDR' => 'localhost' ] ), '' );
check( 'IPv6 peer', scenario( [ 'REMOTE_ADDR' => '2001:db8::1' ] ), '2001:db8::1' );

if ( $mode === 'heuristic' ) {
	// No constant declared: a private/loopback peer is a local reverse proxy.
	check( 'loopback proxy forwards client',
		scenario( [ 'REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_FOR' => '198.51.100.7' ] ), '198.51.100.7' );
	check( 'private-range proxy forwards client',
		scenario( [ 'REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_REAL_IP' => '198.51.100.7' ] ), '198.51.100.7' );
	check( 'IPv6 loopback proxy forwards client',
		scenario( [ 'REMOTE_ADDR' => '::1', 'HTTP_X_REAL_IP' => '198.51.100.7' ] ), '198.51.100.7' );
	check( 'XFF chain uses leftmost (original client)',
		scenario( [ 'REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_FOR' => '198.51.100.7, 10.0.0.5, 10.0.0.6' ] ),
		'198.51.100.7' );
	check( 'CF header preferred over XFF',
		scenario( [ 'REMOTE_ADDR' => '127.0.0.1', 'HTTP_CF_CONNECTING_IP' => '198.51.100.8', 'HTTP_X_FORWARDED_FOR' => '198.51.100.7' ] ),
		'198.51.100.8' );
	check( 'garbage header falls back to peer',
		scenario( [ 'REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_FOR' => 'not-an-ip' ] ), '127.0.0.1' );
	check( 'header with junk then valid IP',
		scenario( [ 'REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_FOR' => 'junk, 198.51.100.7' ] ), '198.51.100.7' );
}

if ( $mode === 'trusted' ) {
	// Constant declared: only the listed peers are proxies (Cloudflare case).
	check( 'listed proxy is honoured',
		scenario( [ 'REMOTE_ADDR' => '203.0.113.9', 'HTTP_CF_CONNECTING_IP' => '198.51.100.7' ] ), '198.51.100.7' );
	check( 'second listed proxy is honoured',
		scenario( [ 'REMOTE_ADDR' => '203.0.113.10', 'HTTP_CF_CONNECTING_IP' => '198.51.100.7' ] ), '198.51.100.7' );
	check( 'unlisted public peer ignored',
		scenario( [ 'REMOTE_ADDR' => '203.0.113.99', 'HTTP_CF_CONNECTING_IP' => '198.51.100.7' ] ), '203.0.113.99' );
	check( 'unlisted loopback peer no longer auto-trusted',
		scenario( [ 'REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_REAL_IP' => '198.51.100.7' ] ), '127.0.0.1' );
}

if ( $mode === 'empty' ) {
	// This site's configuration: no proxy at all, so no header is ever trusted.
	check( 'public peer forging headers ignored',
		scenario( [ 'REMOTE_ADDR' => '203.0.113.1' ] + $forge ), '203.0.113.1' );
	check( 'loopback peer forging headers ignored',
		scenario( [ 'REMOTE_ADDR' => '127.0.0.1' ] + $forge ), '127.0.0.1' );
	check( 'private peer forging headers ignored',
		scenario( [ 'REMOTE_ADDR' => '10.0.0.5' ] + $forge ), '10.0.0.5' );
}

printf( "%s\n", $fails ? "{$fails} FAILURE(S)" : 'mode passed' );
exit( $fails ? 1 : 0 );
