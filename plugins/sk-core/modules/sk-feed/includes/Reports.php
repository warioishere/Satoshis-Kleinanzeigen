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

		$inserted = $wpdb->insert( $table, [
			'post_id'    => $post_id,
			'user_id'    => $user_id,
			'reason'     => mb_substr( sanitize_text_field( $reason ), 0, 255 ),
			'created_at' => current_time( 'mysql' ),
		], [ '%d', '%d', '%s', '%s' ] );

		// Without this the caller would report success for a row that never
		// landed, and the user could report the same post again and again.
		if ( ! $inserted ) {
			return false;
		}

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
		if ( self::get_count( $post_id ) < self::HIDE_THRESHOLD ) {
			return;
		}

		if ( 'pending' === get_post_status( $post_id ) ) {
			return;
		}

		wp_update_post( [
			'ID'          => $post_id,
			'post_status' => 'pending',
		] );

		self::notify_moderators( $post_id );
	}

	/**
	 * Auto-hiding is the only thing standing between a post and a handful of
	 * coordinated reports, so a human has to hear about it.
	 */
	private static function notify_moderators( int $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return;
		}

		$author = get_userdata( (int) $post->post_author );

		$subject = sprintf(
			/* translators: 1) site name */
			__( '[%s] Community-Beitrag automatisch versteckt', 'sk-core' ),
			get_bloginfo( 'name' )
		);

		$body = sprintf(
			/* translators: 1) report count, 2) author name, 3) excerpt, 4) edit link */
			__( "Ein Beitrag wurde nach %1\$d Meldungen automatisch versteckt.

Autor: %2\$s
Text: %3\$s

Pruefen: %4\$s", 'sk-core' ),
			self::get_count( $post_id ),
			$author ? $author->display_name : '#' . (int) $post->post_author,
			wp_trim_words( wp_strip_all_tags( $post->post_content ), 40 ),
			get_edit_post_link( $post_id, 'raw' )
		);

		wp_mail( get_option( 'admin_email' ), $subject, $body );
	}
}
