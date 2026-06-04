<?php

namespace SK\Modules\ProductVotes;

defined( 'ABSPATH' ) || exit;

/**
 * Stores up to 30 unique login dates (YYYY-MM-DD) per user as JSON.
 * Used by Voting::is_qualified() to check ≥3 active days in last 14 days.
 */
final class LoginTracker {

	const META_KEY = 'sk_login_dates_recent';
	const MAX_DAYS = 30;

	public function __construct() {
		add_action( 'wp_login', [ $this, 'on_login' ], 10, 2 );
	}

	public function on_login( string $user_login, \WP_User $user ): void {
		self::record_login( $user->ID );
	}

	public static function record_login( int $user_id ): void {
		$today = gmdate( 'Y-m-d' );
		$dates = self::get_dates( $user_id );

		if ( in_array( $today, $dates, true ) ) {
			return;
		}
		array_unshift( $dates, $today );
		$dates = array_slice( $dates, 0, self::MAX_DAYS );

		update_user_meta( $user_id, self::META_KEY, wp_json_encode( $dates ) );
	}

	public static function get_dates( int $user_id ): array {
		$raw = get_user_meta( $user_id, self::META_KEY, true );
		if ( ! $raw ) {
			return [];
		}
		$dates = json_decode( $raw, true );
		return is_array( $dates ) ? $dates : [];
	}

	public static function count_recent_days( int $user_id, int $window_days = 14 ): int {
		$cutoff = strtotime( '-' . $window_days . ' days' );
		$count  = 0;
		foreach ( self::get_dates( $user_id ) as $date ) {
			if ( strtotime( $date ) >= $cutoff ) {
				$count++;
			}
		}
		return $count;
	}
}
