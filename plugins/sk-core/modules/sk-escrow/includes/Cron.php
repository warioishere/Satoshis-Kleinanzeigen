<?php

namespace SK\Modules\Escrow;

defined( 'ABSPATH' ) || exit;

/**
 * Hourly: let unanswered requests expire and pull the API state of open
 * escrows, so a deposit is noticed even when nobody has the page open and
 * the webhook did not get through.
 */
final class Cron {

    const HOOK = 'weo_escrow_hourly';

    /** Days a seller has to accept a request. */
    const REQUEST_DAYS = 3;

    public function __construct() {
        add_action( self::HOOK, [ __CLASS__, 'run' ] );
        if ( ! wp_next_scheduled( self::HOOK ) ) {
            wp_schedule_event( time() + 300, 'hourly', self::HOOK );
        }
    }

    public static function run(): void {
        $cutoff = time() - self::REQUEST_DAYS * DAY_IN_SECONDS;

        foreach ( Rows::by_status( [ 'requested' ], 200 ) as $row ) {
            if ( strtotime( (string) $row->created_at ) < $cutoff && Rows::set_status( $row->payment_hash, 'requested', 'expired' ) ) {
                Rows::save_meta( $row->payment_hash, [ 'note' => __( 'Abgelaufen: nicht rechtzeitig angenommen', 'sk-core' ) ] );
                Notify::expired( $row );
            }
        }

        if ( ! weo_enabled() ) {
            return;
        }

        foreach ( Rows::by_status( [ 'pending', 'confirmed', 'delivered', 'disputed' ], 50 ) as $row ) {
            $meta = Rows::meta( $row );
            if ( ! empty( $meta['settled_txid'] ) ) {
                continue;
            }
            Actions::sync( $row );
        }
    }
}
