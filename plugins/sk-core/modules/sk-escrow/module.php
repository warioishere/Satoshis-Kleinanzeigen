<?php

namespace SK\Modules\Escrow;

defined( 'ABSPATH' ) || exit;

/**
 * On-chain escrow for WooCommerce — 2-of-3 multisig (buyer/vendor/escrow)
 * with a PSBT flow against an external escrow API.
 *
 * Absorbed from the standalone `sats-escrow` plugin. The escrow logic
 * itself is untouched: the WEO_* classes, the gateway id, the REST routes
 * and the option names all keep their names, so a configured installation
 * keeps working. Only the plugin bootstrap is replaced by the module
 * system, and the settings page moves into SK Admin (see EscrowSettings).
 */
final class Module {

    public $version;

    public function __construct() {
        $this->version = sk_assets_version( __DIR__ . '/assets' );

        $this->define_constants();
        $this->includes();
        $this->load_hooks();
    }

    private function define_constants() {
        /*
         * The WEO_* files address their own assets and templates through
         * these two constants, so pointing them at the module directory is
         * all that the move needs.
         */
        defined( 'WEO_PLUGIN_FILE' ) || define( 'WEO_PLUGIN_FILE', __FILE__ );
        defined( 'WEO_DIR' ) || define( 'WEO_DIR', plugin_dir_path( __FILE__ ) );
        defined( 'WEO_URL' ) || define( 'WEO_URL', plugin_dir_url( __FILE__ ) );
        defined( 'WEO_OPT' ) || define( 'WEO_OPT', 'weo_options' );

        define( 'SK_ESCROW_VERSION', $this->version );
    }

    private function includes() {
        require_once WEO_DIR . 'includes/helpers.php';
        require_once WEO_DIR . 'includes/class-psbt.php';
        require_once WEO_DIR . 'includes/EscrowSettings.php';
        require_once WEO_DIR . 'includes/class-escrow-settings.php';
        require_once WEO_DIR . 'includes/class-escrow-vendor.php';
        require_once WEO_DIR . 'includes/class-escrow-sk.php';
        require_once WEO_DIR . 'includes/class-escrow-order.php';
        require_once WEO_DIR . 'includes/class-escrow-rest.php';
        require_once WEO_DIR . 'includes/class-escrow-admin.php';
        require_once WEO_DIR . 'includes/class-escrow-notifications.php';
    }

    private function load_hooks() {
        /*
         * Modules load on sk_loaded, which fires from woocommerce_loaded —
         * WooCommerce is guaranteed to be there, so the gateway can be
         * wired up right away instead of waiting for plugins_loaded like
         * the standalone plugin had to.
         */
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        require_once WEO_DIR . 'includes/class-escrow-gateway.php';

        add_filter( 'woocommerce_payment_gateways', [ $this, 'register_gateway' ] );

        new EscrowSettings();
        new \WEO_Settings();
        new \WEO_Vendor();

        if ( function_exists( 'sk' ) ) {
            new \WEO_SK();
        }

        new \WEO_Order();
        new \WEO_REST();

        if ( is_admin() ) {
            new \WEO_Admin();
        }

        \WEO_Notifications::init();
    }

    public function register_gateway( $methods ) {
        $methods[] = 'WEO_Gateway';

        return $methods;
    }
}
