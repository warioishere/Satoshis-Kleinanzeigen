<?php

namespace SK\Core\Dashboard;

/**
 * Bootstraps all dashboard modules.
 *
 * Instantiated once during sk_loaded. Each module registers
 * its own hooks in its constructor.
 */
class ModuleLoader {

    /** Bump to let maybe_upgrade_tables() apply schema changes. */
    private const DB_VERSION = '1';

    private const VERSION_OPTION = 'sk_dashboard_db_version';

    public function __construct() {
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
        new Modules\AiCategorizer();
        new Modules\Feedback();
        new Modules\UserOnboarding();
        new Modules\LogoutModal();
        new Modules\SmtpConfig();
        new Modules\AccountDeletion();
        new Modules\VerifiedLinksPage();

        add_action( 'admin_init', [ self::class, 'maybe_upgrade_tables' ] );
    }

    /**
     * Apply schema changes outside the activation hook.
     *
     * maybe_create_tables() only ever ran on activation, and a deploy that
     * resets the working tree never activates the plugin — a new column would
     * have stayed unapplied. Cheap no-op once the version matches.
     */
    public static function maybe_upgrade_tables(): void {
        if ( get_option( self::VERSION_OPTION ) === self::DB_VERSION ) {
            return;
        }

        self::maybe_create_tables();

        update_option( self::VERSION_OPTION, self::DB_VERSION, false );
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

        // No early return on an existing table and no `IF NOT EXISTS`: dbDelta reads
        // the table name with `|CREATE TABLE ([^ ]*)|`, so `IF NOT EXISTS` makes it
        // parse the name as "IF", never match the existing table and never emit the
        // ALTERs for missing columns or keys.
        $sql = "CREATE TABLE {$table_name} (
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
