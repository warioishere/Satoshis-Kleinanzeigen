<?php
/**
 * SK Buy Now — bypass WooCommerce checkout for product boosts and subscriptions.
 *
 * Creates a WC order programmatically and opens the BTCPay Server modal directly,
 * skipping the cart → checkout → payment-method-select flow.
 *
 * Handles two product types:
 *   - 'subscription'  : product_pack (Abonnements) — .buy_product_pack links
 *   - 'adv'           : advertisement (Boosts)     — intercepts purchase_advertisement.js redirect
 */

defined( 'ABSPATH' ) || exit;

// ── Admin settings page ───────────────────────────────────────────────────────
add_action( 'admin_menu', function () {
    add_options_page(
        'SK Buy Now',
        'SK Buy Now',
        'manage_options',
        'sk-buynow',
        function () {
            if ( ! current_user_can( 'manage_options' ) ) return;
            if ( isset( $_POST['sk_buynow_save'] ) && check_admin_referer( 'sk_buynow_settings' ) ) {
                update_option( 'sk_buynow_enabled', isset( $_POST['sk_buynow_enabled'] ) ? 1 : 0 );
                echo '<div class="notice notice-success is-dismissible"><p>Einstellungen gespeichert.</p></div>';
            }
            $enabled = (bool) get_option( 'sk_buynow_enabled', 1 );
            ?>
            <div class="wrap">
                <h1>⚡ SK Buy Now</h1>
                <p style="color:#666;max-width:580px">Öffnet den BTCPay-Zahlungsdialog direkt beim Klick auf "Jetzt kaufen" — ohne Umweg über den WooCommerce-Checkout.</p>
                <form method="post">
                    <?php wp_nonce_field( 'sk_buynow_settings' ); ?>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row">Direktzahlung aktiv</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="sk_buynow_enabled" value="1" <?php checked( $enabled ); ?>>
                                    BTCPay-Modal direkt öffnen (Abonnements &amp; Boosts)
                                </label>
                                <p class="description">Wenn deaktiviert, läuft der normale WooCommerce-Checkout-Prozess.</p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button( 'Einstellungen speichern', 'primary', 'sk_buynow_save' ); ?>
                </form>
            </div>
            <?php
        }
    );
} );

// ── Guard: do nothing when disabled ──────────────────────────────────────────
if ( ! (bool) get_option( 'sk_buynow_enabled', 0 ) ) {
    return;
}

// ── AJAX handler ──────────────────────────────────────────────────────────────
add_action( 'wp_ajax_sk_buynow', function () {

    check_ajax_referer( 'sk_buynow', 'nonce' );

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( [ 'message' => 'Nicht eingeloggt.' ], 401 );
    }

    $type       = sanitize_text_field( wp_unslash( $_POST['type'] ?? '' ) );
    $product_id = absint( $_POST['product_id'] ?? 0 );

    // Ensure WC cart is initialised
    if ( ! WC()->cart ) {
        wc_load_cart();
    }

    if ( $type === 'subscription' ) {
        if ( ! $product_id ) {
            wp_send_json_error( [ 'message' => 'Ungültige Produkt-ID.' ], 400 );
        }
        $product = wc_get_product( $product_id );
        if ( ! $product || $product->get_type() !== 'product_pack' ) {
            wp_send_json_error( [ 'message' => 'Kein gültiges Abonnement-Produkt.' ], 400 );
        }
        WC()->cart->empty_cart();
        WC()->cart->add_to_cart( $product_id );
    }

    if ( WC()->cart->is_empty() ) {
        wp_send_json_error( [ 'message' => 'Warenkorb ist leer.' ], 400 );
    }

    // Build billing data — only first_name is "real", rest are dummy values
    $user = wp_get_current_user();
    $checkout_data = [
        'billing_first_name' => $user->display_name ?: $user->user_login,
        'billing_last_name'  => 'N/A',
        'billing_address_1'  => 'N/A',
        'billing_address_2'  => '',
        'billing_city'       => 'Zürich',
        'billing_postcode'   => '8000',
        'billing_country'    => 'CH',
        'billing_state'      => '',
        'billing_email'      => $user->user_email ?: 'noemail@example.com',
        'billing_phone'      => '0000000000',
        'shipping_first_name'=> '',
        'shipping_last_name' => '',
        'shipping_address_1' => '',
        'shipping_address_2' => '',
        'shipping_city'      => '',
        'shipping_postcode'  => '',
        'shipping_country'   => '',
        'shipping_state'     => '',
        'order_comments'     => '',
        'payment_method'     => 'btcpaygf_default',
        'ship_to_different_address' => false,
    ];

    // Create WC order from cart
    $order_id = WC()->checkout()->create_order( $checkout_data );

    if ( is_wp_error( $order_id ) ) {
        wp_send_json_error( [ 'message' => $order_id->get_error_message() ], 500 );
    }

    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        wp_send_json_error( [ 'message' => 'Bestellung konnte nicht erstellt werden.' ], 500 );
    }

    $order->set_payment_method( 'btcpaygf_default' );
    $order->set_customer_id( get_current_user_id() );
    $order->save();

    // Process payment via BTCPay gateway
    $gateways = WC()->payment_gateways()->payment_gateways();
    if ( ! isset( $gateways['btcpaygf_default'] ) ) {
        wp_send_json_error( [ 'message' => 'BTCPay Gateway nicht gefunden.' ], 500 );
    }

    $result = $gateways['btcpaygf_default']->process_payment( $order_id );

    if ( empty( $result['invoiceId'] ) ) {
        // process_payment failed — cancel the pending order
        $order->update_status( 'cancelled', 'BTCPay invoice creation failed.' );
        wp_send_json_error( [ 'message' => 'BTCPay Invoice konnte nicht erstellt werden.' ], 500 );
    }

    wp_send_json_success( [
        'invoiceId'         => $result['invoiceId'],
        'orderCompleteLink' => $result['orderCompleteLink'],
        'btcpayUrl'         => rtrim( (string) get_option( 'btcpay_gf_url' ), '/' ),
    ] );
} );

// ── Enqueue scripts + localize on vendor dashboard ────────────────────────────
add_action( 'wp_enqueue_scripts', function () {
    if ( ! is_user_logged_in() ) return;
    if ( ! function_exists( 'sk_is_seller_dashboard' ) || ! sk_is_seller_dashboard() ) return;

    // BTCPay modal JS (normally only loaded on checkout)
    $btcpay_url = rtrim( (string) get_option( 'btcpay_gf_url' ), '/' );
    if ( $btcpay_url ) {
        wp_enqueue_script(
            'btcpay_gf_modal_js',
            $btcpay_url . '/modal/btcpay.js',
            [],
            null,
            true
        );
    }

    // Our Buy Now script
    $js_path = __DIR__ . '/sk-buynow.js';
    wp_enqueue_script(
        'sk-buynow',
        content_url( 'mu-plugins/sk-buynow.js' ),
        [ 'jquery', 'btcpay_gf_modal_js' ],
        file_exists( $js_path ) ? filemtime( $js_path ) : '1',
        true
    );

    wp_localize_script( 'sk-buynow', 'skBuynow', [
        'ajaxurl'   => admin_url( 'admin-ajax.php' ),
        'nonce'     => wp_create_nonce( 'sk_buynow' ),
        'btcpayUrl' => $btcpay_url,
    ] );
}, 20 );

// ── Override advertisement checkout_url to trigger hash-based intercept ───────
// Priority 100 = runs AFTER wp_print_footer_scripts (priority 20), so
// sk_purchase_advertisement is already defined when we override checkout_url.
add_action( 'wp_footer', function () {
    if ( ! is_user_logged_in() ) return;
    if ( ! function_exists( 'sk_is_seller_dashboard' ) || ! sk_is_seller_dashboard() ) return;
    ?>
    <script>
    (function () {
        function interceptAdv() {
            if (typeof sk_purchase_advertisement === 'undefined') {
                console.warn('[SK BuyNow] sk_purchase_advertisement not found');
                return;
            }
            try {
                Object.defineProperty(sk_purchase_advertisement, 'checkout_url', {
                    get: function () { return '#sk-buynow'; },
                    configurable: true,
                });
                console.log('[SK BuyNow] advertisement checkout_url intercepted');
            } catch (e) {
                // Fallback: plain assignment
                sk_purchase_advertisement.checkout_url = '#sk-buynow';
                console.log('[SK BuyNow] advertisement checkout_url set (fallback)');
            }
        }
        interceptAdv();
    })();
    </script>
    <?php
}, 100 );
