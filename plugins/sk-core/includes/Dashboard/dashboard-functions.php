<?php
/**
 * Standalone function shims for Dashboard module templates.
 *
 * Loaded unconditionally by ModuleLoader before any module is instantiated,
 * so templates can call these global functions regardless of autoload order
 * or opcode-cache state.
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// =============================================================================
// Merkliste shims
// =============================================================================

if ( ! function_exists( 'dm_get_merkliste_products' ) ) {
	function dm_get_merkliste_products( int $user_id ): array {
		global $wpdb;
		if ( ! $user_id ) {
			return [];
		}
		$table = $wpdb->prefix . 'sk_merkliste';
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d ORDER BY added_date DESC", $user_id )
		) ?: [];
	}
}

if ( ! function_exists( 'dm_remove_from_merkliste' ) ) {
	function dm_remove_from_merkliste( int $product_id, int $user_id ): bool {
		global $wpdb;
		if ( ! $user_id || ! $product_id ) {
			return false;
		}
		$table  = $wpdb->prefix . 'sk_merkliste';
		$result = $wpdb->delete( $table, [ 'user_id' => $user_id, 'product_id' => $product_id ], [ '%d', '%d' ] );
		return $result !== false;
	}
}

// =============================================================================
// VendorChat shims
// =============================================================================

if ( ! function_exists( 'dvc_get_user_chats' ) ) {
	function dvc_get_user_chats( $user_id, $archived = false ) {
		$all_chats = get_posts( [
			'post_type'      => 'vendor_chat',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'meta_value_num',
			'meta_key'       => '_dvc_last_message_time',
			'order'          => 'DESC',
			'meta_query'     => [
				'relation' => 'OR',
				[ 'key' => '_dvc_participant_1', 'value' => $user_id, 'compare' => '=' ],
				[ 'key' => '_dvc_participant_2', 'value' => $user_id, 'compare' => '=' ],
			],
		] );

		$filtered = [];
		foreach ( $all_chats as $chat ) {
			if ( dvc_is_chat_deleted_for_user( $chat->ID, $user_id ) ) {
				continue;
			}
			$archived_by = get_post_meta( $chat->ID, '_dvc_archived_by', true );
			$archived_by = is_array( $archived_by ) ? $archived_by : [];
			if ( $archived === in_array( $user_id, $archived_by ) ) {
				$filtered[] = $chat;
			}
		}
		return $filtered;
	}
}

if ( ! function_exists( 'dvc_is_chat_deleted_for_user' ) ) {
	/**
	 * Did this user delete the chat from their own dashboard?
	 *
	 * @param int $chat_id
	 * @param int $user_id
	 * @return bool
	 */
	function dvc_is_chat_deleted_for_user( $chat_id, $user_id ) {
		$deleted_by = get_post_meta( $chat_id, '_dvc_deleted_by', true );
		$deleted_by = is_array( $deleted_by ) ? array_map( 'intval', $deleted_by ) : [];

		return in_array( (int) $user_id, $deleted_by, true );
	}
}

if ( ! function_exists( 'dvc_can_view_chat' ) ) {
	/**
	 * May this user open the chat? Participant and not deleted by them.
	 *
	 * @param int $chat_id
	 * @param int $user_id
	 * @return bool
	 */
	function dvc_can_view_chat( $chat_id, $user_id ) {
		return dvc_is_chat_participant( $chat_id, $user_id )
			&& ! dvc_is_chat_deleted_for_user( $chat_id, $user_id );
	}
}

if ( ! function_exists( 'dvc_is_chat_participant' ) ) {
	function dvc_is_chat_participant( $chat_id, $user_id ) {
		$p1 = get_post_meta( $chat_id, '_dvc_participant_1', true );
		$p2 = get_post_meta( $chat_id, '_dvc_participant_2', true );
		return ( $p1 == $user_id || $p2 == $user_id );
	}
}

if ( ! function_exists( 'dvc_mark_chat_as_read' ) ) {
	function dvc_mark_chat_as_read( $chat_id, $user_id ) {
		\SK\Core\Dashboard\ChatMessages::mark_read( (int) $chat_id, (int) $user_id );
	}
}

if ( ! function_exists( 'dvc_get_other_participant' ) ) {
	function dvc_get_other_participant( $chat_id, $user_id ) {
		$p1 = get_post_meta( $chat_id, '_dvc_participant_1', true );
		$p2 = get_post_meta( $chat_id, '_dvc_participant_2', true );
		if ( $p1 == $user_id ) return $p2;
		if ( $p2 == $user_id ) return $p1;
		return null;
	}
}

if ( ! function_exists( 'dvc_get_chat_messages' ) ) {
	function dvc_get_chat_messages( $chat_id ) {
		return \SK\Core\Dashboard\ChatMessages::all( (int) $chat_id );
	}
}

if ( ! function_exists( 'dvc_prepare_chat_message' ) ) {
	/**
	 * Render data for one chat message: display text + verified payment card.
	 *
	 * @param array $message
	 * @param int   $chat_id
	 * @return array{text:string, card:?array}
	 */
	function dvc_prepare_chat_message( $message, $chat_id ) {
		if ( class_exists( '\SK\Core\Dashboard\Modules\VendorChat' ) ) {
			return \SK\Core\Dashboard\Modules\VendorChat::prepare_message( $message, $chat_id );
		}

		return [
			'text' => isset( $message['message'] ) ? (string) $message['message'] : '',
			'card' => null,
		];
	}
}

if ( ! function_exists( 'dvc_has_unread_messages' ) ) {
	function dvc_has_unread_messages( $chat_id, $user_id ) {
		$counts = \SK\Core\Dashboard\ChatMessages::unread_counts( [ (int) $chat_id ], (int) $user_id );

		return ! empty( $counts[ (int) $chat_id ] );
	}
}

if ( ! function_exists( 'dvc_get_last_messages' ) ) {
	/**
	 * Newest message of each chat, in one query.
	 *
	 * The list used to load every message of every chat just to show one line.
	 *
	 * @param int[] $chat_ids
	 * @return array [ chat_id => message array ]
	 */
	function dvc_get_last_messages( array $chat_ids ) {
		return \SK\Core\Dashboard\ChatMessages::last_messages( $chat_ids );
	}
}

if ( ! function_exists( 'dvc_get_unread_counts' ) ) {
	/**
	 * Unread counts per chat, in one query.
	 *
	 * @param int[] $chat_ids
	 * @param int   $user_id
	 * @return array [ chat_id => count ]
	 */
	function dvc_get_unread_counts( array $chat_ids, $user_id ) {
		return \SK\Core\Dashboard\ChatMessages::unread_counts( $chat_ids, (int) $user_id );
	}
}
