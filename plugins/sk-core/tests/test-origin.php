<?php
/**
 * sk_is_same_origin_request() — login-CSRF guard, and
 * sk_account_has_password() — who must confirm their password.
 */

require __DIR__ . '/bootstrap.php';

function sanitize_text_field( $s ) { return is_string( $s ) ? trim( strip_tags( $s ) ) : ''; }
function wp_unslash( $s ) { return is_string( $s ) ? stripslashes( $s ) : $s; }
function home_url( $p = '' ) { return 'https://new.satoshiskleinanzeigen.space' . $p; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }

$GLOBALS['meta'] = [];
function get_user_meta( $id, $key, $single = false ) { return $GLOBALS['meta'][ $id ][ $key ] ?? ''; }

$src = file_get_contents(
	SK_TEST_PLUGIN . '/includes/functions.php'
);

foreach ( [ 'sk_is_same_origin_request', 'sk_account_has_password' ] as $fn ) {
	if ( ! preg_match( '/\nfunction ' . $fn . '\([^)]*\) \{.*?\n\}/s', $src, $m ) ) {
		exit( "could not extract {$fn}()\n" );
	}
	eval( $m[0] );
}

$fails = 0;
function check( $label, $actual, $expected ) {
	global $fails;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fails++; }
	printf( "%-6s %-56s got=%-8s expected=%s\n", $ok ? 'PASS' : 'FAIL', $label,
		var_export( $actual, true ), var_export( $expected, true ) );
}

function req( array $server ) {
	$_SERVER = $server;
	return sk_is_same_origin_request();
}

// --- Sec-Fetch-Site: the primary signal, unforgeable by page scripts --------
check( 'same-origin accepted',  req( [ 'HTTP_SEC_FETCH_SITE' => 'same-origin' ] ), true );
check( 'same-site accepted',    req( [ 'HTTP_SEC_FETCH_SITE' => 'same-site' ] ), true );
check( 'none (typed URL) accepted', req( [ 'HTTP_SEC_FETCH_SITE' => 'none' ] ), true );
check( 'cross-site REJECTED',   req( [ 'HTTP_SEC_FETCH_SITE' => 'cross-site' ] ), false );
check( 'cross-site wins over friendly Origin',
	req( [ 'HTTP_SEC_FETCH_SITE' => 'cross-site', 'HTTP_ORIGIN' => 'https://new.satoshiskleinanzeigen.space' ] ), false );
check( 'unknown value REJECTED', req( [ 'HTTP_SEC_FETCH_SITE' => 'whatever' ] ), false );
check( 'case insensitive',      req( [ 'HTTP_SEC_FETCH_SITE' => 'Same-Origin' ] ), true );

// --- Origin fallback (older browsers) ---------------------------------------
check( 'own Origin accepted',
	req( [ 'HTTP_ORIGIN' => 'https://new.satoshiskleinanzeigen.space' ] ), true );
check( 'foreign Origin REJECTED',
	req( [ 'HTTP_ORIGIN' => 'https://evil.tld' ] ), false );
check( 'lookalike domain REJECTED',
	req( [ 'HTTP_ORIGIN' => 'https://new.satoshiskleinanzeigen.space.evil.tld' ] ), false );
check( 'subdomain of our host REJECTED by Origin check',
	req( [ 'HTTP_ORIGIN' => 'https://evil.new.satoshiskleinanzeigen.space' ] ), false );
check( 'Origin with port on our host accepted',
	req( [ 'HTTP_ORIGIN' => 'https://new.satoshiskleinanzeigen.space:443' ] ), true );

// --- Referer fallback -------------------------------------------------------
check( 'own Referer accepted',
	req( [ 'HTTP_REFERER' => 'https://new.satoshiskleinanzeigen.space/dashboard/' ] ), true );
check( 'foreign Referer REJECTED',
	req( [ 'HTTP_REFERER' => 'https://evil.tld/attack.html' ] ), false );
check( 'Origin takes precedence over Referer',
	req( [ 'HTTP_ORIGIN' => 'https://evil.tld', 'HTTP_REFERER' => 'https://new.satoshiskleinanzeigen.space/' ] ), false );

// --- No origin information at all -------------------------------------------
check( 'no headers at all: allowed (non-browser client)', req( [] ), true );
check( 'unparsable Origin falls through to allow', req( [ 'HTTP_ORIGIN' => 'null' ] ), true );

// --- sk_account_has_password ------------------------------------------------
$GLOBALS['meta'] = [
	1 => [],                                                        // plain account, no key login
	2 => [ 'nostr_public_key' => str_repeat( 'a', 64 ) ],           // Nostr only
	3 => [ 'lnurl-auth-bjm-id' => str_repeat( 'b', 66 ) ],          // LNURL only
	4 => [ 'nostr_public_key' => str_repeat( 'a', 64 ), 'sk_password_set' => 1 ], // Nostr, set a password later
	5 => [ 'btc_address' => 'bc1qexample' ],                        // address + password at signup
];

check( 'password-only account must confirm',        sk_account_has_password( 1 ), true );
check( 'Nostr-only account cannot confirm',         sk_account_has_password( 2 ), false );
check( 'LNURL-only account cannot confirm',         sk_account_has_password( 3 ), false );
check( 'Nostr account that set a password must confirm', sk_account_has_password( 4 ), true );
check( 'BtcLogin account must confirm',             sk_account_has_password( 5 ), true );
check( 'user id 0 has no password',                 sk_account_has_password( 0 ), false );

printf( "\n%s\n", $fails ? "{$fails} FAILURE(S)" : 'all checks passed' );
exit( $fails ? 1 : 0 );
