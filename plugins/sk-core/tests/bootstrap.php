<?php
/**
 * Shared setup for the sk-core security tests.
 *
 * The tests run under plain PHP without WordPress: each one stubs exactly the
 * WordPress functions the unit under test touches, then loads that single file.
 * That keeps them fast (whole suite well under a minute) and makes them usable
 * on any checkout — staging, live or a laptop — with nothing installed.
 *
 * Each test file is standalone and defines its own stubs, because different
 * units need conflicting stubs (e.g. get_user_meta). They only share this
 * bootstrap, which resolves the plugin root.
 */

// Plugin root, derived from this file's location — never a hardcoded path.
define( 'SK_TEST_PLUGIN', dirname( __DIR__ ) );

if ( ! is_file( SK_TEST_PLUGIN . '/sk-core.php' ) ) {
	fwrite( STDERR, "bootstrap: plugin root not found at " . SK_TEST_PLUGIN . "\n" );
	exit( 2 );
}
