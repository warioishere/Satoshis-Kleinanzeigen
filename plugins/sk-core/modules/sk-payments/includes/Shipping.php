<?php

namespace SK\Modules\Payments;

defined( 'ABSPATH' ) || exit;

/**
 * Shipping details for an order.
 *
 * Deliberately not a new payment status: the status column drives reputation
 * and commission, and its transitions are guarded against double-booking. An
 * intermediate state there would mean interfering with the payment flow for
 * something that has nothing to do with money. The details are therefore
 * stored alongside the other order details on the payment row.
 *
 * The carrier list is not inherited from the Dokan legacy — that one knew
 * eighteen carriers, two of them for German-speaking countries, with URL
 * templates that partly led nowhere. This one is tailored to DACH.
 */
final class Shipping {

    const ACTION = 'sk_payments_ship';

    /** Placeholder in the templates. */
    const TOKEN = '{nummer}';

    public function __construct() {
        add_action( 'wp_ajax_' . self::ACTION, [ __CLASS__, 'ajax_save' ] );
    }

    /**
     * Carriers with a template for tracking.
     *
     * Checked on 2026-08-24: Post CH, Post AT, DHL, Hermes and FedEx respond.
     * DPD and UPS could not be reached from this server, GLS blocks the path
     * against automated access — their templates are the officially
     * documented ones, but unverified.
     *
     * If a template does lead nowhere, the details remain usable anyway:
     * carrier and number are shown in plain text next to it.
     *
     * @return array<string,array{label:string,url:string}>
     */
    public static function carriers(): array {
        return [
            'post-ch' => [
                'label' => __( 'Schweizerische Post', 'sk-core' ),
                'url'   => 'https://service.post.ch/ekp-web/ui/entry/search/' . self::TOKEN,
            ],
            'post-at' => [
                'label' => __( 'Österreichische Post', 'sk-core' ),
                'url'   => 'https://www.post.at/sendungsverfolgung?snr=' . self::TOKEN,
            ],
            'dhl'     => [
                'label' => __( 'DHL', 'sk-core' ),
                'url'   => 'https://www.dhl.de/de/privatkunden/pakete-empfangen/verfolgen.html?piececode=' . self::TOKEN,
            ],
            'dpd'     => [
                'label' => __( 'DPD', 'sk-core' ),
                'url'   => 'https://tracking.dpd.de/status/de_DE/parcel/' . self::TOKEN,
            ],
            'gls'     => [
                'label' => __( 'GLS', 'sk-core' ),
                'url'   => 'https://gls-group.eu/DE/de/paketverfolgung?match=' . self::TOKEN,
            ],
            'hermes'  => [
                'label' => __( 'Hermes', 'sk-core' ),
                'url'   => 'https://www.myhermes.de/empfangen/sendungsverfolgung/sendungsinformation/?searchTerm=' . self::TOKEN,
            ],
            'ups'     => [
                'label' => __( 'UPS', 'sk-core' ),
                'url'   => 'https://www.ups.com/track?loc=de_DE&tracknum=' . self::TOKEN,
            ],
            'fedex'   => [
                'label' => __( 'FedEx', 'sk-core' ),
                'url'   => 'https://www.fedex.com/fedextrack/?trknbr=' . self::TOKEN,
            ],
            'andere'  => [
                'label' => __( 'Anderer Versender', 'sk-core' ),
                'url'   => '',
            ],
        ];
    }

    /**
     * Shipping details of a payment.
     *
     * @return array{carrier:string,label:string,number:string,url:string,at:string}|null
     */
    public static function get( $payment ): ?array {
        $meta = is_object( $payment ) ? json_decode( (string) $payment->metadata, true ) : $payment;
        if ( ! is_array( $meta ) || empty( $meta['shipping'] ) || ! is_array( $meta['shipping'] ) ) {
            return null;
        }

        $ship     = $meta['shipping'];
        $carriers = self::carriers();
        $key      = (string) ( $ship['carrier'] ?? '' );

        return [
            'carrier' => $key,
            'label'   => (string) ( $carriers[ $key ]['label'] ?? ( $ship['label'] ?? __( 'Versand', 'sk-core' ) ) ),
            'number'  => (string) ( $ship['number'] ?? '' ),
            'url'     => (string) ( $ship['url'] ?? '' ),
            'at'      => (string) ( $ship['at'] ?? '' ),
        ];
    }

    private static function build_url( string $carrier, string $number, string $custom ): string {
        if ( $carrier === 'andere' ) {
            return $custom !== '' && wp_http_validate_url( $custom ) ? esc_url_raw( $custom ) : '';
        }

        $template = self::carriers()[ $carrier ]['url'] ?? '';
        if ( $template === '' || $number === '' ) {
            return '';
        }

        return str_replace( self::TOKEN, rawurlencode( $number ), $template );
    }

    public static function ajax_save(): void {
        check_ajax_referer( self::ACTION, 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => __( 'Nicht eingeloggt.', 'sk-core' ) ] );
        }

        global $wpdb;

        $table = $wpdb->prefix . 'sk_lightning_payments';
        $hash  = isset( $_POST['payment_hash'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_hash'] ) ) : '';

        if ( ! preg_match( '/^[0-9a-f]{64}$/', $hash ) ) {
            wp_send_json_error( [ 'message' => __( 'Zahlung nicht gefunden.', 'sk-core' ) ] );
        }

        $payment = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE payment_hash = %s", $hash ) );

        // Only the vendor of this payment, and only on the shop plan.
        if ( ! $payment || (int) $payment->vendor_id !== get_current_user_id() ) {
            wp_send_json_error( [ 'message' => __( 'Keine Berechtigung für diese Bestellung.', 'sk-core' ) ] );
        }

        if ( ! Notify::is_shop_pack( (int) $payment->vendor_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Die Versandangabe gehört zum Shoptarif.', 'sk-core' ) ] );
        }

        $carrier = isset( $_POST['carrier'] ) ? sanitize_key( wp_unslash( $_POST['carrier'] ) ) : '';
        $number  = isset( $_POST['number'] ) ? sanitize_text_field( wp_unslash( $_POST['number'] ) ) : '';
        $custom  = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';

        if ( ! isset( self::carriers()[ $carrier ] ) ) {
            wp_send_json_error( [ 'message' => __( 'Bitte einen Versender wählen.', 'sk-core' ) ] );
        }

        if ( $carrier !== 'andere' && $number === '' ) {
            wp_send_json_error( [ 'message' => __( 'Bitte die Sendungsnummer angeben.', 'sk-core' ) ] );
        }

        if ( $carrier === 'andere' && $number === '' && $custom === '' ) {
            wp_send_json_error( [ 'message' => __( 'Bitte Sendungsnummer oder Link angeben.', 'sk-core' ) ] );
        }

        $meta = json_decode( (string) $payment->metadata, true );
        $meta = is_array( $meta ) ? $meta : [];

        $meta['shipping'] = [
            'carrier' => $carrier,
            'number'  => $number,
            'url'     => self::build_url( $carrier, $number, $custom ),
            'at'      => current_time( 'mysql' ),
        ];

        $wpdb->update(
            $table,
            [ 'metadata' => wp_json_encode( $meta ) ],
            [ 'payment_hash' => $hash ],
            [ '%s' ],
            [ '%s' ]
        );

        do_action( 'sk_order_shipped', $hash );

        wp_send_json_success( [
            'versand' => self::get( (object) [ 'metadata' => wp_json_encode( $meta ) ] ),
        ] );
    }
}
