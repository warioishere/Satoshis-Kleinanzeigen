<?php

namespace SK\Modules\NostrMarket\Bridge;

defined( 'ABSPATH' ) || exit;

/**
 * Durable record of the Nostr events this site has already dealt with.
 *
 * The poll asks the relays for everything of the last two days on every
 * run, because a gift wrap carries a made-up timestamp. What keeps a
 * message from being delivered on every one of those runs is the record
 * that it was handled — and that record used to be a transient in the
 * object cache. A flushed or restarted Redis dropped every marker at once,
 * and the next poll delivered two days of messages a second time; our own
 * outgoing wraps, noted the same way, came back as a stranger's chat.
 *
 * One row per event, in the database, claimed with a single INSERT so two
 * polls running at the same moment cannot both take the same event.
 */
class SeenEvents {

    const DB_VERSION     = '2';
    const VERSION_OPTION = 'sk_nostr_seen_events_db_version';

    /** Taken by a poll; not yet known to have reached its destination. */
    const STATE_CLAIMED = 0;

    /** Delivered, or rightly ignored. Never looked at again. */
    const STATE_SETTLED = 1;

    /** A wrap this site sent out; it is our own when it comes back. */
    const STATE_SENT = 2;

    /**
     * The message inside a wrap we sent, with the chat it was mirrored
     * from. A reply that names it (NIP-17 "e" tag) belongs in that chat.
     */
    const STATE_RUMOR = 3;

    /** An unsettled claim older than this may be taken over — the earlier attempt failed. */
    const RETRY_AFTER = 5 * MINUTE_IN_SECONDS;

    /** Rows are dropped once both the event and its last handling are older than this; outlasts the two-day fetch window. */
    const RETENTION = 3 * DAY_IN_SECONDS;

    public static function table(): string {
        global $wpdb;

        return $wpdb->prefix . 'sk_nostr_seen_events';
    }

    public static function maybe_install(): void {
        if ( get_option( self::VERSION_OPTION ) === self::DB_VERSION ) {
            return;
        }

        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta(
            'CREATE TABLE ' . self::table() . " (
                event_id CHAR(64) NOT NULL,
                state TINYINT UNSIGNED NOT NULL DEFAULT 0,
                created_at BIGINT NOT NULL DEFAULT 0,
                seen_at BIGINT NOT NULL,
                chat_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY  (event_id),
                KEY seen_at (seen_at)
            ) {$wpdb->get_charset_collate()};"
        );

        update_option( self::VERSION_OPTION, self::DB_VERSION );
    }

    /**
     * Take an event for processing.
     *
     * True exactly once per event, for whichever caller gets there first —
     * unless the earlier claim is stale and was never settled, in which case
     * the event may be tried again. False for a settled event, for one we
     * sent ourselves, and for one another poll is handling right now.
     *
     * @param string $event_id   Lowercase hex id.
     * @param int    $created_at The event's own timestamp; kept for pruning.
     */
    public static function claim( string $event_id, int $created_at ): bool {
        self::maybe_install();

        global $wpdb;

        $now   = time();
        $table = self::table();

        $inserted = $wpdb->query( $wpdb->prepare(
            "INSERT IGNORE INTO {$table} (event_id, state, created_at, seen_at) VALUES (%s, %d, %d, %d)",
            $event_id,
            self::STATE_CLAIMED,
            $created_at,
            $now
        ) );

        if ( false === $inserted ) {
            error_log( '[SK Nostr Bridge] Could not record event ' . substr( $event_id, 0, 12 ) . ': ' . $wpdb->last_error );

            return false;
        }

        if ( $inserted > 0 ) {
            return true;
        }

        // Already there: only a stale, unsettled claim may be taken over.
        $taken = $wpdb->query( $wpdb->prepare(
            "UPDATE {$table} SET seen_at = %d WHERE event_id = %s AND state = %d AND seen_at < %d",
            $now,
            $event_id,
            self::STATE_CLAIMED,
            $now - self::RETRY_AFTER
        ) );

        return (int) $taken > 0;
    }

    /**
     * Mark an event as dealt with, for good.
     */
    public static function settle( string $event_id ): void {
        self::maybe_install();

        global $wpdb;

        $wpdb->query( $wpdb->prepare(
            'UPDATE ' . self::table() . ' SET state = %d, seen_at = %d WHERE event_id = %s',
            self::STATE_SETTLED,
            time(),
            $event_id
        ) );
    }

    /**
     * Note a wrap as one of ours before it goes out.
     */
    public static function remember_sent( string $event_id ): void {
        self::maybe_install();

        global $wpdb;

        $now = time();

        $wpdb->query( $wpdb->prepare(
            'INSERT INTO ' . self::table() . ' (event_id, state, created_at, seen_at) VALUES (%s, %d, %d, %d)
             ON DUPLICATE KEY UPDATE state = VALUES(state), seen_at = VALUES(seen_at)',
            $event_id,
            self::STATE_SENT,
            $now,
            $now
        ) );
    }

    /**
     * Note which chat a mirrored message came from, under the id of the
     * message itself (the rumor, kind 14), not of the wrap around it: the
     * reply names the message.
     */
    public static function remember_rumor( string $rumor_id, int $chat_id ): void {
        if ( $chat_id <= 0 ) {
            return;
        }

        self::maybe_install();

        global $wpdb;

        $now = time();

        $wpdb->query( $wpdb->prepare(
            'INSERT INTO ' . self::table() . ' (event_id, state, created_at, seen_at, chat_id) VALUES (%s, %d, %d, %d, %d)
             ON DUPLICATE KEY UPDATE state = VALUES(state), seen_at = VALUES(seen_at), chat_id = VALUES(chat_id)',
            $rumor_id,
            self::STATE_RUMOR,
            $now,
            $now,
            $chat_id
        ) );
    }

    /**
     * The chat a message of ours was mirrored from, or 0.
     */
    public static function chat_for_rumor( string $rumor_id ): int {
        self::maybe_install();

        global $wpdb;

        return (int) $wpdb->get_var( $wpdb->prepare(
            'SELECT chat_id FROM ' . self::table() . ' WHERE event_id = %s AND state = %d',
            $rumor_id,
            self::STATE_RUMOR
        ) );
    }

    /**
     * Did this event leave here?
     */
    public static function was_sent_by_us( string $event_id ): bool {
        self::maybe_install();

        global $wpdb;

        return self::STATE_SENT === (int) $wpdb->get_var( $wpdb->prepare(
            'SELECT state FROM ' . self::table() . ' WHERE event_id = %s',
            $event_id
        ) );
    }

    /**
     * Drop rows that can no longer come round again.
     *
     * A row goes once both its event timestamp and its last handling are
     * outside the retention; an event dated in the future stays until it
     * has aged out on both counts.
     */
    public static function prune(): void {
        self::maybe_install();

        global $wpdb;

        $cutoff = time() - self::RETENTION;

        $wpdb->query( $wpdb->prepare(
            'DELETE FROM ' . self::table() . ' WHERE seen_at < %d AND created_at < %d',
            $cutoff,
            $cutoff
        ) );
    }
}
