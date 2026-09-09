<?php

namespace SK\Modules\ContactFeewall;

defined( 'ABSPATH' ) || exit;

class Feewall {

    const FEEWALL_AMOUNT = 21; // Sats

    public function __construct() {
        // Always register hooks - the feature works at vendor level.
        // The admin toggle only controls whether vendors see the setting
        // and whether contact icons actually get locked.

        // Save the per-vendor toggle when the store settings form is submitted.
        add_action( 'sk_store_profile_saved', [ $this, 'save_feewall_setting' ], 20 );

        // Hook into contact icons collection.
        add_filter( 'dkp_contact_icons_collection', [ $this, 'add_feewall_to_icons' ], 10, 4 );

        // AJAX endpoints.
        add_action( 'wp_ajax_cdf_create_invoice', [ $this, 'ajax_create_invoice' ] );
        add_action( 'wp_ajax_nopriv_cdf_create_invoice', [ $this, 'ajax_create_invoice' ] );
        add_action( 'wp_ajax_cdf_check_payment', [ $this, 'ajax_check_payment' ] );
        add_action( 'wp_ajax_nopriv_cdf_check_payment', [ $this, 'ajax_check_payment' ] );

        // Enqueue assets.
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );

        // BTCPay webhook handler.
        add_action( 'rest_api_init', [ $this, 'register_webhook_endpoint' ] );
    }

    /** Global switch, migrated from the old Settings → Contact Details Feewall page. */
    private function is_enabled(): bool {
        return sk_get_option( 'cdf_enabled', Settings::SECTION, 'on' ) === 'on';
    }

    /**
     * Save feewall setting.
     */
    public function save_feewall_setting( $store_id ) {
        // Read fresh from DB to avoid object cache returning stale data.
        wp_cache_delete( $store_id, 'user_meta' );
        clean_user_cache( $store_id );
        $settings = get_user_meta( $store_id, 'sk_profile_settings', true );
        if ( ! is_array( $settings ) ) {
            $settings = [];
        }

        $settings['cdf_enabled'] = isset( $_POST['cdf_enabled'] ) ? '1' : '';

        update_user_meta( $store_id, 'sk_profile_settings', $settings );
    }

    /**
     * Check if vendor has feewall enabled.
     */
    private function is_feewall_enabled( $vendor_id ) {
        $settings = sk_get_store_info( $vendor_id );
        return isset( $settings['cdf_enabled'] ) && $settings['cdf_enabled'] === '1';
    }

    /**
     * Check if user has paid for vendor contact access.
     * This only checks if they have actually paid, NOT if they're vendor/admin.
     * Access is per-vendor (not per-product) - paying once unlocks all products from that vendor.
     */
    private function has_paid_access( $vendor_id ) {
        if ( ! is_user_logged_in() ) {
            $session_key = 'cdf_access_' . $vendor_id;
            return isset( $_COOKIE[ $session_key ] ) && $_COOKIE[ $session_key ] === 'paid';
        }

        $user_id = get_current_user_id();

        // Check transient for paid access.
        $transient_key = 'cdf_access_' . $user_id . '_' . $vendor_id;
        return get_transient( $transient_key ) === 'paid';
    }

    /**
     * Check if user can bypass payment (only product owner).
     */
    private function can_bypass_payment( $vendor_id ) {
        if ( ! is_user_logged_in() ) {
            return false;
        }

        $user_id = get_current_user_id();

        // Check if user is the vendor/owner of this product.
        return $user_id === intval( $vendor_id );
    }

    /**
     * Add feewall overlay to contact icons.
     */
    public function add_feewall_to_icons( $icons, $vendor_id, $product_id, $context ) {
        // Only apply on product pages (loop and single).
        if ( ! in_array( $context, [ 'loop', 'single' ], true ) ) {
            return $icons;
        }

        // Check if global setting is enabled.
        if ( ! $this->is_enabled() ) {
            return $icons;
        }

        // Check if vendor has feewall enabled.
        if ( ! $this->is_feewall_enabled( $vendor_id ) ) {
            return $icons;
        }

        // Check if user already has paid access (for non-logged-in or regular users).
        // Note: Vendor and admin checks happen in AJAX handler for silent bypass.
        // Access is per-vendor, so all products from same vendor are unlocked.
        if ( $this->has_paid_access( $vendor_id ) ) {
            return $icons;
        }

        // Replace all icons with locked versions.
        $locked_icons = [];
        foreach ( $icons as $icon ) {
            // Skip chat icon - that should remain accessible.
            if ( isset( $icon['key'] ) && $icon['key'] === 'chat' ) {
                $locked_icons[] = $icon;
                continue;
            }

            $locked_icons[] = [
                'href'       => '#cdf-locked',
                'title'      => __( '🔒 Zahle 21 Sats um Kontaktdetails freizuschalten', 'sk-core' ),
                'class'      => $icon['class'],
                'key'        => $icon['key'] . '-locked',
                'cdf_locked' => true, // Mark as locked for CSS styling.
                'data'       => [
                    'vendor-id'            => $vendor_id,
                    'product-id'           => $product_id,
                    'original-key'         => $icon['key'],
                    'original-href'        => $icon['href'],        // Store original link.
                    'original-title'       => $icon['title'],       // Store original title.
                    'original-class'       => $icon['class'] ?? '', // Store original icon class.
                    'original-icon-class'  => isset( $icon['icon_class'] ) ? $icon['icon_class'] : '', // Alternative naming.
                ],
            ];
        }

        return $locked_icons;
    }

    /**
     * Create BTCPay invoice via AJAX.
     */
    public function ajax_create_invoice() {
        check_ajax_referer( 'cdf_nonce', 'nonce' );

        $vendor_id  = isset( $_POST['vendor_id'] ) ? intval( $_POST['vendor_id'] ) : 0;
        $product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;

        if ( ! $vendor_id ) {
            wp_send_json_error( [ 'message' => __( 'Ungültige Verkäufer-ID', 'sk-core' ) ] );
        }

        // Check if user can bypass payment (vendor or admin).
        if ( $this->can_bypass_payment( $vendor_id ) ) {
            // Silently grant access to vendor/admin (all products from this vendor).
            $this->grant_access( $vendor_id );
            wp_send_json_success( [
                'invoice_id'    => 'bypass_' . time(),
                'checkout_link' => '#', // Not used for bypass.
                'bypassed'      => true,
            ] );
        }

        // Get vendor info.
        $vendor = get_userdata( $vendor_id );
        if ( ! $vendor ) {
            wp_send_json_error( [ 'message' => __( 'Verkäufer nicht gefunden', 'sk-core' ) ] );
        }

        $product_name = $product_id ? get_the_title( $product_id ) : __( 'Kontaktdetails', 'sk-core' );

        // Create invoice with BTCPay.
        $invoice_data = $this->create_btcpay_invoice(
            self::FEEWALL_AMOUNT,
            __( 'Kontaktzugriff:', 'sk-core' ) . ' ' . $vendor->display_name . ' - ' . $product_name,
            [
                'vendor_id'  => $vendor_id,
                'product_id' => $product_id,
                'buyer_id'   => get_current_user_id(),
            ]
        );

        if ( is_wp_error( $invoice_data ) ) {
            wp_send_json_error( [ 'message' => $invoice_data->get_error_message() ] );
        }

        wp_send_json_success( [
            'invoice_id'    => $invoice_data['id'],
            'checkout_link' => $invoice_data['checkoutLink'],
        ] );
    }

    /**
     * Check payment status via AJAX.
     */
    public function ajax_check_payment() {
        check_ajax_referer( 'cdf_nonce', 'nonce' );

        $invoice_id = isset( $_POST['invoice_id'] ) ? sanitize_text_field( $_POST['invoice_id'] ) : '';
        $vendor_id  = isset( $_POST['vendor_id'] ) ? intval( $_POST['vendor_id'] ) : 0;

        if ( ! $invoice_id ) {
            wp_send_json_error( [ 'message' => __( 'Ungültige Invoice-ID', 'sk-core' ) ] );
        }

        $status = $this->check_btcpay_invoice_status( $invoice_id );

        if ( is_wp_error( $status ) ) {
            wp_send_json_error( [ 'message' => $status->get_error_message() ] );
        }

        if ( $status === 'Settled' || $status === 'Processing' ) {
            // Grant access to all products from this vendor.
            $this->grant_access( $vendor_id );

            wp_send_json_success( [
                'status'  => 'paid',
                'message' => __( 'Zahlung erfolgreich! Kontakte werden freigeschaltet...', 'sk-core' ),
            ] );
        } else {
            wp_send_json_success( [
                'status'  => 'pending',
                'message' => __( 'Warte auf Zahlungsbestätigung...', 'sk-core' ),
            ] );
        }
    }

    /**
     * Grant access to contact details.
     * Access is granted per-vendor for 24 hours (not per-product).
     * This means paying once unlocks all products from that vendor for 24h.
     */
    private function grant_access( $vendor_id ) {
        if ( ! is_user_logged_in() ) {
            // Set cookie for non-logged-in users (24 hours).
            $session_key = 'cdf_access_' . $vendor_id;
            $expires     = time() + DAY_IN_SECONDS;

            // Set cookie with options array for PHP 7.3+ compatibility.
            // This ensures SameSite is set which is required by modern browsers.
            $cookie_options = [
                'expires'  => $expires,
                'path'     => '/',
                'domain'   => '', // Empty = current domain.
                'secure'   => is_ssl(),
                'httponly' => false, // Allow JavaScript to read it.
                'samesite' => 'Lax', // Required by modern browsers.
            ];

            setcookie( $session_key, 'paid', $cookie_options );

            // Also set in $_COOKIE immediately so it's available in the same request.
            $_COOKIE[ $session_key ] = 'paid';
        } else {
            // Set transient for logged-in users (24 hours).
            $user_id        = get_current_user_id();
            $transient_key  = 'cdf_access_' . $user_id . '_' . $vendor_id;
            set_transient( $transient_key, 'paid', DAY_IN_SECONDS );
        }
    }

    /**
     * Create BTCPay invoice.
     */
    private function create_btcpay_invoice( $amount_sats, $description, $metadata = [] ) {
        // Get BTCPay settings from WooCommerce Greenfield Gateway.
        $btcpay_url      = get_option( 'btcpay_gf_url' );
        $btcpay_api_key  = get_option( 'btcpay_gf_api_key' );
        $btcpay_store_id = get_option( 'btcpay_gf_store_id' );

        if ( ! $btcpay_url || ! $btcpay_api_key || ! $btcpay_store_id ) {
            return new \WP_Error( 'btcpay_config', __( 'BTCPay ist nicht konfiguriert', 'sk-core' ) );
        }

        // Convert sats to BTC.
        $amount_btc = $amount_sats / 100000000;

        $invoice_data = [
            'amount'   => number_format( $amount_btc, 8, '.', '' ),
            'currency' => 'BTC',
            'metadata' => array_merge( $metadata, [
                'orderId' => 'feewall_' . time(),
                'itemDesc' => $description,
            ] ),
            'checkout' => [
                'redirectURL' => home_url( '/?cdf_payment_complete=1' ),
                'speedPolicy' => 'HighSpeed',
            ],
        ];

        $response = wp_remote_post(
            rtrim( $btcpay_url, '/' ) . '/api/v1/stores/' . $btcpay_store_id . '/invoices',
            [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'token ' . $btcpay_api_key,
                ],
                'body'    => wp_json_encode( $invoice_data ),
                'timeout' => 30,
            ]
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
            /* translators: %s: error message from BTCPay */
            return new \WP_Error( 'btcpay_error', sprintf( __( 'Fehler bei Invoice-Erstellung: %s', 'sk-core' ), $body['message'] ?? __( 'Unbekannter Fehler', 'sk-core' ) ) );
        }

        return $body;
    }

    /**
     * Check BTCPay invoice status.
     */
    private function check_btcpay_invoice_status( $invoice_id ) {
        $btcpay_url      = get_option( 'btcpay_gf_url' );
        $btcpay_api_key  = get_option( 'btcpay_gf_api_key' );
        $btcpay_store_id = get_option( 'btcpay_gf_store_id' );

        if ( ! $btcpay_url || ! $btcpay_api_key || ! $btcpay_store_id ) {
            return new \WP_Error( 'btcpay_config', __( 'BTCPay ist nicht konfiguriert', 'sk-core' ) );
        }

        $response = wp_remote_get(
            rtrim( $btcpay_url, '/' ) . '/api/v1/stores/' . $btcpay_store_id . '/invoices/' . $invoice_id,
            [
                'headers' => [
                    'Authorization' => 'token ' . $btcpay_api_key,
                ],
                'timeout' => 15,
            ]
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return new \WP_Error( 'btcpay_error', __( 'Fehler beim Abrufen des Invoice-Status', 'sk-core' ) );
        }

        return $body['status'] ?? 'Unknown';
    }

    /**
     * Register webhook endpoint for BTCPay callbacks. Route unchanged from
     * the standalone plugin (cdf/v1/webhook) — this is the URL already
     * configured on the BTCPay server side.
     */
    public function register_webhook_endpoint() {
        register_rest_route( 'cdf/v1', '/webhook', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_webhook' ],
            'permission_callback' => '__return_true',
        ] );
    }

    /**
     * Handle BTCPay webhook.
     */
    public function handle_webhook( $request ) {
        $body = $request->get_json_params();

        if ( ! isset( $body['invoiceId'] ) || ! isset( $body['type'] ) ) {
            return new \WP_REST_Response( [ 'error' => 'Invalid webhook data' ], 400 );
        }

        // Only process InvoiceSettled events.
        if ( $body['type'] !== 'InvoiceSettled' && $body['type'] !== 'InvoiceProcessing' ) {
            return new \WP_REST_Response( [ 'success' => true ], 200 );
        }

        $invoice_id = $body['invoiceId'];

        // Get invoice details to extract metadata.
        $invoice = $this->get_btcpay_invoice( $invoice_id );

        if ( ! is_wp_error( $invoice ) && isset( $invoice['metadata'] ) ) {
            $vendor_id = isset( $invoice['metadata']['vendor_id'] ) ? intval( $invoice['metadata']['vendor_id'] ) : 0;

            if ( $vendor_id ) {
                // Grant access to all products from this vendor.
                $this->grant_access( $vendor_id );
            }
        }

        return new \WP_REST_Response( [ 'success' => true ], 200 );
    }

    /**
     * Get BTCPay invoice details.
     */
    private function get_btcpay_invoice( $invoice_id ) {
        $btcpay_url      = get_option( 'btcpay_gf_url' );
        $btcpay_api_key  = get_option( 'btcpay_gf_api_key' );
        $btcpay_store_id = get_option( 'btcpay_gf_store_id' );

        if ( ! $btcpay_url || ! $btcpay_api_key || ! $btcpay_store_id ) {
            return new \WP_Error( 'btcpay_config', __( 'BTCPay ist nicht konfiguriert', 'sk-core' ) );
        }

        $response = wp_remote_get(
            rtrim( $btcpay_url, '/' ) . '/api/v1/stores/' . $btcpay_store_id . '/invoices/' . $invoice_id,
            [
                'headers' => [
                    'Authorization' => 'token ' . $btcpay_api_key,
                ],
                'timeout' => 15,
            ]
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        return json_decode( wp_remote_retrieve_body( $response ), true );
    }

    /**
     * Enqueue assets.
     */
    public function enqueue_assets() {
        if ( ! $this->is_enabled() ) {
            return;
        }

        if ( ! is_product() && ! is_shop() && ! is_product_category() ) {
            return;
        }

        // Get BTCPay settings.
        $btcpay_url = get_option( 'btcpay_gf_url' );

        // Load BTCPay modal library (if BTCPay is configured).
        if ( $btcpay_url ) {
            wp_enqueue_script(
                'btcpay-modal-library',
                rtrim( $btcpay_url, '/' ) . '/modal/btcpay.js',
                [],
                SK_CONTACT_FEEWALL_VERSION,
                true
            );
        }

        wp_enqueue_style(
            'contact-details-feewall',
            SK_CONTACT_FEEWALL_URL . '/assets/css/feewall.css',
            [],
            SK_CONTACT_FEEWALL_VERSION
        );

        wp_enqueue_script(
            'contact-details-feewall',
            SK_CONTACT_FEEWALL_URL . '/assets/js/feewall.js',
            [ 'jquery', 'btcpay-modal-library' ],
            SK_CONTACT_FEEWALL_VERSION,
            true
        );

        wp_localize_script( 'contact-details-feewall', 'cdfData', [
            'ajaxurl'   => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'cdf_nonce' ),
            'amount'    => self::FEEWALL_AMOUNT,
            'btcpayUrl' => $btcpay_url,
            'i18n'      => [
                'creating' => __( 'Erstelle Rechnung...', 'sk-core' ),
                'redirect' => __( 'Weiterleitung zu BTCPay...', 'sk-core' ),
                'checking' => __( 'Prüfe Zahlung...', 'sk-core' ),
                'success'  => __( 'Zahlung erfolgreich!', 'sk-core' ),
                'error'    => __( 'Fehler beim Erstellen der Rechnung', 'sk-core' ),
            ],
        ] );
    }
}
