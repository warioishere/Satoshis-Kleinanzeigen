<?php

namespace SK\Modules\Payments;

defined( 'ABSPATH' ) || exit;

/**
 * E-Mail bei bestätigtem Zahlungseingang.
 *
 * Bis hierher erfuhr ein Verkäufer von einem Verkauf nur, wenn er ins
 * Dashboard schaute — im ganzen Zahlungsmodul stand kein einziges wp_mail
 * ausserhalb der Provisionsmahnung. Bei drei Inseraten geht das, bei einem
 * Katalog nicht.
 *
 * Ausgelöst wird am Statuswechsel auf „bestätigt". Der ist per SQL an
 * `status = 'pending'` gebunden und greift deshalb genau einmal, auch wenn
 * zwei Prüfungen gleichzeitig laufen.
 */
final class Notify {

    /** Ab dieser Inseratszahl gilt ein Paket als Shoptarif (Delphin). */
    const SHOP_MIN_PRODUCTS = 21;

    public function __construct() {
        add_action( 'sk_payment_confirmed', [ __CLASS__, 'on_confirmed' ], 10, 2 );
    }

    /**
     * Hat der Anbieter ein Paket ab Delphin?
     *
     * Dieselbe Regel wie beim Katalogimport. Bewusst hier noch einmal, damit
     * die Benachrichtigung nicht davon abhängt, ob das Importmodul läuft.
     */
    public static function is_shop_pack( int $vendor_id ): bool {
        if ( class_exists( \SK\Modules\ShopImport\Variants::class ) ) {
            return \SK\Modules\ShopImport\Variants::is_allowed( $vendor_id );
        }

        $pack = (int) get_user_meta( $vendor_id, 'product_package_id', true );
        if ( ! $pack ) {
            return false;
        }

        $count = (int) get_post_meta( $pack, '_no_of_product', true );

        return $count === -1 || $count >= self::SHOP_MIN_PRODUCTS;
    }

    public static function on_confirmed( string $payment_hash, string $via ): void {
        global $wpdb;

        $table   = $wpdb->prefix . 'sk_lightning_payments';
        $payment = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE payment_hash = %s", $payment_hash )
        );

        if ( ! $payment ) {
            return;
        }

        $meta = json_decode( (string) $payment->metadata, true );
        $meta = is_array( $meta ) ? $meta : [];

        // Zweiter Gurt neben der Statusbedingung: eine erneut bestaetigte
        // Zahlung soll keine zweite Mail ausloesen.
        if ( ! empty( $meta['mail_sent'] ) ) {
            return;
        }

        $vendor_id = (int) $payment->vendor_id;
        $buyer_id  = (int) $payment->buyer_id;
        $shop      = self::is_shop_pack( $vendor_id );

        $data = self::build( $payment, $meta, $via, $shop );

        self::send_to_vendor( $vendor_id, $data, $shop );

        // Die Bestellbestätigung an den Käufer gehört zum Shoptarif: sie ist
        // das, was einen Kauf beim Händler von einem privaten Handel
        // unterscheidet.
        if ( $shop && $buyer_id ) {
            self::send_to_buyer( $buyer_id, $data );
        }

        $meta['mail_sent'] = current_time( 'mysql' );
        $wpdb->update(
            $table,
            [ 'metadata' => wp_json_encode( $meta ) ],
            [ 'payment_hash' => $payment_hash ],
            [ '%s' ],
            [ '%s' ]
        );
    }

    /**
     * Alles zusammentragen, was in beiden Mails vorkommt.
     */
    private static function build( object $payment, array $meta, string $via, bool $shop ): array {
        $product_id = (int) $payment->product_id;
        // Faellt das Inserat weg, darf der Betreff nicht leer bleiben.
        $title = $product_id ? (string) get_the_title( $product_id ) : '';
        if ( trim( $title ) === '' ) {
            $title = __( 'Inserat', 'sk-core' );
        }
        $variant    = (string) ( $meta['variant'] ?? '' );
        $sats       = (int) $payment->amount_sats;

        $store = function_exists( 'sk_get_store_info' ) ? sk_get_store_info( (int) $payment->vendor_id ) : [];
        $buyer = get_userdata( (int) $payment->buyer_id );

        return [
            'titel'     => $variant !== '' ? $title . ' — ' . $variant : $title,
            'variante'  => $variant,
            'sats'      => $sats,
            'fiat'      => self::fiat( $sats, $payment->exchange_rate ),
            'weg'       => $via === 'onchain' ? __( 'Onchain', 'sk-core' ) : __( 'Lightning', 'sk-core' ),
            'lieferung' => $shop ? (string) ( $meta['delivery_note'] ?? '' ) : '',
            'shop'      => ! empty( $store['store_name'] ) ? $store['store_name'] : get_the_author_meta( 'display_name', (int) $payment->vendor_id ),
            'kaeufer'   => $buyer ? $buyer->display_name : __( 'Käufer', 'sk-core' ),
            'produkt'   => $product_id ? get_permalink( $product_id ) : '',
            'chat'      => $payment->chat_id
                ? add_query_arg( 'chat_id', (int) $payment->chat_id, sk_get_navigation_url( 'vendor-chat' ) )
                : '',
            'verkaeufe' => sk_get_navigation_url( 'lightning-transactions' ),
        ];
    }

    /**
     * Fiat-Betrag aus dem Kurs, der bei der Zahlung galt.
     *
     * Nicht der Tageskurs beim Mailversand — sonst stünde in der Mail ein
     * anderer Betrag als in der Verkaufsübersicht.
     */
    private static function fiat( int $sats, $rate ): string {
        $rate = (float) $rate;
        if ( $rate <= 0 ) {
            return '';
        }

        return number_format_i18n( $sats / 100000000 * $rate, 2 ) . ' EUR';
    }

    private static function send_to_vendor( int $vendor_id, array $data, bool $shop ): void {
        $user = get_userdata( $vendor_id );
        if ( ! $user || ! is_email( $user->user_email ) ) {
            return;
        }

        $subject = sprintf(
            /* translators: 1: amount in sats, 2: product */
            __( 'Zahlung eingegangen: %1$s Sats für %2$s', 'sk-core' ),
            number_format_i18n( $data['sats'] ),
            $data['titel']
        );

        self::send( $user->user_email, $subject, self::render( 'mail-order-vendor', $data + [ 'shop' => $shop ] ) );
    }

    private static function send_to_buyer( int $buyer_id, array $data ): void {
        $user = get_userdata( $buyer_id );
        if ( ! $user || ! is_email( $user->user_email ) ) {
            return;
        }

        $subject = sprintf(
            /* translators: 1: shop name */
            __( 'Deine Bestellung bei %1$s', 'sk-core' ),
            $data['shop']
        );

        self::send( $user->user_email, $subject, self::render( 'mail-order-buyer', $data ) );
    }

    private static function render( string $template, array $data ): string {
        $file = SK_PAYMENTS_TEMPLATES . '/' . $template . '.php';
        if ( ! file_exists( $file ) ) {
            return '';
        }

        ob_start();
        include $file;

        return (string) ob_get_clean();
    }

    private static function send( string $to, string $subject, string $body ): void {
        if ( $body === '' ) {
            return;
        }

        add_filter( 'wp_mail_content_type', [ __CLASS__, 'html' ] );
        wp_mail( $to, $subject, $body );
        remove_filter( 'wp_mail_content_type', [ __CLASS__, 'html' ] );
    }

    public static function html(): string {
        return 'text/html';
    }
}
