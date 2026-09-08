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
        require_once SK_REPUTATION_INCLUDES . '/SocialGraph.php';
        require_once SK_REPUTATION_INCLUDES . '/TrustPage.php';
        require_once SK_REPUTATION_INCLUDES . '/Reports.php';
        require_once SK_REPUTATION_INCLUDES . '/FollowMirror.php';
        require_once SK_REPUTATION_INCLUDES . '/Calculator.php';
        require_once SK_REPUTATION_INCLUDES . '/Cron.php';
    }

    /**
     * Every recurring job of the module in one place: scheduled on
     * activation, cleared on deactivation. Nothing schedules itself from a
     * constructor on every request any more.
     *
     * @return array<string, string> hook => recurrence
     */
    public static function cron_jobs(): array {
        $jobs = [
            Reports::CRON_HOOK      => 'daily',
            FollowMirror::SYNC_HOOK => 'daily',
        ];

        if ( self::payments_available() ) {
            $jobs[ Cron::HOOK ] = Cron::INTERVAL;
        }

        return $jobs;
    }

    public static function schedule_cron(): void {
        // The six-hour interval is the module's own; make sure WordPress
        // knows it even when Cron was not built in this request.
        add_filter( 'cron_schedules', [ Cron::class, 'add_cron_interval' ] );

        $offset = HOUR_IN_SECONDS;

        foreach ( self::cron_jobs() as $hook => $recurrence ) {
            if ( ! wp_next_scheduled( $hook ) ) {
                wp_schedule_event( time() + $offset, $recurrence, $hook );
            }

            $offset += HOUR_IN_SECONDS;
        }
    }

    public static function unschedule_cron(): void {
        foreach ( array_keys( self::cron_jobs() ) as $hook ) {
            wp_clear_scheduled_hook( $hook );
        }

        wp_clear_scheduled_hook( Cron::HOOK );
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

        // "You follow" / "N of your contacts follow", computed in the
        // viewer's browser from their own Nostr graph.
        new SocialGraph();

        // /store/{slug}/vertrauen/: every signal with source and proof; the
        // old /lightning-proof/ address redirects there.
        new TrustPage();

        // Nostr reports from the web of trust, for the operator only.
        new Reports();

        // Internal store follows of SK-made identities, kept in their kind 3.
        if ( sk_module_active( 'follow_store' ) ) {
            new FollowMirror();
        }

        // The payment-based signals (credited transactions, proof list)
        // exist only where SK Payments writes the payment table. Without
        // that module the cron would query a table that is not there.
        if ( self::payments_available() ) {
            new Cron();
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
        self::schedule_cron();
        flush_rewrite_rules( true );
    }

    public function deactivate() {
        self::unschedule_cron();
        flush_rewrite_rules( true );
    }
}
