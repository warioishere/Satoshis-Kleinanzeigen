<?php

namespace SK\Modules\Payments;

defined( 'ABSPATH' ) || exit;

final class Module {

    public $version;

    public function __construct() {
        $this->version = sk_assets_version( __DIR__ . '/assets' );
        $this->define_constants();
        $this->includes();
        $this->maybe_upgrade();
        $this->load_hooks();
        $this->instances();
    }

    /**
     * Tables were only ever built on activation, so an installed module never
     * picked up new columns. dbDelta is idempotent, running it on a version
     * change is enough.
     */
    private function maybe_upgrade() {
        if ( get_option( 'sk_payments_db_version' ) === SK_PAYMENTS_VERSION ) {
            return;
        }

        Activator::create_tables();
    }

    private function define_constants() {
        define( 'SK_PAYMENTS_VERSION', $this->version );
        define( 'SK_PAYMENTS_FILE', __FILE__ );
        define( 'SK_PAYMENTS_PATH', dirname( SK_PAYMENTS_FILE ) );
        define( 'SK_PAYMENTS_INCLUDES', SK_PAYMENTS_PATH . '/includes' );
        define( 'SK_PAYMENTS_URL', plugins_url( '', SK_PAYMENTS_FILE ) );
        define( 'SK_PAYMENTS_ASSETS', SK_PAYMENTS_URL . '/assets' );
        define( 'SK_PAYMENTS_TEMPLATES', SK_PAYMENTS_PATH . '/templates' );
    }

    private function includes() {
        require_once SK_PAYMENTS_INCLUDES . '/Activator.php';
        require_once SK_PAYMENTS_INCLUDES . '/Secret.php';
        require_once SK_PAYMENTS_INCLUDES . '/StoreSettings.php';
        require_once SK_PAYMENTS_INCLUDES . '/QrImage.php';
        require_once SK_PAYMENTS_INCLUDES . '/ClientIp.php';
        require_once SK_PAYMENTS_INCLUDES . '/Admin/AdminPage.php';
        require_once SK_PAYMENTS_INCLUDES . '/Admin/AdminSettings.php';
        require_once SK_PAYMENTS_INCLUDES . '/Chat/PaymentCard.php';
        require_once SK_PAYMENTS_INCLUDES . '/Chat/ChatIntegration.php';
        require_once SK_PAYMENTS_INCLUDES . '/Dashboard/TransactionsPage.php';
        require_once SK_PAYMENTS_INCLUDES . '/NWC/Client.php';
        require_once SK_PAYMENTS_INCLUDES . '/LNDHub/Client.php';
        require_once SK_PAYMENTS_INCLUDES . '/LNURL/Resolver.php';
        require_once SK_PAYMENTS_INCLUDES . '/LNURL/ZapRequest.php';
        require_once SK_PAYMENTS_INCLUDES . '/LNURL/Bolt11Parser.php';
        require_once SK_PAYMENTS_INCLUDES . '/LNURL/ExchangeRate.php';
        require_once SK_PAYMENTS_INCLUDES . '/Onchain/XpubDerivation.php';
        require_once SK_PAYMENTS_INCLUDES . '/Onchain/BlockchainChecker.php';
        require_once SK_PAYMENTS_INCLUDES . '/ProductPage.php';
        require_once SK_PAYMENTS_INCLUDES . '/Commission/Generator.php';
        require_once SK_PAYMENTS_INCLUDES . '/Commission/Enforcement.php';
        require_once SK_PAYMENTS_INCLUDES . '/REST/LnurlPayEndpoint.php';
    }

    public function load_hooks() {
        add_action( 'sk_activated_module_sk_payments', [ $this, 'activate' ] );
        add_action( 'sk_deactivated_module_sk_payments', [ $this, 'deactivate' ] );
        add_filter( 'sk_rest_api_class_map', [ $this, 'register_rest_api' ] );
    }

    private function instances() {
        // Admin settings always load (so the toggle is visible even when disabled).
        new Admin\AdminSettings();

        // Check if payments are enabled in admin settings.
        if ( ! self::is_enabled() ) {
            return;
        }

        new StoreSettings();
        new ProductPage();
        new Admin\AdminPage();
        new Dashboard\TransactionsPage();
        new REST\LnurlPayEndpoint();

        // Chat integration: only if VendorChat is active AND chat integration enabled.
        $chat_enabled = sk_get_option( 'sk_lightning_chat_integration', 'sk_lightning', 'on' ) === 'on';
        $vendor_chat_active = class_exists( 'SK\Core\Dashboard\Modules\VendorChat' ) && get_option( 'dvc_enabled', 'no' ) === 'yes';

        if ( $chat_enabled && $vendor_chat_active ) {
            new Chat\ChatIntegration();
        }

        // Commission system.
        new Commission\Generator();
    }

    /**
     * Check if Lightning Payments are enabled in admin settings.
     */
    public static function is_enabled(): bool {
        return sk_get_option( 'sk_payments_enabled', 'sk_lightning', 'on' ) === 'on';
    }

    public function activate() {
        Activator::activate();
        Commission\Generator::create_table();
        flush_rewrite_rules( true );
    }

    public function deactivate() {
        flush_rewrite_rules( true );
    }

    public function register_rest_api( $class_map ) {
        $class_map[ SK_PAYMENTS_INCLUDES . '/REST/LightningController.php' ] = 'SK\Modules\Payments\REST\LightningController';
        return $class_map;
    }
}
