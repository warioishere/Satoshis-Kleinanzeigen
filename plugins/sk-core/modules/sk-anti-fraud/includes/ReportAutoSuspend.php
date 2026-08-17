<?php

namespace SK\Modules\AntiFraud;

use SK\Core\Vendor\Suspension;

defined( 'ABSPATH' ) || exit;

/**
 * Take a vendor offline once enough independent people reported them.
 *
 * Only reports that are hard to fake count towards the threshold:
 *   - from logged in users (guests are stored but never counted)
 *   - one per reporter, no matter how many listings they reported
 *   - from accounts old enough (see ReportGuards::is_eligible_reporter)
 *   - within the configured time window, so old resolved complaints expire
 */
class ReportAutoSuspend {

    public function __construct() {
        // The hook passes ( $report, $product, $vendor, $customer ) — the old
        // signature here expected ( $report_id, $data ) and fataled on the
        // WC_Product array access.
        add_action( 'sk_report_abuse_created_report', [ $this, 'check_threshold' ], 10, 1 );
    }

    /**
     * @param object $report Row from the reports table.
     */
    public function check_threshold( $report ): void {
        if ( ! is_object( $report ) || empty( $report->vendor_id ) ) {
            return;
        }

        $vendor_id = (int) $report->vendor_id;

        if ( Suspension::is_suspended_by( $vendor_id, Suspension::SOURCE_ANTI_FRAUD ) ) {
            return;
        }

        $threshold = (int) sk_get_option( 'sk_antifraud_report_threshold', 'sk_antifraud', '3' );

        if ( $threshold < 1 ) {
            return;
        }

        $reporters = self::get_eligible_reporters( $vendor_id );

        if ( count( $reporters ) < $threshold ) {
            return;
        }

        $drafted = Suspension::suspend( $vendor_id, Suspension::SOURCE_ANTI_FRAUD, 'report_threshold_' . count( $reporters ) );

        $this->notify_admin( $vendor_id, $reporters, $drafted );
    }

    /**
     * Distinct reporter IDs that count towards the threshold.
     *
     * @return int[]
     */
    public static function get_eligible_reporters( int $vendor_id ): array {
        global $wpdb;

        $table       = $wpdb->prefix . 'sk_report_abuse_reports';
        $window_days = (int) sk_get_option( 'sk_antifraud_report_window_days', 'sk_antifraud', '30' );

        $sql    = "SELECT DISTINCT customer_id FROM {$table} WHERE vendor_id = %d AND customer_id > 0";
        $params = [ $vendor_id ];

        if ( $window_days > 0 ) {
            $sql     .= ' AND reported_at >= %s';
            $params[] = gmdate( 'Y-m-d H:i:s', time() - $window_days * DAY_IN_SECONDS );
        }

        $ids = $wpdb->get_col( $wpdb->prepare( $sql, $params ) );

        return array_values( array_filter(
            array_map( 'intval', (array) $ids ),
            [ ReportGuards::class, 'is_eligible_reporter' ]
        ) );
    }

    private function notify_admin( int $vendor_id, array $reporters, int $drafted ): void {
        $to = get_option( 'admin_email' );

        if ( ! $to ) {
            return;
        }

        $vendor    = get_userdata( $vendor_id );
        $name      = $vendor ? $vendor->user_login : (string) $vendor_id;
        $site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

        $subject = sprintf(
            /* translators: 1: site name, 2: vendor name, 3: number of reporters */
            __( '[%1$s] Anbieter automatisch offline: %2$s (%3$d Meldungen)', 'sk-core' ),
            $site_name,
            $name,
            count( $reporters )
        );

        $body  = __( 'Ein Anbieter wurde automatisch offline genommen, weil mehrere unabhängige Nutzer ihn gemeldet haben.', 'sk-core' ) . "\n\n";
        $body .= VendorSummary::text( $vendor_id ) . "\n\n";
        $body .= sprintf( __( 'Zur Schwelle zählende Melder: %d', 'sk-core' ), count( $reporters ) ) . "\n";
        $body .= sprintf( __( 'Jetzt offline genommen: %d Inserate', 'sk-core' ), $drafted ) . "\n\n";

        $body .= __( 'Prüfen und ggf. wieder freischalten:', 'sk-core' ) . "\n";
        $body .= admin_url( 'admin.php?page=sk&tab=antifraud&sub=suspended' ) . "\n\n";
        $body .= __( 'Anbieter-Profil:', 'sk-core' ) . ' ' . admin_url( 'user-edit.php?user_id=' . $vendor_id ) . "\n";

        wp_mail( $to, $subject, $body );
    }
}
