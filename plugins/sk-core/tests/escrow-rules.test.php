<?php
/**
 * Rulebook numbers and account logic of the escrow module (Rules, Pool,
 * Actions::outputs), without the API. Loads the module files directly so
 * it runs while the module is switched off. SK_TEST_GENERATED is a user
 * whose incident meta is restored at the end.
 *
 *   php tests/escrow-rules.test.php
 */
require dirname( __DIR__ ) . '/tools/nostr-test/php/bootstrap.php';

defined( 'WEO_OPT' ) || define( 'WEO_OPT', 'weo_options' );
foreach ( [ 'helpers', 'Rows', 'Rules', 'Pool', 'Deadlines', 'Notify', 'Actions' ] as $f ) {
    require_once dirname( __DIR__ ) . '/modules/sk-escrow/includes/' . $f . '.php';
}

use SK\Modules\Escrow\Actions;
use SK\Modules\Escrow\Deadlines;
use SK\Modules\Escrow\Pool;
use SK\Modules\Escrow\Rows;
use SK\Modules\Escrow\Rules;

$user = (int) ( getenv( 'SK_TEST_GENERATED' ) ?: 610 );
$fee_address = 'bc1qtestfeeaddress00000000000000000000000';

add_filter( 'pre_option_' . WEO_OPT, fn() => [ 'fee_address' => $fee_address, 'api_base' => 'http://127.0.0.1:1/api', 'api_key' => 'test' ] );

// The escrow API, answered locally: every build hands out a PSBT, the
// freeze is accepted. What was asked for is kept for the checks below.
$GLOBALS['api_calls'] = [];
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
    if ( strpos( $url, 'http://127.0.0.1:1/api' ) !== 0 ) {
        return $pre;
    }
    $GLOBALS['api_calls'][] = [ 'url' => $url, 'body' => json_decode( (string) ( $args['body'] ?? '' ), true ) ];
    $body = strpos( $url, '/psbt/finalize' ) !== false ? [ 'hex' => '' ] : [ 'psbt' => 'cHNidP8BAFake' ];

    return [ 'response' => [ 'code' => 200, 'message' => 'OK' ], 'headers' => [], 'body' => wp_json_encode( $body ), 'cookies' => [] ];
}, 10, 3 );

// ── Fee (§2) ──────────────────────────────────────────────────────────────
sk_check_eq( Rules::fee_for( 10000 ), 3000, 'fee_for(): the floor applies below 30k' );
sk_check_eq( Rules::fee_for( 100000 ), 10000, 'fee_for(): 10 percent' );
sk_check_eq( Rules::fee_for( 33333 ), 3333, 'fee_for(): rounds down' );
sk_check_eq( Rules::fund_share( 3000 ), 1500, 'fund_share(): half' );

// ── Tiers and incidents (§8, §9) ──────────────────────────────────────────
$saved_incidents = get_user_meta( $user, Rules::INCIDENTS_META, true );
$saved_blocked   = get_user_meta( $user, Rules::BLOCKED_META, true );
delete_user_meta( $user, Rules::INCIDENTS_META );
delete_user_meta( $user, Rules::BLOCKED_META );

$tier0 = Rules::tier( $user );
sk_check( in_array( $tier0, [ 0, 1, 2 ], true ), 'tier(): a tier', (string) $tier0 );
sk_check_eq( Rules::limit( $user ), Rules::LIMITS[ $tier0 ], 'limit(): follows the tier' );
sk_check_eq( Rules::refusal( $user, Rules::LIMITS[ $tier0 ] ), '', 'refusal(): none at the limit' );
sk_check( '' !== Rules::refusal( $user, Rules::LIMITS[ $tier0 ] + 1 ), 'refusal(): over the limit' );

Rules::incident( $user, 'test', str_repeat( 'a', 64 ) );
sk_check_eq( [ Rules::incidents( $user ), Rules::blocked( $user ) ], [ 1, false ], 'incident(): one counted, not blocked' );
Rules::incident( $user, 'test', str_repeat( 'b', 64 ) );
sk_check_eq( [ Rules::incidents( $user ), Rules::tier( $user ) ], [ 2, 0 ], 'two incidents: tier forced to 0' );
Rules::incident( $user, 'weight', str_repeat( 'c', 64 ), 2 );
sk_check_eq( [ Rules::incidents( $user ), Rules::blocked( $user ) ], [ 4, true ], 'a weight-2 incident counts double; three or more block' );
sk_check( str_contains( Rules::refusal( $user, 1000 ), '§9' ), 'refusal(): names §9 when blocked' );

// An incident older than the window no longer counts.
update_user_meta( $user, Rules::INCIDENTS_META, [ [ 'at' => time() - Rules::INCIDENT_WINDOW - DAY_IN_SECONDS, 'why' => 'old', 'hash' => '', 'weight' => 5 ] ] );
sk_check_eq( Rules::incidents( $user ), 0, 'incidents(): outside the window they are gone' );
Rules::incident( $user, 'new', str_repeat( 'd', 64 ) );
sk_check_eq( count( get_user_meta( $user, Rules::INCIDENTS_META, true ) ), 1, 'incident(): drops the aged ones from the list' );

update_user_meta( $user, Rules::BLOCKED_META, '1' );
delete_user_meta( $user, Rules::INCIDENTS_META );
sk_check_eq( Rules::blocked( $user ), true, 'blocked(): set by hand' );

// ── Outputs (§2) ──────────────────────────────────────────────────────────
$row = (object) [
    'amount_sats'  => 100000,
    'payment_hash' => str_repeat( 'e', 64 ),
    'metadata'     => wp_json_encode( [ 'escrow' => [ 'fee_sat' => 10000, 'payout_address' => 'bc1qseller', 'refund_address' => 'bc1qbuyer' ] ] ),
    'context'      => 'escrow',
];
sk_check_eq( Actions::outputs( $row, 'payout' ), [ 'bc1qseller' => 100000, $fee_address => 10000 ], 'outputs(payout): price to the seller, fee to the marketplace' );
sk_check_eq( Actions::outputs( $row, 'refund_fee' ), [ 'bc1qbuyer' => 100000, $fee_address => 10000 ], 'outputs(refund_fee): price back, fee kept' );
sk_check_eq( Actions::outputs( $row, 'refund' ), [ 'bc1qbuyer' => 100000 ], 'outputs(refund): the API pays everything back' );
$legacy = clone $row;
$legacy->metadata = wp_json_encode( [ 'escrow' => [ 'payout_address' => 'bc1qseller', 'refund_address' => 'bc1qbuyer' ] ] );
sk_check_eq( Actions::outputs( $legacy, 'payout' ), [ 'bc1qseller' => 100000 ], 'outputs(): a row without a fee pays no fee' );

// ── Pool ledger ───────────────────────────────────────────────────────────
$hash    = bin2hex( random_bytes( 32 ) );
$before  = Pool::balance();
sk_check_eq( Pool::add( Pool::KIND_FEE_SHARE, 1500, $hash ), true, 'Pool::add(): credited' );
sk_check_eq( Pool::add( Pool::KIND_FEE_SHARE, 1500, $hash ), false, 'Pool::add(): the same escrow and kind only once' );
sk_check_eq( Pool::balance(), $before + 1500, 'Pool::balance(): sums the ledger' );
Pool::publish();
sk_check_eq( Pool::published()['sats'], $before + 1500, 'Pool::publish(): the balance for the page' );

// ── Business days (§3) ────────────────────────────────────────────────────
$fri = strtotime( '2026-09-18 10:00:00' ); // a Friday
sk_check_eq( wp_date( 'Y-m-d', Deadlines::add_business_days( $fri, 3 ) ), '2026-09-23', 'add_business_days(): Friday + 3 skips the weekend' );
sk_check_eq( wp_date( 'Y-m-d', Deadlines::add_business_days( strtotime( '2026-12-24 10:00:00' ), 1 ) ), '2026-12-28', 'add_business_days(): Christmas and Boxing Day and the weekend are skipped' );
sk_check_eq( Deadlines::is_business_day( strtotime( '2026-04-03 12:00:00' ) ), false, 'is_business_day(): Good Friday 2026 is none' );

// ── Deadlines on rows (§3, §10), against the local fake API ───────────────
global $wpdb;
$buyer   = 1;
$made    = [];
$mk_row  = function ( array $over, array $escrow, array $top = [] ) use ( &$made, $wpdb, $user, $buyer ) {
    $h = bin2hex( random_bytes( 32 ) );
    $wpdb->insert( Rows::table(), array_merge( [
        'vendor_id'       => $user,
        'buyer_id'        => $buyer,
        'product_id'      => 0,
        'amount_sats'     => 100000,
        'payment_hash'    => $h,
        'payment_request' => '',
        'status'          => 'confirmed',
        'context'         => 'escrow',
        'created_at'      => current_time( 'mysql' ),
        'confirmed_at'    => wp_date( 'Y-m-d H:i:s', time() - 10 * DAY_IN_SECONDS ),
        'metadata'        => wp_json_encode( array_merge( [ 'escrow' => array_merge( [ 'order_id' => 'e' . substr( $h, 0, 31 ), 'fee_sat' => 10000, 'payout_address' => 'bc1qseller', 'refund_address' => 'bc1qbuyer' ], $escrow ) ], $top ) ),
    ], $over ) );
    $made[] = $h;

    return Rows::get( $h );
};
delete_user_meta( $user, Rules::INCIDENTS_META );
delete_user_meta( $user, Rules::BLOCKED_META );

// Not shipped within three business days: full refund, incident for the seller.
$a = $mk_row( [], [] );
Deadlines::check( $a );
$a = Rows::get( $a->payment_hash );
sk_check_eq( [ $a->status, Rows::meta( $a )['psbt_type'] ?? '', Rules::incidents( $user ) ], [ 'disputed', 'refund', 1 ], 'not shipped: disputed, full refund built, seller incident' );
sk_check( str_contains( (string) ( Rows::all_meta( $a )['dispute_reason'] ?? '' ), '§3' ), 'not shipped: the reason names §3' );
sk_check( in_array( '/psbt/build_refund', array_map( fn( $c ) => substr( $c['url'], strlen( 'http://127.0.0.1:1/api' ) ), $GLOBALS['api_calls'] ), true ), 'not shipped: build_refund was asked of the API' );

// Shipped, but still within the three business days at the time: nothing happens.
$fresh = $mk_row( [ 'confirmed_at' => wp_date( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ) ], [] );
Deadlines::check( $fresh );
sk_check_eq( Rows::get( $fresh->payment_hash )->status, 'confirmed', 'freshly paid: the clock has not run out' );

// Shipped 20 days ago, no delivery scan: refund with the fee kept, no incident.
$b = $mk_row( [], [], [ 'shipping' => [ 'carrier' => 'post-ch', 'number' => '99.00.123', 'at' => wp_date( 'Y-m-d H:i:s', time() - 20 * DAY_IN_SECONDS ) ] ] );
Deadlines::check( $b );
$b = Rows::get( $b->payment_hash );
sk_check_eq( [ $b->status, Rows::meta( $b )['psbt_type'] ?? '', Rules::incidents( $user ) ], [ 'disputed', 'refund_fee', 1 ], 'no delivery scan in 14 days: refund with fee kept, no new incident' );
$last = end( $GLOBALS['api_calls'] );
sk_check_eq( [ $last['body']['kind'] ?? '', $last['body']['outputs'] ?? [] ], [ 'refund', [ 'bc1qbuyer' => 100000, $fee_address => 10000 ] ], 'no delivery scan: the API got buyer first, fee fixed, kind refund' );

// Shipped 8 days ago, delivered 5 days ago (too light): payout, and the weight counts double.
$c = $mk_row( [ 'product_id' => 999999901 ], [], [ 'shipping' => [ 'carrier' => 'dhl', 'number' => 'JD1', 'at' => wp_date( 'Y-m-d H:i:s', time() - 8 * DAY_IN_SECONDS ) ] ] );
add_post_meta( 999999901, '_weight', '1.0', true );
add_filter( 'pre_option_woocommerce_weight_unit', fn() => 'kg' );
Deadlines::record_tracking( $c->payment_hash, 'delivered', time() - 5 * DAY_IN_SECONDS, false, 500, 'manual', 1 );
$c = Rows::get( $c->payment_hash );
sk_check( str_contains( Rows::state_label( $c ), 'Zugestellt' ), 'state_label(): shows the delivery' );
sk_check( '' !== Deadlines::may_report( $c ), 'may_report(): closed three days after the scan' );
Deadlines::check( $c );
$c = Rows::get( $c->payment_hash );
sk_check_eq( [ $c->status, Rows::meta( $c )['psbt_type'] ?? '', Rules::incidents( $user ) ], [ 'disputed', 'payout', 3 ], 'delivered, no report: payout built; the light parcel added a double incident' );
sk_check_eq( Rows::meta( $c )['weight_checked']['short'] ?? null, true, 'weight_check(): 500 g against 1000 g listed is short' );
delete_post_meta( 999999901, '_weight' );

// Delivered yesterday: the window is open, nothing runs out yet.
$d = $mk_row( [], [], [ 'shipping' => [ 'carrier' => 'dhl', 'number' => 'JD2', 'at' => wp_date( 'Y-m-d H:i:s', time() - 3 * DAY_IN_SECONDS ) ] ] );
Deadlines::record_tracking( $d->payment_hash, 'delivered', time() - DAY_IN_SECONDS, true, 0, 'manual', 1 );
$d = Rows::get( $d->payment_hash );
sk_check_eq( Deadlines::may_report( $d ), '', 'may_report(): open the day after delivery' );
Deadlines::check( $d );
sk_check_eq( Rows::get( $d->payment_hash )->status, 'confirmed', 'delivered yesterday: still open' );

// A transaction already being signed is left alone.
$e = $mk_row( [], [ 'psbt_type' => 'payout', 'psbt' => 'x' ] );
Deadlines::check( $e );
sk_check_eq( Rows::get( $e->payment_hash )->status, 'confirmed', 'signing in progress: the clock does not interfere' );

foreach ( $made as $h ) {
    $wpdb->delete( Rows::table(), [ 'payment_hash' => $h ], [ '%s' ] );
}

// ── restore ───────────────────────────────────────────────────────────────
$wpdb->delete( Pool::table(), [ 'escrow_hash' => $hash ], [ '%s' ] );
Pool::publish();
foreach ( [ Rules::INCIDENTS_META => $saved_incidents, Rules::BLOCKED_META => $saved_blocked ] as $k => $v ) {
    if ( '' === $v || null === $v || [] === $v ) {
        delete_user_meta( $user, $k );
    } else {
        update_user_meta( $user, $k, $v );
    }
}
sk_check_eq( Pool::balance(), $before, 'restore: ledger as before' );
sk_check_eq( Rules::incidents( $user ), 0, 'restore: no test incidents left' );

sk_test_done();
