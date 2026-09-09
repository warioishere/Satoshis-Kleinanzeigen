<?php

namespace SK\Modules\ContactFeewall;

defined( 'ABSPATH' ) || exit;

/**
 * Contact Details Feewall — a 21-sat paywall over a vendor's contact icons.
 *
 * Absorbed from the standalone `contact-details-feewall` plugin: the
 * vendor's own toggle in their store settings (`sk_profile_settings`) and
 * the payment flow (BTCPay invoice, webhook, per-vendor 24h access via a
 * transient or cookie) are unchanged; only the plugin-lifecycle parts
 * (the WordPress "Settings" admin page, activation hooks) are replaced by
 * the module system — the AJAX actions, the REST webhook route and the
 * localized JS object keep their names so nothing on the BTCPay side or
 * in the browser needs to change.
 */
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
        define( 'SK_CONTACT_FEEWALL_VERSION', $this->version );
        define( 'SK_CONTACT_FEEWALL_FILE', __FILE__ );
        define( 'SK_CONTACT_FEEWALL_PATH', dirname( SK_CONTACT_FEEWALL_FILE ) );
        define( 'SK_CONTACT_FEEWALL_INCLUDES', SK_CONTACT_FEEWALL_PATH . '/includes' );
        define( 'SK_CONTACT_FEEWALL_URL', plugins_url( '', SK_CONTACT_FEEWALL_FILE ) );
    }

    private function includes() {
        require_once SK_CONTACT_FEEWALL_INCLUDES . '/Settings.php';
        require_once SK_CONTACT_FEEWALL_INCLUDES . '/Feewall.php';
    }

    public function load_hooks() {
        add_action( 'sk_activated_module_sk_contact_feewall', [ $this, 'activate' ] );
        add_action( 'sk_deactivated_module_sk_contact_feewall', [ $this, 'deactivate' ] );
    }

    private function instances() {
        new Settings();
        new Feewall();
    }

    public function activate() {
        if ( function_exists( 'wpfc_clear_all_cache' ) ) {
            wpfc_clear_all_cache();
        }
    }

    public function deactivate() {
        if ( function_exists( 'wpfc_clear_all_cache' ) ) {
            wpfc_clear_all_cache();
        }
    }
}
