<?php
/**
 * Loads WordPress for a CLI test with every outside effect turned off:
 * relays point at the local mock, cron events are not persisted, mail is
 * captured, the web of trust can be set from the test. Provides tiny
 * assertion helpers.
 *
 * Environment: WP_ABSPATH (default: derived), SK_TEST_HOST (default: the
 * staging host), MOCK_RELAY (default ws://127.0.0.1:18080).
 */

if ( PHP_SAPI !== 'cli' ) {
    exit( 1 );
}

$abspath = getenv( 'WP_ABSPATH' ) ?: dirname( __DIR__, 6 );
$abspath = rtrim( $abspath, '/' ) . '/';

if ( ! is_file( $abspath . 'wp-load.php' ) ) {
    fwrite( STDERR, "Cannot find wp-load.php in {$abspath}. Set WP_ABSPATH.\n" );
    exit( 1 );
}

$_SERVER['HTTP_HOST'] = getenv( 'SK_TEST_HOST' ) ?: 'staging.satoshiskleinanzeigen.space';

// Every filter below is memory-only: nothing is written to the options table.
// The relay option is read at call time, so a filter added after load is
// early enough; a test may change $GLOBALS['sk_test_relay'] between calls.
$GLOBALS['sk_test_mail']  = [];
$GLOBALS['sk_test_cron']  = [];
$GLOBALS['sk_test_relay'] = getenv( 'MOCK_RELAY' ) ?: 'ws://127.0.0.1:18080';

require $abspath . 'wp-load.php';

add_filter( 'pre_option_nostr_login_relays', fn() => $GLOBALS['sk_test_relay'] );
add_filter( 'pre_schedule_event', function ( $pre, $event ) {
    $GLOBALS['sk_test_cron'][] = $event;
    return true;
}, 10, 2 );
add_filter( 'pre_wp_mail', function ( $null, $atts ) {
    $GLOBALS['sk_test_mail'][] = $atts;
    return false;
}, 10, 2 );

/** Make one key the whole web of trust for this run (memory only). */
function sk_test_wot( string $pubkey ): void {
    $prefix = substr( strtolower( $pubkey ), 0, 16 );
    add_filter( 'pre_transient_' . \SK\Modules\Reputation\Reports::WOT_KEY, fn() => [ $prefix => 1 ] );
}

// ── Assertions ────────────────────────────────────────────────────────────

$GLOBALS['sk_test_failures'] = 0;

function sk_check( $condition, string $label, string $detail = '' ): void {
    if ( ! $condition ) {
        $GLOBALS['sk_test_failures']++;
    }
    echo ( $condition ? 'PASS ' : 'FAIL ' ) . $label . ( $condition || '' === $detail ? '' : '  (' . $detail . ')' ) . "\n";
}

function sk_check_eq( $actual, $expected, string $label ): void {
    sk_check( $actual === $expected, $label, 'got ' . var_export( $actual, true ) . ', expected ' . var_export( $expected, true ) );
}

function sk_test_done(): void {
    if ( $GLOBALS['sk_test_failures'] ) {
        echo $GLOBALS['sk_test_failures'] . " check(s) failed\n";
        exit( 1 );
    }
    echo "all checks passed\n";
}

/** Call a private/protected static method. */
function sk_call_private( string $class, string $method, ...$args ) {
    $m = new ReflectionMethod( $class, $method );
    $m->setAccessible( true );
    return $m->invoke( null, ...$args );
}

/** A signed event from a hex private key, via the same library the plugin uses. */
function sk_test_sign( string $privkey, int $kind, array $tags, string $content = '', ?int $created_at = null ): array {
    $e = new swentel\nostr\Event\Event();
    $e->setKind( $kind );
    $e->setCreatedAt( $created_at ?? time() );
    $e->setContent( $content );
    $e->setTags( $tags );
    ( new swentel\nostr\Sign\Sign() )->signEvent( $e, $privkey );
    return json_decode( $e->toJson(), true );
}

function sk_test_keypair(): array {
    $key  = new swentel\nostr\Key\Key();
    $priv = $key->generatePrivateKey();
    return [ 'priv' => $priv, 'pub' => $key->getPublicKey( $priv ) ];
}

/** Write the mock relay's events file. */
function sk_test_relay_events( array $events ): void {
    file_put_contents( getenv( 'MOCK_EVENTS' ) ?: dirname( __DIR__ ) . '/events.json', json_encode( $events ) );
}
