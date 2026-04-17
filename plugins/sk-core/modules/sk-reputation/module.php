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
        require_once SK_REPUTATION_INCLUDES . '/Calculator.php';
        require_once SK_REPUTATION_INCLUDES . '/Cron.php';
        require_once SK_REPUTATION_INCLUDES . '/ProofPage.php';
    }

    public function load_hooks() {
        add_action( 'sk_activated_module_sk_reputation', [ $this, 'activate' ] );
        add_action( 'sk_deactivated_module_sk_reputation', [ $this, 'deactivate' ] );
    }

    private function instances() {
        if ( ! self::is_enabled() ) {
            return;
        }

        new Cron();
        new ProofPage();
    }

    /**
     * Check if Reputation system is enabled in admin settings.
     */
    public static function is_enabled(): bool {
        return sk_get_option( 'sk_reputation_enabled', 'sk_lightning', 'on' ) === 'on';
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
