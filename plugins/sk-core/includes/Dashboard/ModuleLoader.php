<?php

namespace SK\Core\Dashboard;

/**
 * Bootstraps all dashboard modules.
 *
 * Instantiated once during sk_loaded. Each module registers
 * its own hooks in its constructor.
 */
class ModuleLoader {

    public function __construct() {
        // Load standalone function shims unconditionally (bypasses autoloader/opcode cache)
        require_once __DIR__ . '/dashboard-functions.php';

        // Bootstrap the dashboard registry before instantiating any module so
        // modules extending DashboardModule can self-register.
        DashboardRegistry::bootstrap();

        new Modules\Performance();
        new Modules\Notices();
        new Modules\Merkliste();
        new Modules\Gesuche();
        new Modules\ContactDetails();
        new Modules\VendorChat();
        new Modules\ProductForm();
        new Modules\NavFix();
        new Modules\Terminology();
        new Modules\CurrencyIcon();
        new Modules\ProfileAvatar();
        new Modules\ProductSlider();
        new Modules\BtcLogin();
        new Modules\AiCategorizer();
        new Modules\Feedback();
        new Modules\UserOnboarding();
        new Modules\LogoutModal();
        new Modules\SmtpConfig();
        new Modules\AccountDeletion();
    }

    /**
     * Create the sk_merkliste DB table if it doesn't exist yet.
     * Called on plugin activation.
     */
    public static function maybe_create_tables(): void {
        global $wpdb;

        $table_name      = $wpdb->prefix . 'sk_merkliste';
        $charset_collate = $wpdb->get_charset_collate();

        ChatMessages::maybe_install();

        $already_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
        if ( $already_exists ) {
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            product_id bigint(20) NOT NULL,
            added_date datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY user_product (user_id, product_id),
            KEY user_id (user_id),
            KEY product_id (product_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}
