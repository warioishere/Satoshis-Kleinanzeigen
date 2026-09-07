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
     * Alle Postfaecher, die wir abfragen — Marktplatz und Anbieter.
     *
     * Inserate von Anbietern mit eigenem Schluessel erscheinen unter deren
     * Namen. Wer darauf antwortet, schreibt folglich an deren Postfach, nicht
     * an unseres. Bisher wurde nur das Marktplatz-Postfach abgefragt: die
     * Antwort kam bei einem Schluessel an, den niemand las, und war weg.
     *
     * Der Empfaenger ist zugleich die Zuordnung. Wer auf das Inserat eines
     * Anbieters antwortet, landet bei genau diesem Anbieter, ohne dass die
     * Nachricht sagen muss, um welches Inserat es geht.
     *
     * Ein Eintrag ohne privkey heisst: wir kennen das Postfach, koennen es
     * aber nicht oeffnen — das muss der Anbieter selbst tun.
     *
     * @return array<string, array{privkey: ?string, vendor_id: int}>
     */
    private static function key_ring(): array {
        static $ring = null;

        if ( null !== $ring ) {
            return $ring;
        }

        $ring = [];

        $markt_priv = EventSender::get_privkey();
        $markt_pub  = EventSender::get_pubkey();

        if ( $markt_priv && $markt_pub ) {
            $ring[ strtolower( $markt_pub ) ] = [
                'privkey'   => $markt_priv,
                'vendor_id' => 0,
            ];
        }

        if ( ! class_exists( 'SK\Modules\Auth\NostrIdentity' ) ) {
            return $ring;
        }

        global $wpdb;

        $vendor_ids = $wpdb->get_col(
            "SELECT DISTINCT user_id FROM {$wpdb->usermeta}
             WHERE meta_key = 'sk_nostr_private_key' AND meta_value <> ''"
        );

        foreach ( (array) $vendor_ids as $vendor_id ) {
            $vendor_id = (int) $vendor_id;
            $pub       = strtolower( (string) get_user_meta( $vendor_id, 'nostr_public_key', true ) );

            if ( ! preg_match( '/^[0-9a-f]{64}$/', $pub ) || isset( $ring[ $pub ] ) ) {
                continue;
            }

            $priv = \SK\Modules\Auth\NostrIdentity::get_private_key( $vendor_id );

            if ( $priv ) {
                $ring[ $pub ] = [
                    'privkey'   => $priv,
                    'vendor_id' => $vendor_id,
                ];
            }
        }

        /*
         * Anbieter, die sich ueber eine Erweiterung anmelden: wir haben nur
         * ihren oeffentlichen Schluessel. Ihr Postfach koennen wir abfragen —
         * die Nachrichten liegen offen auf den Relays —, aber nicht oeffnen.
         * Sie werden vorgemerkt und spaeter im Browser des Anbieters
         * entschluesselt.
         */
        $nur_pubkey = $wpdb->get_col(
            "SELECT DISTINCT user_id FROM {$wpdb->usermeta}
             WHERE meta_key = 'nostr_public_key' AND meta_value <> ''
               AND user_id NOT IN (
                   SELECT user_id FROM {$wpdb->usermeta}
                   WHERE meta_key = 'sk_nostr_private_key' AND meta_value <> ''
               )"
        );

        foreach ( (array) $nur_pubkey as $vendor_id ) {
            $vendor_id = (int) $vendor_id;
            $pub       = strtolower( (string) get_user_meta( $vendor_id, 'nostr_public_key', true ) );

            if ( ! preg_match( '/^[0-9a-f]{64}$/', $pub ) || isset( $ring[ $pub ] ) ) {
                continue;
            }

            $ring[ $pub ] = [
                'privkey'   => null,
                'vendor_id' => $vendor_id,
            ];
        }

        return $ring;
    }

    /**
     * Poll relays for new DMs to any of our pubkeys.
     */
    public static function poll(): void {
        $ring = self::key_ring();

        if ( empty( $ring ) ) {
            return;
        }

        $relays = EventSender::get_relays();
        if ( empty( $relays ) ) {
            return;
        }

        $pubkeys = array_keys( $ring );

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
            $events = self::fetch_dms( $relay_url, $pubkeys, $since );

            foreach ( $events as $event ) {
                self::process_dm( $event, $ring );
            }
        }

        // Der Fortschritt haengt an der Uhr, nicht an den Zeitstempeln der
        // Ereignisse — die eines Gift Wraps sind erfunden.
        update_option( self::LAST_SEEN_KEY, time() );
    }

    /**
     * Fetch Kind 4 (NIP-04) DMs addressed to our pubkey since $since.
     */
    /**
     * @param string[] $pubkeys Alle Postfaecher, die uns gehoeren.
     */
    private static function fetch_dms( string $relay_url, array $pubkeys, int $since ): array {
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
                '#p'    => array_values( $pubkeys ),
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
    /**
     * An welches unserer Postfaecher ging das Ereignis?
     *
     * @param array<string, array{privkey: string, vendor_id: int}> $ring
     * @return array{pubkey: string, privkey: string, vendor_id: int}|null
     */
    private static function recipient_from_tags( array $event, array $ring ): ?array {
        foreach ( (array) ( $event['tags'] ?? [] ) as $tag ) {
            if ( ! is_array( $tag ) || ( $tag[0] ?? '' ) !== 'p' ) {
                continue;
            }

            $pub = strtolower( (string) ( $tag[1] ?? '' ) );

            if ( isset( $ring[ $pub ] ) ) {
                return [
                    'pubkey'    => $pub,
                    'privkey'   => $ring[ $pub ]['privkey'],
                    'vendor_id' => $ring[ $pub ]['vendor_id'],
                ];
            }
        }

        return null;
    }

    /**
     * @param array<string, array{privkey: string, vendor_id: int}> $ring
     */
    private static function process_dm( array $event, array $ring ): void {
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

        // Das Relay liefert nach unserem Filter nur Eigenes, aber verlassen
        // wir uns nicht darauf.
        $empfaenger = self::recipient_from_tags( $event, $ring );

        if ( null === $empfaenger ) {
            return;
        }

        /*
         * Kein Schluessel bei uns: nur der Anbieter selbst kann diese
         * Nachricht oeffnen. Roh vormerken, den Rest erledigt sein Browser.
         */
        if ( empty( $empfaenger['privkey'] ) ) {
            self::queue_for_browser( $empfaenger['vendor_id'], $event );
            return;
        }

        $kind = (int) ( $event['kind'] ?? 0 );

        if ( 1059 === $kind ) {
            $inner = self::unwrap_gift_wrap( $event, $empfaenger['privkey'] );

            if ( null === $inner ) {
                return;
            }

            $sender_pubkey = $inner['pubkey'];
            $decrypted     = $inner['content'];
            $inner_tags    = $inner['tags'];
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
            $inner_tags    = is_array( $event['tags'] ?? null ) ? $event['tags'] : [];

            try {
                $decrypted = \swentel\nostr\Encryption\Nip04::decrypt( $content, $empfaenger['privkey'], $sender_pubkey );
            } catch ( \Throwable $e ) {
                error_log( '[SK Nostr Market Bridge] Decrypt failed: ' . $e->getMessage() );
                return;
            }
        }

        if ( ! preg_match( '/^[0-9a-f]{64}$/i', $sender_pubkey ) ) {
            return;
        }

        $sender_pubkey = strtolower( $sender_pubkey );

        // Eigene Nachrichten ueberspringen — auch die eines Anbieters an sich selbst.
        if ( isset( $ring[ $sender_pubkey ] ) ) {
            return;
        }

        if ( ! is_string( $decrypted ) || '' === $decrypted ) {
            return;
        }

        /*
         * Ging die Nachricht an das Postfach eines Anbieters, ist damit klar,
         * wer gemeint ist — auch bei der allerersten Nachricht und ohne dass
         * ein Inserat genannt wird. Genau das ist der Weg, den ein Kaeufer in
         * seinem Client nimmt: Inserat sehen, auf den Absender antworten.
         */
        if ( $empfaenger['vendor_id'] > 0 ) {
            self::route_to_vendor( $empfaenger['vendor_id'], $sender_pubkey, $decrypted, $empfaenger['pubkey'] );
            return;
        }

        /*
         * Ans Marktplatz-Postfach geschrieben. Dort steht kein Anbieter im
         * Empfaenger, also muss die Nachricht selbst sagen, worum es geht:
         * ueber eine Verweis-Markierung auf das Inserat, sonst ueber dessen
         * Adresse oder Kennung im Text.
         *
         * Das betrifft die grosse Mehrheit — nur wer einen eigenen Schluessel
         * hat, bekommt ein eigenes Postfach.
         */
        $post_id = self::listing_from_message( $inner_tags, $decrypted );

        if ( $post_id ) {
            $autor = (int) get_post_field( 'post_author', $post_id );

            if ( $autor ) {
                self::create_bridge_chat( $autor, $sender_pubkey, $post_id, get_the_title( $post_id ), self::clean_field( $decrypted, 4000 ), $empfaenger['pubkey'] );
                return;
            }
        }

        // Plain text message — try to route to a vendor.
        self::handle_message( $decrypted, $sender_pubkey, $event_id );
    }

    /**
     * Eine Nachricht dem Anbieter zustellen, an dessen Postfach sie ging.
     */
    private static function route_to_vendor( int $vendor_id, string $sender_pubkey, string $text, string $inbox ): void {
        $text = self::clean_field( $text, 4000 );

        if ( '' === $text ) {
            return;
        }

        self::create_bridge_chat( $vendor_id, $sender_pubkey, 0, '', $text, $inbox );
    }

    /** Nutzermeta mit den vorgemerkten, noch verschluesselten Nachrichten. */
    const PENDING_META = '_sk_nostr_pending_wraps';

    /** Mehr als das hebt niemand auf. */
    const PENDING_MAX = 30;

    /**
     * Eine Nachricht vormerken, die nur ihr Empfaenger oeffnen kann.
     *
     * Gespeichert wird das rohe Ereignis, so wie es vom Relay kam. Es ist
     * ohnehin oeffentlich; entschluesseln kann es nur, wer den privaten
     * Schluessel hat, und der liegt im Browser des Anbieters.
     */
    private static function queue_for_browser( int $vendor_id, array $event ): void {
        if ( $vendor_id <= 0 ) {
            return;
        }

        $offen = get_user_meta( $vendor_id, self::PENDING_META, true );
        $offen = is_array( $offen ) ? $offen : [];

        $id = (string) ( $event['id'] ?? '' );

        foreach ( $offen as $vorhanden ) {
            if ( ( $vorhanden['id'] ?? '' ) === $id ) {
                return;
            }
        }

        $offen[] = [
            'id'      => $id,
            'kind'    => (int) ( $event['kind'] ?? 0 ),
            'pubkey'  => strtolower( (string) ( $event['pubkey'] ?? '' ) ),
            'content' => (string) ( $event['content'] ?? '' ),
        ];

        // Aeltestes zuerst weg, sonst waechst das Meta unbegrenzt, wenn der
        // Anbieter nie vorbeischaut.
        if ( count( $offen ) > self::PENDING_MAX ) {
            $offen = array_slice( $offen, -self::PENDING_MAX );
        }

        update_user_meta( $vendor_id, self::PENDING_META, $offen );
    }

    /**
     * Die vorgemerkten Nachrichten eines Anbieters.
     */
    public static function pending_for( int $vendor_id ): array {
        $offen = get_user_meta( $vendor_id, self::PENDING_META, true );

        return is_array( $offen ) ? $offen : [];
    }

    /**
     * Eine vorgemerkte Nachricht abhaken.
     */
    public static function forget_pending( int $vendor_id, string $event_id ): void {
        $offen = self::pending_for( $vendor_id );

        $rest = array_values( array_filter( $offen, static function ( $e ) use ( $event_id ) {
            return ( $e['id'] ?? '' ) !== $event_id;
        } ) );

        if ( empty( $rest ) ) {
            delete_user_meta( $vendor_id, self::PENDING_META );
        } else {
            update_user_meta( $vendor_id, self::PENDING_META, $rest );
        }
    }

    /**
     * Deliver a message that was decrypted in the browser.
     *
     * The plaintext comes from the vendor and cannot be checked here; that
     * is the price of not holding the key.
     *
     * The sender can be checked, though: the browser sends the seal (kind
     * 13) along, exactly as it sat inside the wrap. The seal is signed by
     * the real sender and the server verifies that signature. A claimed
     * pubkey alone was not enough: it allowed creating a conversation with
     * any Nostr user, and every reply in it went out as a DM to that user.
     *
     * The event id also has to be in the vendor's queue.
     *
     * @param array $seal The seal as the browser took it out of the wrap:
     *                    id, pubkey, sig, kind, created_at, tags, content
     *                    (still encrypted).
     */
    public static function deliver_decrypted( int $vendor_id, string $event_id, array $seal, string $text ): bool {
        $bekannt = false;

        foreach ( self::pending_for( $vendor_id ) as $e ) {
            if ( ( $e['id'] ?? '' ) === $event_id ) {
                $bekannt = true;
                break;
            }
        }

        if ( ! $bekannt ) {
            return false;
        }

        self::forget_pending( $vendor_id, $event_id );

        $sender_pubkey = self::verified_seal_sender( $seal );

        if ( null === $sender_pubkey ) {
            error_log( '[SK Nostr Market Bridge] Seal for ' . substr( $event_id, 0, 12 ) . ' failed signature verification.' );
            return false;
        }

        $text = self::clean_field( $text, 4000 );

        if ( '' === $text ) {
            return false;
        }

        $inbox = strtolower( (string) get_user_meta( $vendor_id, 'nostr_public_key', true ) );

        self::create_bridge_chat( $vendor_id, $sender_pubkey, 0, '', $text, $inbox );

        return true;
    }

    /**
     * The sender of a seal (kind 13), accepted only with a valid signature.
     *
     * @return string|null Lowercase hex pubkey, or null.
     */
    public static function verified_seal_sender( array $seal ): ?string {
        if ( 13 !== (int) ( $seal['kind'] ?? 0 ) ) {
            return null;
        }

        foreach ( [ 'id', 'pubkey', 'sig', 'content' ] as $feld ) {
            if ( ! isset( $seal[ $feld ] ) || ! is_string( $seal[ $feld ] ) ) {
                return null;
            }
        }

        if ( ! isset( $seal['created_at'] ) || ! is_int( $seal['created_at'] ) ) {
            return null;
        }

        $seal['kind'] = 13;
        $seal['tags'] = isset( $seal['tags'] ) && is_array( $seal['tags'] ) ? $seal['tags'] : [];

        if ( ! class_exists( '\swentel\nostr\Event\Event' ) ) {
            return null;
        }

        try {
            if ( ! ( new \swentel\nostr\Event\Event() )->verify( (object) $seal ) ) {
                return null;
            }
        } catch ( \Throwable $e ) {
            return null;
        }

        return strtolower( $seal['pubkey'] );
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
     * @return array{pubkey: string, content: string, tags: array}|null
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
                'tags'    => is_array( $nachricht['tags'] ?? null ) ? $nachricht['tags'] : [],
            ];
        } catch ( \Throwable $e ) {
            error_log( '[SK Nostr Market Bridge] Gift Wrap liess sich nicht oeffnen: ' . $e->getMessage() );
            return null;
        }
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
            ChatBridge::add_message( $existing_chat, ChatBridge::bridge_user_id(), $text, $sender_pubkey );
            return;
        }

        // No existing chat — can't route without product context. Ignore.
        error_log( '[SK Nostr Market Bridge] Unroutable DM from ' . substr( $sender_pubkey, 0, 16 ) . '...' );
    }

    /**
     * Create a VendorChat bridged to a Nostr user.
     *
     * @param string $inbox Which of our mailboxes the message went to. Decides
     *                      later which key the reply goes out with:
     *                      marketplace or vendor.
     */
    private static function create_bridge_chat( int $vendor_id, string $nostr_pubkey, int $product_id, string $product_title, string $message, string $inbox = '' ): int {
        // The Nostr side of the chat belongs to the bridge user, never to a
        // real account: whoever is participant 1 is shown as the sender.
        $admin_id = ChatBridge::bridge_user_id();

        if ( ! $admin_id ) {
            return 0;
        }

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
        $npub  = self::pubkey_to_npub( $nostr_pubkey );
        $titel = 'Nostr: ' . substr( $npub, 0, 16 ) . '...';

        // Eine Anfrage ohne Inseratsbezug ist der Normalfall, wenn jemand im
        // Client einfach auf den Absender antwortet.
        if ( '' !== $product_title ) {
            $titel .= ' → ' . $product_title;
        }

        $chat_id = wp_insert_post( [
            'post_type'   => 'vendor_chat',
            'post_status' => 'publish',
            'post_title'  => $titel,
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
        update_post_meta( $chat_id, ChatBridge::INBOX_META, strtolower( $inbox ) );

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
     * Aus einer Nachricht ans Marktplatz-Postfach das gemeinte Inserat lesen.
     *
     * Drei Wege, in dieser Reihenfolge:
     *
     * 1. Eine "a"-Markierung, wie sie ein Client setzt, der sich auf ein
     *    Inserat bezieht: "30402:<pubkey>:<kennung>".
     * 2. Die Adresse des Inserats im Text — sie steht in jedem unserer
     *    Inserate unter "Inserat:", wird also oft mitzitiert.
     * 3. Die blosse Kennung "sk-<nummer>" irgendwo im Text.
     *
     * Ohne Treffer bleibt die Nachricht unzustellbar; wir raten nicht.
     *
     * @param array  $tags Markierungen der Nachricht.
     * @param string $text Klartext der Nachricht.
     */
    private static function listing_from_message( array $tags, string $text ): int {
        foreach ( $tags as $tag ) {
            if ( ! is_array( $tag ) || ( $tag[0] ?? '' ) !== 'a' ) {
                continue;
            }

            $teile = explode( ':', (string) ( $tag[1] ?? '' ) );

            if ( count( $teile ) >= 3 && '30402' === $teile[0] ) {
                $id = self::product_ref_to_id( $teile[2] );

                if ( $id && 'product' === get_post_type( $id ) ) {
                    return $id;
                }
            }
        }

        // Adresse des Inserats, wie sie in unserem Inseratstext steht.
        if ( preg_match_all( '#https?://[^\s<>"\']+#i', $text, $treffer ) ) {
            foreach ( $treffer[0] as $url ) {
                $id = url_to_postid( $url );

                if ( $id && 'product' === get_post_type( $id ) ) {
                    return (int) $id;
                }
            }
        }

        if ( preg_match( '/\bsk-(\d+)\b/i', $text, $m ) ) {
            $id = (int) $m[1];

            if ( $id && 'product' === get_post_type( $id ) ) {
                return $id;
            }
        }

        return 0;
    }

    /**
     * Eine Inseratskennung in eine Nummer uebersetzen.
     *
     * Unsere Inserate tragen "sk-<ID>" in der d-Markierung. "product-<ID>"
     * stammt aus der NIP-15-Zeit und wird der Vollstaendigkeit halber noch
     * akzeptiert.
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
}
