<?php

namespace SK\Modules\Feed;

defined( 'ABSPATH' ) || exit;

class Reports {

	const HIDE_THRESHOLD = 5;

	/**
	 * Report a post. Returns true on success, false if already reported.
	 */
	public static function add( int $post_id, int $user_id, string $reason = '' ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'sk_feed_reports';

		if ( self::has_reported( $post_id, $user_id ) ) {
			return false;
		}

		$wpdb->insert( $table, [
			'post_id'    => $post_id,
			'user_id'    => $user_id,
			'reason'     => sanitize_text_field( $reason ),
			'created_at' => current_time( 'mysql' ),
		], [ '%d', '%d', '%s', '%s' ] );

		self::update_count( $post_id );
		self::maybe_auto_hide( $post_id );

		return true;
	}

	public static function get_count( int $post_id ): int {
		return (int) get_post_meta( $post_id, '_sk_feed_report_count', true );
	}

	public static function has_reported( int $post_id, int $user_id ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'sk_feed_reports';

		return (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT 1 FROM {$table} WHERE post_id = %d AND user_id = %d LIMIT 1",
			$post_id,
			$user_id
		) );
	}

	private static function update_count( int $post_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'sk_feed_reports';

		$count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE post_id = %d",
			$post_id
		) );

		update_post_meta( $post_id, '_sk_feed_report_count', $count );
	}

	private static function maybe_auto_hide( int $post_id ) {
		if ( self::get_count( $post_id ) >= self::HIDE_THRESHOLD ) {
			wp_update_post( [
				'ID'          => $post_id,
				'post_status' => 'pending',
			] );
		}
	}
}
