<?php

namespace SK\Modules\Escrow;

use SK\Modules\Payments\Chat\ChatIntegration;
use SK\Modules\Payments\ClientIp;
use SK\Modules\Payments\LNURL\ExchangeRate;
use SK\Modules\Payments\Variant;

defined( 'ABSPATH' ) || exit;

/**
 * "Treuhand" as a payment method of the instant purchase.
 *
 * The buyer generates a key in the browser (or pastes a hardware-wallet
 * xpub), names a refund address and sends a request. Nothing is deposited
 * yet: the seller has to accept first with a key of their own, only then
 * does an escrow address exist. That keeps every key single-use.
 */
final class Purchase {

    const REFUND_META = 'weo_refund_address';

    public function __construct() {
        add_action( 'wp_ajax_weo_request', [ $this, 'ajax_request' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
    }

    /** Seller switched the escrow on and has a payout address. */
    public static function seller_ready( int $vendor_id ): bool {
        return get_user_meta( $vendor_id, Dashboard::ENABLED_META, true ) === '1'
            && weo_validate_btc_address( (string) get_user_meta( $vendor_id, Dashboard::PAYOUT_META, true ) );
    }

    public static function available( int $vendor_id, int $buyer_id ): bool {
        return weo_enabled() && $vendor_id && $buyer_id && $vendor_id !== $buyer_id && self::seller_ready( $vendor_id );
    }

    public function enqueue(): void {
        if ( ! is_user_logged_in() || ! function_exists( 'is_product' ) || ! is_product() ) {
            return;
        }

        $vendor_id = (int) get_post_field( 'post_author', get_queried_object_id() );
        if ( ! self::available( $vendor_id, get_current_user_id() ) ) {
            return;
        }

        weo_enqueue_signer();
        wp_enqueue_script( 'weo-escrow-buy', WEO_URL . 'assets/sk-escrow-buy.js', [ 'jquery', 'weo-escrow-ui' ], SK_ESCROW_VERSION, true );
    }

    /**
     * Modal markup, printed by sk-payments' ProductPage next to its own
     * modals. Same inline styling so it looks like the other steps.
     */
    public static function render_modal( int $product_id ): void {
        $refund = (string) get_user_meta( get_current_user_id(), self::REFUND_META, true );
        ?>
        <div id="weo-escrow-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:99999;align-items:center;justify-content:center;">
            <div style="background:#1a2332;border:1px solid rgba(255,255,255,0.1);border-radius:12px;padding:24px;max-width:460px;width:90%;max-height:90vh;overflow-y:auto;color:#e8ecf0;">
                <h3 style="margin:0 0 8px;font-size:18px;"><i class="fas fa-handshake" style="color:#f7931a;"></i> <?php esc_html_e( 'Kauf über Treuhand', 'sk-core' ); ?></h3>
                <p style="margin:0 0 12px;color:#8a9bb0;font-size:13px;line-height:1.5;">
                    <?php esc_html_e( 'Dein Geld liegt auf einer 2-von-3-Multisig-Adresse, zu der du, der Verkäufer und der Marktplatz je einen Schlüssel halten. Ausgezahlt wird erst, wenn du den Erhalt bestätigst; bei Problemen entscheidet der Marktplatz. Zuerst muss der Verkäufer deine Anfrage annehmen, dann bekommst du die Einzahlungsadresse.', 'sk-core' ); ?>
                </p>
                <p><label><?php esc_html_e( 'Deine Rückerstattungsadresse (bc1…)', 'sk-core' ); ?><br>
                    <input type="text" id="weo_refund_address" value="<?php echo esc_attr( $refund ); ?>" placeholder="bc1q…" style="width:100%;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:#e8ecf0;font-size:13px;padding:8px;font-family:monospace;">
                </label></p>
                <p><label><?php esc_html_e( 'Dein Treuhand-Schlüssel (xpub)', 'sk-core' ); ?><br>
                    <input type="text" id="weo_buyer_xpub" value="" placeholder="xpub…" autocomplete="off" style="width:100%;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:#e8ecf0;font-size:13px;padding:8px;font-family:monospace;">
                </label></p>
                <?php echo weo_keygen_html( 'weo_buyer_xpub' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <p id="weo-escrow-error" style="display:none;margin:8px 0 0;color:#e05252;font-size:13px;"></p>
                <button type="button" id="weo-escrow-submit" data-product="<?php echo esc_attr( $product_id ); ?>"
                        style="display:block;width:100%;padding:12px;margin-top:14px;background:#f7931a;border:none;border-radius:8px;color:#fff;font-size:15px;font-weight:600;cursor:pointer;">
                    <?php esc_html_e( 'Treuhand-Anfrage senden', 'sk-core' ); ?>
                </button>
                <button type="button" id="weo-escrow-cancel"
                        style="display:block;width:100%;padding:10px;margin-top:8px;background:none;border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:#5a6a7e;font-size:14px;cursor:pointer;">
                    <?php esc_html_e( 'Abbrechen', 'sk-core' ); ?>
                </button>
            </div>
        </div>
        <?php
    }

    public function ajax_request(): void {
        check_ajax_referer( 'weo_escrow', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => __( 'Nicht eingeloggt.', 'sk-core' ) ] );
        }

        $buyer_id   = get_current_user_id();
        $product_id = absint( $_POST['product_id'] ?? 0 );
        $product    = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;

        if ( ! $product || get_post_status( $product_id ) !== 'publish' ) {
            wp_send_json_error( [ 'message' => __( 'Dieses Inserat ist nicht kaufbar.', 'sk-core' ) ] );
        }

        if ( function_exists( 'sk_rate_limit' ) && ! sk_rate_limit( 'sk_buy:' . $buyer_id, 10 ) ) {
            wp_send_json_error( [ 'message' => __( 'Zu viele Versuche. Bitte kurz warten.', 'sk-core' ) ] );
        }

        $vendor_id = (int) get_post_field( 'post_author', $product_id );
        if ( ! self::available( $vendor_id, $buyer_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Dieser Anbieter bietet keine Treuhand an.', 'sk-core' ) ] );
        }

        $variant_key = Variant::posted();
        if ( Variant::all( $product_id ) && ! Variant::find( $product_id, $variant_key ) ) {
            wp_send_json_error( [ 'message' => __( 'Bitte eine Ausführung wählen.', 'sk-core' ) ] );
        }

        $amount_sats = (int) Variant::price( $product, $variant_key );
        $title       = Variant::title( $product, $variant_key );
        if ( ! weo_validate_amount( $amount_sats ) ) {
            wp_send_json_error( [ 'message' => __( 'Inserat hat keinen gültigen Preis.', 'sk-core' ) ] );
        }

        $note = isset( $_POST['note'] ) ? trim( mb_substr( sanitize_textarea_field( wp_unslash( $_POST['note'] ) ), 0, 500 ) ) : '';
        if ( $note === '' ) {
            wp_send_json_error( [ 'message' => __( 'Bitte Lieferadresse oder Hinweis angeben.', 'sk-core' ) ] );
        }

        $xpub = weo_normalize_xpub( wp_unslash( $_POST['xpub'] ?? '' ) );
        if ( is_wp_error( $xpub ) ) {
            wp_send_json_error( [ 'message' => __( 'Ungültiger xpub. Bitte einen Schlüssel erzeugen oder einen Mainnet-xpub eintragen.', 'sk-core' ) ] );
        }

        $refund = weo_sanitize_btc_address( wp_unslash( $_POST['refund_address'] ?? '' ) );
        if ( ! weo_validate_btc_address( $refund ) ) {
            wp_send_json_error( [ 'message' => __( 'Bitte eine gültige Rückerstattungsadresse (bc1…) angeben.', 'sk-core' ) ] );
        }

        global $wpdb;
        $table = Rows::table();

        // One open request per listing and buyer is enough.
        $open = $wpdb->get_row( $wpdb->prepare(
            "SELECT payment_hash FROM {$table} WHERE buyer_id = %d AND product_id = %d AND context = %s AND status = 'requested' LIMIT 1",
            $buyer_id,
            $product_id,
            Rows::CONTEXT
        ) );
        if ( $open ) {
            wp_send_json_success( [
                'message' => __( 'Deine Anfrage läuft bereits. Der Verkäufer muss sie annehmen.', 'sk-core' ),
                'url'     => add_query_arg( [ 'tab' => 'purchases' ], sk_get_navigation_url( 'lightning-transactions' ) ),
            ] );
        }

        update_user_meta( $buyer_id, self::REFUND_META, $refund );
        update_user_meta( $buyer_id, 'sk_last_delivery_note', $note );

        $chat_id = 0;
        if ( class_exists( ChatIntegration::class ) ) {
            $found = ChatIntegration::find_or_create_chat_static( $buyer_id, $vendor_id, $product_id, $title );
            if ( ! is_wp_error( $found ) ) {
                $chat_id = (int) $found;
            }
        }

        $variant = Variant::find( $product_id, $variant_key );
        $rate    = class_exists( ExchangeRate::class ) ? ExchangeRate::get_btc_eur_rate() : null;
        $hash    = bin2hex( random_bytes( 32 ) );

        $meta = [
            'delivery_note' => $note,
            'variant'       => $variant ? (string) $variant['name'] : '',
            'escrow'        => [
                'buyer_xpub'     => $xpub,
                'refund_address' => $refund,
                'requested_at'   => current_time( 'mysql' ),
            ],
        ];

        $wpdb->insert( $table, [
            'vendor_id'       => $vendor_id,
            'buyer_id'        => $buyer_id,
            'product_id'      => $product_id,
            'chat_id'         => $chat_id ?: null,
            'amount_sats'     => $amount_sats,
            'payment_hash'    => $hash,
            'payment_request' => '',
            'status'          => 'requested',
            'context'         => Rows::CONTEXT,
            'exchange_rate'   => is_wp_error( $rate ) || $rate === null ? null : $rate,
            'buyer_ip_hash'   => class_exists( ClientIp::class ) ? ClientIp::hash() : null,
            'created_at'      => current_time( 'mysql' ),
            'metadata'        => wp_json_encode( $meta ),
        ] );

        $row = Rows::get( $hash );
        if ( ! $row ) {
            wp_send_json_error( [ 'message' => __( 'Anfrage konnte nicht gespeichert werden.', 'sk-core' ) ] );
        }

        if ( $chat_id ) {
            Notify::chat( $row, $buyer_id, sprintf(
                /* translators: 1: product, 2: amount in sats, 3: delivery note */
                __( "Bestellung (Treuhand): %1\$s (%2\$s Sats)\n\n%3\$s", 'sk-core' ),
                $title,
                number_format_i18n( $amount_sats ),
                $note
            ) );
        }
        Notify::requested( $row );

        wp_send_json_success( [
            'message' => __( 'Anfrage gesendet. Sobald der Verkäufer annimmt, findest du die Einzahlungsadresse unter „Käufe“ und im Chat.', 'sk-core' ),
            'url'     => add_query_arg( [ 'tab' => 'purchases' ], sk_get_navigation_url( 'lightning-transactions' ) ),
        ] );
    }
}
