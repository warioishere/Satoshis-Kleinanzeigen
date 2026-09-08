<?php

namespace SK\Modules\Reputation;

defined( 'ABSPATH' ) || exit;

final class Module {

    public $version;

    public function __construct() {
        $this->version = sk_assets_version( __DIR__ . '/assets' );
        $this->define_constants();
        $this->includes();
        $this->load_hooks();
        $this->instances();
    }

    private function define_constants() {
        define( 'SK_REPUTATION_VERSION', $this->version );
        define( 'SK_REPUTATION_FILE', __FILE__ );
        define( 'SK_REPUTATION_PATH', dirname( SK_REPUTATION_FILE ) );
        define( 'SK_REPUTATION_INCLUDES', SK_REPUTATION_PATH . '/includes' );
        define( 'SK_REPUTATION_URL', plugins_url( '', SK_REPUTATION_FILE ) );
        define( 'SK_REPUTATION_TEMPLATES', SK_REPUTATION_PATH . '/templates' );
    }

    private function includes() {
        require_once SK_REPUTATION_INCLUDES . '/Settings.php';
        require_once SK_REPUTATION_INCLUDES . '/Calculator.php';
        require_once SK_REPUTATION_INCLUDES . '/Cron.php';
        require_once SK_REPUTATION_INCLUDES . '/ProofPage.php';
    }

    public function load_hooks() {
        add_action( 'sk_activated_module_sk_reputation', [ $this, 'activate' ] );
        add_action( 'sk_deactivated_module_sk_reputation', [ $this, 'deactivate' ] );
    }

    private function instances() {
        new Settings();

        // Modules are built before the module manager is in the container,
        // so whether other modules are active is only known from init on.
        // Priority 0 is still ahead of the rewrite rules (init, 10) that
        // the proof page hooks into.
        add_action( 'init', [ $this, 'boot' ], 0 );
    }

    public function boot(): void {
        if ( ! self::is_enabled() ) {
            return;
        }

        // The payment-based signals (credited transactions, proof page)
        // exist only where SK Payments writes the payment table. Without
        // that module the cron would query a table that is not there.
        if ( self::payments_available() ) {
            new Cron();
            new ProofPage();
        }
    }

    /**
     * Module switched on in the module manager and enabled in its own
     * settings section.
     */
    public static function is_enabled(): bool {
        return sk_module_active( 'sk_reputation' )
            && sk_get_option( 'sk_reputation_enabled', Settings::SECTION, 'on' ) === 'on';
    }

    /**
     * Whether payments are a source of signals on this site.
     */
    public static function payments_available(): bool {
        return sk_module_active( 'sk_payments' )
            && class_exists( 'SK\Modules\Payments\Module', false )
            && \SK\Modules\Payments\Module::is_enabled();
    }

    public function activate() {
        Cron::schedule();
        flush_rewrite_rules( true );
    }

    public function deactivate() {
        wp_clear_scheduled_hook( 'sk_recalculate_reputation_scores' );
        flush_rewrite_rules( true );
    }
}
