<?php

namespace SK\Core;

defined( 'ABSPATH' ) || exit;

/**
 * SK Buy Now — bypass WooCommerce checkout for product boosts and subscriptions.
 *
 * Creates a WC order programmatically and opens the BTCPay Server modal directly,
 * skipping the cart → checkout → payment-method-select flow.
 */
final class BuyNow {

    /** Settings section id, replacing the old Settings → SK Buy Now page. */
    const SECTION = 'sk_buynow';

    /** The option this section replaces. */
    const LEGACY_OPTION = 'sk_buynow_enabled';

    public static function init(): void {
        self::migrate_legacy_option();

        add_filter( 'sk_settings_sections', [ __CLASS__, 'add_section' ] );
        add_filter( 'sk_settings_fields', [ __CLASS__, 'add_fields' ] );

        if ( ! self::is_enabled() ) {
            return;
        }

        add_action( 'wp_ajax_sk_buynow', [ __CLASS__, 'ajax_handler' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ], 20 );
        add_action( 'wp_footer', [ __CLASS__, 'adv_intercept_script' ], 100 );
    }

    /**
     * Read the switch straight from the option, not through sk_get_option():
     * init() runs at plugin-load time in sk-core.php, before includes/functions.php
     * (where that helper lives) is guaranteed to be loaded. get_option() is core
     * WordPress and always available this early.
     */
    private static function is_enabled(): bool {
        $section = get_option( self::SECTION );

        return ! is_array( $section ) || ! isset( $section['sk_buynow_enabled'] ) || 'on' === $section['sk_buynow_enabled'];
    }

    /**
     * The old scalar option (Settings → SK Buy Now, "1"/"0") migrated into
     * this section once, then dropped. After that this never runs again —
     * get_option() on a deleted option returns false immediately.
     */
    private static function migrate_legacy_option(): void {
        $legacy = get_option( self::LEGACY_OPTION, null );

        if ( null === $legacy ) {
            return;
        }

        $section = get_option( self::SECTION );
        $section = is_array( $section ) ? $section : [];

        if ( ! isset( $section['sk_buynow_enabled'] ) ) {
            $section['sk_buynow_enabled'] = (bool) $legacy ? 'on' : 'off';
            update_option( self::SECTION, $section );
        }

        delete_option( self::LEGACY_OPTION );
    }

    public static function add_section( $sections ) {
        $sections[] = [
            'id'                   => self::SECTION,
            'title'                => __( 'SK Buy Now', 'sk-core' ),
            'icon_url'             => '',
            'description'          => __( 'Direktzahlung ohne WooCommerce-Checkout', 'sk-core' ),
            'settings_title'       => __( 'SK Buy Now', 'sk-core' ),
            'settings_description' => __( 'Öffnet den BTCPay-Zahlungsdialog direkt beim Klick auf „Jetzt kaufen" — ohne Umweg über den WooCommerce-Checkout. Betrifft Abonnements und Boosts im Verkäufer-Dashboard.', 'sk-core' ),
        ];

        return $sections;
    }

    public static function add_fields( $settings_fields ) {
        $settings_fields[ self::SECTION ] = [
            'sk_buynow_enabled' => [
                'name'    => 'sk_buynow_enabled',
                'label'   => __( 'Direktzahlung aktiv', 'sk-core' ),
                'type'    => 'switcher',
                'default' => 'on',
                'desc'    => __( 'BTCPay-Modal direkt öffnen (Abonnements &amp; Boosts). Wenn deaktiviert, läuft der normale WooCommerce-Checkout-Prozess.', 'sk-core' ),
            ],
        ];

        return $settings_fields;
    }

    public static function ajax_handler(): void {
        check_ajax_referer( 'sk_buynow', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'Nicht eingeloggt.' ], 401 );
        }

        $type       = sanitize_text_field( wp_unslash( $_POST['type'] ?? '' ) );
        $product_id = absint( $_POST['product_id'] ?? 0 );

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

        $user = wp_get_current_user();
        $checkout_data = [
            'billing_first_name'  => $user->display_name ?: $user->user_login,
            'billing_last_name'   => 'N/A',
            'billing_address_1'   => 'N/A',
            'billing_address_2'   => '',
            'billing_city'        => 'Zürich',
            'billing_postcode'    => '8000',
            'billing_country'     => 'CH',
            'billing_state'       => '',
            'billing_email'       => $user->user_email ?: 'noemail@example.com',
            'billing_phone'       => '0000000000',
            'shipping_first_name' => '',
            'shipping_last_name'  => '',
            'shipping_address_1'  => '',
            'shipping_address_2'  => '',
            'shipping_city'       => '',
            'shipping_postcode'   => '',
            'shipping_country'    => '',
            'shipping_state'      => '',
            'order_comments'      => '',
            'payment_method'      => 'btcpaygf_default',
            'ship_to_different_address' => false,
        ];

        /*
         * create_order() skips WC_Checkout::process_checkout(), so the modules that
         * hook into the checkout never get to re-validate the cart. Run their
         * validation here, before an order is created and money is taken.
         */
        $validation_errors = new \WP_Error();
        do_action( 'woocommerce_after_checkout_validation', $checkout_data, $validation_errors );

        if ( $validation_errors->has_errors() ) {
            wp_send_json_error( [ 'message' => wp_strip_all_tags( $validation_errors->get_error_message() ) ], 400 );
        }

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

        /*
         * WC_Checkout::process_checkout() fires this right after create_order().
         * Modules use it to freeze what was bought onto the order — the subscription
         * module stores pack validity and the allowed number of products there. Without
         * it the order carries no terms and later falls back to whatever the pack says
         * at that moment.
         */
        do_action( 'woocommerce_checkout_order_processed', $order_id, $checkout_data, $order );

        $payment = self::pay_order( $order );

        if ( is_wp_error( $payment ) ) {
            wp_send_json_error( [ 'message' => $payment->get_error_message() ], 500 );
        }

        wp_send_json_success( $payment );
    }

    /**
     * Make an order payable via BTCPay and return the data for the dialog.
     *
     * Extracted so other modules can follow the same path without
     * rebuilding the flow — the donations module uses it for its modal.
     *
     * @return array{invoiceId:string,orderCompleteLink:string,btcpayUrl:string}|\WP_Error
     */
    public static function pay_order( \WC_Order $order ) {
        $gateways = WC()->payment_gateways()->payment_gateways();

        if ( ! isset( $gateways['btcpaygf_default'] ) ) {
            return new \WP_Error( 'sk_buynow_gateway', 'BTCPay Gateway nicht gefunden.' );
        }

        $result = $gateways['btcpaygf_default']->process_payment( $order->get_id() );

        if ( empty( $result['invoiceId'] ) ) {
            $order->update_status( 'cancelled', 'BTCPay invoice creation failed.' );

            return new \WP_Error( 'sk_buynow_invoice', 'BTCPay Invoice konnte nicht erstellt werden.' );
        }

        return [
            'invoiceId'         => $result['invoiceId'],
            'orderCompleteLink' => $result['orderCompleteLink'] ?? $order->get_checkout_order_received_url(),
            'btcpayUrl'         => rtrim( (string) get_option( 'btcpay_gf_url' ), '/' ),
        ];
    }

    public static function enqueue_assets(): void {
        if ( ! is_user_logged_in() ) {
            return;
        }
        if ( ! function_exists( 'sk_is_seller_dashboard' ) || ! sk_is_seller_dashboard() ) {
            return;
        }

        $btcpay_url = rtrim( (string) get_option( 'btcpay_gf_url' ), '/' );
        if ( $btcpay_url ) {
            wp_enqueue_script( 'btcpay_gf_modal_js', $btcpay_url . '/modal/btcpay.js', [], null, true );
        }

        wp_enqueue_script(
            'sk-buynow',
            SK_CORE_ASSETS . '/js/sk-buynow.js',
            [ 'jquery', 'btcpay_gf_modal_js' ],
            SK_CORE_VERSION,
            true
        );

        wp_localize_script( 'sk-buynow', 'skBuynow', [
            'ajaxurl'   => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'sk_buynow' ),
            'btcpayUrl' => $btcpay_url,
        ] );
    }

    public static function adv_intercept_script(): void {
        if ( ! is_user_logged_in() ) {
            return;
        }
        if ( ! function_exists( 'sk_is_seller_dashboard' ) || ! sk_is_seller_dashboard() ) {
            return;
        }
        ?>
        <script>
        (function () {
            function interceptAdv() {
                if (typeof sk_purchase_advertisement === 'undefined') return;
                try {
                    Object.defineProperty(sk_purchase_advertisement, 'checkout_url', {
                        get: function () { return '#sk-buynow'; },
                        configurable: true,
                    });
                } catch (e) {
                    sk_purchase_advertisement.checkout_url = '#sk-buynow';
                }
            }
            interceptAdv();
        })();
        </script>
        <?php
    }
}
