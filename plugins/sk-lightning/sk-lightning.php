<?php
/**
 * Plugin Name: SK Lightning
 * Plugin URI:  https://satoshiskleinanzeigen.space
 * Description: Non-custodial Lightning-Zahlungen & Reputation für Satoshis Kleinanzeigen.
 * Version:     1.0.0
 * Author:      Satoshis Kleinanzeigen
 * Text Domain: sk-lightning
 * Requires PHP: 7.4
 * License:     GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

define( 'SK_LIGHTNING_VERSION', '1.0.0' );
define( 'SK_LIGHTNING_FILE', __FILE__ );
define( 'SK_LIGHTNING_DIR', plugin_dir_path( __FILE__ ) );
define( 'SK_LIGHTNING_URL', plugin_dir_url( __FILE__ ) );

/**
 * Check dependencies on activation.
 */
function sk_lightning_activate() {
    if ( ! function_exists( 'is_plugin_active' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    if ( ! is_plugin_active( 'sk-core/sk-core.php' ) && ! class_exists( 'SK_Core' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die(
            'SK Lightning benötigt das Plugin <strong>SK-Core</strong>. Bitte SK-Core zuerst aktivieren.',
            'Plugin-Abhängigkeit fehlt',
            [ 'back_link' => true ]
        );
    }

    // VendorChat muss aktiviert sein (Option dvc_enabled = 'yes').
    if ( get_option( 'dvc_enabled', 'no' ) !== 'yes' ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die(
            'SK Lightning benötigt den <strong>VendorChat</strong>. Bitte unter Einstellungen → Vendor Chat den Chat aktivieren (<code>dvc_enabled = yes</code>).',
            'VendorChat nicht aktiviert',
            [ 'back_link' => true ]
        );
    }

    require_once SK_LIGHTNING_DIR . 'includes/Activator.php';
    SK_Lightning\Activator::activate();
}
register_activation_hook( __FILE__, 'sk_lightning_activate' );

/**
 * Runtime dependency check.
 */
function sk_lightning_check_dependencies() {
    if ( ! class_exists( 'SK_Core' ) || ! is_plugin_active( 'sk-core/sk-core.php' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>SK Lightning:</strong> SK-Core ist nicht aktiv. Plugin wurde deaktiviert.';
            echo '</p></div>';
        } );
        deactivate_plugins( plugin_basename( __FILE__ ) );
        return false;
    }

    if ( get_option( 'dvc_enabled', 'no' ) !== 'yes' ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>SK Lightning:</strong> VendorChat ist nicht aktiviert. Bitte unter Einstellungen → Vendor Chat den Chat aktivieren. Plugin wurde deaktiviert.';
            echo '</p></div>';
        } );
        deactivate_plugins( plugin_basename( __FILE__ ) );
        return false;
    }

    return true;
}

/**
 * Bootstrap the plugin after all plugins loaded.
 */
add_action( 'plugins_loaded', function () {
    if ( ! function_exists( 'is_plugin_active' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    if ( ! sk_lightning_check_dependencies() ) {
        return;
    }

    // Load includes.
    require_once SK_LIGHTNING_DIR . 'includes/Activator.php';
    require_once SK_LIGHTNING_DIR . 'includes/StoreSettings.php';
    require_once SK_LIGHTNING_DIR . 'includes/LNURL/Resolver.php';
    require_once SK_LIGHTNING_DIR . 'includes/LNURL/Bolt11Parser.php';
    require_once SK_LIGHTNING_DIR . 'includes/LNURL/ExchangeRate.php';
    require_once SK_LIGHTNING_DIR . 'includes/REST/LightningController.php';
    require_once SK_LIGHTNING_DIR . 'includes/Chat/ChatIntegration.php';
    require_once SK_LIGHTNING_DIR . 'includes/Dashboard/TransactionsPage.php';
    require_once SK_LIGHTNING_DIR . 'includes/Admin/AdminPage.php';
    require_once SK_LIGHTNING_DIR . 'includes/Reputation/Calculator.php';
    require_once SK_LIGHTNING_DIR . 'includes/Reputation/Cron.php';
    require_once SK_LIGHTNING_DIR . 'includes/NWC/Client.php';
    require_once SK_LIGHTNING_DIR . 'includes/LNDHub/Client.php';
    require_once SK_LIGHTNING_DIR . 'includes/ProofPage.php';

    // Initialize components.
    new SK_Lightning\StoreSettings();
    new SK_Lightning\Chat\ChatIntegration();
    new SK_Lightning\Dashboard\TransactionsPage();
    new SK_Lightning\Admin\AdminPage();
    new SK_Lightning\Reputation\Cron();
    new SK_Lightning\ProofPage();

    // Register REST controller via sk-core's filter.
    add_filter( 'sk_rest_api_class_map', function ( $map ) {
        $map[ SK_LIGHTNING_DIR . 'includes/REST/LightningController.php' ] = '\SK_Lightning\REST\LightningController';
        return $map;
    } );
}, 20 );
