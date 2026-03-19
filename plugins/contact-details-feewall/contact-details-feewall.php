<?php
/**
 * Plugin Name: Contact Details Feewall
 * Description: Schützt Kontaktdetails von Verkäufern mit einer 21 Sats Paywall via BTCPay. Die Zahlung unterstützt die Weiterentwicklung von Satoshi's Kleinanzeigen.
 * Version: 1.0.0
 * Author: Satoshi's Kleinanzeigen
 * Author URI: https://satoshiskleinanzeigen.com
 * License: GPL-2.0+
 * Text Domain: contact-details-feewall
 */

if (!defined('ABSPATH')) exit;

define('CDF_VERSION', '1.0.0');
define('CDF_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CDF_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CDF_FEEWALL_AMOUNT', 21); // Sats

class Contact_Details_Feewall {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Always register hooks - the plugin works at vendor level
        // The admin toggle only controls whether vendors see the setting

        // Feewall setting is now integrated directly in Contact Details plugin
        // Just save the setting when form is submitted
        add_action('sk_store_profile_saved', array($this, 'save_feewall_setting'), 20);

        // Hook into contact icons collection
        add_filter('dkp_contact_icons_collection', array($this, 'add_feewall_to_icons'), 10, 4);

        // AJAX endpoints
        add_action('wp_ajax_cdf_create_invoice', array($this, 'ajax_create_invoice'));
        add_action('wp_ajax_nopriv_cdf_create_invoice', array($this, 'ajax_create_invoice'));
        add_action('wp_ajax_cdf_check_payment', array($this, 'ajax_check_payment'));
        add_action('wp_ajax_nopriv_cdf_check_payment', array($this, 'ajax_check_payment'));

        // Enqueue assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));

        // BTCPay webhook handler
        add_action('rest_api_init', array($this, 'register_webhook_endpoint'));

        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Check if plugin is enabled
     */
    private function is_enabled() {
        return get_option('cdf_enabled', 'yes') === 'yes';
    }

    /**
     * Add feewall setting to Dokan store settings form
     */
    public function add_feewall_setting($current_user, $profile_info) {
        if (!is_array($profile_info)) {
            $profile_info = array();
        }

        $feewall_enabled = isset($profile_info['cdf_enabled']) && $profile_info['cdf_enabled'] === '1';
        ?>
        <div class="sk-form-group">
            <label class="sk-w3 sk-control-label">
                <strong>⚡ Kontaktdetails Paywall</strong>
            </label>
            <div class="sk-w5">
                <label style="display: block; margin-bottom: 10px;">
                    <input type="checkbox" name="cdf_enabled" value="1" <?php checked($feewall_enabled, true); ?>>
                    <strong>Paywall aktivieren (21 Sats)</strong>
                </label>
                <p class="description" style="margin-top: 8px; color: #666;">
                    Interessenten zahlen 21 Sats via BTCPay, um deine Kontaktdaten zu sehen. Dies reduziert Spam, und schützt deine Kontakten vor Website Crawlern und Scrappern. Ausserdem unterstützt du damit Satoshis Kleinanzeigen.
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Save feewall setting
     */
    public function save_feewall_setting($store_id) {
        // Read fresh from DB to avoid object cache returning stale data
        wp_cache_delete($store_id, 'user_meta');
        clean_user_cache($store_id);
        $settings = get_user_meta($store_id, 'sk_profile_settings', true);
        if (!is_array($settings)) {
            $settings = array();
        }

        $settings['cdf_enabled'] = isset($_POST['cdf_enabled']) ? '1' : '';

        update_user_meta($store_id, 'sk_profile_settings', $settings);
    }

    /**
     * Check if vendor has feewall enabled
     */
    private function is_feewall_enabled($vendor_id) {
        $settings = sk_get_store_info($vendor_id);
        return isset($settings['cdf_enabled']) && $settings['cdf_enabled'] === '1';
    }

    /**
     * Check if user has paid for vendor contact access
     * This only checks if they have actually paid, NOT if they're vendor/admin
     * Access is per-vendor (not per-product) - paying once unlocks all products from that vendor
     */
    private function has_paid_access($vendor_id) {
        if (!is_user_logged_in()) {
            $session_key = 'cdf_access_' . $vendor_id;
            return isset($_COOKIE[$session_key]) && $_COOKIE[$session_key] === 'paid';
        }

        $user_id = get_current_user_id();

        // Check transient for paid access
        $transient_key = 'cdf_access_' . $user_id . '_' . $vendor_id;
        return get_transient($transient_key) === 'paid';
    }

    /**
     * Check if user can bypass payment (only product owner)
     */
    private function can_bypass_payment($vendor_id) {
        if (!is_user_logged_in()) {
            return false;
        }

        $user_id = get_current_user_id();

        // Check if user is the vendor/owner of this product
        return $user_id === intval($vendor_id);
    }

    /**
     * Add feewall overlay to contact icons
     */
    public function add_feewall_to_icons($icons, $vendor_id, $product_id, $context) {
        // Only apply on product pages (loop and single)
        if (!in_array($context, array('loop', 'single'), true)) {
            return $icons;
        }

        // Check if global plugin is enabled
        if (!$this->is_enabled()) {
            return $icons;
        }

        // Check if vendor has feewall enabled
        if (!$this->is_feewall_enabled($vendor_id)) {
            return $icons;
        }

        // Check if user already has paid access (for non-logged-in or regular users)
        // Note: Vendor and admin checks happen in AJAX handler for silent bypass
        // Access is per-vendor, so all products from same vendor are unlocked
        if ($this->has_paid_access($vendor_id)) {
            return $icons;
        }

        // Replace all icons with locked versions
        $locked_icons = array();
        foreach ($icons as $icon) {
            // Skip chat icon - that should remain accessible
            if (isset($icon['key']) && $icon['key'] === 'chat') {
                $locked_icons[] = $icon;
                continue;
            }

            $locked_icons[] = array(
                'href' => '#cdf-locked',
                'title' => '🔒 Zahle 21 Sats um Kontaktdetails freizuschalten',
                'class' => $icon['class'],
                'key' => $icon['key'] . '-locked',
                'cdf_locked' => true,  // Mark as locked for CSS styling
                'data' => array(
                    'vendor-id' => $vendor_id,
                    'product-id' => $product_id,
                    'original-key' => $icon['key'],
                    'original-href' => $icon['href'],        // Store original link
                    'original-title' => $icon['title'],      // Store original title
                    'original-class' => $icon['class'] ?? '', // Store original icon class
                    'original-icon-class' => isset($icon['icon_class']) ? $icon['icon_class'] : '' // Alternative naming
                )
            );
        }

        return $locked_icons;
    }

    /**
     * Create BTCPay invoice via AJAX
     */
    public function ajax_create_invoice() {
        check_ajax_referer('cdf_nonce', 'nonce');

        $vendor_id = isset($_POST['vendor_id']) ? intval($_POST['vendor_id']) : 0;
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;

        if (!$vendor_id) {
            wp_send_json_error(array('message' => 'Ungültige Verkäufer-ID'));
        }

        // Check if user can bypass payment (vendor or admin)
        if ($this->can_bypass_payment($vendor_id)) {
            // Silently grant access to vendor/admin (all products from this vendor)
            $this->grant_access($vendor_id);
            wp_send_json_success(array(
                'invoice_id' => 'bypass_' . time(),
                'checkout_link' => '#',  // Not used for bypass
                'bypassed' => true
            ));
        }

        // Get vendor info
        $vendor = get_userdata($vendor_id);
        if (!$vendor) {
            wp_send_json_error(array('message' => 'Verkäufer nicht gefunden'));
        }

        $product_name = $product_id ? get_the_title($product_id) : 'Kontaktdetails';

        // Create invoice with BTCPay
        $invoice_data = $this->create_btcpay_invoice(
            CDF_FEEWALL_AMOUNT,
            'Kontaktzugriff: ' . $vendor->display_name . ' - ' . $product_name,
            array(
                'vendor_id' => $vendor_id,
                'product_id' => $product_id,
                'buyer_id' => get_current_user_id()
            )
        );

        if (is_wp_error($invoice_data)) {
            wp_send_json_error(array('message' => $invoice_data->get_error_message()));
        }

        wp_send_json_success(array(
            'invoice_id' => $invoice_data['id'],
            'checkout_link' => $invoice_data['checkoutLink']
        ));
    }

    /**
     * Check payment status via AJAX
     */
    public function ajax_check_payment() {
        check_ajax_referer('cdf_nonce', 'nonce');

        $invoice_id = isset($_POST['invoice_id']) ? sanitize_text_field($_POST['invoice_id']) : '';
        $vendor_id = isset($_POST['vendor_id']) ? intval($_POST['vendor_id']) : 0;
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;

        if (!$invoice_id) {
            wp_send_json_error(array('message' => 'Ungültige Invoice-ID'));
        }

        $status = $this->check_btcpay_invoice_status($invoice_id);

        if (is_wp_error($status)) {
            wp_send_json_error(array('message' => $status->get_error_message()));
        }

        if ($status === 'Settled' || $status === 'Processing') {
            // Grant access to all products from this vendor
            $this->grant_access($vendor_id);

            wp_send_json_success(array(
                'status' => 'paid',
                'message' => 'Zahlung erfolgreich! Kontakte werden freigeschaltet...'
            ));
        } else {
            wp_send_json_success(array(
                'status' => 'pending',
                'message' => 'Warte auf Zahlungsbestätigung...'
            ));
        }
    }

    /**
     * Grant access to contact details
     * Access is granted per-vendor for 24 hours (not per-product)
     * This means paying once unlocks all products from that vendor for 24h
     */
    private function grant_access($vendor_id) {
        if (!is_user_logged_in()) {
            // Set cookie for non-logged-in users (24 hours)
            $session_key = 'cdf_access_' . $vendor_id;
            $expires = time() + DAY_IN_SECONDS;

            // Set cookie with options array for PHP 7.3+ compatibility
            // This ensures SameSite is set which is required by modern browsers
            $cookie_options = array(
                'expires' => $expires,
                'path' => '/',
                'domain' => '',  // Empty = current domain
                'secure' => is_ssl(),
                'httponly' => false,  // Allow JavaScript to read it
                'samesite' => 'Lax'  // Required by modern browsers
            );

            setcookie($session_key, 'paid', $cookie_options);

            // Also set in $_COOKIE immediately so it's available in the same request
            $_COOKIE[$session_key] = 'paid';
        } else {
            // Set transient for logged-in users (24 hours)
            $user_id = get_current_user_id();
            $transient_key = 'cdf_access_' . $user_id . '_' . $vendor_id;
            set_transient($transient_key, 'paid', DAY_IN_SECONDS);
        }
    }

    /**
     * Create BTCPay invoice
     */
    private function create_btcpay_invoice($amount_sats, $description, $metadata = array()) {
        // Get BTCPay settings from WooCommerce Greenfield Gateway
        $btcpay_url = get_option('btcpay_gf_url');
        $btcpay_api_key = get_option('btcpay_gf_api_key');
        $btcpay_store_id = get_option('btcpay_gf_store_id');

        if (!$btcpay_url || !$btcpay_api_key || !$btcpay_store_id) {
            return new WP_Error('btcpay_config', 'BTCPay ist nicht konfiguriert');
        }

        // Convert sats to BTC
        $amount_btc = $amount_sats / 100000000;

        $invoice_data = array(
            'amount' => number_format($amount_btc, 8, '.', ''),
            'currency' => 'BTC',
            'metadata' => array_merge($metadata, array(
                'orderId' => 'feewall_' . time(),
                'itemDesc' => $description
            )),
            'checkout' => array(
                'redirectURL' => home_url('/?cdf_payment_complete=1'),
                'speedPolicy' => 'HighSpeed'
            )
        );

        $response = wp_remote_post(
            rtrim($btcpay_url, '/') . '/api/v1/stores/' . $btcpay_store_id . '/invoices',
            array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Authorization' => 'token ' . $btcpay_api_key
                ),
                'body' => wp_json_encode($invoice_data),
                'timeout' => 30
            )
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (wp_remote_retrieve_response_code($response) !== 200) {
            return new WP_Error('btcpay_error', 'Fehler bei Invoice-Erstellung: ' . ($body['message'] ?? 'Unbekannter Fehler'));
        }

        return $body;
    }

    /**
     * Check BTCPay invoice status
     */
    private function check_btcpay_invoice_status($invoice_id) {
        $btcpay_url = get_option('btcpay_gf_url');
        $btcpay_api_key = get_option('btcpay_gf_api_key');
        $btcpay_store_id = get_option('btcpay_gf_store_id');

        if (!$btcpay_url || !$btcpay_api_key || !$btcpay_store_id) {
            return new WP_Error('btcpay_config', 'BTCPay ist nicht konfiguriert');
        }

        $response = wp_remote_get(
            rtrim($btcpay_url, '/') . '/api/v1/stores/' . $btcpay_store_id . '/invoices/' . $invoice_id,
            array(
                'headers' => array(
                    'Authorization' => 'token ' . $btcpay_api_key
                ),
                'timeout' => 15
            )
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (wp_remote_retrieve_response_code($response) !== 200) {
            return new WP_Error('btcpay_error', 'Fehler beim Abrufen des Invoice-Status');
        }

        return $body['status'] ?? 'Unknown';
    }

    /**
     * Register webhook endpoint for BTCPay callbacks
     */
    public function register_webhook_endpoint() {
        register_rest_route('cdf/v1', '/webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_webhook'),
            'permission_callback' => '__return_true'
        ));
    }

    /**
     * Handle BTCPay webhook
     */
    public function handle_webhook($request) {
        $body = $request->get_json_params();

        if (!isset($body['invoiceId']) || !isset($body['type'])) {
            return new WP_REST_Response(array('error' => 'Invalid webhook data'), 400);
        }

        // Only process InvoiceSettled events
        if ($body['type'] !== 'InvoiceSettled' && $body['type'] !== 'InvoiceProcessing') {
            return new WP_REST_Response(array('success' => true), 200);
        }

        $invoice_id = $body['invoiceId'];

        // Get invoice details to extract metadata
        $invoice = $this->get_btcpay_invoice($invoice_id);

        if (!is_wp_error($invoice) && isset($invoice['metadata'])) {
            $vendor_id = isset($invoice['metadata']['vendor_id']) ? intval($invoice['metadata']['vendor_id']) : 0;

            if ($vendor_id) {
                // Grant access to all products from this vendor
                $this->grant_access($vendor_id);
            }
        }

        return new WP_REST_Response(array('success' => true), 200);
    }

    /**
     * Get BTCPay invoice details
     */
    private function get_btcpay_invoice($invoice_id) {
        $btcpay_url = get_option('btcpay_gf_url');
        $btcpay_api_key = get_option('btcpay_gf_api_key');
        $btcpay_store_id = get_option('btcpay_gf_store_id');

        if (!$btcpay_url || !$btcpay_api_key || !$btcpay_store_id) {
            return new WP_Error('btcpay_config', 'BTCPay ist nicht konfiguriert');
        }

        $response = wp_remote_get(
            rtrim($btcpay_url, '/') . '/api/v1/stores/' . $btcpay_store_id . '/invoices/' . $invoice_id,
            array(
                'headers' => array(
                    'Authorization' => 'token ' . $btcpay_api_key
                ),
                'timeout' => 15
            )
        );

        if (is_wp_error($response)) {
            return $response;
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    /**
     * Enqueue assets
     */
    public function enqueue_assets() {
        // Check if global plugin is enabled
        if (!$this->is_enabled()) {
            return;
        }

        if (!is_product() && !is_shop() && !is_product_category()) {
            return;
        }

        // Get BTCPay settings
        $btcpay_url = get_option('btcpay_gf_url');

        // Load BTCPay modal library (if BTCPay is configured)
        if ($btcpay_url) {
            wp_enqueue_script(
                'btcpay-modal-library',
                rtrim($btcpay_url, '/') . '/modal/btcpay.js',
                array(),
                CDF_VERSION,
                true
            );
        }

        wp_enqueue_style(
            'contact-details-feewall',
            CDF_PLUGIN_URL . 'assets/css/feewall.css',
            array(),
            CDF_VERSION
        );

        wp_enqueue_script(
            'contact-details-feewall',
            CDF_PLUGIN_URL . 'assets/js/feewall.js',
            array('jquery', 'btcpay-modal-library'),
            CDF_VERSION,
            true
        );

        wp_localize_script('contact-details-feewall', 'cdfData', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cdf_nonce'),
            'amount' => CDF_FEEWALL_AMOUNT,
            'btcpayUrl' => $btcpay_url,
            'i18n' => array(
                'creating' => 'Erstelle Rechnung...',
                'redirect' => 'Weiterleitung zu BTCPay...',
                'checking' => 'Prüfe Zahlung...',
                'success' => 'Zahlung erfolgreich!',
                'error' => 'Fehler beim Erstellen der Rechnung'
            )
        ));
    }

    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        add_options_page(
            __('Contact Details Feewall Einstellungen', 'contact-details-feewall'),
            __('Contact Details Feewall', 'contact-details-feewall'),
            'manage_options',
            'contact-details-feewall-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('cdf_settings', 'cdf_enabled');
    }

    /**
     * Render admin settings page
     */
    public function render_settings_page() {
        // Save settings if form submitted
        if (isset($_POST['cdf_save_settings']) && check_admin_referer('cdf_settings_nonce')) {
            $enabled = isset($_POST['cdf_enabled']) ? 'yes' : 'no';
            update_option('cdf_enabled', $enabled);
            // Clear cache when settings change
            if (function_exists('wpfc_clear_all_cache')) {
                wpfc_clear_all_cache();
            }
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Einstellungen gespeichert.', 'contact-details-feewall') . '</p></div>';
        }

        $enabled = get_option('cdf_enabled', 'yes') === 'yes';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="cdf-admin-container">
                <form method="post" action="">
                    <?php wp_nonce_field('cdf_settings_nonce'); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="cdf_enabled"><?php _e('Paywall-System Status', 'contact-details-feewall'); ?></label>
                            </th>
                            <td>
                                <label class="cdf-toggle-switch">
                                    <input type="checkbox" id="cdf_enabled" name="cdf_enabled" value="yes" <?php checked($enabled, true); ?>>
                                    <span class="cdf-toggle-slider"></span>
                                </label>
                                <p class="description">
                                    <?php if ($enabled) : ?>
                                        <strong style="color: #46b450;">✓ <?php _e('Paywall-System ist aktiviert', 'contact-details-feewall'); ?></strong>
                                        <br>
                                        <?php _e('Verkäufer können ihre Kontaktdaten mit einer 21 Sats Paywall schützen.', 'contact-details-feewall'); ?>
                                    <?php else : ?>
                                        <strong style="color: #dc3232;">✗ <?php _e('Paywall-System ist deaktiviert', 'contact-details-feewall'); ?></strong>
                                        <br>
                                        <?php _e('Die Paywall-Funktion ist nicht verfügbar.', 'contact-details-feewall'); ?>
                                    <?php endif; ?>
                                </p>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" name="cdf_save_settings" class="button button-primary button-large">
                            <?php _e('Einstellungen speichern', 'contact-details-feewall'); ?>
                        </button>
                    </p>
                </form>

                <hr>

                <div class="cdf-admin-info">
                    <h2><?php _e('Contact Details Feewall Informationen', 'contact-details-feewall'); ?></h2>

                    <div class="cdf-info-grid">
                        <div class="cdf-info-card">
                            <h3><i class="dashicons dashicons-shield"></i> <?php _e('Schutz vor Spam', 'contact-details-feewall'); ?></h3>
                            <p><?php _e('Verkäufer können ihre Kontaktdaten mit einer 21 Sats Paywall schützen. Dies verhindert automatisches Scraping durch Bots.', 'contact-details-feewall'); ?></p>
                        </div>

                        <div class="cdf-info-card">
                            <h3><i class="dashicons dashicons-bitcoin"></i> <?php _e('BTCPay Integration', 'contact-details-feewall'); ?></h3>
                            <p><?php _e('Die Zahlung erfolgt über BTCPay Server (Lightning oder On-Chain). Stellen Sie sicher, dass BTCPay korrekt konfiguriert ist.', 'contact-details-feewall'); ?></p>
                        </div>

                        <div class="cdf-info-card">
                            <h3><i class="dashicons dashicons-admin-users"></i> <?php _e('Verkäufer-Kontrolle', 'contact-details-feewall'); ?></h3>
                            <p><?php _e('Verkäufer können die Paywall in ihren Dokan-Einstellungen unter "Shop" aktivieren oder deaktivieren.', 'contact-details-feewall'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <style>
            .cdf-admin-container {
                max-width: 900px;
                margin-top: 20px;
            }

            .cdf-toggle-switch {
                position: relative;
                display: inline-block;
                width: 60px;
                height: 34px;
            }

            .cdf-toggle-switch input {
                opacity: 0;
                width: 0;
                height: 0;
            }

            .cdf-toggle-slider {
                position: absolute;
                cursor: pointer;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: #ccc;
                transition: .4s;
                border-radius: 34px;
            }

            .cdf-toggle-slider:before {
                position: absolute;
                content: "";
                height: 26px;
                width: 26px;
                left: 4px;
                bottom: 4px;
                background-color: white;
                transition: .4s;
                border-radius: 50%;
            }

            input:checked + .cdf-toggle-slider {
                background-color: #f7931a;
            }

            input:focus + .cdf-toggle-slider {
                box-shadow: 0 0 1px #f7931a;
            }

            input:checked + .cdf-toggle-slider:before {
                transform: translateX(26px);
            }

            .cdf-admin-info {
                margin-top: 30px;
            }

            .cdf-info-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
                margin-top: 20px;
            }

            .cdf-info-card {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                padding: 20px;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
            }

            .cdf-info-card h3 {
                margin-top: 0;
                display: flex;
                align-items: center;
                gap: 8px;
                color: #1d2327;
            }

            .cdf-info-card .dashicons {
                color: #f7931a;
            }

            .cdf-info-card p {
                color: #50575e;
                line-height: 1.6;
            }
        </style>
        <?php
    }
}

/**
 * Clear WP Fastest Cache when plugin is activated or deactivated
 */
function cdf_clear_wp_fastest_cache() {
    if (function_exists('wpfc_clear_all_cache')) {
        wpfc_clear_all_cache();
    }
}

register_activation_hook(__FILE__, 'cdf_clear_wp_fastest_cache');
register_deactivation_hook(__FILE__, 'cdf_clear_wp_fastest_cache');

// Initialize plugin
Contact_Details_Feewall::get_instance();
