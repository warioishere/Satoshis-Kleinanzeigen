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

    /** The account the marketplace itself writes from. */
    const PLATFORM_USER_ID = 1;

    /** Chat meta: which of our mailboxes the first message went to. */
    const INBOX_META = '_dvc_nostr_inbox';

    /** User meta: replies the vendor still has to seal in the browser. */
    const REPLIES_META = '_sk_nostr_pending_replies';

    /** Cap for the reply queue. */
    const REPLIES_MAX = 30;

    /**
     * Is the DM bridge switched on?
     *
     * Both switches: the module and the bridge. The classes are loaded
     * regardless (settings and chat display need them), and ChatMessages
     * and VendorChat call into this class whenever it exists. Every path
     * that sends to a relay checks here, so "off" really means nothing
     * leaves.
     */
    public static function is_enabled(): bool {
        return sk_get_option( 'sk_nostr_market_enabled', 'sk_nostr_market', 'off' ) === 'on'
            && sk_get_option( 'sk_nostr_market_bridge_enabled', 'sk_nostr_market', 'off' ) === 'on';
    }

    /**
     * Tell the network which relays we read private messages on (kind 10050).
     *
     * NIP-17 clients deliver a gift wrap to the recipient's mailbox list, not
     * to wherever they happen to be connected. Without this list Amethyst and
     * friends had nowhere to put a reply to us, and the answers ended up on
     * relays this server cannot even reach — the mirror image of the bug that
     * kept our own messages from arriving.
     *
     * The announcement names exactly the relays the poll reads, so we never
     * advertise a mailbox nobody empties.
     */
    public static function announce_dm_relays(): void {
        if ( ! self::is_enabled() ) {
            return;
        }

        $privkey = EventSender::get_privkey();
        $relays  = EventSender::get_relays();

        if ( ! $privkey || empty( $relays ) || ! class_exists( 'SK\Modules\Auth\RelayPublisher' ) ) {
            return;
        }

        $announce = function () use ( $privkey, $relays ) {
            if ( function_exists( 'fastcgi_finish_request' ) ) {
                fastcgi_finish_request();
            }

            try {
                $event = new \swentel\nostr\Event\Event();
                $event->setKind( 10050 );
                $event->setContent( '' );
                $event->setCreatedAt( time() );

                foreach ( $relays as $relay ) {
                    $event->addTag( [ 'relay', $relay ] );
                }

                ( new \swentel\nostr\Sign\Sign() )->signEvent( $event, $privkey );
                \SK\Modules\Auth\RelayPublisher::publish( $event, $relays );
            } catch ( \Throwable $e ) {
                error_log( '[SK Nostr Bridge] Announcing the DM relays failed: ' . $e->getMessage() );
            }
        };

        register_shutdown_function( $announce );
    }

    /**
     * Does this user write on behalf of the marketplace?
     *
     * The marketplace key is our identity on Nostr, so a DM from it arrives
     * as Satoshiskleinanzeigen — which is exactly who wrote it. This account
     * has no key of its own, and without this exception its messages were
     * dropped silently: the mirror only ran for senders whose key we hold.
     *
     * It stays a single named account on purpose. Letting every admin send
     * under the marketplace key would put our name on personal messages, and
     * for vendors it is plainly wrong — that mistake is why replies used to
     * come back to us instead of to them.
     */
    public static function is_platform_account( int $user_id ): bool {
        return self::PLATFORM_USER_ID === $user_id;
    }

    public static function init(): void {
        if ( ! self::is_enabled() ) {
            return;
        }

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

        // One text for chat and Nostr. Adding it to the chat mirrors it to the
        // buyer through mirror_to_nostr() with the right key; a second, direct
        // send_dm() used to deliver the invoice twice, the copy under the
        // marketplace key.
        $sats_formatted = number_format( $amount_sats, 0, ',', '.' );
        $chat_msg = "Zahlung: {$sats_formatted} Sats";
        if ( $product_title ) {
            $chat_msg .= " für {$product_title}";
        }
        if ( $bolt11 ) {
            $chat_msg .= "\n\nLightning Invoice:\n{$bolt11}";
        }
        if ( $btc_address ) {
            $btc_amount = number_format( $amount_sats / 100000000, 8, '.', '' );
            $chat_msg .= "\n\nBitcoin Adresse:\n{$btc_address}\nBetrag: {$btc_amount} BTC";
        }

        self::add_message( $chat_id, $vendor_id, $chat_msg, '' );

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
        // Bridge messages can originate from any Nostr user, so payment markers
        // are stripped here as well — a card must always come from a verified
        // payment row, never from message text.
        if ( class_exists( 'SK\Core\Dashboard\Modules\VendorChat' ) ) {
            $message = \SK\Core\Dashboard\Modules\VendorChat::sanitize_user_message( $message );
        }

        if ( $message === '' ) {
            return;
        }

        if ( $nostr_pubkey !== '' && ! preg_match( '/^[0-9a-f]{64}$/i', $nostr_pubkey ) ) {
            $nostr_pubkey = '';
        }

        \SK\Core\Dashboard\ChatMessages::append( $chat_id, $sender_id, $message, [
            'nostr_pubkey' => strtolower( $nostr_pubkey ),
        ] );
    }

    /**
     * Mirror a vendor reply in a bridge chat back to Nostr as a DM.
     *
     * Called directly by ChatMessages::append(). It used to hang off an
     * updated_post_meta hook on _dvc_messages, which stopped being a thing when
     * messages moved into their own table.
     *
     * @param int    $chat_id
     * @param int    $sender_id
     * @param string $text
     * @param string $nostr_pubkey Set when the message CAME from Nostr.
     */
    public static function mirror_to_nostr( int $chat_id, int $sender_id, string $text, string $nostr_pubkey = '' ): void {
        if ( $text === '' || ! self::is_enabled() ) {
            return;
        }

        // Came from Nostr — do not echo it back.
        if ( $nostr_pubkey !== '' ) {
            return;
        }

        if ( get_post_meta( $chat_id, '_dvc_nostr_bridge', true ) !== '1' ) {
            return;
        }

        $recipient = get_post_meta( $chat_id, '_dvc_nostr_pubkey', true );
        if ( empty( $recipient ) ) {
            return;
        }

        // Messages written as the bridge user are incoming ones.
        if ( self::is_bridge_user( $sender_id ) ) {
            return;
        }

        /*
         * Which key does the reply go out with?
         *
         * The buyer wrote to a specific mailbox and expects the reply from
         * there. If the message went to the vendor, the vendor replies: with
         * the key we hold, or by sealing the reply in the browser. Only a
         * buyer who wrote to the marketplace mailbox gets the reply from the
         * marketplace.
         *
         * Every reply without a vendor key used to fall back to the
         * marketplace key. That made the SK account speak for everyone, and
         * whoever obtained a chat with an arbitrary pubkey could message
         * that pubkey under our name.
         */
        if ( class_exists( 'SK\Modules\Auth\NostrIdentity' ) && \SK\Modules\Auth\NostrIdentity::has_identity( $sender_id ) ) {
            self::send_dm( $recipient, $text, $sender_id );
            return;
        }

        $inbox      = strtolower( (string) get_post_meta( $chat_id, self::INBOX_META, true ) );
        $markt      = strtolower( (string) EventSender::get_pubkey() );
        $vendor_pub = strtolower( (string) get_user_meta( $sender_id, 'nostr_public_key', true ) );

        if ( '' !== $vendor_pub && $inbox !== $markt ) {
            self::queue_reply( $sender_id, $chat_id, $recipient, $text );
            return;
        }

        $store_info  = function_exists( 'sk_get_store_info' ) ? sk_get_store_info( $sender_id ) : [];
        $vendor_name = $store_info['store_name'] ?? ( get_userdata( $sender_id )->display_name ?? 'Vendor' );

        self::send_dm( $recipient, "{$vendor_name}: {$text}" );
    }

    /**
     * Queue a reply only the vendor can seal.
     *
     * The key lives in the vendor's extension. On the next visit the browser
     * encrypts the reply for the recipient and signs the seal; the server
     * then adds the wrap with a throwaway key, which needs no vendor key.
     */
    private static function queue_reply( int $vendor_id, int $chat_id, string $recipient, string $text ): void {
        $offen = self::pending_replies_for( $vendor_id );

        $offen[] = [
            'id'      => bin2hex( random_bytes( 16 ) ),
            'chat_id' => $chat_id,
            'to'      => strtolower( $recipient ),
            'text'    => $text,
            'time'    => time(),
        ];

        if ( count( $offen ) > self::REPLIES_MAX ) {
            error_log( '[SK Nostr Bridge] Vendor ' . $vendor_id . ': more than ' . self::REPLIES_MAX . ' unsealed replies, oldest dropped.' );
            $offen = array_slice( $offen, -self::REPLIES_MAX );
        }

        update_user_meta( $vendor_id, self::REPLIES_META, $offen );
    }

    /**
     * The queued replies of a vendor.
     */
    public static function pending_replies_for( int $vendor_id ): array {
        $offen = get_user_meta( $vendor_id, self::REPLIES_META, true );

        return is_array( $offen ) ? array_values( $offen ) : [];
    }

    /**
     * Remove a reply from the queue.
     */
    public static function forget_reply( int $vendor_id, string $reply_id ): void {
        $rest = array_values( array_filter( self::pending_replies_for( $vendor_id ), static function ( $e ) use ( $reply_id ) {
            return ( $e['id'] ?? '' ) !== $reply_id;
        } ) );

        if ( empty( $rest ) ) {
            delete_user_meta( $vendor_id, self::REPLIES_META );
        } else {
            update_user_meta( $vendor_id, self::REPLIES_META, $rest );
        }
    }

    /**
     * Wrap a seal (kind 13) signed in the browser and send it.
     *
     * The browser encrypted the reply for the recipient and signed the seal
     * with the vendor's key. Here the seal is checked to really come from
     * the vendor, then the wrap (kind 1059) with a throwaway key goes around
     * it and out to the relays.
     *
     * The recipient comes from the queue entry, not from the browser.
     *
     * @return bool True if a relay accepted the wrap.
     */
    public static function deliver_sealed( int $vendor_id, string $reply_id, array $seal ): bool {
        $eintrag = null;

        foreach ( self::pending_replies_for( $vendor_id ) as $e ) {
            if ( ( $e['id'] ?? '' ) === $reply_id ) {
                $eintrag = $e;
                break;
            }
        }

        if ( null === $eintrag || ! preg_match( '/^[0-9a-f]{64}$/', (string) ( $eintrag['to'] ?? '' ) ) ) {
            return false;
        }

        $absender   = NostrDMListener::verified_seal_sender( $seal );
        $vendor_pub = strtolower( (string) get_user_meta( $vendor_id, 'nostr_public_key', true ) );

        if ( null === $absender || '' === $vendor_pub || $absender !== $vendor_pub ) {
            error_log( '[SK Nostr Bridge] Seal for reply ' . $reply_id . ' is not from vendor ' . $vendor_id . '.' );
            self::forget_reply( $vendor_id, $reply_id );
            return false;
        }

        if ( ! class_exists( '\swentel\nostr\Nip59\GiftWrapService' ) ) {
            return false;
        }

        try {
            $seal['kind'] = 13;
            $seal['tags'] = isset( $seal['tags'] ) && is_array( $seal['tags'] ) ? $seal['tags'] : [];

            $seal_event = ( new \swentel\nostr\Event\Event() )->populate( (object) $seal );
            $svc        = new \swentel\nostr\Nip59\GiftWrapService( new \swentel\nostr\Key\Key(), new \swentel\nostr\Sign\Sign() );
            $wrap       = $svc->createGiftWrap( $seal_event, $eintrag['to'] );
            $sent       = self::send_wrap( $wrap, (string) $eintrag['to'] );
        } catch ( \Throwable $e ) {
            error_log( '[SK Nostr Bridge] Wrapping reply ' . $reply_id . ' failed: ' . $e->getMessage() );
            return false;
        }

        if ( $sent ) {
            self::forget_reply( $vendor_id, $reply_id );
        }

        return $sent;
    }

    /**
     * Send a NIP-17 encrypted DM (Gift Wrap) to a Nostr pubkey.
     * Falls back to NIP-04 if GiftWrapService is unavailable.
     *
     * @param string $recipient_pubkey Recipient's hex pubkey.
     * @param string $text             Plaintext message.
     * @param int    $sender_user_id   SK user whose key signs the DM; 0 means
     *                                 the marketplace key. A user without a
     *                                 key held here gets false, never the
     *                                 marketplace as a silent stand-in.
     */
    public static function send_dm( string $recipient_pubkey, string $text, int $sender_user_id = 0 ): bool {
        if ( ! self::is_enabled() ) {
            return false;
        }

        if ( $sender_user_id && ! self::is_platform_account( $sender_user_id ) ) {
            if ( ! class_exists( 'SK\Modules\Auth\NostrIdentity' ) ) {
                return false;
            }

            $sender_privkey = \SK\Modules\Auth\NostrIdentity::get_private_key( $sender_user_id );
        } else {
            $sender_privkey = EventSender::get_privkey();
        }

        if ( ! $sender_privkey ) {
            return false;
        }

        // Try NIP-17 Gift Wrap (NIP-59 + NIP-44).
        if ( class_exists( '\swentel\nostr\Nip59\GiftWrapService' ) ) {
            try {
                return self::send_gift_wrap_dm( $sender_privkey, $recipient_pubkey, $text );
            } catch ( \Throwable $e ) {
                error_log( '[SK Nostr Bridge] NIP-17 DM failed, falling back to NIP-04: ' . $e->getMessage() );
            }
        }

        // Fallback: NIP-04, signed with the same key that encrypted it. It
        // used to go through EventSender::send(), which signs with the
        // marketplace key: the recipient derived the shared secret from the
        // wrong pubkey and could not read it.
        try {
            return self::send_nip04_dm( $sender_privkey, $recipient_pubkey, $text );
        } catch ( \Throwable $e ) {
            error_log( '[SK Nostr Bridge] NIP-04 DM failed: ' . $e->getMessage() );
        }

        return false;
    }

    /**
     * Send a NIP-04 DM (kind 4), encrypted and signed with the same key.
     */
    private static function send_nip04_dm( string $sender_privkey, string $recipient_pubkey, string $text ): bool {
        if ( ! class_exists( '\swentel\nostr\Encryption\Nip04' ) || ! class_exists( 'SK\Modules\Auth\RelayPublisher' ) ) {
            return false;
        }

        $event = new \swentel\nostr\Event\Event();
        $event->setKind( 4 );
        $event->setContent( \swentel\nostr\Encryption\Nip04::encrypt( $text, $sender_privkey, $recipient_pubkey ) );
        $event->addTag( [ 'p', $recipient_pubkey ] );
        $event->setCreatedAt( time() );
        ( new \swentel\nostr\Sign\Sign() )->signEvent( $event, $sender_privkey );

        $result = \SK\Modules\Auth\RelayPublisher::publish( $event, \SK\Modules\Auth\NostrIdentity::get_relays() );

        return ! empty( $result['accepted'] );
    }

    /**
     * Send a NIP-17 private DM using NIP-59 Gift Wrap.
     */
    private static function send_gift_wrap_dm( string $sender_privkey, string $recipient_pubkey, string $text ): bool {
        $keyService  = new \swentel\nostr\Key\Key();
        $signService = new \swentel\nostr\Sign\Sign();
        $giftWrapSvc = new \swentel\nostr\Nip59\GiftWrapService( $keyService, $signService );

        $sender_pubkey = $keyService->getPublicKey( $sender_privkey );
        $created_at    = time();

        /*
         * The rumor is unsigned, but it still has to carry the author's
         * pubkey and its own id. A client checks that the rumor's author
         * matches the seal's author — that check is what stops anyone from
         * sealing a message in someone else's name, so a rumor without a
         * pubkey is dropped, silently and by every client.
         *
         * It is assembled here rather than through the library's rumor,
         * whose unsigned event left pubkey and id empty and added an empty
         * sig that a rumor must not carry at all.
         */
        $rumor = [
            'id'         => '',
            'pubkey'     => $sender_pubkey,
            'created_at' => $created_at,
            'kind'       => 14,
            'tags'       => [ [ 'p', $recipient_pubkey ] ],
            'content'    => $text,
        ];

        $rumor['id'] = hash( 'sha256', (string) wp_json_encode(
            [ 0, $rumor['pubkey'], $rumor['created_at'], $rumor['kind'], $rumor['tags'], $rumor['content'] ],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) );

        // Seal (kind 13): the rumor encrypted to the recipient, signed by
        // the sender, so the recipient learns who wrote it.
        $seal = new \swentel\nostr\Event\Event();
        $seal->setKind( 13 );
        $seal->setCreatedAt( $created_at );
        $seal->setContent( \swentel\nostr\Encryption\Nip44::encrypt(
            (string) wp_json_encode( $rumor, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
            \swentel\nostr\Encryption\Nip44::getConversationKey( $sender_privkey, $recipient_pubkey )
        ) );
        $signService->signEvent( $seal, $sender_privkey );

        // Gift wrap (kind 1059) — the seal under a one-time key.
        $giftWrap = $giftWrapSvc->createGiftWrap( $seal, $recipient_pubkey );

        return self::send_wrap( $giftWrap, $recipient_pubkey );
    }

    /**
     * Send a finished wrap (kind 1059) to the relays.
     *
     * @param \swentel\nostr\EventInterface $giftWrap
     */
    private static function send_wrap( $giftWrap, string $recipient_pubkey = '' ): bool {
        if ( ! self::is_enabled() ) {
            return false;
        }

        if ( ! class_exists( 'SK\Modules\Auth\RelayPublisher' ) ) {
            return false;
        }

        /*
         * A private message has to go where the recipient reads them, and
         * that is the list they published, not ours. Sending only to our own
         * relays is why gift wraps arrived nowhere: the relays we write to
         * and the ones a client watches for DMs need not overlap at all.
         *
         * Ours stay in the list as a fallback — for recipients who published
         * no list, and for clients that simply query broadly.
         */
        $relays = array_values( array_unique( array_merge(
            $recipient_pubkey ? self::dm_relays_for( $recipient_pubkey ) : [],
            \SK\Modules\Auth\NostrIdentity::get_relays()
        ) ) );

        $result = \SK\Modules\Auth\RelayPublisher::publish( $giftWrap, $relays );

        return ! empty( $result['accepted'] );
    }

    /**
     * The relays where a recipient reads private messages (NIP-17, kind 10050).
     *
     * Looked up on our own relays, since that is where a published list is
     * most likely to be found, and remembered for a while: the list changes
     * rarely, and a round trip per message would show up as a delay.
     *
     * @return string[] Relay URLs, empty when the recipient published none.
     */
    private static function dm_relays_for( string $pubkey ): array {
        $cache_key = 'sk_nostr_dm_relays_' . substr( $pubkey, 0, 24 );
        $cached    = get_transient( $cache_key );

        if ( is_array( $cached ) ) {
            return $cached;
        }

        $relays = [];

        if ( class_exists( '\WebSocket\Client' ) ) {
            $newest = 0;

            foreach ( \SK\Modules\Auth\NostrIdentity::get_relays() as $relay_url ) {
                try {
                    $client = new \WebSocket\Client( $relay_url );
                    $client->setTimeout( 5 );

                    $sub = bin2hex( random_bytes( 8 ) );
                    $client->text( wp_json_encode( [ 'REQ', $sub, [ 'authors' => [ $pubkey ], 'kinds' => [ 10050 ], 'limit' => 1 ] ] ) );

                    $start = time();

                    while ( time() - $start < 5 ) {
                        $data = json_decode( $client->receive()->getContent(), true );

                        if ( ! is_array( $data ) ) {
                            continue;
                        }

                        if ( 'EOSE' === ( $data[0] ?? '' ) ) {
                            break;
                        }

                        if ( 'EVENT' !== ( $data[0] ?? '' ) || ! is_array( $data[2] ?? null ) ) {
                            continue;
                        }

                        $event = $data[2];

                        if ( strtolower( (string) ( $event['pubkey'] ?? '' ) ) !== strtolower( $pubkey )
                            || (int) ( $event['created_at'] ?? 0 ) <= $newest ) {
                            continue;
                        }

                        $newest = (int) $event['created_at'];
                        $relays = [];

                        foreach ( (array) ( $event['tags'] ?? [] ) as $tag ) {
                            if ( 'relay' === ( $tag[0] ?? '' ) && ! empty( $tag[1] )
                                && preg_match( '#^wss?://\S+$#i', (string) $tag[1] ) ) {
                                $relays[] = untrailingslashit( (string) $tag[1] );
                            }
                        }
                    }

                    $client->text( wp_json_encode( [ 'CLOSE', $sub ] ) );
                    $client->disconnect();
                } catch ( \Throwable $e ) {
                    // Next relay.
                }
            }
        }

        $relays = array_values( array_unique( $relays ) );

        set_transient( $cache_key, $relays, HOUR_IN_SECONDS );

        return $relays;
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

        wp_enqueue_script(
            'sk-nostr-bridge-invoice',
            plugins_url( 'assets/js/bridge-invoice.js', SK_NOSTR_MARKET_PATH . '/module.php' ),
            array( 'jquery' ),
            SK_NOSTR_MARKET_VERSION,
            true
        );
        wp_localize_script(
            'sk-nostr-bridge-invoice',
            'skNostrBridge',
            array(
                'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
                'nonce'     => wp_create_nonce( 'sk_lightning_nonce' ),
                'chatId'    => $chat_id,
                'priceSats' => $price_sats,
            )
        );
    }

    // ── The bridge user and how Nostr contacts are shown ──────────────────

    /** Login of the system user that stands in for Nostr senders. */
    const BRIDGE_LOGIN = 'sk-nostr-bridge';

    /**
     * The system user that owns the Nostr side of every bridge chat.
     *
     * Incoming messages used to be written as the admin account, so a
     * stranger's DM showed up under the marketplace's name and logo. The
     * bridge user has no role, no password anyone knows and no store; what
     * the vendor sees instead is the sender's npub (see contact_name()).
     *
     * Created on first use.
     */
    public static function bridge_user_id(): int {
        static $id = null;

        if ( null !== $id ) {
            return $id;
        }

        $user = get_user_by( 'login', self::BRIDGE_LOGIN );

        if ( $user ) {
            return $id = (int) $user->ID;
        }

        $host    = (string) wp_parse_url( home_url(), PHP_URL_HOST );
        $created = wp_insert_user( [
            'user_login'           => self::BRIDGE_LOGIN,
            'user_pass'            => wp_generate_password( 64, true, true ),
            'user_email'           => self::BRIDGE_LOGIN . '@' . ( $host ?: 'localhost' ),
            'display_name'         => 'Nostr',
            'role'                 => '',
            'show_admin_bar_front' => 'false',
        ] );

        if ( is_wp_error( $created ) ) {
            error_log( '[SK Nostr Bridge] Bridge user could not be created: ' . $created->get_error_message() );
            return $id = 0;
        }

        // The onboarding hook marks every new user; this one never logs in.
        delete_user_meta( (int) $created, 'uob_show_onboarding' );

        return $id = (int) $created;
    }

    public static function is_bridge_user( int $user_id ): bool {
        return $user_id > 0 && $user_id === self::bridge_user_id();
    }

    /**
     * Name and link of the Nostr contact behind a bridge chat, or null for
     * an ordinary chat.
     *
     * @return array{name: string, url: string}|null
     */
    public static function contact_for_chat( int $chat_id ): ?array {
        if ( get_post_meta( $chat_id, '_dvc_nostr_bridge', true ) !== '1' ) {
            return null;
        }

        $pubkey = (string) get_post_meta( $chat_id, '_dvc_nostr_pubkey', true );

        if ( '' === $pubkey ) {
            return null;
        }

        return [
            'name' => self::contact_name( $pubkey, $chat_id ),
            'url'  => self::contact_url( $pubkey ),
        ];
    }

    /** Chat meta: resolved name of the Nostr contact, and when it was looked up. */
    const NAME_META      = '_dvc_nostr_name';
    const NAME_TIME_META = '_dvc_nostr_name_time';

    /**
     * How a Nostr sender is named in the chat.
     *
     * The resolved name (store name of an SK account, otherwise the profile
     * name from the relays) followed by the shortened npub. The npub always
     * stays visible: a profile name is self-declared and proves nothing.
     *
     * @param int $chat_id Chat whose stored name is used; 0 for npub only.
     */
    public static function contact_name( string $pubkey, int $chat_id = 0 ): string {
        $npub = self::npub( $pubkey );

        if ( '' === $npub ) {
            return 'Nostr';
        }

        $short = substr( $npub, 0, 12 ) . '…' . substr( $npub, -4 );
        $name  = $chat_id ? (string) get_post_meta( $chat_id, self::NAME_META, true ) : '';

        return '' === $name ? $short : $name . ' · ' . $short;
    }

    /**
     * Look the contact's name up and store it on the chat, at most once a
     * day. Runs when a message arrives, never while rendering: the lookup
     * may go to the relays.
     */
    public static function refresh_contact_name( int $chat_id, string $pubkey ): void {
        if ( (int) get_post_meta( $chat_id, self::NAME_TIME_META, true ) > time() - DAY_IN_SECONDS ) {
            return;
        }

        update_post_meta( $chat_id, self::NAME_META, self::resolve_contact_name( $pubkey ) );
        update_post_meta( $chat_id, self::NAME_TIME_META, time() );
    }

    /**
     * Store name if the pubkey belongs to an SK account, otherwise the name
     * from the Kind 0 profile on the relays. Empty if neither is known.
     */
    private static function resolve_contact_name( string $pubkey ): string {
        $users = get_users( [
            'meta_key'    => 'nostr_public_key',
            'meta_value'  => $pubkey,
            'number'      => 1,
            'fields'      => 'ID',
            'count_total' => false,
        ] );

        if ( ! empty( $users ) ) {
            $user_id = (int) $users[0];
            $store   = function_exists( 'sk_get_store_info' ) ? sk_get_store_info( $user_id ) : [];
            $name    = is_array( $store ) ? (string) ( $store['store_name'] ?? '' ) : '';

            if ( '' === $name ) {
                $user = get_userdata( $user_id );
                $name = $user ? (string) $user->display_name : '';
            }

            return self::clean_contact_name( $name );
        }

        return self::clean_contact_name( self::fetch_profile_name( $pubkey ) );
    }

    /**
     * One line, capped, and never our own name: a stranger's profile may
     * call itself whatever it likes, and this label is what the vendor
     * reads first.
     */
    private static function clean_contact_name( string $name ): string {
        $name = trim( preg_replace( '/\s+/u', ' ', sanitize_text_field( $name ) ) );
        $name = mb_substr( $name, 0, 40 );

        if ( '' === $name ) {
            return '';
        }

        $own = array_filter( [ 'satoshiskleinanzeigen', mb_strtolower( (string) get_bloginfo( 'name' ) ) ] );

        foreach ( $own as $verboten ) {
            if ( false !== mb_stripos( $name, $verboten ) ) {
                return '';
            }
        }

        return $name;
    }

    /**
     * Newest Kind 0 profile of a pubkey from the configured relays.
     *
     * Short timeout: this runs while a message is being delivered, and a
     * relay that stalls must not hold that up.
     */
    private static function fetch_profile_name( string $pubkey ): string {
        if ( ! class_exists( '\WebSocket\Client' ) ) {
            return '';
        }

        $best    = null;
        $best_at = 0;

        foreach ( EventSender::get_relays() as $relay_url ) {
            try {
                $client = new \WebSocket\Client( $relay_url );
                $client->setTimeout( 5 );

                $sub = bin2hex( random_bytes( 8 ) );
                $client->text( wp_json_encode( [ 'REQ', $sub, [ 'authors' => [ $pubkey ], 'kinds' => [ 0 ], 'limit' => 1 ] ] ) );

                $start = time();

                while ( time() - $start < 5 ) {
                    $data = json_decode( $client->receive()->getContent(), true );

                    if ( ! is_array( $data ) ) {
                        continue;
                    }

                    if ( 'EOSE' === ( $data[0] ?? '' ) ) {
                        break;
                    }

                    if ( 'EVENT' === ( $data[0] ?? '' ) && is_array( $data[2] ?? null ) && 0 === (int) ( $data[2]['kind'] ?? -1 ) ) {
                        $at = (int) ( $data[2]['created_at'] ?? 0 );

                        if ( $at > $best_at && strtolower( (string) ( $data[2]['pubkey'] ?? '' ) ) === $pubkey ) {
                            $best_at = $at;
                            $best    = $data[2];
                        }
                    }
                }

                $client->text( wp_json_encode( [ 'CLOSE', $sub ] ) );
                $client->disconnect();
            } catch ( \Throwable $e ) {
                // Next relay.
            }

            if ( null !== $best ) {
                break;
            }
        }

        if ( null === $best ) {
            return '';
        }

        $profile = json_decode( (string) ( $best['content'] ?? '' ), true );

        if ( ! is_array( $profile ) ) {
            return '';
        }

        foreach ( [ 'display_name', 'name' ] as $key ) {
            if ( ! empty( $profile[ $key ] ) && is_string( $profile[ $key ] ) ) {
                return $profile[ $key ];
            }
        }

        return '';
    }

    /**
     * Where the vendor can look the sender up.
     */
    public static function contact_url( string $pubkey ): string {
        $npub = self::npub( $pubkey );

        return '' === $npub ? '' : 'https://njump.me/' . $npub;
    }

    private static function npub( string $pubkey ): string {
        $pubkey = strtolower( trim( $pubkey ) );

        if ( ! preg_match( '/^[0-9a-f]{64}$/', $pubkey ) || ! class_exists( '\swentel\nostr\Key\Key' ) ) {
            return '';
        }

        try {
            return (string) ( new \swentel\nostr\Key\Key() )->convertPublicKeyToBech32( $pubkey );
        } catch ( \Throwable $e ) {
            return '';
        }
    }

    /**
     * Avatar of the bridge user: a plain Nostr mark instead of the Gravatar
     * default. Hooked on pre_get_avatar_data, so get_avatar() and
     * get_avatar_url() both pick it up.
     *
     * @param array $args
     * @param mixed $id_or_email
     * @return array
     */
    public static function avatar_data( array $args, $id_or_email ): array {
        $user_id = 0;

        if ( is_numeric( $id_or_email ) ) {
            $user_id = (int) $id_or_email;
        } elseif ( $id_or_email instanceof \WP_User ) {
            $user_id = (int) $id_or_email->ID;
        } elseif ( $id_or_email instanceof \WP_Comment ) {
            $user_id = (int) $id_or_email->user_id;
        }

        if ( ! $user_id || ! self::is_bridge_user( $user_id ) ) {
            return $args;
        }

        // A file, not a data URI: get_avatar() runs the URL through esc_url(),
        // which drops the data: scheme and leaves an empty src.
        $args['url']          = plugins_url( 'assets/img/nostr-avatar.svg', SK_NOSTR_MARKET_PATH . '/module.php' );
        $args['found_avatar'] = true;

        return $args;
    }
}
