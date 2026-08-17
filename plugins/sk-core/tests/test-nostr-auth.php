<?php
/**
 * End-to-end test of UAC_Nostr_Login_Integration::verify_nostr_identity() using
 * real Schnorr-signed NIP-98 events produced with the bundled nostr-php lib.
 */

require __DIR__ . '/bootstrap.php';

define( 'ABSPATH', '/tmp/' );

require SK_TEST_PLUGIN . '/lib/autoload.php';

// --- Minimal WordPress stubs ------------------------------------------------
class WP_Error {
	public $code;
	public $message;
	public function __construct( $code = '', $message = '' ) { $this->code = $code; $this->message = $message; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function admin_url( $path = '' ) { return 'https://staging.satoshiskleinanzeigen.space/wp-admin/' . $path; }
function add_action( ...$a ) {}
function add_filter( ...$a ) {}

function sanitize_text_field( $v ) { return is_string( $v ) ? trim( strip_tags( $v ) ) : ''; }
function wp_unslash( $v ) { return is_string( $v ) ? stripslashes( $v ) : $v; }

$GLOBALS['logins'] = [];
$GLOBALS['user_meta'] = [];
function get_user_meta( $id, $key, $single = false ) { return $GLOBALS['user_meta'][ $id ][ $key ] ?? ''; }
function get_user_by( $field, $value ) { $u = new stdClass(); $u->ID = (int) $value; $u->user_login = 'u' . $value; return $u; }
function get_userdata( $id ) { return get_user_by( 'ID', $id ); }
function wp_set_current_user( $id ) { $GLOBALS['logins'][] = (int) $id; }
function wp_set_auth_cookie( $id, $remember = false ) { $GLOBALS['logins'][] = (int) $id; throw new RuntimeException( 'logged-in:' . $id ); }
function wp_verify_nonce( $n, $a ) { return $n === 'valid-public-nonce'; }
function wp_send_json_success( $d = null ) { throw new RuntimeException( 'json-success' ); }
function get_option( $k, $default = false ) { return $default; }
function home_url( $p = '' ) { return 'https://staging.satoshiskleinanzeigen.space' . $p; }
function get_edit_profile_url( $id ) { return 'https://staging.satoshiskleinanzeigen.space/profile'; }

$GLOBALS['transients'] = [];
function get_transient( $k ) { return $GLOBALS['transients'][ $k ] ?? false; }
function set_transient( $k, $v, $ttl = 0 ) { $GLOBALS['transients'][ $k ] = $v; return true; }

require SK_TEST_PLUGIN . '/modules/sk-auth/includes/Connector/NostrIntegration.php';

$fails = 0;
function check( $label, $actual, $expected ) {
	global $fails;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fails++; }
	printf( "%-6s %-50s got=%-24s expected=%s\n", $ok ? 'PASS' : 'FAIL', $label,
		var_export( $actual, true ), var_export( $expected, true ) );
}

/** Result code: 'ok' or the WP_Error code. */
function attempt( array $overrides = [] ): string {
	static $integration = null;
	if ( $integration === null ) {
		$rc = new ReflectionClass( 'UAC_Nostr_Login_Integration' );
		$integration = $rc->newInstanceWithoutConstructor();
	}

	$token  = build_token( $overrides );
	$result = $integration->verify_nostr_identity( $token );

	return is_wp_error( $result ) ? $result->get_error_code() : 'ok';
}

/** Build a base64 NIP-98 token, signed for real. */
function build_token( array $overrides = [] ): string {
	$keyService = new \swentel\nostr\Key\Key();
	$privkey    = $overrides['privkey'] ?? $GLOBALS['test_privkey'];
	$pubkey     = $keyService->getPublicKey( $privkey );

	$event = new \swentel\nostr\Event\Event();
	$event->setKind( $overrides['kind'] ?? 27235 );
	$event->setContent( '' );
	$event->setCreatedAt( $overrides['created_at'] ?? time() );
	$event->addTag( [ 'u', $overrides['u'] ?? admin_url( 'admin-ajax.php' ) ] );
	$event->addTag( [ 'method', $overrides['method'] ?? 'POST' ] );
	// Nostr event ids are deterministic, so make each test token a distinct
	// event — otherwise two tokens built in the same second collide and the
	// replay guard (correctly) rejects the second one.
	$event->addTag( [ 'nonce', $overrides['nonce'] ?? bin2hex( random_bytes( 8 ) ) ] );
	$event->setPublicKey( $pubkey );

	( new \swentel\nostr\Sign\Sign() )->signEvent( $event, $privkey );

	$json = json_decode( $event->toJson(), true );

	if ( array_key_exists( 'tamper_sig', $overrides ) ) {
		$json['sig'] = str_repeat( '0', 128 );
	}

	return base64_encode( wp_json_encode_compat( $json ) );
}

function wp_json_encode_compat( $data ) {
	return json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
}

$keyService = new \swentel\nostr\Key\Key();
$GLOBALS['test_privkey'] = $keyService->generatePrivateKey();

// --- Happy path -------------------------------------------------------------
check( 'valid freshly signed token accepted', attempt(), 'ok' );

// --- Replay: the exact same event must not work twice ------------------------
$token = build_token();
$rc = new ReflectionClass( 'UAC_Nostr_Login_Integration' );
$integration = $rc->newInstanceWithoutConstructor();
$first  = $integration->verify_nostr_identity( $token );
$second = $integration->verify_nostr_identity( $token );
check( 'first use of a token accepted', is_wp_error( $first ) ? $first->get_error_code() : 'ok', 'ok' );
check( 'replay of same token rejected', is_wp_error( $second ) ? $second->get_error_code() : 'ok', 'token_reused' );
check( 'returned pubkey is lowercase hex',
	(bool) preg_match( '/^[0-9a-f]{64}$/', is_wp_error( $first ) ? '' : ( $first['pubkey'] ?? '' ) ), true );

// --- Timestamp window -------------------------------------------------------
check( 'expired token (5 min old) rejected', attempt( [ 'created_at' => time() - 300 ] ), 'expired_token' );
check( 'far-future token rejected', attempt( [ 'created_at' => time() + 315360000 ] ), 'expired_token' );
check( 'slight clock skew (30s ahead) accepted', attempt( [ 'created_at' => time() + 30 ] ), 'ok' );

// --- Endpoint binding: a token signed for another service must not work -----
check( 'token for another site rejected', attempt( [ 'u' => 'https://someothersite.tld/api' ] ), 'invalid_url' );
check( 'token without u tag rejected', attempt( [ 'u' => '' ] ), 'invalid_url' );
check( 'http/https normalisation still matches',
	attempt( [ 'u' => 'http://staging.satoshiskleinanzeigen.space/wp-admin/admin-ajax.php' ] ), 'ok' );
check( 'wrong method rejected', attempt( [ 'method' => 'GET' ] ), 'invalid_method' );

// --- Event integrity --------------------------------------------------------
check( 'wrong kind rejected', attempt( [ 'kind' => 1 ] ), 'invalid_kind' );
check( 'forged signature rejected', attempt( [ 'tamper_sig' => true ] ), 'invalid_signature' );
check( 'garbage token rejected', ( function () {
	$rc = new ReflectionClass( 'UAC_Nostr_Login_Integration' );
	$i  = $rc->newInstanceWithoutConstructor();
	$r  = $i->verify_nostr_identity( base64_encode( '{"not":"an event"}' ) );
	return is_wp_error( $r ) ? $r->get_error_code() : 'ok';
} )(), 'invalid_signature' );
check( 'empty token rejected', ( function () {
	$rc = new ReflectionClass( 'UAC_Nostr_Login_Integration' );
	$i  = $rc->newInstanceWithoutConstructor();
	$r  = $i->verify_nostr_identity( '' );
	return is_wp_error( $r ) ? $r->get_error_code() : 'ok';
} )(), 'invalid_authtoken' );

// --- A different key yields a different identity, never a shared one --------
$other = $keyService->generatePrivateKey();
$rc2 = new ReflectionClass( 'UAC_Nostr_Login_Integration' );
$i2  = $rc2->newInstanceWithoutConstructor();
$a = $i2->verify_nostr_identity( build_token() );
$b = $i2->verify_nostr_identity( build_token( [ 'privkey' => $other ] ) );
$a_key = is_wp_error( $a ) ? 'x' : ( $a['pubkey'] ?? 'x' );
$b_key = is_wp_error( $b ) ? 'y' : ( $b['pubkey'] ?? 'y' );
check( 'both distinct keys verify', ! is_wp_error( $a ) && ! is_wp_error( $b ), true );
check( 'distinct keys give distinct pubkeys', $a_key !== $b_key, true );

// --- Regression: the login path must not select accounts by claimed email ---
$login_src = file_get_contents(
	SK_TEST_PLUGIN . '/modules/sk-auth/includes/NostrLogin.php'
);
check( 'login no longer looks up users by submitted email',
	strpos( $login_src, "get_user_by( 'email'" ), false );
check( 'login no longer reads email from metadata',
	strpos( $login_src, "sanitized_metadata['email']" ), false );


// --- maybe_handle_linked_login(): the nopriv interceptor --------------------
//
// Scenario: a victim registered via LNURL and later linked their Nostr key, so
// the UAC mapping knows the key but the nostr_public_key meta does not match.
// That is exactly the branch this handler exists for.

class FakeLinker {
	public $map = [];
	public function get_user_by_nostr( $pubkey ) { return $this->map[ strtolower( $pubkey ) ] ?? false; }
}

const VICTIM_ID = 4242;

function run_interceptor( string $authtoken, string $nonce, string $victim_pubkey ): string {
	$GLOBALS['logins']    = [];
	$GLOBALS['user_meta'] = [ VICTIM_ID => [ 'nostr_public_key' => 'ff' . str_repeat( '0', 62 ) ] ];

	$linker = new FakeLinker();
	$linker->map[ strtolower( $victim_pubkey ) ] = VICTIM_ID;

	$rc = new ReflectionClass( 'UAC_Nostr_Login_Integration' );
	$i  = $rc->newInstanceWithoutConstructor();
	$prop = $rc->getProperty( 'account_linker' );
	$prop->setAccessible( true );
	$prop->setValue( $i, $linker );

	$_POST = [ 'authtoken' => $authtoken, 'nonce' => $nonce ];

	try {
		$i->maybe_handle_linked_login();
	} catch ( RuntimeException $e ) {
		return 'logged-in';
	}

	return empty( $GLOBALS['logins'] ) ? 'no-login' : 'logged-in';
}

// The victim's public key — published on relays, not a secret.
$victim_priv   = $keyService->generatePrivateKey();
$victim_pubkey = $keyService->getPublicKey( $victim_priv );

// THE ATTACK: unsigned JSON that merely names the victim's public key.
$forged = base64_encode( wp_json_encode_compat( [ 'pubkey' => $victim_pubkey ] ) );
check( 'unsigned token naming victim pubkey does NOT log in',
	run_interceptor( $forged, 'valid-public-nonce', $victim_pubkey ), 'no-login' );

// Same, but shaped like a full event with a bogus signature.
$forged_event = base64_encode( wp_json_encode_compat( [
	'id' => str_repeat( 'a', 64 ), 'pubkey' => $victim_pubkey, 'created_at' => time(),
	'kind' => 27235, 'tags' => [ [ 'u', admin_url( 'admin-ajax.php' ) ], [ 'method', 'POST' ] ],
	'content' => '', 'sig' => str_repeat( '0', 128 ),
] ) );
check( 'forged event with fake signature does NOT log in',
	run_interceptor( $forged_event, 'valid-public-nonce', $victim_pubkey ), 'no-login' );

// A token signed by a DIFFERENT key must not reach the victim's account.
$attacker_priv = $keyService->generatePrivateKey();
$GLOBALS['test_privkey'] = $attacker_priv;
check( 'validly signed token from another key does NOT log in',
	run_interceptor( build_token(), 'valid-public-nonce', $victim_pubkey ), 'no-login' );

// The legitimate case must still work: the victim signs with their own key.
$GLOBALS['test_privkey'] = $victim_priv;
check( 'victim signing with their own key logs in',
	run_interceptor( build_token(), 'valid-public-nonce', $victim_pubkey ), 'logged-in' );

// Signature valid but nonce wrong -> still no login.
check( 'valid signature with bad nonce does NOT log in',
	run_interceptor( build_token(), 'wrong-nonce', $victim_pubkey ), 'no-login' );

printf( "\n%s\n", $fails ? "{$fails} FAILURE(S)" : 'all checks passed' );
exit( $fails ? 1 : 0 );
