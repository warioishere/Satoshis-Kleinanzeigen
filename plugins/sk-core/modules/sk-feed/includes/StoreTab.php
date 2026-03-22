<?php

namespace SK\Modules\Feed;

defined( 'ABSPATH' ) || exit;

class StoreTab {

	public function __construct() {
		add_filter( 'sk_store_tabs', [ $this, 'add_store_tab' ], 10, 2 );
		add_action( 'sk_rewrite_rules_loaded', [ $this, 'rewrite_rules' ] );
		add_filter( 'query_vars', [ $this, 'register_query_var' ] );
		add_filter( 'template_include', [ $this, 'load_template' ], 200 );
	}

	public function add_store_tab( array $tabs, int $store_id ): array {
		// Only show tab if vendor has at least one post.
		$has_posts = (bool) get_posts( [
			'post_type'      => PostType::POST_TYPE,
			'author'         => $store_id,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
		] );

		if ( ! $has_posts ) {
			return $tabs;
		}

		$tabs['vendor_feed'] = [
			'title' => __( 'Feed', 'sk-core' ),
			'url'   => function_exists( 'sk_get_store_url' ) ? sk_get_store_url( $store_id, 'beitraege' ) : '',
		];

		return $tabs;
	}

	public function rewrite_rules( $store_url ) {
		add_rewrite_rule(
			$store_url . '/([^/]+)/beitraege/?$',
			'index.php?' . $store_url . '=$matches[1]&vendor_feed=true',
			'top'
		);
	}

	public function register_query_var( $vars ) {
		$vars[] = 'vendor_feed';
		return $vars;
	}

	public function load_template( $template ) {
		if ( ! get_query_var( 'vendor_feed' ) ) {
			return $template;
		}

		return SK_FEED_TEMPLATES . '/store-feed.php';
	}
}
