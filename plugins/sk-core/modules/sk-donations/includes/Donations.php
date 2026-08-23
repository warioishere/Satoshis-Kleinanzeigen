<?php

namespace SK\Modules\Donations;

defined( 'ABSPATH' ) || exit;

/**
 * Spendenlogik: Ziel, Stand, Rechnung, Gutschrift.
 */
class Donations {

    const OPTION_GOAL    = 'sk_donations_goal_sats';
    const OPTION_PRODUCT = 'sk_donations_product_id';

    /**
     * Voreinstellung aus der eigenen Spendenseite: "210.000 Sats decken
     * unsere Hosting- und Wartungskosten für drei Monate".
     */
    const DEFAULT_GOAL = 70000;

    const ORDER_FLAG = '_sk_donation';
    const ORDER_SATS = '_sk_donation_sats';

    const ACTION = 'sk_donate';

    public function __construct() {
        add_action( 'admin_post_' . self::ACTION, [ $this, 'handle_form' ] );
        add_action( 'admin_post_nopriv_' . self::ACTION, [ $this, 'handle_form' ] );
    }

    public static function goal(): int {
        return max( 0, (int) get_option( self::OPTION_GOAL, self::DEFAULT_GOAL ) );
    }

    public static function set_goal( int $sats ): void {
        update_option( self::OPTION_GOAL, max( 0, $sats ) );
    }

    /**
     * Im laufenden Kalendermonat eingegangene Spenden.
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
     * Summe bezahlter Spendenbestellungen in einem Zeitraum.
     *
     * Gezählt werden nur Bestellungen, die auch bezahlt sind — eine
     * abgebrochene Zahlung darf den Balken nicht bewegen.
     */
    public static function sum_between( string $from, string $to ): int {
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
     * Deckungsgrad in Prozent, gedeckelt bei 100 für die Balkenbreite.
     */
    public static function coverage(): int {
        $goal = self::goal();
        if ( $goal <= 0 ) {
            return 100;
        }

        return (int) min( 100, round( self::received_this_month() / $goal * 100 ) );
    }

    /**
     * Verstecktes Trägerprodukt.
     *
     * "private" statt "publish", sonst legt sk-feed beim Anlegen einen
     * öffentlichen Beitrag im Community-Feed an und die Telegram-/Nostr-Poster
     * springen an.
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
     * Formular entgegennehmen und zur Zahlung weiterleiten.
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
