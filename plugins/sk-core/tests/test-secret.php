<?php
/**
 * Secret:: AES-256-GCM encryption, legacy CBC compatibility, tamper detection.
 */

require __DIR__ . '/bootstrap.php';

define( 'ABSPATH', '/tmp/' );

$GLOBALS['sk_test_salt'] = 'k9Xq2!vLm4Zt7Rw0PbNc8FhJ1sYd6EuA3gTiOa5MnQrVzW+lKpB/eS-XyCfDhGjU';
function wp_salt( $scheme = 'auth' ) { return $GLOBALS['sk_test_salt']; }

require SK_TEST_PLUGIN . '/modules/sk-payments/includes/Secret.php';

use SK\Modules\Payments\Secret;

$fails = 0;
function check( $label, $actual, $expected ) {
	global $fails;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fails++; }
	$fmt = function ( $v ) { return is_string( $v ) && strlen( $v ) > 30 ? substr( $v, 0, 27 ) . '...' : var_export( $v, true ); };
	printf( "%-6s %-50s got=%-32s expected=%s\n", $ok ? 'PASS' : 'FAIL', $label, $fmt( $actual ), $fmt( $expected ) );
}

/** Encrypt the way the plugin did before this change. */
function legacy_encrypt( string $plaintext ): string {
	$iv = random_bytes( 16 );
	return base64_encode( $iv . openssl_encrypt( $plaintext, 'aes-256-cbc', wp_salt( 'auth' ), OPENSSL_RAW_DATA, $iv ) );
}

$nwc    = 'nostr+walletconnect://b889ff5b1513b641e2a139f661a661364979c5beee91842f8f0ef42ab558e9d4?relay=wss%3A%2F%2Frelay.example%2F&secret=71a8c14c1407c113601079c4302dab36460f0ccd0ad506f1f2dc73b5100e4f3c';
$lndhub = 'lndhub://admin:supersecretpassword@https://lndhub.example.tld';
$xpub   = 'zpub6rFR7y4Q2AijBEqTUquhVz398htDFrtymD9xYYfG1m4wAcvPhXNfE3EfH1r1ADqtfSdVCToUG868RvUUkgDKf31mGDtKsAYz2oz2AGutZYs';

// --- Round trip -------------------------------------------------------------
foreach ( [ 'NWC' => $nwc, 'LNDHub' => $lndhub, 'xpub' => $xpub ] as $name => $secret ) {
	check( "round trip {$name}", Secret::decrypt( Secret::encrypt( $secret ) ), $secret );
}

check( 'round trip unicode', Secret::decrypt( Secret::encrypt( 'pässwörd-мир-🔐' ) ), 'pässwörd-мир-🔐' );
check( 'empty stays empty', Secret::encrypt( '' ), '' );
check( 'decrypt empty', Secret::decrypt( '' ), '' );

// --- Format -----------------------------------------------------------------
$cipher = Secret::encrypt( $nwc );
check( 'has v2 prefix', strpos( $cipher, 'skv2:' ) === 0, true );
check( 'plaintext not in ciphertext', strpos( $cipher, 'secret=' ) === false, true );
check( 'nonce is random (two encryptions differ)', Secret::encrypt( $nwc ) !== Secret::encrypt( $nwc ), true );

// --- Tamper detection (the whole point of GCM) ------------------------------
$raw = base64_decode( substr( $cipher, 5 ) );
$flipped = $raw;
$flipped[40] = chr( ord( $flipped[40] ) ^ 0x01 );   // flip a ciphertext bit
check( 'flipped ciphertext byte rejected',
	Secret::decrypt( 'skv2:' . base64_encode( $flipped ) ), '' );

$bad_tag = $raw;
$bad_tag[12] = chr( ord( $bad_tag[12] ) ^ 0xff );   // corrupt the auth tag
check( 'corrupted auth tag rejected',
	Secret::decrypt( 'skv2:' . base64_encode( $bad_tag ) ), '' );

$bad_iv = $raw;
$bad_iv[0] = chr( ord( $bad_iv[0] ) ^ 0xff );       // corrupt the nonce
check( 'corrupted nonce rejected',
	Secret::decrypt( 'skv2:' . base64_encode( $bad_iv ) ), '' );

check( 'truncated payload rejected', Secret::decrypt( 'skv2:' . base64_encode( substr( $raw, 0, 20 ) ) ), '' );
check( 'garbage base64 rejected', Secret::decrypt( 'skv2:!!!not base64!!!' ), '' );
check( 'garbage without prefix rejected', Secret::decrypt( 'total nonsense' ), '' );

// --- Legacy CBC values still readable, and flagged for upgrade --------------
$legacy = legacy_encrypt( $nwc );
check( 'legacy CBC value decrypts', Secret::decrypt( $legacy ), $nwc );
check( 'legacy flagged for upgrade', Secret::needs_upgrade( $legacy ), true );
check( 'v2 value not flagged', Secret::needs_upgrade( $cipher ), false );
check( 'empty not flagged', Secret::needs_upgrade( '' ), false );
check( 'legacy re-encrypts to v2',
	Secret::decrypt( Secret::encrypt( Secret::decrypt( $legacy ) ) ), $nwc );

// --- Key binding: another site's salt must not decrypt our data -------------
$GLOBALS['sk_test_salt'] = 'a-completely-different-salt-value-0123456789abcdefghijklmnopqrstuv';
check( 'v2 unreadable under other salt', Secret::decrypt( $cipher ), '' );
check( 'legacy unreadable under other salt', Secret::decrypt( $legacy ), '' );

printf( "\n%s\n", $fails ? "{$fails} FAILURE(S)" : 'all checks passed' );
exit( $fails ? 1 : 0 );
