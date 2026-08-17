<?php
/**
 * ChatMessages — the storage that replaced the serialized _dvc_messages array.
 *
 * The point of the change was to remove a lost-update race: appending used to be
 * read-modify-write on one post meta, so two concurrent sends dropped one
 * message. These checks pin down that appending is a single INSERT and performs
 * no read of existing messages at all.
 */

require __DIR__ . '/bootstrap.php';

// --- Recording $wpdb stub ---------------------------------------------------
class Fake_WPDB {
	public $prefix = 'wp_';
	public $insert_id = 0;
	public $ops = [];          // every operation, in order
	public $rows = [];         // chat_id => list of row objects
	private $next_id = 1;

	public function get_charset_collate() { return 'DEFAULT CHARSET=utf8mb4'; }

	public function prepare( $query, ...$args ) {
		foreach ( $args as $a ) {
			$query = preg_replace( '/%[dsf]/', is_int( $a ) ? (string) $a : "'" . $a . "'", $query, 1 );
		}
		return $query;
	}

	public function insert( $table, $data, $format = null ) {
		$this->ops[] = 'insert';
		$row = (object) $data;
		$row->id = $this->next_id++;
		$this->rows[ (int) $data['chat_id'] ][] = $row;
		$this->insert_id = $row->id;
		return 1;
	}

	/** All rows across all chats. */
	private function all_rows() {
		$out = [];
		foreach ( $this->rows as $rows ) { foreach ( $rows as $r ) { $out[] = $r; } }
		return $out;
	}

	public function get_results( $query ) {
		$this->ops[] = 'select';

		// unread_counts(): "... WHERE user_id <> N AND ( (chat_id = A AND id > B) OR ... ) GROUP BY chat_id"
		if ( stripos( $query, 'COUNT(*) AS unread' ) !== false ) {
			preg_match( '/user_id <> (\d+)/', $query, $u );
			$viewer = (int) ( $u[1] ?? 0 );
			preg_match_all( '/\(chat_id = (\d+) AND id > (\d+)\)/', $query, $cl, PREG_SET_ORDER );
			$out = [];
			foreach ( $cl as $c ) {
				$chat = (int) $c[1]; $after = (int) $c[2]; $n = 0;
				foreach ( $this->rows[ $chat ] ?? [] as $r ) {
					if ( (int) $r->user_id !== $viewer && (int) $r->id > $after ) { $n++; }
				}
				if ( $n ) { $out[] = (object) [ 'chat_id' => $chat, 'unread' => $n ]; }
			}
			return $out;
		}

		// last_messages(): newest row per chat.
		if ( stripos( $query, 'MAX(id) AS max_id' ) !== false ) {
			preg_match( '/chat_id IN \(([0-9,]*)\)/', $query, $m );
			$ids = array_filter( array_map( 'intval', explode( ',', $m[1] ?? '' ) ) );
			$out = [];
			foreach ( $ids as $chat ) {
				$rows = $this->rows[ $chat ] ?? [];
				if ( ! $rows ) { continue; }
				$newest = $rows[0];
				foreach ( $rows as $r ) { if ( (int) $r->id > (int) $newest->id ) { $newest = $r; } }
				$copy = clone $newest; $copy->chat_id = $chat;
				$out[] = $copy;
			}
			return $out;
		}

		if ( ! preg_match( '/chat_id = (\d+)/', $query, $m ) ) { return []; }
		return $this->rows[ (int) $m[1] ] ?? [];
	}

	public function get_var( $query ) {
		$this->ops[] = 'select_var';
		if ( ! preg_match( '/chat_id = (\d+)/', $query, $m ) ) { return 0; }
		$rows = $this->rows[ (int) $m[1] ] ?? [];
		if ( stripos( $query, 'MAX(id)' ) !== false ) {
			$max = 0;
			foreach ( $rows as $r ) { $max = max( $max, (int) $r->id ); }
			return $max;
		}
		if ( stripos( $query, 'payment_hash =' ) !== false ) {
			preg_match( "/payment_hash = '([^']*)'/", $query, $h );
			preg_match( "/card_type = '([^']*)'/", $query, $c );
			$n = 0;
			foreach ( $rows as $r ) {
				if ( ( $r->payment_hash ?? null ) === ( $h[1] ?? '' ) && ( $r->card_type ?? null ) === ( $c[1] ?? '' ) ) { $n++; }
			}
			return $n;
		}
		return count( $rows );
	}

	public function delete( $table, $where, $format = null ) {
		$this->ops[] = 'delete';
		unset( $this->rows[ (int) $where['chat_id'] ] );
		return 1;
	}
}

$wpdb = new Fake_WPDB();

// --- WordPress stubs --------------------------------------------------------
$GLOBALS['options'] = [ 'sk_chat_messages_db_version' => '1' ];   // table already installed
$GLOBALS['postmeta'] = [];
$GLOBALS['transients'] = [];
$GLOBALS['usermeta'] = [];

function get_user_meta( $id, $key, $single = false ) { return $GLOBALS['usermeta'][ $id ][ $key ] ?? ''; }
function update_user_meta( $id, $key, $v ) { $GLOBALS['usermeta'][ $id ][ $key ] = $v; return true; }

function get_option( $k, $d = false ) { return $GLOBALS['options'][ $k ] ?? $d; }
function update_option( $k, $v ) { $GLOBALS['options'][ $k ] = $v; return true; }
function current_time( $type = 'mysql' ) {
	// Site runs at UTC+2 in this fixture, mirroring WordPress' behaviour:
	// 'timestamp' is a local-shifted unix value, 'mysql' the same wall clock.
	$shifted = time() + 7200;
	return $type === 'timestamp' ? $shifted : gmdate( 'Y-m-d H:i:s', $shifted );
}
function metadata_exists( $type, $id, $key ) { return isset( $GLOBALS['postmeta'][ $id ][ $key ] ); }
function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['postmeta'][ $id ][ $key ] ?? ''; }
function update_post_meta( $id, $key, $v ) { $GLOBALS['postmeta'][ $id ][ $key ] = $v; return true; }
function delete_post_meta( $id, $key ) { unset( $GLOBALS['postmeta'][ $id ][ $key ] ); return true; }
function get_transient( $k ) { return $GLOBALS['transients'][ $k ] ?? false; }
function set_transient( $k, $v, $t = 0 ) { $GLOBALS['transients'][ $k ] = $v; return true; }
function delete_transient( $k ) { unset( $GLOBALS['transients'][ $k ] ); return true; }

require SK_TEST_PLUGIN . '/includes/Dashboard/ChatMessages.php';

use SK\Core\Dashboard\ChatMessages;

$fails = 0;
function check( $label, $actual, $expected ) {
	global $fails;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fails++; }
	printf( "%-6s %-56s got=%-26s expected=%s\n", $ok ? 'PASS' : 'FAIL', $label,
		var_export( $actual, true ), var_export( $expected, true ) );
}

// --- Appending must not read first (that was the race) ----------------------
$wpdb->ops = [];
ChatMessages::append( 10, 3, 'Hallo, ist das noch zu haben?' );
check( 'append does exactly one INSERT', $wpdb->ops, [ 'insert' ] );
check( 'append performs no read of existing messages',
	in_array( 'select', $wpdb->ops, true ) || in_array( 'select_var', $wpdb->ops, true ), false );

$wpdb->ops = [];
ChatMessages::append( 10, 7, 'Ja, ist noch da.' );
ChatMessages::append( 10, 3, 'Super, ich nehme es.' );
check( 'three messages, three inserts', count( $wpdb->rows[10] ), 3 );
check( 'still no reads while appending', $wpdb->ops, [ 'insert', 'insert' ] );

// --- Reading keeps the shape the old meta array had -------------------------
$all = ChatMessages::all( 10 );
check( 'all() returns every message', count( $all ), 3 );
check( 'order is oldest first', $all[0]['message'], 'Hallo, ist das noch zu haben?' );
check( 'legacy keys present',
	array_keys( $all[0] ) === [ 'user_id', 'message', 'timestamp', 'card_type', 'payment_hash', 'nostr_pubkey' ], true );
check( 'user_id is an int', is_int( $all[0]['user_id'] ), true );
check( 'timestamp is an int', is_int( $all[0]['timestamp'] ), true );
check( 'timestamp round-trips to current_time(timestamp)',
	abs( $all[0]['timestamp'] - current_time( 'timestamp' ) ) <= 2, true );

// --- Side effects on append -------------------------------------------------
check( 'last_message_time updated', isset( $GLOBALS['postmeta'][10]['_dvc_last_message_time'] ), true );

$GLOBALS['postmeta'][11]['_dvc_deleted_by'] = [ 3 ];
ChatMessages::append( 11, 7, 'Antwort nach dem Loeschen' );
check( 'new message revives a deleted thread',
	isset( $GLOBALS['postmeta'][11]['_dvc_deleted_by'] ), false );

check( 'empty message is not stored', ChatMessages::append( 12, 3, '' ), 0 );
check( 'chat id 0 is not stored', ChatMessages::append( 0, 3, 'x' ), 0 );

// --- Structured payment fields ---------------------------------------------
$hash = str_repeat( 'ab', 32 );
ChatMessages::append( 20, 7, '[lightning_invoice]{...}[/lightning_invoice]', [
	'card_type' => 'lightning_invoice', 'payment_hash' => $hash,
] );
$card_msg = ChatMessages::all( 20 )[0];
check( 'card_type stored', $card_msg['card_type'], 'lightning_invoice' );
check( 'payment_hash stored', $card_msg['payment_hash'], $hash );

check( 'has_payment_message finds it', ChatMessages::has_payment_message( 20, $hash, 'lightning_invoice' ), true );
check( 'has_payment_message is type specific',
	ChatMessages::has_payment_message( 20, $hash, 'payment_confirmed' ), false );
check( 'has_payment_message rejects a non-hex hash',
	ChatMessages::has_payment_message( 20, 'nope', 'lightning_invoice' ), false );

// Garbage in the extra fields must not be persisted.
ChatMessages::append( 21, 7, 'text', [ 'card_type' => '<script>', 'payment_hash' => 'zz', 'nostr_pubkey' => 'short' ] );
$clean = ChatMessages::all( 21 )[0];
check( 'invalid card_type dropped', $clean['card_type'], null );
check( 'invalid payment_hash dropped', $clean['payment_hash'], null );
check( 'invalid nostr_pubkey dropped', $clean['nostr_pubkey'], null );

// --- Migration of a legacy chat --------------------------------------------
$legacy_ts = current_time( 'timestamp' ) - 3600;
$GLOBALS['postmeta'][30]['_dvc_messages'] = [
	[ 'user_id' => 3, 'message' => 'alte Nachricht 1', 'timestamp' => $legacy_ts ],
	[ 'user_id' => 7, 'message' => 'alte Nachricht 2', 'timestamp' => $legacy_ts + 60 ],
	[ 'user_id' => 7, 'message' => 'aus Nostr', 'timestamp' => $legacy_ts + 120, 'nostr_pubkey' => str_repeat( 'c', 64 ) ],
];

$migrated = ChatMessages::all( 30 );
check( 'legacy messages imported', count( $migrated ), 3 );
check( 'legacy text preserved', $migrated[1]['message'], 'alte Nachricht 2' );
check( 'legacy timestamp preserved exactly', $migrated[0]['timestamp'], $legacy_ts );
check( 'legacy nostr_pubkey preserved', $migrated[2]['nostr_pubkey'], str_repeat( 'c', 64 ) );
check( 'legacy meta moved to backup',
	isset( $GLOBALS['postmeta'][30]['_dvc_messages_legacy'] ), true );
check( 'legacy meta no longer the live source',
	isset( $GLOBALS['postmeta'][30]['_dvc_messages'] ), false );

// Reading again must not duplicate anything.
$again = ChatMessages::all( 30 );
check( 'second read does not re-import', count( $again ), 3 );

// An empty legacy array is just cleaned up.
$GLOBALS['postmeta'][31]['_dvc_messages'] = [];
check( 'empty legacy chat yields no messages', ChatMessages::all( 31 ), [] );
check( 'empty legacy meta removed', isset( $GLOBALS['postmeta'][31]['_dvc_messages'] ), false );

// --- Deleting a chat clears its rows ---------------------------------------
ChatMessages::delete_for_chat( 10 );
check( 'delete_for_chat removes the messages', ChatMessages::all( 10 ), [] );

// --- Read markers and unread counts ----------------------------------------
//
// Unread state used to be one entry per received message in a user meta array
// that was never pruned. It is now derived: one marker per chat, counted
// against the messages table.

$BUYER  = 3;
$VENDOR = 7;

// Chat 50: buyer writes twice, vendor once.
ChatMessages::append( 50, $BUYER,  'Frage 1' );
ChatMessages::append( 50, $BUYER,  'Frage 2' );
ChatMessages::append( 50, $VENDOR, 'Antwort' );

check( 'vendor sees the buyer messages as unread',
	ChatMessages::unread_counts( [ 50 ], $VENDOR ), [ 50 => 2 ] );
check( 'buyer sees the vendor message as unread',
	ChatMessages::unread_counts( [ 50 ], $BUYER ), [ 50 => 1 ] );
check( 'own messages never count as unread',
	array_sum( ChatMessages::unread_counts( [ 50 ], $VENDOR ) ), 2 );

ChatMessages::mark_read( 50, $VENDOR );
check( 'after reading, nothing is unread for the vendor',
	ChatMessages::unread_counts( [ 50 ], $VENDOR ), [] );
check( 'reading does not affect the other party',
	ChatMessages::unread_counts( [ 50 ], $BUYER ), [ 50 => 1 ] );

ChatMessages::append( 50, $BUYER, 'Noch eine Frage' );
check( 'a new message becomes unread again',
	ChatMessages::unread_counts( [ 50 ], $VENDOR ), [ 50 => 1 ] );

// The marker is one entry per chat, not per message.
$markers = ChatMessages::read_markers( $VENDOR );
check( 'exactly one marker per chat', count( $markers ), 1 );
check( 'marker is a message id', is_int( $markers[50] ) && $markers[50] > 0, true );

// Several chats in a single query.
ChatMessages::append( 51, $BUYER, 'Chat B' );
ChatMessages::append( 52, $BUYER, 'Chat C 1' );
ChatMessages::append( 52, $BUYER, 'Chat C 2' );

$wpdb->ops = [];
$counts = ChatMessages::unread_counts( [ 50, 51, 52 ], $VENDOR );
check( 'three chats counted in ONE query', count( array_filter( $wpdb->ops, function ( $o ) { return $o === 'select'; } ) ), 1 );
check( 'counts per chat', $counts, [ 50 => 1, 51 => 1, 52 => 2 ] );
check( 'total unread across chats', array_sum( $counts ), 4 );

check( 'chats without unread are absent', isset( ChatMessages::unread_counts( [ 53 ], $VENDOR )[53] ), false );
check( 'empty chat list needs no query', ChatMessages::unread_counts( [], $VENDOR ), [] );
check( 'user 0 has no unread', ChatMessages::unread_counts( [ 50 ], 0 ), [] );

// Deleting a chat forgets its marker.
ChatMessages::forget_read_marker( 50, [ $VENDOR, $BUYER ] );
check( 'marker removed with the chat', isset( ChatMessages::read_markers( $VENDOR )[50] ), false );

// --- Last message per chat, one query --------------------------------------
$wpdb->ops = [];
$last = ChatMessages::last_messages( [ 50, 51, 52 ] );
check( 'previews for three chats in ONE query', count( array_filter( $wpdb->ops, function ( $o ) { return $o === 'select'; } ) ), 1 );
check( 'newest message of chat 50', $last[50]['message'], 'Noch eine Frage' );
check( 'newest message of chat 52', $last[52]['message'], 'Chat C 2' );
check( 'preview has the legacy keys',
	array_keys( $last[51] ) === [ 'user_id', 'message', 'timestamp', 'card_type', 'payment_hash', 'nostr_pubkey' ], true );
check( 'unknown chat has no preview', isset( $last[999] ), false );
check( 'empty list needs no query', ChatMessages::last_messages( [] ), [] );

printf( "\n%s\n", $fails ? "{$fails} FAILURE(S)" : 'all checks passed' );
exit( $fails ? 1 : 0 );
