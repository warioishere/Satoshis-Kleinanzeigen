<?php

namespace SK\Modules\Feed;

defined( 'ABSPATH' ) || exit;

class FeedPage {

	public function __construct() {
		// Nothing to hook. Feed templates render their text through
		// self::render_content(), everything else on the site is none of our
		// business — the filters used to run on every comment sitewide.
	}

	/**
	 * Turn stored feed text into display HTML.
	 *
	 * Mentions and hashtags are resolved on the plain text first, so the regex
	 * can never reach into an attribute of a link we just built.
	 */
	public static function render_content( string $raw ): string {
		$text = self::linkify_mentions( $raw );
		$text = self::linkify_hashtags( $text );
		$text = make_clickable( $text );
		$text = wpautop( $text );

		return wp_kses_post( $text );
	}

	/**
	 * Replace @StoreName with a link to the store page.
	 *
	 * The @ has to start a word, otherwise every e-mail address in a post
	 * would be treated as a mention.
	 */
	public static function linkify_mentions( string $text ): string {
		return preg_replace_callback( '/(?<![\w.@-])@([A-Za-z0-9À-ÿ][A-Za-z0-9À-ÿ .&\'-]{0,49})/u', function ( $m ) {
			$name = trim( $m[1] );
			$user = self::find_vendor_by_store_name( $name );
			if ( ! $user ) {
				return $m[0];
			}
			$url = function_exists( 'sk_get_store_url' ) ? sk_get_store_url( $user->ID ) : '#';
			return '<a href="' . esc_url( $url ) . '" class="sk-feed-mention">@' . esc_html( $name ) . '</a>';
		}, $text );
	}

	/**
	 * Replace #hashtag with a link that filters the feed.
	 */
	public static function linkify_hashtags( string $text ): string {
		return preg_replace_callback( '/#([A-Za-z0-9À-ÿ_]{2,30})/', function ( $m ) {
			$tag = $m[1];
			$url = home_url( '/community/?tag=' . urlencode( strtolower( $tag ) ) );
			return '<a href="' . esc_url( $url ) . '" class="sk-feed-hashtag">#' . esc_html( $tag ) . '</a>';
		}, $text );
	}

	/**
	 * Look the store name up in the database instead of scanning a fixed
	 * slice of users. Resolved names are cached for the request, a feed page
	 * can easily contain the same mention a dozen times.
	 */
	private static function find_vendor_by_store_name( string $name ): ?\WP_User {
		static $cache = [];

		$key = mb_strtolower( $name );

		if ( array_key_exists( $key, $cache ) ) {
			return $cache[ $key ];
		}

		$users = get_users( [
			'role__in'   => [ 'seller', 'administrator' ],
			'number'     => 1,
			'meta_query' => [
				[
					'key'     => 'sk_store_name',
					'value'   => $name,
					'compare' => '=',
				],
			],
		] );

		$cache[ $key ] = $users ? $users[0] : null;

		return $cache[ $key ];
	}

	/**
	 * Build the WP_Query for the feed list.
	 */
	public static function get_feed_query( string $filter = 'all' ): \WP_Query {
		$args = [
			'post_type'      => PostType::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => 10,
			'paged'          => 1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		if ( 'posts' === $filter ) {
			$args['meta_query'] = [
				[ 'key' => '_sk_feed_type', 'value' => 'posting' ],
			];
		}

		if ( 'inserate' === $filter ) {
			$args['meta_query'] = [
				[ 'key' => '_sk_feed_type', 'value' => 'product_announce' ],
			];
		}

		if ( 'gesuche' === $filter ) {
			$args['meta_query'] = [
				[ 'key' => '_sk_feed_type', 'value' => 'gesuch_announce' ],
			];
		}

		if ( 'following' === $filter && is_user_logged_in() ) {
			$following_ids = self::get_following_vendor_ids( get_current_user_id() );
			if ( empty( $following_ids ) ) {
				$args['author__in'] = [ 0 ];
			} else {
				$args['author__in'] = $following_ids;
			}
		}

		if ( 'trending' === $filter ) {
			$args['date_query'] = [ [ 'after' => '24 hours ago' ] ];
			$args['meta_key']   = '_sk_feed_like_count';
			$args['orderby']    = 'meta_value_num';
			$args['order']      = 'DESC';
		}

		// Hashtag search.
		$tag = sanitize_text_field( wp_unslash( $_GET['tag'] ?? '' ) );
		if ( $tag ) {
			$args['s'] = '#' . $tag;
		}

		return new \WP_Query( $args );
	}

	public static function get_following_vendor_ids( int $user_id ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'sk_follow_store_followers';

		if ( ! $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) ) {
			return [];
		}

		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
			"SELECT vendor_id FROM {$table} WHERE follower_id = %d AND unfollowed_at IS NULL",
			$user_id
		) ) );
	}
}
