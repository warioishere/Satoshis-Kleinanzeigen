<?php

namespace SK\Modules\Donations;

defined( 'ABSPATH' ) || exit;

/**
 * Donation logic: goal, current total, invoice, credit.
 */
class Donations {

    const OPTION_GOAL    = 'sk_donations_goal_sats';
    const OPTION_PRODUCT = 'sk_donations_product_id';

    /**
     * Default from our own donations page: "210,000 sats cover our
     * hosting and maintenance costs for three months".
     */
    const DEFAULT_GOAL = 70000;

    /**
     * Amount presets. Kept separate because the two spots are different
     * moments: the modal asks spontaneously after a sale, the bar is shown
     * to someone who's already engaging with the costs anyway.
     */
    const OPTION_PRESETS_MODAL = 'sk_donations_presets_modal';
    const OPTION_PRESETS_BAR   = 'sk_donations_presets_bar';

    const DEFAULT_PRESETS_MODAL = '2100,5000,21000';
    const DEFAULT_PRESETS_BAR   = '5000,21000,100000';

    const ORDER_FLAG = '_sk_donation';
    const ORDER_SATS = '_sk_donation_sats';

    const ACTION       = 'sk_donate';
    const AJAX_ACTION  = 'sk_donate_invoice';

    /**
     * Cutoff date: inflow only counts from here on.
     *
     * The crowdfund apps collected roughly 4.1M sats in 2025, almost all of
     * it in May, July and September of the build-up phase. If that counted
     * too, the coverage bar would permanently sit at 100 percent, cutting
     * off exactly the question it's meant to ask.
     */
    const OPTION_SINCE = 'sk_donations_count_since';

    public function __construct() {
        add_action( 'admin_post_' . self::ACTION, [ $this, 'handle_form' ] );
        add_action( 'admin_post_nopriv_' . self::ACTION, [ $this, 'handle_form' ] );

        add_action( 'wp_ajax_' . self::AJAX_ACTION, [ $this, 'handle_ajax' ] );
        add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, [ $this, 'handle_ajax' ] );
    }

    /**
     * Point in time from which counting starts. Set to now on first call.
     */
    public static function count_since(): int {
        $ts = (int) get_option( self::OPTION_SINCE, 0 );

        if ( $ts <= 0 ) {
            // Start of month, not "now": otherwise the bar would start at
            // zero mid-month and hide inflow that already came in.
            $ts = (int) strtotime( current_time( 'Y-m-01 00:00:00' ) );
            update_option( self::OPTION_SINCE, $ts );
        }

        return $ts;
    }

    public static function set_count_since( int $ts ): void {
        update_option( self::OPTION_SINCE, max( 0, $ts ) );
        BtcPay::flush_cache();
    }

    public static function goal(): int {
        return max( 0, (int) get_option( self::OPTION_GOAL, self::DEFAULT_GOAL ) );
    }

    public static function set_goal( int $sats ): void {
        update_option( self::OPTION_GOAL, max( 0, $sats ) );
    }

    /**
     * Amount presets as a list of numbers.
     *
     * @param string $where 'modal' or 'bar'
     * @return int[]
     */
    public static function presets( string $where = 'bar' ): array {
        $option  = $where === 'modal' ? self::OPTION_PRESETS_MODAL : self::OPTION_PRESETS_BAR;
        $default = $where === 'modal' ? self::DEFAULT_PRESETS_MODAL : self::DEFAULT_PRESETS_BAR;

        $values = array_map( 'absint', explode( ',', (string) get_option( $option, $default ) ) );
        $values = array_values( array_filter( $values, static fn( $v ) => $v > 0 ) );

        // Empty or unusable input falls back to the default,
        // otherwise the modal would end up with not a single button.
        if ( empty( $values ) ) {
            $values = array_map( 'absint', explode( ',', $default ) );
        }

        return array_slice( $values, 0, 4 );
    }

    /**
     * Normalize input from the admin.
     */
    public static function set_presets( string $where, string $raw ): void {
        $values = array_map( 'absint', preg_split( '/[,;\s]+/', trim( $raw ) ) ?: [] );
        $values = array_values( array_filter( $values, static fn( $v ) => $v > 0 ) );
        $values = array_slice( $values, 0, 4 );

        $option = $where === 'modal' ? self::OPTION_PRESETS_MODAL : self::OPTION_PRESETS_BAR;

        update_option( $option, implode( ',', $values ) );
    }

    /**
     * Donations received in the current calendar month.
     */
    public static function received_this_month(): int {
        return self::sum_between(
            current_time( 'Y-m-01 00:00:00' ),
            current_time( 'mysql' )
        );
    }

    public static function received_total(): int {
        return self::sum_between( '2000-01-01 00:00:00', current_time( 'mysql' ) );
    }

    /**
     * Sum of paid donation orders in a time range.
     *
     * Only orders that are actually paid are counted — an aborted
     * payment must not move the bar.
     */
    public static function sum_between( string $from, string $to ): int {
        return self::sum_woocommerce( $from, $to ) + self::sum_btcpay( $from, $to );
    }

    /**
     * Crowdfund payments from the BTCPay server, from the cutoff date onward.
     */
    public static function sum_btcpay( string $from, string $to ): int {
        $from_ts = max( (int) strtotime( $from ), self::count_since() );
        $to_ts   = (int) strtotime( $to );

        if ( $from_ts >= $to_ts ) {
            return 0;
        }

        return BtcPay::settled_sats( $from_ts, $to_ts );
    }

    public static function sum_woocommerce( string $from, string $to ): int {
        $orders = wc_get_orders(
            [
                'limit'        => -1,
                'status'       => [ 'processing', 'completed' ],
                'date_created' => strtotime( $from ) . '...' . strtotime( $to ),
                'meta_key'     => self::ORDER_FLAG,
                'meta_value'   => 1,
                'return'       => 'objects',
            ]
        );

        $sum = 0;
        foreach ( (array) $orders as $order ) {
            $sum += (int) $order->get_total();
        }

        return $sum;
    }

    /**
     * Coverage percentage, capped at 100 for the bar width.
     */
    public static function coverage(): int {
        $goal = self::goal();
        if ( $goal <= 0 ) {
            return 100;
        }

        return (int) min( 100, round( self::received_this_month() / $goal * 100 ) );
    }

    /**
     * Hidden carrier product.
     *
     * "private" instead of "publish", otherwise sk-feed creates a public
     * post in the community feed when it's created, and the
     * Telegram/Nostr posters kick in.
     */
    public static function product_id(): int {
        $id = (int) get_option( self::OPTION_PRODUCT );

        if ( $id > 0 && wc_get_product( $id ) ) {
            return $id;
        }

        $product = new \WC_Product_Simple();
        $product->set_name( __( 'Spende', 'sk-core' ) );
        $product->set_status( 'private' );
        $product->set_catalog_visibility( 'hidden' );
        $product->set_virtual( true );
        $product->set_price( 0 );
        $product->set_regular_price( 0 );
        $id = $product->save();

        update_option( self::OPTION_PRODUCT, $id );

        return (int) $id;
    }

    /**
     * @return \WC_Order|\WP_Error
     */
    public static function create_invoice( int $sats, string $email = '' ) {
        if ( $sats <= 0 ) {
            return new \WP_Error( 'sk_donations_amount', __( 'Bitte einen Betrag größer als 0 wählen.', 'sk-core' ) );
        }
        if ( ! function_exists( 'wc_create_order' ) ) {
            return new \WP_Error( 'sk_donations_no_wc', __( 'WooCommerce ist nicht verfügbar.', 'sk-core' ) );
        }

        $product = wc_get_product( self::product_id() );
        if ( ! $product ) {
            return new \WP_Error( 'sk_donations_no_product', __( 'Trägerprodukt fehlt.', 'sk-core' ) );
        }

        try {
            $order = wc_create_order();
            $order->add_product( $product, 1 );

            foreach ( $order->get_items() as $item ) {
                $item->set_subtotal( $sats );
                $item->set_total( $sats );
                $item->set_name( __( 'Spende für Satoshis Kleinanzeigen', 'sk-core' ) );
                $item->save();
            }

            if ( $email !== '' && is_email( $email ) ) {
                $order->set_billing_email( $email );
            }

            if ( is_user_logged_in() ) {
                $order->set_customer_id( get_current_user_id() );
            }

            $order->set_payment_method( 'btcpaygf_default' );
            $order->update_meta_data( self::ORDER_FLAG, 1 );
            $order->update_meta_data( self::ORDER_SATS, $sats );
            $order->calculate_totals();
            $order->set_status( 'pending' );
            $order->save();

            return $order;
        } catch ( \Throwable $e ) {
            return new \WP_Error( 'sk_donations_order', $e->getMessage() );
        }
    }

    /**
     * Create the invoice and prepare the BTCPay dialog.
     *
     * Uses BuyNow::pay_order() — the same path subscriptions and boosts
     * take. This skips the WooCommerce checkout, and the mechanism only
     * exists once.
     */
    public function handle_ajax(): void {
        check_ajax_referer( self::AJAX_ACTION, 'nonce' );

        $sats  = isset( $_POST['sats'] ) ? absint( $_POST['sats'] ) : 0;
        $order = self::create_invoice( $sats );

        if ( is_wp_error( $order ) ) {
            wp_send_json_error( [ 'message' => $order->get_error_message() ], 400 );
        }

        if ( ! class_exists( '\\SK\\Core\\BuyNow' ) ) {
            // Without BuyNow, the normal payment path via the order page remains.
            wp_send_json_success( [ 'payUrl' => $order->get_checkout_payment_url() ] );
        }

        $payment = \SK\Core\BuyNow::pay_order( $order );

        if ( is_wp_error( $payment ) ) {
            // Fallback instead of a dead end: the order exists and can
            // still be settled via the normal payment page.
            wp_send_json_success( [ 'payUrl' => $order->get_checkout_payment_url() ] );
        }

        wp_send_json_success( $payment );
    }

    /**
     * Accept the form submission and redirect to payment.
     *
     * Remains as a fallback path for when JavaScript or the BTCPay dialog
     * are unavailable.
     */
    public function handle_form(): void {
        $referer = wp_get_referer() ?: home_url( '/' );

        if ( ! isset( $_POST['sk_donation_nonce'] ) || ! wp_verify_nonce( $_POST['sk_donation_nonce'], self::ACTION ) ) {
            wp_safe_redirect( add_query_arg( 'spende', 'fehler', $referer ) );
            exit;
        }

        $sats = isset( $_POST['sk_donation_sats'] ) ? absint( $_POST['sk_donation_sats'] ) : 0;
        if ( isset( $_POST['sk_donation_custom'] ) && absint( $_POST['sk_donation_custom'] ) > 0 ) {
            $sats = absint( $_POST['sk_donation_custom'] );
        }

        $order = self::create_invoice( $sats );

        if ( is_wp_error( $order ) ) {
            wp_safe_redirect( add_query_arg( 'spende', 'fehler', $referer ) );
            exit;
        }

        wp_safe_redirect( $order->get_checkout_payment_url() );
        exit;
    }
}
