<?php
/**
 * Reports cron: a flood of junk does not push a real report out; a later
 * run without it keeps it; reporters are web of trust, vendor, or dropped.
 *
 * Needs the mock relay running. SK_TEST_VENDOR is a vendor with a proven
 * key (default 573 on staging), SK_TEST_GENERATED a user whose key this
 * site holds (default 610). The vendor's stored reports and the run option
 * are saved before and restored after.
 */
require __DIR__ . '/bootstrap.php';

use SK\Modules\Reputation\Reports;
use SK\Core\Trust\VendorKey;

$vendor_id = (int) ( getenv( 'SK_TEST_VENDOR' ) ?: 573 );
$generated = (int) ( getenv( 'SK_TEST_GENERATED' ) ?: 610 );
$target    = VendorKey::bound( $vendor_id );

// One fresh key is the whole web of trust for this run.
$wot      = sk_test_keypair();
$wot_priv = $wot['priv'];
sk_test_wot( $wot['pub'] );

sk_check( '' !== $target, "vendor {$vendor_id} has a proven key" );

if ( '' === $target ) {
    sk_test_done();
}

$saved_run = get_option( Reports::RUN_OPTION );
$saved_rep = get_user_meta( $vendor_id, Reports::REPORTS_META, true );
delete_option( Reports::RUN_OPTION );
delete_user_meta( $vendor_id, Reports::REPORTS_META );

$restore = function () use ( $vendor_id, $saved_run, $saved_rep ) {
    delete_user_meta( $vendor_id, Reports::REPORTS_META );
    delete_user_meta( $vendor_id, Reports::TIME_META );
    if ( $saved_rep ) {
        update_user_meta( $vendor_id, Reports::REPORTS_META, $saved_rep );
    }
    if ( $saved_run ) {
        update_option( Reports::RUN_OPTION, $saved_run, false );
    } else {
        delete_option( Reports::RUN_OPTION );
    }
};

try {
    $now  = time();
    $real = sk_test_sign( $wot_priv, 1984, [ [ 'p', $target, 'spam' ] ], 'real report from the web of trust', $now - 3600 );

    // ── Flood: 700 newer junk reports, real one behind them ───────────────
    $junk = [];
    for ( $i = 0; $i < 700; $i++ ) {
        $junk[] = [
            'id'         => bin2hex( random_bytes( 32 ) ),
            'pubkey'     => bin2hex( random_bytes( 32 ) ),
            'kind'       => 1984,
            'created_at' => $now - 60 + intdiv( $i, 20 ),
            'tags'       => [ [ 'p', $target, 'spam' ] ],
            'content'    => 'junk ' . $i,
            'sig'        => bin2hex( random_bytes( 64 ) ),
        ];
    }

    sk_test_relay_events( array_merge( [ $real ], $junk ) );
    Reports::fetch();
    $run = get_option( Reports::RUN_OPTION );
    sk_check( ( $run['pages'] ?? 0 ) >= 2, 'run A: a full page led to a second page', 'pages=' . ( $run['pages'] ?? 0 ) );
    sk_check_eq( $run['new'] ?? null, 1, 'run A: exactly one new report' );
    sk_check_eq( in_array( $real['id'], array_column( Reports::for_vendor( $vendor_id ), 'id' ), true ), true, 'run A: the real report behind 700 junk ones was found' );

    // ── Second run, real report gone from the relay ───────────────────────
    sk_test_relay_events( $junk );
    Reports::fetch();
    $run = get_option( Reports::RUN_OPTION );
    sk_check_eq( $run['new'] ?? null, 0, 'run B: nothing new' );
    sk_check( ( $run['since'] ?? 0 ) > $now - 3 * DAY_IN_SECONDS, 'run B: incremental window (since = last run minus margin)' );
    sk_check_eq( in_array( $real['id'], array_column( Reports::for_vendor( $vendor_id ), 'id' ), true ), true, 'run B: the stored report survives its absence from the relay' );

    // ── Reporter sources: wot, vendor, nobody ─────────────────────────────
    delete_user_meta( $vendor_id, Reports::REPORTS_META );
    delete_option( Reports::RUN_OPTION );

    // The daily mail throttle may be set from a real run; lift it for this
    // run and put it back afterwards (notify_admin sets it again anyway).
    $had_throttle = (bool) get_transient( Reports::MAIL_THROTTLE );
    delete_transient( Reports::MAIL_THROTTLE );
    $vendor_priv = \SK\Modules\Auth\NostrIdentity::get_private_key( $generated );
    sk_check( (bool) $vendor_priv, "user {$generated} has a key this site holds" );

    $events = [ sk_test_sign( $wot_priv, 1984, [ [ 'p', $target, 'spam' ] ], 'wot' ) ];
    if ( $vendor_priv ) {
        $events[] = sk_test_sign( $vendor_priv, 1984, [ [ 'p', $target, 'illegal' ] ], 'vendor' );
    }
    $nobody   = sk_test_keypair();
    $events[] = sk_test_sign( $nobody['priv'], 1984, [ [ 'p', $target, 'spam' ] ], 'nobody' );

    sk_test_relay_events( $events );
    Reports::fetch();
    $stored  = Reports::for_vendor( $vendor_id );
    $sources = array_column( $stored, 'source', 'content' );
    sk_check_eq( $sources['wot'] ?? null, 'wot', 'web-of-trust reporter kept as source=wot' );
    if ( $vendor_priv ) {
        sk_check_eq( $sources['vendor'] ?? null, 'vendor', 'vendor reporter kept as source=vendor' );
        sk_check_eq( array_column( $stored, 'reporter_user', 'content' )['vendor'] ?? null, $generated, 'vendor reporter carries the vendor user id' );
    }
    sk_check_eq( isset( $sources['nobody'] ), false, 'unknown reporter dropped' );

    // ── Mail ──────────────────────────────────────────────────────────────
    $mails = $GLOBALS['sk_test_mail'];
    sk_check_eq( count( $mails ), 1, 'exactly one admin mail composed for the new reports' );
    if ( $mails && $vendor_priv ) {
        sk_check( str_contains( end( $mails )['message'], 'Anbieter #' . $generated ), 'mail names the vendor reporter as Anbieter #ID' );
    }
    if ( ! $had_throttle ) {
        delete_transient( Reports::MAIL_THROTTLE );
    }
} finally {
    $restore();
}

sk_check_eq( count( Reports::for_vendor( $vendor_id ) ), is_array( $saved_rep ) ? count( $saved_rep ) : 0, 'vendor reports restored' );
sk_test_done();
