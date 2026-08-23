<?php

namespace SK\Modules\ContactClicks;

defined( 'ABSPATH' ) || exit;

final class Install {

    /**
     * Eine Zeile je Inserat, Kanal, Tag und Besucher.
     *
     * Damit liefert dieselbe Tabelle beide Groessen: SUM(clicks) sind alle
     * Klicks, COUNT(*) die eindeutigen. Der Besucher-Hash rotiert taeglich und
     * enthaelt keine IP.
     */
    public static function install(): void {
        global $wpdb;

        $charset = $wpdb->get_charset_collate();
        $table   = $wpdb->prefix . 'sk_contact_clicks';

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            vendor_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            channel VARCHAR(16) NOT NULL,
            context VARCHAR(16) NOT NULL DEFAULT '',
            click_day DATE NOT NULL,
            visitor_hash CHAR(32) NOT NULL,
            clicks INT UNSIGNED NOT NULL DEFAULT 1,
            first_seen DATETIME NOT NULL,
            last_seen DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_click (product_id, channel, click_day, visitor_hash),
            KEY idx_day (click_day),
            KEY idx_product (product_id, click_day),
            KEY idx_vendor (vendor_id, click_day),
            KEY idx_channel (channel, click_day)
        ) {$charset};";

        dbDelta( $sql );
    }
}
