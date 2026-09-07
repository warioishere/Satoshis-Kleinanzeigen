<?php

namespace SK\Modules\Sponsors;

defined( 'ABSPATH' ) || exit;

final class Install {

    /**
     * One row per sponsor, day, and visitor.
     *
     * This way the same table delivers both numbers needed for selling:
     * SUM(clicks) is all clicks, COUNT(*) is the unique ones. The visitor
     * hash rotates daily (see Tracker::visitor_hash), so it's not a
     * cross-day identifier and stores no IP.
     */
    public static function install(): void {
        global $wpdb;

        $charset = $wpdb->get_charset_collate();
        $table   = $wpdb->prefix . 'sk_sponsor_clicks';

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            sponsor_id BIGINT UNSIGNED NOT NULL,
            click_day DATE NOT NULL,
            visitor_hash CHAR(32) NOT NULL,
            clicks INT UNSIGNED NOT NULL DEFAULT 1,
            first_seen DATETIME NOT NULL,
            last_seen DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_sponsor_day_visitor (sponsor_id, click_day, visitor_hash),
            KEY idx_sponsor_day (sponsor_id, click_day),
            KEY idx_day (click_day)
        ) {$charset};";

        dbDelta( $sql );

        // Every balance movement is logged — without a record, a paying
        // partner can't be shown what was deducted and why.
        $ledger = $wpdb->prefix . 'sk_sponsor_ledger';

        $sql_ledger = "CREATE TABLE {$ledger} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            sponsor_id BIGINT UNSIGNED NOT NULL,
            delta_sats BIGINT NOT NULL,
            balance_after BIGINT NOT NULL,
            note VARCHAR(191) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_sponsor (sponsor_id, id)
        ) {$charset};";

        dbDelta( $sql_ledger );
    }
}
