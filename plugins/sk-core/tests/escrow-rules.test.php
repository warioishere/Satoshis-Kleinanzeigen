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
foreach ( [ 'helpers', 'Rows', 'Rules', 'Pool', 'Notify', 'Actions' ] as $f ) {
    require_once dirname( __DIR__ ) . '/modules/sk-escrow/includes/' . $f . '.php';
}

use SK\Modules\Escrow\Actions;
use SK\Modules\Escrow\Pool;
use SK\Modules\Escrow\Rules;

$user = (int) ( getenv( 'SK_TEST_GENERATED' ) ?: 610 );
$fee_address = 'bc1qtestfeeaddress00000000000000000000000';

add_filter( 'pre_option_' . WEO_OPT, fn() => [ 'fee_address' => $fee_address ] );

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

// ── restore ───────────────────────────────────────────────────────────────
global $wpdb;
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
