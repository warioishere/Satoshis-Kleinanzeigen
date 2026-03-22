<?php

namespace SK\Modules\Feed;

defined( 'ABSPATH' ) || exit;

class Dashboard {

	public function __construct() {
		add_action( 'init', [ $this, 'add_endpoint' ] );
		add_filter( 'sk_get_dashboard_nav', [ $this, 'add_dashboard_nav' ] );
		add_action( 'sk_load_custom_template', [ $this, 'load_template' ] );
	}

	public function add_endpoint() {
		add_rewrite_endpoint( 'feed-posts', EP_PAGES );
	}

	public function add_dashboard_nav( $settings ) {
		$settings['feed-posts'] = [
			'title'      => __( 'Beiträge', 'sk-core' ),
			'icon'       => '<i class="fas fa-rss"></i>',
			'url'        => function_exists( 'sk_get_navigation_url' ) ? sk_get_navigation_url( 'feed-posts' ) : '',
			'pos'        => 56,
			'permission' => 'sk_view_overview_menu',
		];

		return $settings;
	}

	public function load_template( $query_vars ) {
		if ( empty( $query_vars ) || ! array_key_exists( 'feed-posts', $query_vars ) ) {
			return;
		}

		$vendor_id = function_exists( 'sk_get_current_user_id' ) ? sk_get_current_user_id() : get_current_user_id();

		$posts = get_posts( [
			'post_type'      => PostType::POST_TYPE,
			'post_status'    => [ 'publish', 'pending' ],
			'author'         => $vendor_id,
			'posts_per_page' => 50,
			'orderby'        => 'date',
			'order'          => 'DESC',
		] );

		$post_count = count( $posts );

		include SK_FEED_TEMPLATES . '/dashboard-feed.php';
	}
}
