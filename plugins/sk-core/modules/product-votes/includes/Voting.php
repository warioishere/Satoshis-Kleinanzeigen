<?php

namespace SK\Modules\ProductVotes;

defined( 'ABSPATH' ) || exit;

/**
 * Vote storage + qualification logic + aggregate cache.
 *
 * Qualification:
 *   - ≥14 days account age
 *   - Profile picture set (Vendor::get_avatar_id() > 0)
 */
final class Voting {

	const MIN_ACCOUNT_AGE_DAYS  = 14;
	const MIN_VOTES_FOR_DISPLAY = 5;
	const META_HOT              = '_sk_pv_hot_count';
	const META_COLD             = '_sk_pv_cold_count';
	const META_TOTAL            = '_sk_pv_total_count';

	/**
	 * Returns the disqualification reason (empty string if qualified).
	 * Use this for tooltips on disabled buttons.
	 */
	public static function disqualification_reason( int $user_id ): string {
		if ( ! $user_id ) {
			return __( 'Bitte einloggen um zu bewerten.', 'sk-core' );
		}
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return __( 'Account nicht gefunden.', 'sk-core' );
		}

		$registered = strtotime( $user->user_registered );
		if ( $registered ) {
			$age_days = (int) floor( ( time() - $registered ) / DAY_IN_SECONDS );
			if ( $age_days < self::MIN_ACCOUNT_AGE_DAYS ) {
				return sprintf(
					/* translators: 1: current age in days, 2: required age in days */
					__( 'Dein Account ist erst %1$d Tage alt — du brauchst mindestens %2$d Tage.', 'sk-core' ),
					$age_days,
					self::MIN_ACCOUNT_AGE_DAYS
				);
			}
		}

		if ( ! self::has_profile_picture( $user_id ) ) {
			return __( 'Setze ein Profilbild in den Einstellungen, dann kannst du bewerten.', 'sk-core' );
		}

		return '';
	}

	public static function is_qualified( int $user_id ): bool {
		return '' === self::disqualification_reason( $user_id );
	}

	private static function has_profile_picture( int $user_id ): bool {
		if ( function_exists( 'sk' ) && sk()->vendor ) {
			$vendor = sk()->vendor->get( $user_id );
			if ( $vendor && (int) $vendor->get_avatar_id() > 0 ) {
				return true;
			}
		}
		// Fallback: any custom avatar meta key used historically.
		return (bool) get_user_meta( $user_id, 'sk_profile_picture', true );
	}

	public static function can_vote_on( int $product_id, int $user_id ): bool {
		if ( ! self::is_qualified( $user_id ) ) {
			return false;
		}
		$product = get_post( $product_id );
		if ( ! $product || $product->post_type !== 'product' || $product->post_status !== 'publish' ) {
			return false;
		}
		// Vendor darf nicht für eigene Produkte voten.
		if ( (int) $product->post_author === $user_id ) {
			return false;
		}
		return true;
	}

	public static function get_user_vote( int $product_id, int $user_id ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'sk_product_votes';
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT value FROM {$table} WHERE product_id=%d AND voter_id=%d", $product_id, $user_id )
		);
		return $row ? (int) $row->value : 0;
	}

	/**
	 * Cast or update a vote. Returns the persisted value (-1, 0, +1).
	 * Passing the same value twice removes the vote (toggle).
	 */
	public static function cast_vote( int $product_id, int $user_id, int $value ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'sk_product_votes';

		$value   = $value < 0 ? -1 : 1;
		$current = self::get_user_vote( $product_id, $user_id );

		if ( $current === $value ) {
			$wpdb->delete( $table, [ 'product_id' => $product_id, 'voter_id' => $user_id ], [ '%d', '%d' ] );
			self::recount( $product_id );
			return 0;
		}

		$wpdb->replace(
			$table,
			[
				'product_id' => $product_id,
				'voter_id'   => $user_id,
				'value'      => $value,
				'created_at' => time(),
			],
			[ '%d', '%d', '%d', '%d' ]
		);
		self::recount( $product_id );
		return $value;
	}

	/**
	 * Recount aggregate and write to product meta. Called on every vote write
	 * so the display path is cheap (single get_post_meta).
	 */
	public static function recount( int $product_id ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'sk_product_votes';

		$hot  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE product_id=%d AND value=1", $product_id ) );
		$cold = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE product_id=%d AND value=-1", $product_id ) );

		update_post_meta( $product_id, self::META_HOT, $hot );
		update_post_meta( $product_id, self::META_COLD, $cold );
		update_post_meta( $product_id, self::META_TOTAL, $hot + $cold );
	}

	public static function get_counts( int $product_id ): array {
		return [
			'hot'   => (int) get_post_meta( $product_id, self::META_HOT, true ),
			'cold'  => (int) get_post_meta( $product_id, self::META_COLD, true ),
			'total' => (int) get_post_meta( $product_id, self::META_TOTAL, true ),
		];
	}

	public static function should_show_counts( int $product_id ): bool {
		$counts = self::get_counts( $product_id );
		return $counts['total'] >= self::MIN_VOTES_FOR_DISPLAY;
	}
}
