<?php

namespace SK\Modules\AntiFraud;

defined( 'ABSPATH' ) || exit;

class ReportAutoSuspend {

    public function __construct() {
        add_action( 'sk_report_abuse_created_report', [ $this, 'check_threshold' ], 10, 2 );
    }

    /**
     * After a report is created, check if the vendor has enough reports for auto-suspend.
     *
     * @param int   $report_id  The new report ID.
     * @param array $data       Report data.
     */
    public function check_threshold( $report_id, $data ) {
        $vendor_id = $data['vendor_id'] ?? 0;
        if ( ! $vendor_id ) {
            return;
        }

        // Already suspended?
        if ( get_user_meta( $vendor_id, 'sk_auto_suspended', true ) ) {
            return;
        }

        $threshold = (int) sk_get_option( 'sk_antifraud_report_threshold', 'sk_antifraud', '3' );

        // Count unique reporter IPs for this vendor.
        global $wpdb;
        $table = $wpdb->prefix . 'sk_report_abuse_reports';

        // Get all reports for this vendor.
        $reports = $wpdb->get_results( $wpdb->prepare(
            "SELECT customer_id FROM {$table} WHERE vendor_id = %d",
            $vendor_id
        ) );

        if ( count( $reports ) < $threshold ) {
            return;
        }

        // Count unique reporters (customer_id).
        $unique_reporters = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT customer_id) FROM {$table} WHERE vendor_id = %d AND customer_id IS NOT NULL AND customer_id > 0",
            $vendor_id
        ) );

        if ( (int) $unique_reporters < $threshold ) {
            return;
        }

        // Auto-suspend.
        FingerprintCollector::suspend_user( $vendor_id, 'report_threshold_' . $unique_reporters );

        // Notify admin.
        $vendor = get_userdata( $vendor_id );
        $subject = sprintf(
            '[SK Anti-Fraud] Auto-Suspend: %s (%d Reports)',
            $vendor ? $vendor->user_login : $vendor_id,
            $unique_reporters
        );

        $body  = "Vendor wurde automatisch suspendiert wegen {$unique_reporters} Reports von verschiedenen Usern.\n\n";
        $body .= "Vendor: " . ( $vendor ? $vendor->user_login : $vendor_id ) . " (ID {$vendor_id})\n";
        $body .= "Reports gesamt: " . count( $reports ) . "\n";
        $body .= "Verschiedene Reporter: {$unique_reporters}\n\n";
        $body .= admin_url( "user-edit.php?user_id={$vendor_id}" );

        wp_mail( get_option( 'admin_email' ), $subject, $body );
    }
}
