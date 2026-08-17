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
		$this->rows[ (int) $data['chat_id'] ][] = $row;
		$this->insert_id = $this->next_id++;
		return 1;
	}

	public function get_results( $query ) {
		$this->ops[] = 'select';
		if ( ! preg_match( '/chat_id = (\d+)/', $query, $m ) ) { return []; }
		return $this->rows[ (int) $m[1] ] ?? [];
	}

	public function get_var( $query ) {
		$this->ops[] = 'select_var';
		if ( ! preg_match( '/chat_id = (\d+)/', $query, $m ) ) { return 0; }
		$rows = $this->rows[ (int) $m[1] ] ?? [];
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

printf( "\n%s\n", $fails ? "{$fails} FAILURE(S)" : 'all checks passed' );
exit( $fails ? 1 : 0 );
