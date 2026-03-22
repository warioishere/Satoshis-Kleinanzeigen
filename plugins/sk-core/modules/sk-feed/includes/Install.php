<?php

namespace SK\Modules\Feed;

defined( 'ABSPATH' ) || exit;

class Install {

	public static function create_tables() {
		global $wpdb;

		$charset = $wpdb->get_charset_collate();
		$likes   = $wpdb->prefix . 'sk_feed_likes';
		$reports = $wpdb->prefix . 'sk_feed_reports';

		$sql = "CREATE TABLE {$likes} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id BIGINT UNSIGNED NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY post_user (post_id, user_id),
			KEY post_id (post_id)
		) {$charset};

		CREATE TABLE {$reports} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id BIGINT UNSIGNED NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			reason VARCHAR(255) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY post_user (post_id, user_id),
			KEY post_id (post_id)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'sk_feed_db_version', SK_FEED_VERSION );
	}
}
