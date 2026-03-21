<?php

namespace SK\Modules\NostrMarket\Bridge;

use SK\Modules\NostrMarket\EventSender;

defined( 'ABSPATH' ) || exit;

/**
 * Bridges VendorChat messages to Nostr DMs and vice versa.
 *
 * Incoming: NostrDMListener creates chat messages from Nostr DMs.
 * Outgoing: When vendor replies in a bridge chat, sends NIP-04 DM back to Nostr user.
 */
class ChatBridge {

    public static function init(): void {
        if ( sk_get_option( 'sk_nostr_market_bridge_enabled', 'sk_nostr_market', 'off' ) !== 'on' ) {
            return;
        }

        // Hook into VendorChat message saving to detect vendor replies.
        add_action( 'updated_post_meta', [ __CLASS__, 'on_chat_meta_updated' ], 10, 4 );

        // AJAX: Vendor creates invoice in a bridge chat → sent as Nostr DM.
        add_action( 'wp_ajax_sk_nostr_bridge_invoice', [ __CLASS__, 'ajax_create_bridge_invoice' ] );

        // Inject invoice button JS into vendor dashboard.
        add_action( 'wp_footer', [ __CLASS__, 'render_bridge_invoice_js' ] );
    }

    /**
     * AJAX: Vendor creates a Lightning invoice in a bridge chat.
     * Invoice is added to chat AND sent as Nostr DM to the buyer.
     */
    public static function ajax_create_bridge_invoice(): void {
        check_ajax_referer( 'sk_lightning_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'Nicht eingeloggt.' ] );
        }

        $chat_id     = absint( $_POST['chat_id'] ?? 0 );
        $amount_sats = absint( $_POST['amount_sats'] ?? 0 );
        $vendor_id   = get_current_user_id();

        if ( ! $chat_id || ! $amount_sats ) {
            wp_send_json_error( [ 'message' => 'Fehlende Parameter.' ] );
        }

        // Verify this is a bridge chat and vendor is participant.
        $is_bridge = get_post_meta( $chat_id, '_dvc_nostr_bridge', true );
        if ( $is_bridge !== '1' ) {
            wp_send_json_error( [ 'message' => 'Kein Nostr-Bridge-Chat.' ] );
        }

        $p2 = (int) get_post_meta( $chat_id, '_dvc_participant_2', true );
        if ( $p2 !== $vendor_id ) {
            wp_send_json_error( [ 'message' => 'Keine Berechtigung.' ] );
        }

        $nostr_pubkey = get_post_meta( $chat_id, '_dvc_nostr_pubkey', true );
        if ( empty( $nostr_pubkey ) ) {
            wp_send_json_error( [ 'message' => 'Kein Nostr Pubkey für diesen Chat.' ] );
        }

        $product_id = (int) get_post_meta( $chat_id, '_dvc_product_id', true );
        $product_title = $product_id ? get_the_title( $product_id ) : '';

        // Create Lightning invoice via sk-payments.
        $bolt11      = '';
        $btc_address = '';

        if ( class_exists( 'SK\Modules\Payments\StoreSettings' ) ) {
            // Lightning.
            if ( \SK\Modules\Payments\StoreSettings::has_lightning( $vendor_id ) ) {
                $request = new \WP_REST_Request( 'POST', '/sk/v1/lightning/invoice' );
                $request->set_param( 'vendor_id', $vendor_id );
                $request->set_param( 'amount_sats', $amount_sats );
                $request->set_param( 'product_id', $product_id );
                $request->set_param( 'chat_id', $chat_id );
                $request->set_param( 'buyer_id', 0 );

                if ( class_exists( 'SK\Modules\Payments\REST\LightningController' ) ) {
                    $controller = new \SK\Modules\Payments\REST\LightningController();
                    $response   = $controller->create_invoice( $request );
                    if ( ! is_wp_error( $response ) ) {
                        $data   = $response->get_data();
                        $bolt11 = $data['payment_request'] ?? '';
                    }
                }
            }

            // Onchain.
            if ( \SK\Modules\Payments\StoreSettings::has_onchain( $vendor_id ) ) {
                $btc_address = \SK\Modules\Payments\StoreSettings::get_next_onchain_address( $vendor_id );
            }
        }

        if ( empty( $bolt11 ) && empty( $btc_address ) ) {
            wp_send_json_error( [ 'message' => 'Keine Zahlungsmethode konfiguriert.' ] );
        }

        // Add invoice to VendorChat.
        $sats_formatted = number_format( $amount_sats, 0, ',', '.' );
        $chat_msg = "Invoice erstellt: {$sats_formatted} Sats";
        if ( $product_title ) {
            $chat_msg .= " für {$product_title}";
        }
        if ( $bolt11 ) {
            $chat_msg .= "\n\nLightning: {$bolt11}";
        }
        if ( $btc_address ) {
            $btc_amount = number_format( $amount_sats / 100000000, 8, '.', '' );
            $chat_msg .= "\n\nOnchain: {$btc_address} ({$btc_amount} BTC)";
        }

        self::add_message( $chat_id, $vendor_id, $chat_msg, '' );

        // Send invoice as Nostr DM to the buyer.
        $dm_text = "Zahlung: {$sats_formatted} Sats";
        if ( $product_title ) {
            $dm_text .= " für {$product_title}";
        }
        if ( $bolt11 ) {
            $dm_text .= "\n\nLightning Invoice:\n{$bolt11}";
        }
        if ( $btc_address ) {
            $btc_amount = number_format( $amount_sats / 100000000, 8, '.', '' );
            $dm_text .= "\n\nBitcoin Adresse:\n{$btc_address}\nBetrag: {$btc_amount} BTC";
        }

        self::send_dm( $nostr_pubkey, $dm_text );

        wp_send_json_success( [
            'message'     => 'Invoice erstellt und an Nostr User gesendet.',
            'amount_sats' => $amount_sats,
            'has_ln'      => ! empty( $bolt11 ),
            'has_onchain' => ! empty( $btc_address ),
        ] );
    }

    /**
     * Add a message to a bridge chat (incoming from Nostr).
     */
    public static function add_message( int $chat_id, int $sender_id, string $message, string $nostr_pubkey ): void {
        $messages   = get_post_meta( $chat_id, '_dvc_messages', true );
        $messages   = is_array( $messages ) ? $messages : [];
        $messages[] = [
            'user_id'      => $sender_id,
            'message'      => $message,
            'timestamp'    => current_time( 'timestamp' ),
            'nostr_pubkey' => $nostr_pubkey,
        ];
        update_post_meta( $chat_id, '_dvc_messages', $messages );
        update_post_meta( $chat_id, '_dvc_last_message_time', current_time( 'timestamp' ) );
    }

    /**
     * Detect when VendorChat messages are updated.
     * If a vendor adds a message to a bridge chat, send it as NIP-04 DM.
     */
    public static function on_chat_meta_updated( $meta_id, $post_id, $meta_key, $meta_value ): void {
        if ( $meta_key !== '_dvc_messages' ) {
            return;
        }

        // Is this a bridge chat?
        $is_bridge = get_post_meta( $post_id, '_dvc_nostr_bridge', true );
        if ( $is_bridge !== '1' ) {
            return;
        }

        $nostr_pubkey = get_post_meta( $post_id, '_dvc_nostr_pubkey', true );
        if ( empty( $nostr_pubkey ) ) {
            return;
        }

        // Get the latest message.
        $messages = is_array( $meta_value ) ? $meta_value : [];
        if ( empty( $messages ) ) {
            return;
        }

        $latest = end( $messages );

        // Skip if message is from the admin (incoming Nostr message).
        $admin_id = self::get_admin_user_id();
        if ( (int) ( $latest['user_id'] ?? 0 ) === $admin_id ) {
            return;
        }

        // Skip if message already has a nostr_pubkey (it came from Nostr, don't echo back).
        if ( ! empty( $latest['nostr_pubkey'] ) ) {
            return;
        }

        // This is a vendor reply — send as NIP-04 DM to the Nostr user.
        $text = $latest['message'] ?? '';
        if ( empty( $text ) ) {
            return;
        }

        // Get vendor name for context.
        $vendor_id = (int) ( $latest['user_id'] ?? 0 );
        $store_info = function_exists( 'sk_get_store_info' ) ? sk_get_store_info( $vendor_id ) : [];
        $vendor_name = $store_info['store_name'] ?? ( get_userdata( $vendor_id )->display_name ?? 'Vendor' );

        $dm_text = "{$vendor_name}: {$text}";

        self::send_dm( $nostr_pubkey, $dm_text );
    }

    /**
     * Send a NIP-04 encrypted DM to a Nostr pubkey.
     */
    public static function send_dm( string $recipient_pubkey, string $text ): bool {
        $privkey = EventSender::get_privkey();
        if ( ! $privkey ) {
            return false;
        }

        if ( ! class_exists( '\swentel\nostr\Encryption\Nip04' ) ) {
            return false;
        }

        try {
            $encrypted = \swentel\nostr\Encryption\Nip04::encrypt( $text, $privkey, $recipient_pubkey );

            $event_id = EventSender::send( 4, $encrypted, [
                [ 'p', $recipient_pubkey ],
            ] );

            return $event_id !== null;

        } catch ( \Exception $e ) {
            error_log( '[SK Nostr Market Bridge] DM send failed: ' . $e->getMessage() );
            return false;
        }
    }

    /**
     * Inject JS for the "Invoice erstellen" button in Nostr bridge chats.
     * Only renders on vendor-chat dashboard page.
     */
    public static function render_bridge_invoice_js(): void {
        if ( ! is_user_logged_in() ) {
            return;
        }

        // Only on vendor chat pages.
        $chat_id = absint( $_GET['chat_id'] ?? 0 );
        if ( ! $chat_id ) {
            return;
        }

        // Only for bridge chats.
        if ( get_post_meta( $chat_id, '_dvc_nostr_bridge', true ) !== '1' ) {
            return;
        }

        $product_id = (int) get_post_meta( $chat_id, '_dvc_product_id', true );
        $price_sats = 0;
        if ( $product_id && function_exists( 'wc_get_product' ) ) {
            $product = wc_get_product( $product_id );
            if ( $product ) {
                $price_sats = (int) $product->get_price();
            }
        }

        $nonce = wp_create_nonce( 'sk_lightning_nonce' );
        ?>
        <script>
        (function($) {
            // Add "Invoice erstellen" button to bridge chat.
            var $chatArea = $('#dvc-messages-area, .dvc-chat-messages');
            if (!$chatArea.length) return;

            var btnHtml = '<div id="sk-nostr-bridge-invoice" style="padding:12px;background:#1a2332;border-top:1px solid rgba(255,255,255,0.07);display:flex;gap:8px;align-items:center;">' +
                '<input type="number" id="sk-nostr-invoice-amount" value="<?php echo esc_attr( $price_sats ); ?>" min="1" placeholder="Sats" ' +
                'style="flex:1;background:#0f1923;border:1px solid rgba(255,255,255,0.08);color:#e8ecf0;padding:8px 12px;border-radius:6px;font-size:14px;" />' +
                '<button type="button" id="sk-nostr-invoice-btn" ' +
                'style="background:#f7931a;color:#fff;border:none;padding:8px 16px;border-radius:6px;cursor:pointer;font-size:14px;font-weight:600;white-space:nowrap;">' +
                'Invoice erstellen + senden</button>' +
                '</div>';

            $chatArea.after(btnHtml);

            $('#sk-nostr-invoice-btn').on('click', function() {
                var $btn = $(this);
                var amount = parseInt($('#sk-nostr-invoice-amount').val(), 10);
                if (!amount || amount < 1) {
                    alert('Bitte Betrag in Sats eingeben.');
                    return;
                }

                $btn.prop('disabled', true).text('Wird erstellt...');

                $.post('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
                    action: 'sk_nostr_bridge_invoice',
                    nonce: '<?php echo $nonce; ?>',
                    chat_id: <?php echo $chat_id; ?>,
                    amount_sats: amount
                }, function(res) {
                    $btn.prop('disabled', false).text('Invoice erstellen + senden');
                    if (res.success) {
                        $btn.text('Gesendet!');
                        setTimeout(function() { $btn.text('Invoice erstellen + senden'); }, 3000);
                        // Reload chat messages.
                        if (typeof window.dvcLoadMessages === 'function') {
                            window.dvcLoadMessages();
                        }
                    } else {
                        alert(res.data && res.data.message ? res.data.message : 'Fehler.');
                    }
                }).fail(function() {
                    $btn.prop('disabled', false).text('Invoice erstellen + senden');
                    alert('Netzwerkfehler.');
                });
            });
        })(jQuery);
        </script>
        <?php
    }

    private static function get_admin_user_id(): int {
        $admin = get_user_by( 'email', get_option( 'admin_email' ) );
        return $admin ? $admin->ID : 1;
    }
}
