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

        /*
         * Der Filter MUSS vor dem Einplanen stehen. Sonst kennt WordPress das
         * Intervall in dem Moment noch nicht, wp_schedule_event() liefert
         * false, und weil init() bei jedem Aufruf dieselbe Reihenfolge
         * durchlaeuft, wurde die Abfrage nie eingeplant — die Bruecke lief nie.
         */
        add_filter( 'cron_schedules', [ __CLASS__, 'add_cron_interval' ] );

        add_action( self::CRON_HOOK, [ __CLASS__, 'poll' ] );

        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time(), 'two_minutes', self::CRON_HOOK );
        }
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

        /*
         * Zwei Tage zurueckschauen statt ab dem letzten Stand.
         *
         * Ein Gift Wrap traegt nach NIP-59 absichtlich einen verwuerfelten
         * Zeitstempel, bis zu zwei Tage in der Vergangenheit. Ein Fenster ab
         * dem letzten Stand haette solche Nachrichten dauerhaft uebersehen.
         * Dass dabei Bekanntes erneut kommt, faengt die Dublettenpruefung ab.
         */
        $since = max( 0, $last_seen - 2 * DAY_IN_SECONDS );

        foreach ( $relays as $relay_url ) {
            $events = self::fetch_dms( $relay_url, $pubkey, $since );

            foreach ( $events as $event ) {
                self::process_dm( $event, $privkey, $pubkey );
            }
        }

        // Der Fortschritt haengt an der Uhr, nicht an den Zeitstempeln der
        // Ereignisse — die eines Gift Wraps sind erfunden.
        update_option( self::LAST_SEEN_KEY, time() );
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
                // 4 = NIP-04, 1059 = NIP-17 Gift Wrap. Wir senden selbst als
                // Gift Wrap; wer darauf antwortet, tut es ebenfalls, und diese
                // Antworten waren mit einem Filter auf Kind 4 unsichtbar.
                'kinds' => [ 4, 1059 ],
                '#p'    => [ $pubkey ],
                'since' => $since,
                'limit' => 100,
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
        $event_id = $event['id'] ?? '';

        if ( empty( $event_id ) || ! preg_match( '/^[0-9a-f]{64}$/i', $event_id ) ) {
            return;
        }

        /*
         * Dublettenpruefung zuerst, und auf die aeussere Kennung. Das Fenster
         * reicht zwei Tage zurueck, also kommt Bekanntes bei jedem Lauf erneut
         * vorbei; die Marke muss deshalb laenger halten als das Fenster.
         */
        $processed_key = 'sk_dm_' . substr( $event_id, 0, 32 );

        if ( get_transient( $processed_key ) ) {
            return;
        }

        set_transient( $processed_key, 1, 3 * DAY_IN_SECONDS );

        $kind = (int) ( $event['kind'] ?? 0 );

        if ( 1059 === $kind ) {
            $inner = self::unwrap_gift_wrap( $event, $privkey );

            if ( null === $inner ) {
                return;
            }

            $sender_pubkey = $inner['pubkey'];
            $decrypted     = $inner['content'];
        } else {
            $sender_pubkey = $event['pubkey'] ?? '';
            $content       = $event['content'] ?? '';

            if ( '' === $content ) {
                return;
            }

            // Pubkeys are used in meta queries and displayed to vendors.
            if ( ! preg_match( '/^[0-9a-f]{64}$/i', $sender_pubkey ) ) {
                return;
            }

            $sender_pubkey = strtolower( $sender_pubkey );

            try {
                $decrypted = \swentel\nostr\Encryption\Nip04::decrypt( $content, $privkey, $sender_pubkey );
            } catch ( \Throwable $e ) {
                error_log( '[SK Nostr Market Bridge] Decrypt failed: ' . $e->getMessage() );
                return;
            }
        }

        if ( ! preg_match( '/^[0-9a-f]{64}$/i', $sender_pubkey ) ) {
            return;
        }

        $sender_pubkey = strtolower( $sender_pubkey );

        // Skip our own messages.
        if ( $sender_pubkey === strtolower( $our_pubkey ) ) {
            return;
        }

        if ( ! is_string( $decrypted ) || '' === $decrypted ) {
            return;
        }

        // Try to parse as NIP-15 order (JSON with type field).
        $order    = json_decode( $decrypted, true );
        $is_order = is_array( $order ) && isset( $order['type'] ) && (int) $order['type'] === 0;

        if ( $is_order ) {
            self::handle_order( $order, $sender_pubkey, $event_id );
        } else {
            // Plain text message — try to route to a vendor.
            self::handle_message( $decrypted, $sender_pubkey, $event_id );
        }
    }

    /**
     * Ein Gift Wrap (NIP-59) auspacken.
     *
     * Drei Schichten: aussen das Kind 1059 mit einem Wegwerfschluessel als
     * Absender, darin versiegelt (Kind 13) der echte Absender, und darin die
     * eigentliche Nachricht (Kind 14). Beide Schichten sind mit NIP-44
     * verschluesselt, jede gegen einen anderen Gegenschluessel.
     *
     * Der Absender darf nur aus der innersten Schicht kommen: der aeussere
     * Schluessel ist Einwegware und sagt nichts darueber, wer geschrieben hat.
     *
     * Die Bibliothek kann Gift Wraps nur bauen, nicht oeffnen — deshalb hier
     * von Hand.
     *
     * @return array{pubkey: string, content: string}|null
     */
    private static function unwrap_gift_wrap( array $event, string $privkey ): ?array {
        $aeusserer = $event['pubkey'] ?? '';
        $inhalt    = $event['content'] ?? '';

        if ( '' === $inhalt || ! preg_match( '/^[0-9a-f]{64}$/i', $aeusserer ) ) {
            return null;
        }

        if ( ! class_exists( '\swentel\nostr\Encryption\Nip44' ) ) {
            return null;
        }

        try {
            // Schicht 1: gegen den Wegwerfschluessel des Umschlags.
            $schluessel = \swentel\nostr\Encryption\Nip44::getConversationKey( $privkey, strtolower( $aeusserer ) );
            $siegel     = json_decode( \swentel\nostr\Encryption\Nip44::decrypt( $inhalt, $schluessel ), true );

            if ( ! is_array( $siegel ) || 13 !== (int) ( $siegel['kind'] ?? 0 ) ) {
                return null;
            }

            $absender = $siegel['pubkey'] ?? '';

            if ( ! preg_match( '/^[0-9a-f]{64}$/i', $absender ) ) {
                return null;
            }

            // Schicht 2: gegen den echten Absender.
            $schluessel2 = \swentel\nostr\Encryption\Nip44::getConversationKey( $privkey, strtolower( $absender ) );
            $nachricht   = json_decode( \swentel\nostr\Encryption\Nip44::decrypt( (string) ( $siegel['content'] ?? '' ), $schluessel2 ), true );

            if ( ! is_array( $nachricht ) ) {
                return null;
            }

            /*
             * Das Siegel beweist den Absender, die innerste Schicht ist nicht
             * signiert. Weichen die beiden ab, hat jemand eine fremde Nachricht
             * untergeschoben.
             */
            if ( isset( $nachricht['pubkey'] ) && strtolower( (string) $nachricht['pubkey'] ) !== strtolower( $absender ) ) {
                error_log( '[SK Nostr Market Bridge] Gift Wrap: Absender im Siegel und in der Nachricht weichen ab.' );
                return null;
            }

            return [
                'pubkey'  => strtolower( $absender ),
                'content' => (string) ( $nachricht['content'] ?? '' ),
            ];
        } catch ( \Throwable $e ) {
            error_log( '[SK Nostr Market Bridge] Gift Wrap liess sich nicht oeffnen: ' . $e->getMessage() );
            return null;
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

        $post_id = self::product_ref_to_id( (string) $product_ref );

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

    /**
     * Die Produktkennung einer Bestellung in eine Inseratsnummer uebersetzen.
     *
     * Unsere Inserate tragen "sk-<ID>" in der d-Markierung. Erwartet wurde hier
     * "product-<ID>", ein Rest aus der NIP-15-Zeit — jede Bestellung auf ein
     * SK-Inserat fiel damit durch. Beide Formen werden jetzt akzeptiert, dazu
     * eine blanke Zahl.
     */
    private static function product_ref_to_id( string $ref ): int {
        $ref = trim( $ref );

        foreach ( [ 'sk-', 'product-' ] as $praefix ) {
            if ( 0 === strpos( $ref, $praefix ) ) {
                return (int) substr( $ref, strlen( $praefix ) );
            }
        }

        return ctype_digit( $ref ) ? (int) $ref : 0;
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
