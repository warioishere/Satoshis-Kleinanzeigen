<?php

namespace SK\Modules\Reputation;

defined( 'ABSPATH' ) || exit;

final class Module {

    public $version = '1.0.0';

    public function __construct() {
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
        require_once SK_REPUTATION_INCLUDES . '/MeetupReputation.php';
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
        new MeetupReputation();

        // Ensure meetup table exists (safe to call repeatedly — uses CREATE IF NOT EXISTS).
        if ( get_option( 'sk_meetup_rep_db_version' ) !== $this->version ) {
            MeetupReputation::create_table();
            update_option( 'sk_meetup_rep_db_version', $this->version );
        }
    }

    /**
     * Check if Reputation system is enabled in admin settings.
     */
    public static function is_enabled(): bool {
        return sk_get_option( 'sk_reputation_enabled', 'sk_lightning', 'on' ) === 'on';
    }

    public function activate() {
        Cron::schedule();
        MeetupReputation::create_table();
        flush_rewrite_rules( true );
    }

    public function deactivate() {
        wp_clear_scheduled_hook( 'sk_recalculate_reputation_scores' );
        flush_rewrite_rules( true );
    }
}
