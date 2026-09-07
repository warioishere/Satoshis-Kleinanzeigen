<?php

namespace SK\Modules\Payments;

defined( 'ABSPATH' ) || exit;

/**
 * Email on confirmed incoming payment.
 *
 * Until now, a seller only learned about a sale by checking the dashboard —
 * the entire payments module didn't have a single wp_mail outside the
 * commission reminder. That works for three listings, not for a catalog.
 *
 * Triggered on the status change to "confirmed". That's bound via SQL to
 * `status = 'pending'` and therefore fires exactly once, even when two
 * checks run at the same time.
 */
final class Notify {

    /** From this many listings, a package counts as the shop tier (Delphin). */
    const SHOP_MIN_PRODUCTS = 21;

    public function __construct() {
        add_action( 'sk_order_placed', [ __CLASS__, 'on_order_placed' ] );
        add_action( 'sk_order_shipped', [ __CLASS__, 'on_shipped' ] );
        add_action( 'sk_payment_confirmed', [ __CLASS__, 'on_confirmed' ], 10, 2 );
    }

    /**
     * Does the vendor have a package at Delphin tier or above?
     *
     * Same rule as for the catalog import. Deliberately duplicated here so
     * the notification doesn't depend on the import module being active.
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

    /**
     * Order received — not paid yet.
     *
     * Deliberately immediate rather than only after payment: that's how
     * card-payment shops do it too. With onchain, minutes pass until
     * confirmation anyway, and an order that never gets paid is still useful
     * information for the merchant.
     */
    public static function on_order_placed( string $payment_hash ): void {
        global $wpdb;

        $payment = self::load( $payment_hash );
        if ( ! $payment ) {
            return;
        }

        $vendor_id = (int) $payment->vendor_id;

        // Distinction from the free package: a private seller sees the
        // request in chat, a merchant gets it in their inbox.
        if ( ! self::is_shop_pack( $vendor_id ) ) {
            return;
        }

        $meta = self::meta( $payment );
        if ( ! empty( $meta['order_mail_sent'] ) ) {
            return;
        }

        $via  = $payment->context === 'onchain' ? 'onchain' : 'lightning';
        $data = self::build( $payment, $meta, $via, true );

        $user = get_userdata( $vendor_id );
        if ( $user && is_email( $user->user_email ) ) {
            $subject = sprintf(
                /* translators: 1: product */
                __( 'Neue Bestellung: %1$s', 'sk-core' ),
                $data['titel']
            );

            self::send( $user->user_email, $subject, self::render( 'mail-order-placed', $data ) );
        }

        $meta['order_mail_sent'] = current_time( 'mysql' );
        self::save_meta( $payment_hash, $meta );
    }

    private static function load( string $payment_hash ): ?object {
        global $wpdb;

        $table = $wpdb->prefix . 'sk_lightning_payments';

        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE payment_hash = %s", $payment_hash )
        );
    }

    private static function meta( object $payment ): array {
        $meta = json_decode( (string) $payment->metadata, true );

        return is_array( $meta ) ? $meta : [];
    }

    private static function save_meta( string $payment_hash, array $meta ): void {
        global $wpdb;

        $wpdb->update(
            $wpdb->prefix . 'sk_lightning_payments',
            [ 'metadata' => wp_json_encode( $meta ) ],
            [ 'payment_hash' => $payment_hash ],
            [ '%s' ],
            [ '%s' ]
        );
    }

    /**
     * Goods are on the way — the buyer gets the tracking information.
     *
     * Without this email, the shipping details would sit unnoticed in the
     * vendor's dashboard and the buyer would keep asking in chat.
     */
    public static function on_shipped( string $payment_hash ): void {
        $payment = self::load( $payment_hash );
        if ( ! $payment ) {
            return;
        }

        $buyer_id = (int) $payment->buyer_id;
        $ship     = Shipping::get( $payment );

        if ( ! $buyer_id || ! $ship ) {
            return;
        }

        $user = get_userdata( $buyer_id );
        if ( ! $user || ! is_email( $user->user_email ) ) {
            return;
        }

        $meta = self::meta( $payment );
        $via  = $payment->context === 'onchain' ? 'onchain' : 'lightning';
        $data = self::build( $payment, $meta, $via, true ) + [
            'versender'      => $ship['label'],
            'sendungsnummer' => $ship['number'],
            'sendungslink'   => $ship['url'],
        ];

        $subject = sprintf(
            /* translators: %s: product */
            __( 'Unterwegs: %s', 'sk-core' ),
            $data['titel']
        );

        self::send( $user->user_email, $subject, self::render( 'mail-order-shipped', $data ) );
    }

    public static function on_confirmed( string $payment_hash, string $via ): void {
        $payment = self::load( $payment_hash );
        if ( ! $payment ) {
            return;
        }

        $meta = self::meta( $payment );

        // Second safeguard next to the status condition: a payment confirmed
        // again should not trigger a second email.
        if ( ! empty( $meta['mail_sent'] ) ) {
            return;
        }

        $vendor_id = (int) $payment->vendor_id;
        $buyer_id  = (int) $payment->buyer_id;
        $shop      = self::is_shop_pack( $vendor_id );

        $data = self::build( $payment, $meta, $via, $shop );

        self::send_to_vendor( $vendor_id, $data, $shop );

        // The order confirmation to the buyer belongs to the shop tier: it's
        // what distinguishes a purchase from a merchant from a private
        // trade.
        if ( $shop && $buyer_id ) {
            self::send_to_buyer( $buyer_id, $data );
        }

        $meta['mail_sent'] = current_time( 'mysql' );
        self::save_meta( $payment_hash, $meta );
    }

    /**
     * Gather everything that appears in both emails.
     */
    private static function build( object $payment, array $meta, string $via, bool $shop ): array {
        $product_id = (int) $payment->product_id;
        // If the listing is gone, the subject must not stay empty.
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
     * Fiat amount from the rate that applied at the time of payment.
     *
     * Not the day's rate at the time of sending — otherwise the email would
     * show a different amount than the sales overview.
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
