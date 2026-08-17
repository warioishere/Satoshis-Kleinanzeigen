<?php

namespace SK\Modules\NostrMarket\Bridge;

use SK\Modules\NostrMarket\EventSender;

defined( 'ABSPATH' ) || exit;

/**
 * Polls Nostr relays for incoming NIP-04 DMs to the marketplace pubkey.
 * Parses messages, identifies the target vendor, and creates VendorChat entries.
 *
 * Runs via WP Cron every 2 minutes.
 */
class NostrDMListener {

    const CRON_HOOK     = 'sk_nostr_market_poll_dms';
    const LAST_SEEN_KEY = 'sk_nostr_market_last_dm_timestamp';

    public static function init(): void {
        if ( sk_get_option( 'sk_nostr_market_bridge_enabled', 'sk_nostr_market', 'off' ) !== 'on' ) {
            return;
        }

        add_action( self::CRON_HOOK, [ __CLASS__, 'poll' ] );

        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time(), 'two_minutes', self::CRON_HOOK );
        }

        add_filter( 'cron_schedules', [ __CLASS__, 'add_cron_interval' ] );
    }

    public static function add_cron_interval( $schedules ) {
        $schedules['two_minutes'] = [
            'interval' => 2 * MINUTE_IN_SECONDS,
            'display'  => __( 'Alle 2 Minuten', 'sk-core' ),
        ];
        return $schedules;
    }

    /**
     * Poll relays for new DMs to our pubkey.
     */
    public static function poll(): void {
        $privkey = EventSender::get_privkey();
        $pubkey  = EventSender::get_pubkey();
        if ( ! $privkey || ! $pubkey ) {
            return;
        }

        $relays = EventSender::get_relays();
        if ( empty( $relays ) ) {
            return;
        }

        $last_seen = (int) get_option( self::LAST_SEEN_KEY, time() - 300 );

        foreach ( $relays as $relay_url ) {
            $events = self::fetch_dms( $relay_url, $pubkey, $last_seen );
            if ( empty( $events ) ) {
                continue;
            }

            foreach ( $events as $event ) {
                self::process_dm( $event, $privkey, $pubkey );

                // Track latest timestamp.
                $ts = (int) ( $event['created_at'] ?? 0 );
                if ( $ts > $last_seen ) {
                    $last_seen = $ts;
                }
            }

            // One relay is enough — break after first successful poll.
            break;
        }

        update_option( self::LAST_SEEN_KEY, $last_seen );
    }

    /**
     * Fetch Kind 4 (NIP-04) DMs addressed to our pubkey since $since.
     */
    private static function fetch_dms( string $relay_url, string $pubkey, int $since ): array {
        if ( ! class_exists( '\WebSocket\Client' ) ) {
            return [];
        }

        try {
            // Relay TLS must be verified — otherwise a MITM can inject events
            // that end up as chat messages.
            $ctx = stream_context_create( [ 'ssl' => [ 'verify_peer' => true, 'verify_peer_name' => true ] ] );
            $client = new \WebSocket\Client( $relay_url, [ 'context' => $ctx, 'timeout' => 10 ] );

            $sub_id = bin2hex( random_bytes( 8 ) );
            $filter = [
                'kinds' => [ 4 ],
                '#p'    => [ $pubkey ],
                'since' => $since + 1,
                'limit' => 50,
            ];

            $client->text( wp_json_encode( [ 'REQ', $sub_id, $filter ] ) );

            $events = [];
            $start  = time();

            while ( time() - $start < 8 ) {
                $msg = $client->receive();
                if ( $msg === null ) {
                    break;
                }

                $data = json_decode( $msg->getContent(), true );
                if ( ! is_array( $data ) ) {
                    continue;
                }

                if ( $data[0] === 'EVENT' && isset( $data[2] ) ) {
                    $events[] = $data[2];
                }

                if ( $data[0] === 'EOSE' ) {
                    break;
                }
            }

            $client->text( wp_json_encode( [ 'CLOSE', $sub_id ] ) );
            $client->disconnect();

            return $events;

        } catch ( \Exception $e ) {
            error_log( '[SK Nostr Market Bridge] Relay poll error: ' . $e->getMessage() );
            return [];
        }
    }

    /**
     * Process a single incoming DM.
     */
    private static function process_dm( array $event, string $privkey, string $our_pubkey ): void {
        $sender_pubkey = $event['pubkey'] ?? '';
        $content       = $event['content'] ?? '';
        $event_id      = $event['id'] ?? '';

        if ( empty( $content ) || empty( $event_id ) ) {
            return;
        }

        // Pubkeys are used in meta queries and displayed to vendors.
        if ( ! preg_match( '/^[0-9a-f]{64}$/i', $sender_pubkey ) ) {
            return;
        }
        $sender_pubkey = strtolower( $sender_pubkey );

        // Skip our own messages.
        if ( $sender_pubkey === $our_pubkey ) {
            return;
        }

        // Deduplicate: check if we already processed this event.
        $processed_key = 'sk_dm_' . substr( $event_id, 0, 32 );
        if ( get_transient( $processed_key ) ) {
            return;
        }
        set_transient( $processed_key, 1, DAY_IN_SECONDS );

        // Decrypt NIP-04 content.
        try {
            $decrypted = \swentel\nostr\Encryption\Nip04::decrypt( $content, $privkey, $sender_pubkey );
        } catch ( \Exception $e ) {
            error_log( '[SK Nostr Market Bridge] Decrypt failed: ' . $e->getMessage() );
            return;
        }

        if ( empty( $decrypted ) ) {
            return;
        }

        // Try to parse as NIP-15 order (JSON with type field).
        $order = json_decode( $decrypted, true );
        $is_order = is_array( $order ) && isset( $order['type'] ) && $order['type'] === 0;

        if ( $is_order ) {
            self::handle_order( $order, $sender_pubkey, $event_id );
        } else {
            // Plain text message — try to route to a vendor.
            self::handle_message( $decrypted, $sender_pubkey, $event_id );
        }
    }

    /**
     * Handle a NIP-15 order (type 0).
     */
    private static function handle_order( array $order, string $sender_pubkey, string $event_id ): void {
        $items = $order['items'] ?? [];
        if ( empty( $items ) ) {
            return;
        }

        // Find the product and vendor.
        $first_item  = $items[0];
        $product_ref = $first_item['product_id'] ?? '';

        // product_ref format: "product-{post_id}"
        $post_id = 0;
        if ( strpos( $product_ref, 'product-' ) === 0 ) {
            $post_id = (int) substr( $product_ref, 8 );
        }

        if ( ! $post_id ) {
            return;
        }

        $post = get_post( $post_id );
        if ( ! $post || $post->post_type !== 'product' ) {
            return;
        }

        $vendor_id = (int) $post->post_author;

        // Build order message for VendorChat.
        $product_title = $post->post_title;
        // Everything below comes from an unauthenticated Nostr DM.
        $quantity      = max( 1, (int) ( $first_item['quantity'] ?? 1 ) );
        $name          = self::clean_field( $order['name'] ?? '', 120 );
        $address       = self::clean_field( $order['address'] ?? '', 400 );
        $note          = self::clean_field( $order['message'] ?? '', 2000 );
        $npub          = self::pubkey_to_npub( $sender_pubkey );

        $message = "[nostr_order]\n";
        $message .= "Nostr-Bestellung von {$npub}\n";
        $message .= "Produkt: {$product_title} x{$quantity}\n";
        if ( $name ) {
            $message .= "Name: {$name}\n";
        }
        if ( $address ) {
            $message .= "Adresse: {$address}\n";
        }
        if ( $note ) {
            $message .= "Nachricht: {$note}\n";
        }
        $message .= "[/nostr_order]";

        self::create_bridge_chat( $vendor_id, $sender_pubkey, $post_id, $product_title, $message );

        // Auto-create invoice and send NIP-15 Payment Request (Type 1) back.
        self::send_payment_request( $sender_pubkey, $post_id, $vendor_id, $order );
    }

    /**
     * Create an invoice via sk-payments and send NIP-15 Payment Request (Type 1).
     */
    private static function send_payment_request( string $buyer_pubkey, int $post_id, int $vendor_id, array $order ): void {
        $product = function_exists( 'wc_get_product' ) ? wc_get_product( $post_id ) : null;
        if ( ! $product ) {
            return;
        }

        $quantity    = (int) ( $order['items'][0]['quantity'] ?? 1 );
        $price_sats  = (int) $product->get_price() * $quantity;
        $order_id    = $order['id'] ?? 'nostr-' . substr( bin2hex( random_bytes( 8 ) ), 0, 16 );

        // Build payment options.
        $payment_options = [];

        // Try Lightning invoice via sk-payments (NWC/LNDHub/LNURL).
        if ( class_exists( 'SK\Modules\Payments\StoreSettings' ) ) {
            $has_ln = \SK\Modules\Payments\StoreSettings::has_lightning( $vendor_id );

            if ( $has_ln ) {
                // Create invoice via REST controller internally.
                $request = new \WP_REST_Request( 'POST', '/sk/v1/lightning/invoice' );
                $request->set_param( 'vendor_id', $vendor_id );
                $request->set_param( 'amount_sats', $price_sats );
                $request->set_param( 'product_id', $post_id );
                $request->set_param( 'buyer_id', 0 ); // Nostr user has no WP account.

                if ( class_exists( 'SK\Modules\Payments\REST\LightningController' ) ) {
                    $controller = new \SK\Modules\Payments\REST\LightningController();
                    $response = $controller->create_invoice( $request );

                    if ( ! is_wp_error( $response ) ) {
                        $data = $response->get_data();
                        if ( ! empty( $data['payment_request'] ) ) {
                            $payment_options[] = [
                                'type' => 'ln',
                                'link' => $data['payment_request'],
                            ];
                        }
                    }
                }
            }

            // Onchain address.
            $has_onchain = \SK\Modules\Payments\StoreSettings::has_onchain( $vendor_id );
            if ( $has_onchain ) {
                $btc_address = \SK\Modules\Payments\StoreSettings::get_next_onchain_address( $vendor_id );
                if ( $btc_address ) {
                    $btc_amount = number_format( $price_sats / 100000000, 8, '.', '' );
                    $payment_options[] = [
                        'type' => 'btc',
                        'link' => $btc_address,
                    ];
                }
            }
        }

        if ( empty( $payment_options ) ) {
            // No payment method — send URL fallback to product page.
            $payment_options[] = [
                'type' => 'url',
                'link' => get_permalink( $post_id ),
            ];
        }

        // Build NIP-15 Payment Request (Type 1).
        $payment_request = wp_json_encode( [
            'id'              => $order_id,
            'type'            => 1,
            'message'         => 'Zahlung für: ' . $product->get_name(),
            'payment_options' => $payment_options,
        ] );

        // Send as NIP-04 encrypted DM back to the buyer.
        ChatBridge::send_dm( $buyer_pubkey, $payment_request );
    }

    /**
     * Handle a plain text message — route to existing bridge chat or first vendor.
     */
    private static function handle_message( string $text, string $sender_pubkey, string $event_id ): void {
        $text = self::clean_field( $text, 4000 );
        if ( $text === '' ) {
            return;
        }

        // Find existing bridge chat for this pubkey.
        $existing_chat = self::find_bridge_chat( $sender_pubkey );

        if ( $existing_chat ) {
            $admin_id = self::get_admin_user_id();
            ChatBridge::add_message( $existing_chat, $admin_id, $text, $sender_pubkey );
            return;
        }

        // No existing chat — can't route without product context. Ignore.
        error_log( '[SK Nostr Market Bridge] Unroutable DM from ' . substr( $sender_pubkey, 0, 16 ) . '...' );
    }

    /**
     * Create a VendorChat bridged to a Nostr user.
     */
    private static function create_bridge_chat( int $vendor_id, string $nostr_pubkey, int $product_id, string $product_title, string $message ): int {
        $admin_id = self::get_admin_user_id();

        // Check for existing bridge chat with this pubkey + vendor.
        $args = [
            'post_type'      => 'vendor_chat',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'meta_query'     => [
                'relation' => 'AND',
                [ 'key' => '_dvc_nostr_bridge', 'value' => '1' ],
                [ 'key' => '_dvc_nostr_pubkey', 'value' => $nostr_pubkey ],
                [ 'key' => '_dvc_participant_2', 'value' => $vendor_id ],
            ],
        ];

        $query = new \WP_Query( $args );
        if ( $query->have_posts() ) {
            $chat_id = $query->posts[0]->ID;
            ChatBridge::add_message( $chat_id, $admin_id, $message, $nostr_pubkey );
            return $chat_id;
        }

        // Create new bridge chat.
        $npub = self::pubkey_to_npub( $nostr_pubkey );
        $chat_id = wp_insert_post( [
            'post_type'   => 'vendor_chat',
            'post_status' => 'publish',
            'post_title'  => 'Nostr: ' . substr( $npub, 0, 16 ) . '... → ' . $product_title,
            'post_author' => $admin_id,
        ] );

        if ( is_wp_error( $chat_id ) ) {
            return 0;
        }

        update_post_meta( $chat_id, '_dvc_participant_1', $admin_id );
        update_post_meta( $chat_id, '_dvc_participant_2', $vendor_id );
        update_post_meta( $chat_id, '_dvc_product_id', $product_id );
        update_post_meta( $chat_id, '_dvc_messages', [] );
        update_post_meta( $chat_id, '_dvc_archived_by', [] );

        // Bridge metadata.
        update_post_meta( $chat_id, '_dvc_nostr_bridge', '1' );
        update_post_meta( $chat_id, '_dvc_nostr_pubkey', $nostr_pubkey );

        ChatBridge::add_message( $chat_id, $admin_id, $message, $nostr_pubkey );

        return $chat_id;
    }

    /**
     * Find an existing bridge chat for a Nostr pubkey.
     */
    private static function find_bridge_chat( string $nostr_pubkey ): int {
        $args = [
            'post_type'      => 'vendor_chat',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'meta_query'     => [
                'relation' => 'AND',
                [ 'key' => '_dvc_nostr_bridge', 'value' => '1' ],
                [ 'key' => '_dvc_nostr_pubkey', 'value' => $nostr_pubkey ],
            ],
        ];

        $query = new \WP_Query( $args );
        return $query->have_posts() ? $query->posts[0]->ID : 0;
    }

    /**
     * Sanitize a field from an untrusted Nostr DM before it becomes chat text.
     *
     * Strips markup, caps the length, and removes payment markers so an
     * outside party cannot inject a payment card into a vendor's chat.
     */
    private static function clean_field( $value, int $max_length ): string {
        if ( ! is_scalar( $value ) ) {
            return '';
        }

        $text = sanitize_textarea_field( (string) $value );

        if ( class_exists( 'SK\Core\Dashboard\Modules\VendorChat' ) ) {
            $text = \SK\Core\Dashboard\Modules\VendorChat::sanitize_user_message( $text );
        }

        if ( mb_strlen( $text ) > $max_length ) {
            $text = mb_substr( $text, 0, $max_length ) . '…';
        }

        return trim( $text );
    }

    private static function pubkey_to_npub( string $hex_pubkey ): string {
        try {
            $key = new \swentel\nostr\Key\Key();
            return $key->convertPublicKeyToBech32( $hex_pubkey );
        } catch ( \Exception $e ) {
            return 'npub...' . substr( $hex_pubkey, 0, 8 );
        }
    }

    private static function get_admin_user_id(): int {
        $admin = get_user_by( 'email', get_option( 'admin_email' ) );
        return $admin ? $admin->ID : 1;
    }
}
