<?php

namespace SK\Modules\Feed;

defined( 'ABSPATH' ) || exit;

class FeedPage {

	public function __construct() {
		// Convert @StoreName mentions and #hashtags to links — only on feed pages.
		add_filter( 'comment_text', [ __CLASS__, 'linkify_mentions' ], 20 );
		add_filter( 'comment_text', [ __CLASS__, 'linkify_hashtags' ], 21 );

		// Only apply to post content on our feed templates, not globally.
		add_action( 'template_redirect', function () {
			if ( get_query_var( 'sk_feed_view' ) || get_query_var( 'vendor_feed' ) ) {
				add_filter( 'the_content', [ __CLASS__, 'linkify_mentions' ], 20 );
				add_filter( 'the_content', [ __CLASS__, 'linkify_hashtags' ], 21 );
			}
		} );
	}

	/**
	 * Replace @StoreName with a link to the store page.
	 */
	public static function linkify_mentions( string $text ): string {
		return preg_replace_callback( '/@([A-Za-z0-9À-ÿ][A-Za-z0-9À-ÿ .&\'-]{0,49})/', function ( $m ) {
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

	private static function find_vendor_by_store_name( string $name ): ?\WP_User {
		$users = get_users( [
			'role__in' => [ 'seller', 'administrator' ],
			'number'   => 50,
		] );

		foreach ( $users as $user ) {
			$info = function_exists( 'sk_get_store_info' ) ? sk_get_store_info( $user->ID ) : [];
			$store_name = $info['store_name'] ?? '';
			if ( $store_name && mb_strtolower( $store_name ) === mb_strtolower( $name ) ) {
				return $user;
			}
		}

		return null;
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
		$tag = sanitize_text_field( $_GET['tag'] ?? '' );
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
