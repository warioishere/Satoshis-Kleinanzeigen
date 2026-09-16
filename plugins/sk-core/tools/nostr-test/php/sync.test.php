<?php
/**
 * NostrRelaySync::filters() and handle(): the profile update and the zap
 * receipt through the one door the cron and the resident worker share.
 * Then the worker itself, as a subprocess against the mock relay.
 *
 * Needs the mock relay running (see run.sh). SK_TEST_GENERATED is a user
 * with a key this site holds; its metas are restored at the end.
 */
require __DIR__ . '/bootstrap.php';

use SK\Core\Nostr\Events;
use SK\Core\Nostr\Keys;
use SK\Modules\Auth\NostrRelaySync;
use SK\Modules\Zaps\ZapStats;

$mock      = getenv( 'MOCK_RELAY' ) ?: 'ws://127.0.0.1:18080';
$generated = (int) ( getenv( 'SK_TEST_GENERATED' ) ?: 610 );
$priv      = Keys::nsec_to_hex( (string) \SK\Modules\Auth\NostrIdentity::get_private_key( $generated ) );
$pub       = Keys::pubkey_of( $priv );

sk_check( '' !== $priv && $pub === \SK\Core\Trust\VendorKey::bound( $generated ), 'test user has a held, bound key' );

// Nothing leaves the machine: NIP-05 checks and image sideloads are refused.
add_filter( 'pre_http_request', fn() => new WP_Error( 'test', 'offline' ) );

$saved = [];
foreach ( [ 'description', 'sk_nostr_profile_at', 'nip05', 'nip05_checked_at', 'nip05_verified', 'sk_profile_settings', 'sk_store_name', \SK\Core\Nostr\Handle::META, ZapStats::SATS_META, ZapStats::COUNT_META, ZapStats::TIME_META ] as $k ) {
    $saved[ $k ] = get_user_meta( $generated, $k, true );
}
$saved_display = (string) get_userdata( $generated )->display_name;
$saved_sync    = get_option( NostrRelaySync::LAST_SYNC_KEY );
$saved_zapper  = get_transient( 'sk_zap_zapper_' . $generated );

// ── filters() ─────────────────────────────────────────────────────────────
$f = NostrRelaySync::filters( 1700000000 );
sk_check_eq( [ $f[0]['kinds'], $f[0]['since'], $f[1]['kinds'], $f[1]['since'] ], [ [ 0 ], 1700000000, [ 9735 ], 1700000000 ], 'filters(): kind 0 by author and 9735 by #p, both from since' );
sk_check( in_array( $pub, $f[0]['authors'], true ) && in_array( $pub, $f[1]['#p'], true ), 'filters(): the test user is asked for on both' );

// ── handle(): kind 0 ──────────────────────────────────────────────────────
$now    = time();
$about  = 'sync-test ' . $now;
$event  = sk_test_sign( $priv, 0, [], json_encode( [ 'about' => $about ] ), $now );
$forged = $event; $forged['content'] = json_encode( [ 'about' => 'forged' ] ); $forged['id'] = Events::id( $forged );

sk_check_eq( NostrRelaySync::handle( $forged ), false, 'handle(): forged profile refused' );
sk_check_eq( get_user_meta( $generated, 'description', true ) === 'forged', false, 'handle(): forged profile changed nothing' );
sk_check_eq( NostrRelaySync::handle( $event ), true, 'handle(): signed profile taken' );
sk_check_eq( get_user_meta( $generated, 'description', true ), $about, 'handle(): about landed in the bio' );
sk_check_eq( (int) get_user_meta( $generated, 'sk_nostr_profile_at', true ), $now, 'handle(): profile time remembered' );
sk_check_eq( NostrRelaySync::handle( $event ), false, 'handle(): the same event a second time is not taken' );
$stranger = sk_test_sign( sk_test_keypair()['priv'], 0, [], json_encode( [ 'about' => 'x' ] ), $now + 1 );
sk_check_eq( NostrRelaySync::handle( $stranger ), false, 'handle(): a profile of nobody here is dropped' );

// ── handle(): kind 9735 ───────────────────────────────────────────────────
$zapper  = sk_test_keypair();
$request = sk_test_sign( sk_test_keypair()['priv'], 9734, [ [ 'p', $pub ], [ 'amount', '21000' ] ], '', $now );
$receipt = sk_test_sign( $zapper['priv'], 9735, [ [ 'p', $pub ], [ 'description', json_encode( $request ) ] ], '', $now + 2 );
$sats0   = (int) get_user_meta( $generated, ZapStats::SATS_META, true );

// A receipt from a key that is not the vendor's zap service, before that
// service is known here at all (the address cannot be resolved offline).
$forged_receipt = sk_test_sign( sk_test_keypair()['priv'], 9735, [ [ 'p', $pub ], [ 'description', json_encode( $request ) ] ], '', $now + 2 );
sk_check_eq( NostrRelaySync::handle( $forged_receipt ), false, 'handle(): a receipt from an unknown signer is refused' );
sk_check_eq( NostrRelaySync::handle( $receipt ), false, 'handle(): no receipt counts while the zap service key is unknown' );
sk_check_eq( (int) get_user_meta( $generated, ZapStats::SATS_META, true ), $sats0, 'handle(): nothing counted so far' );

// The vendor's Lightning address names $zapper as its zap service (memory only).
add_filter( 'pre_transient_sk_zap_zapper_' . $generated, fn() => $zapper['pub'] );
sk_check_eq( NostrRelaySync::handle( $forged_receipt ), false, 'handle(): a receipt signed by another key is still refused' );
sk_check_eq( NostrRelaySync::handle( $receipt ), true, 'handle(): zap receipt from the zap service naming the test user is taken' );
sk_check_eq( (int) get_user_meta( $generated, ZapStats::SATS_META, true ), $sats0 + 21, 'handle(): 21 sats counted for the zapped user' );
sk_check_eq( NostrRelaySync::handle( $receipt ), false, 'handle(): the same receipt is not counted twice' );
$elsewhere = sk_test_sign( $priv, 9735, [ [ 'p', str_repeat( 'a', 64 ) ], [ 'description', json_encode( $request ) ] ], '', $now + 3 );
sk_check_eq( NostrRelaySync::handle( $elsewhere ), false, 'handle(): a receipt to a stranger is dropped although our key signed it' );
sk_check_eq( NostrRelaySync::handle( sk_test_sign( $priv, 1, [], 'note', $now ) ), false, 'handle(): kind 1 is not handled' );

// ── run(): the cron through the same door ─────────────────────────────────
$later = sk_test_sign( $priv, 0, [], json_encode( [ 'about' => $about . ' cron' ] ), $now + 10 );
sk_test_relay_events( [ $later, $forged ] );
NostrRelaySync::run();
sk_check_eq( get_user_meta( $generated, 'description', true ), $about . ' cron', 'run(): fetches raw, handle() verifies and applies' );

// ── the worker binary against the mock ────────────────────────────────────
$php  = PHP_BINARY;
$tool = dirname( __DIR__, 2 ) . '/nostr-worker.php';
$proc = proc_open( [ $php, $tool, '--relay=' . $mock, '--lifetime=16' ], [ 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes );
sk_check( is_resource( $proc ), 'worker: started' );
usleep( 2500000 ); // boot + dial

$live = sk_test_sign( $priv, 0, [], json_encode( [ 'about' => $about . ' worker' ] ), $now + 20 );
sk_check_eq( \SK\Core\Nostr\Relays::publish( $live, [ $mock ] )['accepted'], [ $mock ], 'worker: profile published to the mock while it listens' );
$t0 = microtime( true );
while ( microtime( true ) - $t0 < 5 && get_user_meta( $generated, 'description', true ) !== $about . ' worker' ) {
    usleep( 100000 );
    clean_user_cache( $generated );
}
sk_check_eq( get_user_meta( $generated, 'description', true ), $about . ' worker', 'worker: the pushed profile was applied without a cron run' );
sk_check( microtime( true ) - $t0 < 3, 'worker: applied in under 3 s', sprintf( '%.2fs', microtime( true ) - $t0 ) );

// A profile older than the one just applied (but inside the subscription's
// since window) is read and dropped: it loads this user's meta into the
// worker's process cache without writing anything that would clear it
// again. A change made elsewhere afterwards must survive the next profile
// the worker applies: a name change goes through sk_set_store_name(),
// which reads the settings array and writes it back whole.
$prime = sk_test_sign( $priv, 0, [], json_encode( [ 'about' => 'old' ] ), $now + 15 );
\SK\Core\Nostr\Relays::publish( $prime, [ $mock ] );
$t0 = microtime( true );
while ( microtime( true ) - $t0 < 3 && ! get_transient( 'sk_nsync_' . substr( $prime['id'], 0, 16 ) ) ) {
    usleep( 100000 );
    wp_cache_flush_runtime();
}
sk_check( (bool) get_transient( 'sk_nsync_' . substr( $prime['id'], 0, 16 ) ), 'worker: the older profile was read' );
usleep( 300000 );

$settings                   = get_user_meta( $generated, 'sk_profile_settings', true );
$settings                   = is_array( $settings ) ? $settings : [];
$settings['sk_test_marker'] = 'saved in the browser';
update_user_meta( $generated, 'sk_profile_settings', $settings );

$renamed = sk_test_sign( $priv, 0, [], json_encode( [ 'name' => 'Sync ' . $now ] ), $now + 30 );
\SK\Core\Nostr\Relays::publish( $renamed, [ $mock ] );
$t0 = microtime( true );
while ( microtime( true ) - $t0 < 5 && ( get_user_meta( $generated, 'sk_profile_settings', true )['store_name'] ?? '' ) !== 'Sync ' . $now ) {
    usleep( 100000 );
    clean_user_cache( $generated );
}
$after = get_user_meta( $generated, 'sk_profile_settings', true );
sk_check_eq( $after['store_name'] ?? '', 'Sync ' . $now, 'worker: the second profile renamed the store' );
sk_check_eq( $after['sk_test_marker'] ?? '', 'saved in the browser', 'worker: a setting changed by another process meanwhile is kept' );

$out = stream_get_contents( $pipes[1] );
$err = stream_get_contents( $pipes[2] );
proc_close( $proc );
sk_check( str_contains( $out, 'subscriptions ' ) && str_contains( $out, 'connected' ) && str_contains( $out, 'lifetime over' ), 'worker: log shows subscribe, connect and self-termination', $out );
sk_check( '' === trim( preg_replace( '/.*WP_CACHE_KEY_SALT.*\n?/', '', $err ) ), 'worker: nothing on stderr', $err );

// ── restore ───────────────────────────────────────────────────────────────
foreach ( $saved as $k => $v ) {
    if ( '' === $v || null === $v ) {
        delete_user_meta( $generated, $k );
    } else {
        update_user_meta( $generated, $k, $v );
    }
}
delete_user_meta( $generated, '_sk_zap_seen_' . strtolower( $receipt['id'] ) );
wp_update_user( [ 'ID' => $generated, 'display_name' => $saved_display ] );
remove_all_filters( 'pre_transient_sk_zap_zapper_' . $generated );
if ( false === $saved_zapper ) {
    delete_transient( 'sk_zap_zapper_' . $generated );
} else {
    set_transient( 'sk_zap_zapper_' . $generated, $saved_zapper, DAY_IN_SECONDS );
}
if ( false === $saved_sync ) {
    delete_option( NostrRelaySync::LAST_SYNC_KEY );
} else {
    update_option( NostrRelaySync::LAST_SYNC_KEY, $saved_sync );
}
foreach ( [ $event, $forged, $receipt, $later, $live, $prime, $renamed ] as $e ) {
    delete_transient( 'sk_nsync_' . substr( $e['id'], 0, 16 ) );
}
sk_check_eq( get_user_meta( $generated, 'description', true ), $saved['description'], 'restore: bio as before' );
sk_check_eq( (int) get_user_meta( $generated, ZapStats::SATS_META, true ), $sats0, 'restore: zap total as before' );

sk_test_done();
