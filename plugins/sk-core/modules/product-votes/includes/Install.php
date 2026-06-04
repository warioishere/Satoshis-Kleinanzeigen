<?php

namespace SK\Modules\ProductVotes;

defined( 'ABSPATH' ) || exit;

final class Install {

	public static function install(): void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$table   = $wpdb->prefix . 'sk_product_votes';

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id BIGINT UNSIGNED NOT NULL,
			voter_id BIGINT UNSIGNED NOT NULL,
			value TINYINT NOT NULL,
			created_at INT UNSIGNED NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_product_voter (product_id, voter_id),
			KEY idx_product (product_id),
			KEY idx_voter (voter_id)
		) {$charset};";

		dbDelta( $sql );
	}
}
