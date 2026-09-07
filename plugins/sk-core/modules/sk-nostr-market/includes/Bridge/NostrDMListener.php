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
         * The filter MUST be registered before scheduling. Otherwise WordPress
         * does not yet know the interval at that moment, wp_schedule_event()
         * returns false, and since init() runs the same order on every call,
         * the poll was never scheduled — the bridge never ran.
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
     * All mailboxes we poll — marketplace and vendors.
     *
     * Listings from vendors with their own key appear under their name.
     * Whoever replies therefore writes to their mailbox, not ours. Until now
     * only the marketplace mailbox was polled: the reply arrived at a key
     * nobody read, and was lost.
     *
     * The recipient doubles as the routing. Whoever replies to a vendor's
     * listing ends up with that exact vendor, without the message having to
     * say which listing it's about.
     *
     * An entry without a privkey means: we know the mailbox but cannot open
     * it — that's for the vendor to do themselves.
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
         * Vendors who log in via a browser extension: we only have their
         * public key. We can poll their mailbox — the messages sit openly on
         * the relays — but cannot open it. They're queued and decrypted later
         * in the vendor's browser.
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
         * Look back two days instead of starting from the last checkpoint.
         *
         * Per NIP-59, a gift wrap deliberately carries a randomized timestamp,
         * up to two days in the past. A window starting at the last checkpoint
         * would permanently miss such messages. The duplicate check catches
         * anything already-known that comes around again.
         */
        $since = max( 0, $last_seen - 2 * DAY_IN_SECONDS );

        foreach ( $relays as $relay_url ) {
            $events = self::fetch_dms( $relay_url, $pubkeys, $since );

            foreach ( $events as $event ) {
                // One bad event must not end the run for every relay after it.
                try {
                    self::process_dm( $event, $ring );
                } catch ( \Throwable $e ) {
                    error_log( '[SK Nostr Market Bridge] Event ' . substr( (string) ( $event['id'] ?? '' ), 0, 12 ) . ' from ' . $relay_url . ' failed: ' . $e->getMessage() );
                }
            }
        }

        // Progress is tied to the clock, not to event timestamps — a gift
        // wrap's timestamp is made up.
        update_option( self::LAST_SEEN_KEY, time() );
    }

    /** Seconds per relay: connect, subscribe and read until EOSE. */
    const RELAY_TIMEOUT = 10;

    /** Events accepted per relay and poll; the relay is asked for the same limit. */
    const MAX_EVENTS = 200;

    /**
     * Largest event content taken from a relay. A NIP-44 payload is at most
     * 65535 bytes of plaintext, about 88 KB as base64; anything bigger is
     * not a message anyone could decrypt.
     */
    const MAX_CONTENT = 100000;

    /**
     * Fetch kind 4 (NIP-04) and kind 1059 (NIP-17 gift wrap) events addressed
     * to our pubkeys since $since.
     *
     * We send as gift wraps ourselves; replies come back the same way, and a
     * filter on kind 4 alone never saw them.
     *
     * Whatever was collected is returned, also when the relay stalls or
     * fails halfway: a relay that never sends EOSE used to cost the client's
     * default 60 s and then lose every event it had already delivered. The
     * timeout is set on the client itself; the options array the constructor
     * used to get is ignored by this websocket library. TLS verification is
     * PHP's default and stays on.
     *
     * @param string[] $pubkeys All mailboxes that belong to us.
     */
    private static function fetch_dms( string $relay_url, array $pubkeys, int $since ): array {
        if ( ! class_exists( '\WebSocket\Client' ) ) {
            return [];
        }

        $events = [];
        $client = null;
        $sub_id = bin2hex( random_bytes( 8 ) );

        try {
            $client = new \WebSocket\Client( $relay_url );
            $client->setTimeout( self::RELAY_TIMEOUT );

            $client->text( wp_json_encode( [ 'REQ', $sub_id, [
                'kinds' => [ 4, 1059 ],
                '#p'    => array_values( $pubkeys ),
                'since' => $since,
                'limit' => self::MAX_EVENTS,
            ] ] ) );

            $deadline = microtime( true ) + self::RELAY_TIMEOUT;

            while ( microtime( true ) < $deadline ) {
                $data = json_decode( $client->receive()->getContent(), true );

                if ( ! is_array( $data ) || ! isset( $data[0] ) ) {
                    continue;
                }

                if ( 'EOSE' === $data[0] || 'CLOSED' === $data[0] ) {
                    break;
                }

                if ( 'EVENT' !== $data[0] ) {
                    continue;
                }

                $event = self::valid_event( $data[2] ?? null );

                if ( null === $event ) {
                    continue;
                }

                $events[] = $event;

                // The relay was asked for this limit; not every relay honours it.
                if ( count( $events ) >= self::MAX_EVENTS ) {
                    break;
                }
            }

            $client->text( wp_json_encode( [ 'CLOSE', $sub_id ] ) );
            $client->disconnect();
        } catch ( \Throwable $e ) {
            error_log( '[SK Nostr Market Bridge] Relay ' . $relay_url . ': ' . $e->getMessage() . ' (' . count( $events ) . ' events kept)' );

            if ( $client ) {
                try {
                    $client->disconnect();
                } catch ( \Throwable $ignored ) {
                    // Already gone.
                }
            }
        }

        return $events;
    }

    /**
     * Only the fields we use, each in the shape we expect, or null.
     *
     * A relay is not trusted to send well-formed events: an EVENT whose
     * payload was a string used to reach process_dm() and end the whole
     * poll with a TypeError.
     *
     * @param mixed $raw
     */
    private static function valid_event( $raw ): ?array {
        if ( ! is_array( $raw ) ) {
            return null;
        }

        $hex64 = '/^[0-9a-f]{64}$/i';

        if ( ! isset( $raw['id'], $raw['pubkey'], $raw['kind'] )
            || ! is_string( $raw['id'] ) || ! preg_match( $hex64, $raw['id'] )
            || ! is_string( $raw['pubkey'] ) || ! preg_match( $hex64, $raw['pubkey'] )
            || ! is_int( $raw['kind'] ) || ! in_array( $raw['kind'], [ 4, 1059 ], true ) ) {
            return null;
        }

        $content = $raw['content'] ?? '';

        if ( ! is_string( $content ) || strlen( $content ) > self::MAX_CONTENT ) {
            return null;
        }

        $tags = [];

        foreach ( (array) ( $raw['tags'] ?? [] ) as $tag ) {
            if ( is_array( $tag ) ) {
                $tags[] = array_values( array_filter( $tag, 'is_string' ) );
            }
        }

        return [
            'id'         => strtolower( $raw['id'] ),
            'pubkey'     => strtolower( $raw['pubkey'] ),
            'kind'       => $raw['kind'],
            'created_at' => (int) ( $raw['created_at'] ?? 0 ),
            'content'    => $content,
            'tags'       => $tags,
        ];
    }

    /**
     * Process a single incoming DM.
     */
    /**
     * Which of our mailboxes did the event go to?
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
         * Duplicate check first, and on the outer id. The window reaches two
         * days back, so anything already-known comes by again on every run;
         * the marker therefore has to outlast the window.
         *
         * Held briefly at this point and only made to last once the event has
         * actually been dealt with. Marking it here for the full three days
         * turned every failure into a permanent one: an event that tripped
         * over a bug on its first pass was never looked at again, so fixing
         * the bug did not bring the message back.
         */
        $processed_key = 'sk_dm_' . substr( $event_id, 0, 32 );

        if ( get_transient( $processed_key ) ) {
            return;
        }

        set_transient( $processed_key, 1, 5 * MINUTE_IN_SECONDS );

        // The relay should only return our own messages per our filter, but
        // we don't rely on that.
        $empfaenger = self::recipient_from_tags( $event, $ring );

        if ( null === $empfaenger ) {
            self::settle( $event_id );

            return;
        }

        /*
         * Our own message, on its way back. It is addressed to one of our
         * mailboxes, so the poll picks it up like any other, and for a member
         * whose key we do not hold there is nothing inside we could read to
         * tell that we wrote it. The id we noted when sending is the only
         * way to know — and it has to be checked before the message is handed
         * to the member's browser, or it returns as a stranger's chat.
         */
        if ( ChatBridge::was_sent_by_us( $event_id ) ) {
            self::settle( $event_id );

            return;
        }

        /*
         * No key held here: only the vendor themselves can open this
         * message. Queue it raw; the rest happens in their browser.
         */
        if ( empty( $empfaenger['privkey'] ) ) {
            self::queue_for_browser( $empfaenger['vendor_id'], $event );
            self::settle( $event_id );

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

        /*
         * Skip what we sent ourselves. Our own gift wraps are addressed to
         * one of our mailboxes' correspondents and come back on the next
         * poll, so they have to be recognised — but only by the key that
         * could have signed them, which means a key we hold.
         *
         * Membership in the ring alone was too much: a vendor who signs in
         * their own browser is in it too, with a pubkey and no key of ours.
         * When such a vendor wrote to the marketplace, their message was
         * dropped as if it were our own echo, and nothing ever arrived.
         */
        if ( ! empty( $ring[ $sender_pubkey ]['privkey'] ) ) {
            self::settle( $event_id );

            return;
        }

        // A mailbox writing to itself is an echo either way.
        if ( $sender_pubkey === strtolower( (string) $empfaenger['pubkey'] ) ) {
            self::settle( $event_id );

            return;
        }

        if ( ! is_string( $decrypted ) || '' === $decrypted ) {
            return;
        }

        /*
         * An answer to something we mirrored out of a conversation that
         * already exists here. Both ends are known at this point — the sender
         * by their key, the mailbox by whose it is — so the message belongs
         * in the chat those two are already having.
         *
         * Without this a reply had nowhere to go: the chat it answers is an
         * ordinary one between two members, not a bridge chat, and the search
         * for a bridge chat came up empty. The message was logged as
         * unroutable and dropped.
         */
        $absender_user = self::user_for_pubkey( $sender_pubkey );

        if ( $absender_user ) {
            $postfach_owner = $empfaenger['vendor_id'] > 0
                ? $empfaenger['vendor_id']
                : ChatBridge::PLATFORM_USER_ID;

            $chat_id = self::chat_between( $absender_user, $postfach_owner );

            if ( $chat_id ) {
                // The pubkey marks it as arriving from Nostr, which keeps the
                // outgoing mirror from sending it straight back.
                ChatBridge::add_message( $chat_id, $absender_user, self::clean_field( $decrypted, 4000 ), $sender_pubkey );
                self::settle( $event_id );

                return;
            }
        }

        /*
         * If the message went to a vendor's mailbox, that already settles who
         * is meant — even for the very first message and without a listing
         * being named. That's exactly the path a buyer takes in their
         * client: see the listing, reply to the sender.
         */
        if ( $empfaenger['vendor_id'] > 0 ) {
            self::route_to_vendor( $empfaenger['vendor_id'], $sender_pubkey, $decrypted, $empfaenger['pubkey'] );
            self::settle( $event_id );

            return;
        }

        /*
         * Written to the marketplace mailbox. There's no vendor in the
         * recipient here, so the message itself has to say what it's about:
         * via a reference tag to the listing, otherwise via its URL or id in
         * the text.
         *
         * This covers the large majority — only someone with their own key
         * gets their own mailbox.
         */
        $post_id = self::listing_from_message( $inner_tags, $decrypted );

        if ( $post_id ) {
            $autor = (int) get_post_field( 'post_author', $post_id );

            if ( $autor ) {
                self::create_bridge_chat( $autor, $sender_pubkey, $post_id, get_the_title( $post_id ), self::clean_field( $decrypted, 4000 ), $empfaenger['pubkey'] );
                self::settle( $event_id );

                return;
            }
        }

        // Plain text message — try to route to a vendor.
        self::handle_message( $decrypted, $sender_pubkey, $event_id );
        self::settle( $event_id );
    }

    /**
     * The member behind a Nostr key, if it belongs to one of ours.
     *
     * @return int User id, or 0 for someone we don't know.
     */
    private static function user_for_pubkey( string $pubkey ): int {
        if ( ! preg_match( '/^[0-9a-f]{64}$/i', $pubkey ) ) {
            return 0;
        }

        global $wpdb;

        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta}
             WHERE meta_key = 'nostr_public_key' AND LOWER(meta_value) = %s
             LIMIT 1",
            strtolower( $pubkey )
        ) );
    }

    /**
     * The chat two members already share, newest first.
     *
     * @return int Chat id, or 0 when they have none.
     */
    private static function chat_between( int $one, int $other ): int {
        if ( ! $one || ! $other || $one === $other ) {
            return 0;
        }

        global $wpdb;

        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} a ON a.post_id = p.ID AND a.meta_key = '_dvc_participant_1'
             INNER JOIN {$wpdb->postmeta} b ON b.post_id = p.ID AND b.meta_key = '_dvc_participant_2'
             WHERE p.post_type = 'vendor_chat' AND p.post_status = 'publish'
               AND ( ( a.meta_value = %d AND b.meta_value = %d )
                  OR ( a.meta_value = %d AND b.meta_value = %d ) )
             ORDER BY p.ID DESC
             LIMIT 1",
            $one,
            $other,
            $other,
            $one
        ) );
    }

    /**
     * Remember an event as dealt with, for longer than the fetch window.
     *
     * Only called where the event reached its destination or was rightly
     * ignored — never after a failure, so a failed event comes round again.
     */
    private static function settle( string $event_id ): void {
        set_transient( 'sk_dm_' . substr( $event_id, 0, 32 ), 1, 3 * DAY_IN_SECONDS );
    }

    /**
     * Deliver a message to the vendor whose mailbox it went to.
     */
    private static function route_to_vendor( int $vendor_id, string $sender_pubkey, string $text, string $inbox ): void {
        $text = self::clean_field( $text, 4000 );

        if ( '' === $text ) {
            return;
        }

        self::create_bridge_chat( $vendor_id, $sender_pubkey, 0, '', $text, $inbox );
    }

    /** User meta holding queued, still-encrypted messages. */
    const PENDING_META = '_sk_nostr_pending_wraps';

    /** Nobody keeps more than this. */
    const PENDING_MAX = 30;

    /**
     * Queue a message that only its recipient can open.
     *
     * The raw event is stored exactly as it came from the relay. It's public
     * anyway; only whoever holds the private key can decrypt it, and that
     * key sits in the vendor's browser.
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

        // Oldest goes first, otherwise the meta grows unbounded when the
        // vendor never checks in.
        if ( count( $offen ) > self::PENDING_MAX ) {
            $offen = array_slice( $offen, -self::PENDING_MAX );
        }

        update_user_meta( $vendor_id, self::PENDING_META, $offen );
    }

    /**
     * The queued messages of a vendor.
     */
    public static function pending_for( int $vendor_id ): array {
        $offen = get_user_meta( $vendor_id, self::PENDING_META, true );

        return is_array( $offen ) ? $offen : [];
    }

    /**
     * Remove a message from the queue.
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

        /*
         * A message the browser opened for us that turns out to be ours.
         * Written from a mailbox we sign for, it belongs in the chat it was
         * mirrored from, and putting it in a bridge chat would show the
         * marketplace to a member as an unknown npub writing to them.
         */
        $ring = self::key_ring();

        if ( ! empty( $ring[ $sender_pubkey ]['privkey'] ) ) {
            return true;
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
     * Unwrap a Gift Wrap (NIP-59).
     *
     * Three layers: outermost is kind 1059 with a throwaway key as sender,
     * inside that the seal (kind 13) with the real sender, and inside that
     * the actual message (kind 14). Both layers are encrypted with NIP-44,
     * each against a different counterpart key.
     *
     * The sender may only be taken from the innermost layer: the outer key
     * is single-use and says nothing about who actually wrote it.
     *
     * The library can only build gift wraps, not open them — hence this is
     * done by hand here.
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
            // Layer 1: against the wrap's throwaway key.
            $schluessel = \swentel\nostr\Encryption\Nip44::getConversationKey( $privkey, strtolower( $aeusserer ) );
            $siegel     = json_decode( \swentel\nostr\Encryption\Nip44::decrypt( $inhalt, $schluessel ), true );

            if ( ! is_array( $siegel ) || 13 !== (int) ( $siegel['kind'] ?? 0 ) ) {
                return null;
            }

            $absender = $siegel['pubkey'] ?? '';

            if ( ! preg_match( '/^[0-9a-f]{64}$/i', $absender ) ) {
                return null;
            }

            // Layer 2: against the real sender.
            $schluessel2 = \swentel\nostr\Encryption\Nip44::getConversationKey( $privkey, strtolower( $absender ) );
            $nachricht   = json_decode( \swentel\nostr\Encryption\Nip44::decrypt( (string) ( $siegel['content'] ?? '' ), $schluessel2 ), true );

            if ( ! is_array( $nachricht ) ) {
                return null;
            }

            // Only NIP-17 text messages become chat text.
            if ( 14 !== (int) ( $nachricht['kind'] ?? 0 ) ) {
                return null;
            }

            /*
             * The seal proves the sender; the innermost layer isn't signed.
             * If the two diverge, someone has slipped in a foreign message.
             */
            if ( isset( $nachricht['pubkey'] ) && strtolower( (string) $nachricht['pubkey'] ) !== strtolower( $absender ) ) {
                error_log( '[SK Nostr Market Bridge] Gift Wrap: sender in seal and in message diverge.' );
                return null;
            }

            return [
                'pubkey'  => strtolower( $absender ),
                'content' => (string) ( $nachricht['content'] ?? '' ),
                'tags'    => is_array( $nachricht['tags'] ?? null ) ? $nachricht['tags'] : [],
            ];
        } catch ( \Throwable $e ) {
            error_log( '[SK Nostr Market Bridge] Gift Wrap could not be opened: ' . $e->getMessage() );
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
            ChatBridge::refresh_contact_name( $chat_id, $nostr_pubkey );
            ChatBridge::add_message( $chat_id, $admin_id, $message, $nostr_pubkey );
            return $chat_id;
        }

        // Create new bridge chat.
        $npub  = self::pubkey_to_npub( $nostr_pubkey );
        $titel = 'Nostr: ' . substr( $npub, 0, 16 ) . '...';

        // An inquiry without a listing reference is the normal case when
        // someone in their client simply replies to the sender.
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

        ChatBridge::refresh_contact_name( $chat_id, $nostr_pubkey );
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

        // Not sanitize_textarea_field(): it strips every %xx sequence, so a
        // URL with percent-encoding in a DM lost characters. Markup and
        // control characters go, line breaks and invalid UTF-8 are handled;
        // the chat escapes on output.
        $text = wp_check_invalid_utf8( (string) $value );
        $text = wp_strip_all_tags( $text, false );
        $text = (string) preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text );

        if ( class_exists( 'SK\Core\Dashboard\Modules\VendorChat' ) ) {
            $text = \SK\Core\Dashboard\Modules\VendorChat::sanitize_user_message( $text );
        }

        if ( mb_strlen( $text ) > $max_length ) {
            $text = mb_substr( $text, 0, $max_length ) . '…';
        }

        return trim( $text );
    }

    /**
     * Read the intended listing from a message sent to the marketplace
     * mailbox.
     *
     * Three ways, in this order:
     *
     * 1. An "a" tag, as set by a client referring to a listing:
     *    "30402:<pubkey>:<id>".
     * 2. The listing's URL in the text — it appears in every one of our
     *    listings under "Inserat:", so it's often quoted back.
     * 3. The bare id "sk-<number>" anywhere in the text.
     *
     * Without a match, the message stays undeliverable; we don't guess.
     *
     * @param array  $tags Tags of the message.
     * @param string $text Plaintext of the message.
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

        // The listing's URL, as it appears in our listing text.
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
     * Translate a listing reference into a numeric id.
     *
     * Our listings carry "sk-<ID>" in the d tag. "product-<ID>" is a
     * holdover from the NIP-15 era and is still accepted for completeness.
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
