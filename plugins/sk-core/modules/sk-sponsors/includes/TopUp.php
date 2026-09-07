<?php

namespace SK\Modules\Sponsors;

defined( 'ABSPATH' ) || exit;

/**
 * Top up balance via BTCPay.
 *
 * Follows the same path as boosts and subscription packages: a WooCommerce
 * order for a hidden carrier product, paid through the existing BTCPay
 * gateway. Once the order is paid, the amount is credited to the sponsor
 * balance.
 *
 * Deliberately NOT a subscription plugin: Bitcoin has no direct debit, and a
 * subscription would ultimately just generate an invoice someone pays
 * manually anyway. The balance IS the subscription mechanic — it gets topped
 * up once and consumed monthly (see Billing).
 *
 * Sponsors are companies without a user account on the site. That's why the
 * operator creates the invoice and sends the payment link; nobody needs to
 * register.
 */
final class TopUp {

    const OPTION_PRODUCT   = 'sk_sponsors_topup_product_id';
    const ORDER_SPONSOR    = '_sk_sponsor_id';
    const ORDER_SATS       = '_sk_sponsor_topup_sats';
    const ORDER_CREDITED   = '_sk_sponsor_topup_credited';

    public function __construct() {
        // Both hooks, because depending on the gateway sometimes one fires,
        // sometimes the other. Crediting is guarded against double-booking.
        add_action( 'woocommerce_payment_complete', [ __CLASS__, 'credit_from_order' ], 20, 1 );
        add_action( 'woocommerce_order_status_completed', [ __CLASS__, 'credit_from_order' ], 20, 1 );
        add_action( 'woocommerce_order_status_processing', [ __CLASS__, 'credit_from_order' ], 20, 1 );
    }

    /**
     * Hidden carrier product, created on demand.
     */
    public static function product_id(): int {
        $id = (int) get_option( self::OPTION_PRODUCT );

        if ( $id > 0 && wc_get_product( $id ) ) {
            return $id;
        }

        $product = new \WC_Product_Simple();
        $product->set_name( __( 'Sponsoren-Guthaben', 'sk-core' ) );
        /*
         * Deliberately "private" instead of "publish": a published product
         * triggers transition_post_status, which makes sk-feed create a
         * public post in the community feed (AutoPost::on_product_publish)
         * and kicks off the Telegram/Nostr posters. The carrier product is
         * pure bookkeeping and has no business there. Orders can still carry
         * it regardless, because add_product() doesn't check the status.
         */
        $product->set_status( 'private' );
        $product->set_catalog_visibility( 'hidden' );
        $product->set_virtual( true );
        $product->set_price( 0 );
        $product->set_regular_price( 0 );
        $product->set_sold_individually( false );
        $id = $product->save();

        update_option( self::OPTION_PRODUCT, $id );

        return (int) $id;
    }

    /**
     * Create an invoice for a balance amount.
     *
     * @return \WC_Order|\WP_Error
     */
    public static function create_invoice( int $sponsor_id, int $sats, string $email = '' ) {
        if ( get_post_type( $sponsor_id ) !== PostType::POST_TYPE ) {
            return new \WP_Error( 'sk_sponsors_invalid', __( 'Unbekannter Sponsor.', 'sk-core' ) );
        }
        if ( $sats <= 0 ) {
            return new \WP_Error( 'sk_sponsors_amount', __( 'Betrag muss größer als 0 sein.', 'sk-core' ) );
        }
        if ( ! function_exists( 'wc_create_order' ) ) {
            return new \WP_Error( 'sk_sponsors_no_wc', __( 'WooCommerce ist nicht verfügbar.', 'sk-core' ) );
        }

        $product = wc_get_product( self::product_id() );
        if ( ! $product ) {
            return new \WP_Error( 'sk_sponsors_no_product', __( 'Trägerprodukt fehlt.', 'sk-core' ) );
        }

        try {
            $order = wc_create_order();
            $order->add_product( $product, 1 );

            // The carrier product's price is 0 — the amount comes from the
            // line item, otherwise every invoice would be for 0 sats.
            foreach ( $order->get_items() as $item ) {
                $item->set_subtotal( $sats );
                $item->set_total( $sats );
                $item->set_name(
                    sprintf(
                        /* translators: %s: sponsor name */
                        __( 'Sponsoren-Guthaben: %s', 'sk-core' ),
                        get_the_title( $sponsor_id )
                    )
                );
                $item->save();
            }

            if ( $email !== '' && is_email( $email ) ) {
                $order->set_billing_email( $email );
            }

            $order->set_payment_method( 'btcpaygf_default' );
            $order->update_meta_data( self::ORDER_SPONSOR, $sponsor_id );
            $order->update_meta_data( self::ORDER_SATS, $sats );
            $order->calculate_totals();
            $order->set_status( 'pending' );
            $order->save();

            $order->add_order_note(
                sprintf(
                    /* translators: 1: sats, 2: sponsor */
                    __( 'Guthabenrechnung über %1$s Sats für %2$s.', 'sk-core' ),
                    number_format_i18n( $sats ),
                    get_the_title( $sponsor_id )
                )
            );

            return $order;
        } catch ( \Throwable $e ) {
            return new \WP_Error( 'sk_sponsors_order', $e->getMessage() );
        }
    }

    /**
     * Credit a paid invoice to the balance.
     */
    public static function credit_from_order( $order_id ): void {
        $order = wc_get_order( $order_id );
        if ( ! $order instanceof \WC_Abstract_Order ) {
            return;
        }

        $sponsor_id = (int) $order->get_meta( self::ORDER_SPONSOR );
        $sats       = (int) $order->get_meta( self::ORDER_SATS );

        if ( $sponsor_id <= 0 || $sats <= 0 ) {
            return;
        }

        // Three hooks can fire for the same order — without this guard the
        // balance would be credited multiple times.
        if ( (int) $order->get_meta( self::ORDER_CREDITED ) === 1 ) {
            return;
        }

        if ( get_post_type( $sponsor_id ) !== PostType::POST_TYPE ) {
            return;
        }

        $order->update_meta_data( self::ORDER_CREDITED, 1 );
        $order->save();

        $balance = Billing::top_up(
            $sponsor_id,
            $sats,
            sprintf(
                /* translators: %d: order number */
                __( 'Aufladung über Bestellung #%d', 'sk-core' ),
                (int) $order->get_id()
            )
        );

        $order->add_order_note(
            sprintf(
                /* translators: 1: sats, 2: new balance */
                __( '%1$s Sats gutgeschrieben. Neues Guthaben: %2$s Sats.', 'sk-core' ),
                number_format_i18n( $sats ),
                number_format_i18n( $balance )
            )
        );
    }

    /**
     * A sponsor's open balance invoices.
     *
     * @return \WC_Order[]
     */
    public static function open_invoices( int $sponsor_id ): array {
        $orders = wc_get_orders(
            [
                'limit'      => 10,
                'status'     => [ 'pending', 'on-hold', 'failed' ],
                'meta_key'   => self::ORDER_SPONSOR,
                'meta_value' => $sponsor_id,
            ]
        );

        return is_array( $orders ) ? $orders : [];
    }
}
