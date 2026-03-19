<?php

namespace SK\Modules\Payments;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the "Sofortkauf" button on single product pages.
 * Works independently of VendorChat.
 */
class ProductPage {

    public function __construct() {
        add_action( 'woocommerce_single_product_summary', [ $this, 'render_button' ], 30 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );

        // Onchain AJAX handlers.
        add_action( 'wp_ajax_skp_create_onchain_payment', [ $this, 'ajax_create_onchain_payment' ] );
    }

    public function render_button() {
        if ( ! is_user_logged_in() ) {
            return;
        }

        global $product;
        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
            return;
        }

        $vendor_id  = (int) get_post_field( 'post_author', $product->get_id() );
        $buyer_id   = get_current_user_id();

        if ( $buyer_id === $vendor_id ) {
            return;
        }

        $has_ln     = StoreSettings::has_lightning( $vendor_id );
        $has_onchain = StoreSettings::has_onchain( $vendor_id );

        if ( ! $has_ln && ! $has_onchain ) {
            return;
        }

        $price_sats = (int) $product->get_price();
        $product_id = $product->get_id();
        $product_title = $product->get_name();

        ?>
        <div class="skp-buy-wrapper" style="margin:12px 0;">
            <button type="button"
                    class="skp-buy-btn button alt"
                    data-vendor-id="<?php echo esc_attr( $vendor_id ); ?>"
                    data-product-id="<?php echo esc_attr( $product_id ); ?>"
                    data-product-title="<?php echo esc_attr( $product_title ); ?>"
                    data-price-sats="<?php echo esc_attr( $price_sats ); ?>"
                    data-has-ln="<?php echo $has_ln ? '1' : '0'; ?>"
                    data-has-onchain="<?php echo $has_onchain ? '1' : '0'; ?>"
                    style="background:#f7931a !important;color:#fff !important;border:none !important;padding:10px 24px !important;font-size:16px !important;border-radius:6px !important;cursor:pointer !important;display:inline-flex !important;align-items:center !important;gap:8px !important;">
                Sofortkauf
            </button>
        </div>

        <!-- Payment Method Modal -->
        <?php if ( $has_ln && $has_onchain ) : ?>
        <div id="skp-method-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:99999;align-items:center;justify-content:center;">
            <div style="background:#1a2332;border:1px solid rgba(255,255,255,0.1);border-radius:12px;padding:24px;max-width:360px;width:90%;">
                <h3 style="margin:0 0 16px;color:#e8ecf0;font-size:18px;">Wie möchtest du bezahlen?</h3>
                <button type="button" class="skp-method-choice" data-method="lightning"
                        style="display:block;width:100%;padding:14px;margin-bottom:10px;background:rgba(247,147,26,0.1);border:1px solid rgba(247,147,26,0.3);border-radius:8px;color:#f7931a;font-size:15px;font-weight:600;cursor:pointer;text-align:left;">
                    <i class="fas fa-bolt"></i> Lightning (sofort, niedrige Gebühren)
                </button>
                <button type="button" class="skp-method-choice" data-method="onchain"
                        style="display:block;width:100%;padding:14px;margin-bottom:10px;background:rgba(247,147,26,0.1);border:1px solid rgba(247,147,26,0.3);border-radius:8px;color:#f7931a;font-size:15px;font-weight:600;cursor:pointer;text-align:left;">
                    <i class="fab fa-bitcoin"></i> Onchain (Bitcoin-Adresse)
                </button>
                <button type="button" id="skp-method-cancel"
                        style="display:block;width:100%;padding:10px;background:none;border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:#5a6a7e;font-size:14px;cursor:pointer;">
                    Abbrechen
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Onchain Payment Modal -->
        <div id="skp-onchain-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:99999;align-items:center;justify-content:center;">
            <div style="background:#1a2332;border:1px solid rgba(255,255,255,0.1);border-radius:12px;padding:24px;max-width:400px;width:90%;">
                <h3 style="margin:0 0 16px;color:#e8ecf0;font-size:18px;"><i class="fab fa-bitcoin" style="color:#f7931a;"></i> Onchain-Zahlung</h3>
                <div id="skp-onchain-content"></div>
                <button type="button" id="skp-onchain-close"
                        style="display:block;width:100%;padding:10px;margin-top:12px;background:none;border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:#5a6a7e;font-size:14px;cursor:pointer;">
                    Schliessen
                </button>
            </div>
        </div>
        <?php
    }

    public function enqueue_assets() {
        if ( ! is_user_logged_in() || ! is_product() ) {
            return;
        }

        wp_enqueue_style(
            'sk-payments-css',
            SK_PAYMENTS_ASSETS . '/css/sk-lightning.css',
            [],
            SK_PAYMENTS_VERSION
        );

        wp_enqueue_script(
            'sk-payments-product',
            SK_PAYMENTS_ASSETS . '/js/sk-payments-product.js',
            [ 'jquery' ],
            SK_PAYMENTS_VERSION,
            true
        );

        wp_localize_script( 'sk-payments-product', 'skPayments', [
            'ajaxurl'   => admin_url( 'admin-ajax.php' ),
            'resturl'   => rest_url( 'sk/v1/lightning/' ),
            'nonce'     => wp_create_nonce( 'sk_lightning_nonce' ),
            'restNonce' => wp_create_nonce( 'wp_rest' ),
            'userId'    => get_current_user_id(),
        ] );
    }

    public function ajax_create_onchain_payment() {
        check_ajax_referer( 'sk_lightning_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'Nicht eingeloggt.' ] );
        }

        $vendor_id     = absint( $_POST['vendor_id'] ?? 0 );
        $product_id    = absint( $_POST['product_id'] ?? 0 );
        $product_title = sanitize_text_field( wp_unslash( $_POST['product_title'] ?? '' ) );
        $price_sats    = absint( $_POST['price_sats'] ?? 0 );
        $buyer_id      = get_current_user_id();

        if ( ! $vendor_id || ! $product_id || ! $price_sats ) {
            wp_send_json_error( [ 'message' => 'Fehlende Parameter.' ] );
        }

        if ( $buyer_id === $vendor_id ) {
            wp_send_json_error( [ 'message' => 'Du kannst nicht bei dir selbst kaufen.' ] );
        }

        if ( ! StoreSettings::has_onchain( $vendor_id ) ) {
            wp_send_json_error( [ 'message' => 'Verkäufer akzeptiert keine Onchain-Zahlungen.' ] );
        }

        // Derive a fresh address for this buyer.
        $address = StoreSettings::get_next_onchain_address( $vendor_id );
        if ( empty( $address ) ) {
            wp_send_json_error( [ 'message' => 'Keine Empfangsadresse verfügbar.' ] );
        }

        // Convert sats to BTC for display.
        $btc_amount = number_format( $price_sats / 100000000, 8, '.', '' );

        // Store payment record.
        global $wpdb;
        $table = $wpdb->prefix . 'sk_lightning_payments';
        $payment_hash = hash( 'sha256', $address . $buyer_id . $price_sats . time() );

        $wpdb->insert( $table, [
            'vendor_id'       => $vendor_id,
            'buyer_id'        => $buyer_id,
            'product_id'      => $product_id,
            'amount_sats'     => $price_sats,
            'payment_hash'    => $payment_hash,
            'payment_request' => 'bitcoin:' . $address . '?amount=' . $btc_amount,
            'status'          => 'pending',
            'context'         => 'onchain',
            'verify_url'      => $address,
            'buyer_ip_hash'   => hash( 'sha256', self::get_client_ip() ),
            'created_at'      => current_time( 'mysql' ),
        ], [
            '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
        ] );

        // If VendorChat is active, send a message.
        $chat_enabled = sk_get_option( 'sk_lightning_chat_integration', 'sk_lightning', 'on' ) === 'on';
        $vendor_chat_active = class_exists( 'SK\Core\Dashboard\Modules\VendorChat' ) && get_option( 'dvc_enabled', 'no' ) === 'yes';
        $chat_url = '';

        if ( $chat_enabled && $vendor_chat_active ) {
            $chat_id = Chat\ChatIntegration::find_or_create_chat_static( $buyer_id, $vendor_id, $product_id, $product_title );
            if ( ! is_wp_error( $chat_id ) ) {
                $message_data = wp_json_encode( [
                    'type'          => 'onchain_payment',
                    'product_id'    => $product_id,
                    'product_title' => $product_title,
                    'price_sats'    => $price_sats,
                    'btc_amount'    => $btc_amount,
                    'address'       => $address,
                    'payment_hash'  => $payment_hash,
                ] );
                $message_text = "[onchain_payment]{$message_data}[/onchain_payment]";
                Chat\ChatIntegration::add_chat_message_static( $chat_id, $buyer_id, $message_text );

                $wpdb->update(
                    $table,
                    [ 'chat_id' => $chat_id ],
                    [ 'payment_hash' => $payment_hash ],
                    [ '%d' ],
                    [ '%s' ]
                );

                $dashboard_url = sk_get_navigation_url( 'vendor-chat' );
                $chat_url = add_query_arg( 'chat_id', $chat_id, $dashboard_url );
            }
        }

        wp_send_json_success( [
            'address'       => $address,
            'amount_sats'   => $price_sats,
            'btc_amount'    => $btc_amount,
            'payment_hash'  => $payment_hash,
            'product_title' => $product_title,
            'chat_url'      => $chat_url,
            'bip21'         => 'bitcoin:' . $address . '?amount=' . $btc_amount,
        ] );
    }

    private static function get_client_ip(): string {
        foreach ( [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' ] as $header ) {
            if ( ! empty( $_SERVER[ $header ] ) ) {
                $ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
                if ( strpos( $ip, ',' ) !== false ) {
                    $ip = trim( explode( ',', $ip )[0] );
                }
                return $ip;
            }
        }
        return 'unknown-' . wp_generate_password( 16, false );
    }
}
