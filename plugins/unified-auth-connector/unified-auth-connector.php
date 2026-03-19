<?php
/**
 * Plugin Name: Unified Auth Connector
 * Plugin URI: https://satoshiskleinanzeigen.com
 * Description: Links LNURL-Auth and Nostr Login authentication methods to a single WordPress account, allowing users to log in with either method.
 * Version: 1.1.0
 * Author: Satoshi's Kleinanzeigen
 * Author URI: https://satoshiskleinanzeigen.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: unified-auth-connector
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Current plugin version.
 */
define('UNIFIED_AUTH_CONNECTOR_VERSION', '1.1.0');
define('UNIFIED_AUTH_CONNECTOR_PATH', plugin_dir_path(__FILE__));
define('UNIFIED_AUTH_CONNECTOR_URL', plugin_dir_url(__FILE__));

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-unified-auth-connector.php';

/**
 * Check if plugin is enabled
 */
function uac_is_enabled() {
    return get_option('uac_enabled', 'no') === 'yes';
}

/**
 * Begins execution of the plugin.
 */
function run_unified_auth_connector() {
    // Always register the admin settings page so the toggle is accessible even when disabled.
    require_once UNIFIED_AUTH_CONNECTOR_PATH . 'admin/class-admin-settings.php';
    $admin_settings = new UAC_Admin_Settings();
    add_action( 'admin_menu', array( $admin_settings, 'add_admin_menu' ) );
    add_action( 'admin_init', array( $admin_settings, 'register_settings' ) );

    // Only run the full plugin functionality when enabled.
    if ( ! uac_is_enabled() ) {
        return;
    }

    $plugin = new Unified_Auth_Connector();
    $plugin->run();
}

/**
 * @deprecated — kept only so old bookmarks to unified-auth-connector-settings
 * redirect gracefully. The real settings page is now in UAC_Admin_Settings.
 */
function uac_render_settings_page() {
    wp_redirect( admin_url( 'options-general.php?page=unified-auth-connector' ) );
    exit;
}

run_unified_auth_connector();
