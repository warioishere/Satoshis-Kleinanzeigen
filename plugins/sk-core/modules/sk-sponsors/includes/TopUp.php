<?php

namespace SK\Modules\Sponsors;

defined( 'ABSPATH' ) || exit;

/**
 * Guthaben per BTCPay aufladen.
 *
 * Geht denselben Weg wie Boosts und Abo-Pakete: eine WooCommerce-Bestellung
 * auf ein verstecktes Trägerprodukt, bezahlt über das vorhandene BTCPay-
 * Gateway. Ist die Bestellung bezahlt, wird der Betrag dem Sponsorenguthaben
 * gutgeschrieben.
 *
 * Bewusst KEIN Abo-Plugin: In Bitcoin gibt es keine Lastschrift, ein Abo
 * erzeugt am Ende ebenfalls nur eine Rechnung, die jemand manuell bezahlt.
 * Das Guthaben ist die Abo-Mechanik — es wird einmal gefüllt und monatlich
 * verbraucht (siehe Billing).
 *
 * Sponsoren sind Firmen ohne Benutzerkonto auf der Seite. Deshalb erzeugt der
 * Betreiber die Rechnung und verschickt den Zahllink; niemand muss sich
 * registrieren.
 */
final class TopUp {

    const OPTION_PRODUCT   = 'sk_sponsors_topup_product_id';
    const ORDER_SPONSOR    = '_sk_sponsor_id';
    const ORDER_SATS       = '_sk_sponsor_topup_sats';
    const ORDER_CREDITED   = '_sk_sponsor_topup_credited';

    public function __construct() {
        // Beide Haken, weil je nach Gateway mal der eine, mal der andere
        // feuert. Die Gutschrift ist gegen Doppelbuchung abgesichert.
        add_action( 'woocommerce_payment_complete', [ __CLASS__, 'credit_from_order' ], 20, 1 );
        add_action( 'woocommerce_order_status_completed', [ __CLASS__, 'credit_from_order' ], 20, 1 );
        add_action( 'woocommerce_order_status_processing', [ __CLASS__, 'credit_from_order' ], 20, 1 );
    }

    /**
     * Verstecktes Trägerprodukt, bei Bedarf angelegt.
     */
    public static function product_id(): int {
        $id = (int) get_option( self::OPTION_PRODUCT );

        if ( $id > 0 && wc_get_product( $id ) ) {
            return $id;
        }

        $product = new \WC_Product_Simple();
        $product->set_name( __( 'Sponsoren-Guthaben', 'sk-core' ) );
        /*
         * Bewusst "private" statt "publish": Ein veroeffentlichtes Produkt
         * loest transition_post_status aus, worauf sk-feed einen oeffentlichen
         * Beitrag im Community-Feed anlegt (AutoPost::on_product_publish) und
         * die Telegram-/Nostr-Poster anspringen. Das Traegerprodukt ist reine
         * Buchhaltung und hat dort nichts verloren. Bestellungen koennen es
         * trotzdem fuehren, weil add_product() den Status nicht prueft.
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
     * Rechnung über einen Guthabenbetrag erstellen.
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

            // Der Preis steht am Trägerprodukt auf 0 — der Betrag kommt aus
            // der Position, sonst wäre jede Rechnung über 0 Sats.
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
     * Bezahlte Rechnung dem Guthaben gutschreiben.
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

        // Drei Haken können für dieselbe Bestellung feuern — ohne diese
        // Sperre würde das Guthaben mehrfach gutgeschrieben.
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
     * Offene Guthabenrechnungen eines Sponsors.
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
