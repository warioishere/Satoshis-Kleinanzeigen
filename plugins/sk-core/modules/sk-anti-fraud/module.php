<?php

namespace SK\Modules\AntiFraud;

defined( 'ABSPATH' ) || exit;

/**
 * SK Anti-Fraud — Fingerprinting, Buyer Warnings, Sale Limits, Report Auto-Suspend.
 *
 * All features are OFF by default and must be individually enabled in SK Core Settings.
 */
final class Module {

    public $version;

    public function __construct() {
        $this->version = sk_assets_version( __DIR__ . '/assets' );
        define( 'SK_ANTIFRAUD_VERSION', $this->version );
        define( 'SK_ANTIFRAUD_PATH', dirname( __FILE__ ) );
        define( 'SK_ANTIFRAUD_URL', plugins_url( '', __FILE__ ) );
        define( 'SK_ANTIFRAUD_INCLUDES', SK_ANTIFRAUD_PATH . '/includes' );

        require_once SK_ANTIFRAUD_INCLUDES . '/AntifraudSettings.php';
        require_once SK_ANTIFRAUD_INCLUDES . '/Suspension.php';
        require_once SK_ANTIFRAUD_INCLUDES . '/ReportGuards.php';
        require_once SK_ANTIFRAUD_INCLUDES . '/VendorSummary.php';
        require_once SK_ANTIFRAUD_INCLUDES . '/BanSignals.php';
        new AntifraudSettings();

        // Only load features if master switch is on.
        if ( sk_get_option( 'sk_antifraud_enabled', 'sk_antifraud', 'off' ) !== 'on' ) {
            return;
        }

        // Report guards are always active with the master switch — they protect
        // the reporting itself, independent of what reacts to reports.
        new ReportGuards();

        // Watch for banned identifiers coming back on a new account.
        require_once SK_ANTIFRAUD_INCLUDES . '/BanWatcher.php';
        new BanWatcher();

        if ( sk_get_option( 'sk_antifraud_fingerprint', 'sk_antifraud', 'off' ) === 'on' ) {
            require_once SK_ANTIFRAUD_INCLUDES . '/FingerprintCollector.php';
            new FingerprintCollector();
        }

        if ( sk_get_option( 'sk_antifraud_buyer_warning', 'sk_antifraud', 'off' ) === 'on' ) {
            require_once SK_ANTIFRAUD_INCLUDES . '/BuyerWarnings.php';
            new BuyerWarnings();
        }

        if ( sk_get_option( 'sk_antifraud_sale_limit', 'sk_antifraud', 'off' ) === 'on' ) {
            require_once SK_ANTIFRAUD_INCLUDES . '/SaleLimits.php';
            new SaleLimits();
        }

        if ( sk_get_option( 'sk_antifraud_report_suspend', 'sk_antifraud', 'off' ) === 'on' ) {
            require_once SK_ANTIFRAUD_INCLUDES . '/ReportAutoSuspend.php';
            new ReportAutoSuspend();
        }

        if ( sk_get_option( 'sk_antifraud_store_name_guard', 'sk_antifraud', 'off' ) === 'on' ) {
            require_once SK_ANTIFRAUD_INCLUDES . '/StoreNameGuard.php';
            new StoreNameGuard();
        }

        if ( sk_get_option( 'sk_antifraud_keyword_review', 'sk_antifraud', 'off' ) === 'on' ) {
            require_once SK_ANTIFRAUD_INCLUDES . '/KeywordReview.php';
            new KeywordReview();
        }

        add_action( 'sk_activated_module_sk_anti_fraud', [ $this, 'activate' ] );

        // Ensure tables exist (safe to call repeatedly — uses IF NOT EXISTS).
        if ( false === get_option( 'sk_antifraud_db_version' ) ) {
            $this->activate();
            update_option( 'sk_antifraud_db_version', '1.0' );
        }
    }

    /**
     * Create DB tables on module activation.
     */
    public function activate() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta( "CREATE TABLE {$wpdb->prefix}sk_fingerprints (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            fingerprint_hash varchar(64) NOT NULL,
            canvas_hash varchar(64) DEFAULT '',
            webgl_hash varchar(64) DEFAULT '',
            audio_hash varchar(64) DEFAULT '',
            fonts_hash varchar(64) DEFAULT '',
            ip_hash varchar(64) DEFAULT '',
            geo_city varchar(100) DEFAULT '',
            timezone varchar(64) DEFAULT '',
            screen varchar(32) DEFAULT '',
            platform varchar(64) DEFAULT '',
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_user (user_id),
            KEY idx_fp (fingerprint_hash),
            KEY idx_canvas (canvas_hash),
            KEY idx_audio (audio_hash),
            KEY idx_ip (ip_hash)
        ) $charset;" );

        dbDelta( "CREATE TABLE {$wpdb->prefix}sk_banned_signals (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            banned_user_id bigint(20) unsigned NOT NULL,
            signal_type varchar(20) NOT NULL,
            signal_value varchar(100) NOT NULL,
            banned_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_lookup (signal_type, signal_value)
        ) $charset;" );
    }
}
