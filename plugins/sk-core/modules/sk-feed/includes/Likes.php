<?php

namespace SK\Modules\Feed;

defined( 'ABSPATH' ) || exit;

class Likes {

	/**
	 * Toggle like. Returns true if now liked, false if unliked.
	 */
	public static function toggle( int $post_id, int $user_id ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'sk_feed_likes';

		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE post_id = %d AND user_id = %d",
			$post_id,
			$user_id
		) );

		if ( $exists ) {
			$wpdb->delete( $table, [ 'post_id' => $post_id, 'user_id' => $user_id ], [ '%d', '%d' ] );
			self::update_count( $post_id );
			return false;
		}

		$wpdb->insert( $table, [
			'post_id'    => $post_id,
			'user_id'    => $user_id,
			'created_at' => current_time( 'mysql' ),
		], [ '%d', '%d', '%s' ] );

		self::update_count( $post_id );
		return true;
	}

	public static function get_count( int $post_id ): int {
		return (int) get_post_meta( $post_id, '_sk_feed_like_count', true );
	}

	public static function has_liked( int $post_id, int $user_id ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'sk_feed_likes';

		return (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT 1 FROM {$table} WHERE post_id = %d AND user_id = %d LIMIT 1",
			$post_id,
			$user_id
		) );
	}

	private static function update_count( int $post_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'sk_feed_likes';

		$count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE post_id = %d",
			$post_id
		) );

		update_post_meta( $post_id, '_sk_feed_like_count', $count );
	}
}
