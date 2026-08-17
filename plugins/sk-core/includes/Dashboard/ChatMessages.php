<?php

namespace SK\Core\Dashboard;

/**
 * Storage for vendor chat messages.
 *
 * Messages used to live as one serialized array in the `_dvc_messages` post
 * meta, appended by four separate read-modify-write code paths with no locking.
 * Two concurrent sends therefore lost one message — and since payment cards
 * travel through the same channel, the lost one could be an invoice or a
 * payment confirmation.
 *
 * One row per message makes appending a single atomic INSERT, so there is
 * nothing left to race. Existing chats are migrated on first read; the old meta
 * is kept under `_dvc_messages_legacy` as a backup rather than deleted.
 */
class ChatMessages {

	/** Bump to let maybe_install() apply schema changes. */
	const DB_VERSION = '1';

	const VERSION_OPTION = 'sk_chat_messages_db_version';

	/** Meta key the messages used to live in. */
	const LEGACY_META = '_dvc_messages';

	/** Where the pre-migration array is parked. */
	const LEGACY_BACKUP_META = '_dvc_messages_legacy';

	public static function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'sk_chat_messages';
	}

	/**
	 * Create or update the table. Cheap no-op once the version matches.
	 */
	public static function maybe_install(): void {
		if ( get_option( self::VERSION_OPTION ) === self::DB_VERSION ) {
			return;
		}

		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta(
			'CREATE TABLE ' . self::table() . " (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				chat_id BIGINT UNSIGNED NOT NULL,
				user_id BIGINT UNSIGNED NOT NULL,
				message LONGTEXT NOT NULL,
				card_type VARCHAR(32) NULL,
				payment_hash VARCHAR(64) NULL,
				nostr_pubkey VARCHAR(64) NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY (id),
				KEY chat_message (chat_id, id),
				KEY payment_hash (payment_hash)
			) {$wpdb->get_charset_collate()};"
		);

		update_option( self::VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Append a message to a chat.
	 *
	 * @param int    $chat_id
	 * @param int    $user_id Sender.
	 * @param string $message
	 * @param array  $extra   Optional 'card_type', 'payment_hash', 'nostr_pubkey'.
	 * @return int Row id, or 0 on failure.
	 */
	public static function append( int $chat_id, int $user_id, string $message, array $extra = [] ): int {
		if ( ! $chat_id || $message === '' ) {
			return 0;
		}

		self::maybe_install();

		global $wpdb;

		$now = current_time( 'mysql' );

		$inserted = $wpdb->insert(
			self::table(),
			[
				'chat_id'      => $chat_id,
				'user_id'      => $user_id,
				'message'      => $message,
				'card_type'    => self::clean_short( $extra['card_type'] ?? '' ) ?: null,
				'payment_hash' => self::clean_hash( $extra['payment_hash'] ?? '' ) ?: null,
				'nostr_pubkey' => self::clean_hash( $extra['nostr_pubkey'] ?? '' ) ?: null,
				'created_at'   => $now,
			],
			[ '%d', '%d', '%s', '%s', '%s', '%s', '%s' ]
		);

		if ( ! $inserted ) {
			return 0;
		}

		update_post_meta( $chat_id, '_dvc_last_message_time', current_time( 'timestamp' ) );

		// A new message revives the thread for anyone who had deleted it, so
		// nobody loses an incoming reply by having cleaned up earlier.
		delete_post_meta( $chat_id, '_dvc_deleted_by' );

		// Bridge chats mirror vendor replies back to Nostr. This used to hang off
		// an updated_post_meta hook, which no longer fires now that messages are
		// not post meta any more.
		if ( class_exists( '\SK\Modules\NostrMarket\Bridge\ChatBridge' ) ) {
			\SK\Modules\NostrMarket\Bridge\ChatBridge::mirror_to_nostr(
				$chat_id,
				$user_id,
				$message,
				self::clean_hash( $extra['nostr_pubkey'] ?? '' )
			);
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * All messages of a chat, oldest first.
	 *
	 * Keys match the old meta format (user_id, message, timestamp, …) so readers
	 * did not have to change.
	 *
	 * @param int $chat_id
	 * @return array
	 */
	public static function all( int $chat_id ): array {
		if ( ! $chat_id ) {
			return [];
		}

		self::maybe_install();
		self::migrate_legacy( $chat_id );

		global $wpdb;

		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT user_id, message, card_type, payment_hash, nostr_pubkey, created_at
			 FROM ' . self::table() . ' WHERE chat_id = %d ORDER BY id ASC',
			$chat_id
		) );

		$messages = [];

		foreach ( (array) $rows as $row ) {
			$messages[] = [
				'user_id'      => (int) $row->user_id,
				'message'      => (string) $row->message,
				// created_at holds site-local wall clock, same as
				// current_time( 'mysql' ). Reading it back as UTC reproduces
				// current_time( 'timestamp' ) exactly — and the explicit ' UTC'
				// keeps that true whatever PHP's ambient timezone is.
				'timestamp'    => (int) strtotime( $row->created_at . ' UTC' ),
				'card_type'    => $row->card_type,
				'payment_hash' => $row->payment_hash,
				'nostr_pubkey' => $row->nostr_pubkey,
			];
		}

		return $messages;
	}

	/**
	 * Is there already a card of this type for this payment in the chat?
	 */
	public static function has_payment_message( int $chat_id, string $payment_hash, string $card_type ): bool {
		$hash = self::clean_hash( $payment_hash );

		if ( ! $chat_id || $hash === '' ) {
			return false;
		}

		self::maybe_install();
		self::migrate_legacy( $chat_id );

		global $wpdb;

		return (bool) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . self::table() . '
			 WHERE chat_id = %d AND payment_hash = %s AND card_type = %s',
			$chat_id,
			$hash,
			$card_type
		) );
	}

	/**
	 * User meta holding [ chat_id => last read message id ].
	 *
	 * Unread state used to be one entry per received message in
	 * `_dvc_notifications`, appended forever and never pruned, unserialized on
	 * every page load for the nav badge. One marker per chat is bounded by the
	 * number of conversations instead of the number of messages.
	 */
	const READ_META = '_dvc_read_up_to';

	/**
	 * Read markers of a user: [ chat_id => message_id ].
	 */
	public static function read_markers( int $user_id ): array {
		$markers = get_user_meta( $user_id, self::READ_META, true );

		if ( ! is_array( $markers ) ) {
			return [];
		}

		$clean = [];
		foreach ( $markers as $chat_id => $message_id ) {
			$clean[ (int) $chat_id ] = (int) $message_id;
		}

		return $clean;
	}

	/**
	 * Mark everything currently in the chat as read for this user.
	 */
	public static function mark_read( int $chat_id, int $user_id ): void {
		if ( ! $chat_id || ! $user_id ) {
			return;
		}

		self::maybe_install();

		global $wpdb;

		$latest = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT MAX(id) FROM ' . self::table() . ' WHERE chat_id = %d',
			$chat_id
		) );

		$markers = self::read_markers( $user_id );

		if ( ( $markers[ $chat_id ] ?? 0 ) === $latest ) {
			return;
		}

		$markers[ $chat_id ] = $latest;

		update_user_meta( $user_id, self::READ_META, $markers );
	}

	/**
	 * Forget the read marker of a chat, for every user that has one.
	 */
	public static function forget_read_marker( int $chat_id, array $user_ids ): void {
		foreach ( $user_ids as $user_id ) {
			$user_id = (int) $user_id;
			$markers = self::read_markers( $user_id );

			if ( ! isset( $markers[ $chat_id ] ) ) {
				continue;
			}

			unset( $markers[ $chat_id ] );
			update_user_meta( $user_id, self::READ_META, $markers );
		}
	}

	/**
	 * IDs of the chats a user takes part in.
	 *
	 * Lean postmeta query — the nav badge needs the IDs on every page load and
	 * has no use for the post objects.
	 *
	 * @param int $user_id
	 * @return int[]
	 */
	public static function chat_ids_for_participant( int $user_id ): array {
		if ( ! $user_id ) {
			return [];
		}

		global $wpdb;

		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT pm.post_id
			 FROM {$wpdb->postmeta} pm
			 JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE p.post_type = 'vendor_chat'
			   AND p.post_status = 'publish'
			   AND pm.meta_key IN ( '_dvc_participant_1', '_dvc_participant_2' )
			   AND pm.meta_value = %s",
			(string) $user_id
		) );

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Unread message counts per chat, in one query.
	 *
	 * Counts messages from somebody else that are newer than the user's read
	 * marker for that chat.
	 *
	 * @param int[] $chat_ids
	 * @param int   $user_id
	 * @return array [ chat_id => count ], only chats that have unread messages.
	 */
	public static function unread_counts( array $chat_ids, int $user_id ): array {
		$chat_ids = array_values( array_unique( array_map( 'intval', $chat_ids ) ) );
		$chat_ids = array_filter( $chat_ids );

		if ( empty( $chat_ids ) || ! $user_id ) {
			return [];
		}

		self::maybe_install();

		$markers = self::read_markers( $user_id );

		// One OR-clause per chat, so a single query covers all of them.
		$clauses = [];
		foreach ( $chat_ids as $chat_id ) {
			$clauses[] = sprintf( '(chat_id = %d AND id > %d)', $chat_id, (int) ( $markers[ $chat_id ] ?? 0 ) );
		}

		global $wpdb;

		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT chat_id, COUNT(*) AS unread FROM ' . self::table() . '
			 WHERE user_id <> %d AND ( ' . implode( ' OR ', $clauses ) . ' )
			 GROUP BY chat_id',
			$user_id
		) );

		$counts = [];
		foreach ( (array) $rows as $row ) {
			$counts[ (int) $row->chat_id ] = (int) $row->unread;
		}

		return $counts;
	}

	/**
	 * Newest message of each chat, in one query.
	 *
	 * For the conversation list, which used to load every message of every chat
	 * just to show the last line.
	 *
	 * @param int[] $chat_ids
	 * @return array [ chat_id => message array ]
	 */
	public static function last_messages( array $chat_ids ): array {
		$chat_ids = array_values( array_unique( array_map( 'intval', $chat_ids ) ) );
		$chat_ids = array_filter( $chat_ids );

		if ( empty( $chat_ids ) ) {
			return [];
		}

		self::maybe_install();

		foreach ( $chat_ids as $chat_id ) {
			self::migrate_legacy( $chat_id );
		}

		global $wpdb;

		$ids   = implode( ',', $chat_ids );
		$table = self::table();

		$rows = $wpdb->get_results(
			"SELECT m.chat_id, m.user_id, m.message, m.card_type, m.payment_hash, m.nostr_pubkey, m.created_at
			 FROM {$table} m
			 JOIN ( SELECT chat_id, MAX(id) AS max_id FROM {$table}
			        WHERE chat_id IN ({$ids}) GROUP BY chat_id ) newest
			   ON newest.chat_id = m.chat_id AND newest.max_id = m.id"
		);

		$last = [];
		foreach ( (array) $rows as $row ) {
			$last[ (int) $row->chat_id ] = [
				'user_id'      => (int) $row->user_id,
				'message'      => (string) $row->message,
				'timestamp'    => (int) strtotime( $row->created_at . ' UTC' ),
				'card_type'    => $row->card_type,
				'payment_hash' => $row->payment_hash,
				'nostr_pubkey' => $row->nostr_pubkey,
			];
		}

		return $last;
	}

	/**
	 * Remove all messages of a chat. Called when the chat post is deleted.
	 */
	public static function delete_for_chat( int $chat_id ): void {
		if ( ! $chat_id ) {
			return;
		}

		global $wpdb;

		$wpdb->delete( self::table(), [ 'chat_id' => $chat_id ], [ '%d' ] );
	}

	/**
	 * Import a chat's legacy meta array into the table, once.
	 */
	private static function migrate_legacy( int $chat_id ): void {
		if ( ! metadata_exists( 'post', $chat_id, self::LEGACY_META ) ) {
			return;
		}

		$legacy = get_post_meta( $chat_id, self::LEGACY_META, true );

		if ( ! is_array( $legacy ) || empty( $legacy ) ) {
			// Nothing to carry over — stop looking on every read.
			delete_post_meta( $chat_id, self::LEGACY_META );
			return;
		}

		$lock = 'sk_chatmig_' . $chat_id;

		if ( get_transient( $lock ) ) {
			return;
		}

		set_transient( $lock, 1, 60 );

		global $wpdb;

		$existing = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . self::table() . ' WHERE chat_id = %d',
			$chat_id
		) );

		if ( 0 === $existing ) {
			foreach ( $legacy as $entry ) {
				if ( ! is_array( $entry ) || ! isset( $entry['message'] ) ) {
					continue;
				}

				$timestamp = isset( $entry['timestamp'] ) ? (int) $entry['timestamp'] : 0;

				$wpdb->insert(
					self::table(),
					[
						'chat_id'      => $chat_id,
						'user_id'      => (int) ( $entry['user_id'] ?? 0 ),
						'message'      => (string) $entry['message'],
						'card_type'    => null,
						'payment_hash' => null,
						'nostr_pubkey' => self::clean_hash( $entry['nostr_pubkey'] ?? '' ) ?: null,
						'created_at'   => $timestamp
							? gmdate( 'Y-m-d H:i:s', $timestamp )
							: current_time( 'mysql' ),
					],
					[ '%d', '%d', '%s', '%s', '%s', '%s', '%s' ]
				);
			}
		}

		// Keep the original array as a backup instead of dropping it.
		update_post_meta( $chat_id, self::LEGACY_BACKUP_META, $legacy );
		delete_post_meta( $chat_id, self::LEGACY_META );

		delete_transient( $lock );
	}

	private static function clean_hash( $value ): string {
		$value = is_scalar( $value ) ? strtolower( trim( (string) $value ) ) : '';

		return preg_match( '/^[0-9a-f]{64}$/', $value ) ? $value : '';
	}

	private static function clean_short( $value ): string {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';

		return preg_match( '/^[a-z_]{1,32}$/', $value ) ? $value : '';
	}
}
