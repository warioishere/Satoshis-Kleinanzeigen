<?php

namespace SK_Lightning;

defined( 'ABSPATH' ) || exit;

class Activator {

    public static function activate() {
        self::create_tables();
        self::schedule_cron();
    }

    public static function create_tables() {
        global $wpdb;

        $charset = $wpdb->get_charset_collate();

        $payments_table = $wpdb->prefix . 'sk_lightning_payments';
        $reputation_table = $wpdb->prefix . 'sk_reputation_scores';

        $sql = "CREATE TABLE {$payments_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id BIGINT UNSIGNED NULL,
            product_id BIGINT UNSIGNED NULL,
            vendor_id BIGINT UNSIGNED NOT NULL,
            buyer_id BIGINT UNSIGNED NULL,
            amount_sats BIGINT UNSIGNED NOT NULL,
            payment_hash VARCHAR(64) NOT NULL,
            payment_request TEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            context VARCHAR(20) NOT NULL DEFAULT 'chat',
            chat_id BIGINT UNSIGNED NULL,
            chat_message_idx INT NULL,
            reputation_valid TINYINT(1) NOT NULL DEFAULT 0,
            reputation_flags TEXT NULL,
            reputation_at DATETIME NULL,
            buyer_ip_hash VARCHAR(64) NULL,
            buyer_fingerprint VARCHAR(64) NULL,
            verify_url VARCHAR(512) NULL,
            preimage VARCHAR(64) NULL,
            preimage_verified TINYINT(1) NOT NULL DEFAULT 0,
            exchange_rate DECIMAL(16,8) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            confirmed_at DATETIME NULL,
            metadata TEXT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY payment_hash (payment_hash),
            KEY vendor_id (vendor_id),
            KEY buyer_id (buyer_id),
            KEY status (status),
            KEY reputation_at (reputation_at),
            KEY chat_id (chat_id)
        ) {$charset};

        CREATE TABLE {$reputation_table} (
            vendor_id BIGINT UNSIGNED NOT NULL,
            total_transactions INT UNSIGNED NOT NULL DEFAULT 0,
            valid_transactions INT UNSIGNED NOT NULL DEFAULT 0,
            unique_buyers INT UNSIGNED NOT NULL DEFAULT 0,
            total_volume_sats BIGINT UNSIGNED NOT NULL DEFAULT 0,
            valid_volume_sats BIGINT UNSIGNED NOT NULL DEFAULT 0,
            reputation_score INT UNSIGNED NOT NULL DEFAULT 0,
            last_calculated_at DATETIME NULL,
            PRIMARY KEY (vendor_id)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( 'sk_lightning_db_version', SK_LIGHTNING_VERSION );
    }

    public static function schedule_cron() {
        if ( ! wp_next_scheduled( 'sk_recalculate_reputation_scores' ) ) {
            wp_schedule_event( time(), 'six_hours', 'sk_recalculate_reputation_scores' );
        }
    }

    public static function add_cron_interval( $schedules ) {
        $schedules['six_hours'] = [
            'interval' => 6 * HOUR_IN_SECONDS,
            'display'  => __( 'Alle 6 Stunden', 'sk-lightning' ),
        ];
        return $schedules;
    }
}

add_filter( 'cron_schedules', [ Activator::class, 'add_cron_interval' ] );
