<?php

namespace SK\Modules\Escrow;

defined( 'ABSPATH' ) || exit;

/**
 * On-chain escrow for the instant purchase: 2-of-3 multisig between buyer,
 * seller and marketplace, PSBTs against the external escrow API, keys and
 * signatures in the browser (assets/js/sk-escrow-signer.js).
 *
 * Rides on sk_payments: an escrow is a row in sk_lightning_payments with
 * context "escrow", so it appears in "Käufe/Verkäufe" next to Lightning and
 * onchain purchases, uses the same chat, shipping and commission handling,
 * and adds only what the escrow itself needs (Rows::meta()).
 *
 * Flow: buyer requests (own key) -> seller accepts (own key, payout address)
 * -> API derives the escrow address -> buyer deposits -> seller ships ->
 * buyer confirms receipt and signs the payout -> seller signs -> broadcast.
 * Refunds run the same way in the other direction; disputes go to the admin,
 * who co-signs with the marketplace key.
 */
final class Module {

    public $version;

    public function __construct() {
        $this->version = sk_assets_version( __DIR__ . '/assets' );

        defined( 'WEO_DIR' ) || define( 'WEO_DIR', plugin_dir_path( __FILE__ ) );
        defined( 'WEO_URL' ) || define( 'WEO_URL', plugin_dir_url( __FILE__ ) );
        defined( 'WEO_OPT' ) || define( 'WEO_OPT', 'weo_options' );
        define( 'SK_ESCROW_VERSION', $this->version );

        require_once WEO_DIR . 'includes/helpers.php';
        require_once WEO_DIR . 'includes/EscrowSettings.php';
        require_once WEO_DIR . 'includes/class-escrow-settings.php';

        // Marketplace settings are always available, so the section can be
        // configured before the payments module is switched on.
        new EscrowSettings();
        new \WEO_Settings();

        if ( ! class_exists( 'SK\Modules\Payments\StoreSettings' ) ) {
            return;
        }

        require_once WEO_DIR . 'includes/Rows.php';
        require_once WEO_DIR . 'includes/Notify.php';
        require_once WEO_DIR . 'includes/Purchase.php';
        require_once WEO_DIR . 'includes/Actions.php';
        require_once WEO_DIR . 'includes/Dashboard.php';
        require_once WEO_DIR . 'includes/Cron.php';
        require_once WEO_DIR . 'includes/class-escrow-rest.php';
        require_once WEO_DIR . 'includes/class-escrow-admin.php';

        new Purchase();
        new Actions();
        new Dashboard();
        new Cron();
        new \WEO_REST();

        if ( is_admin() ) {
            new \WEO_Admin();
        }
    }
}
