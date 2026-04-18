<?php

namespace SK\Modules\Feed;

use SK\Core\Dashboard\DashboardModule;

defined( 'ABSPATH' ) || exit;

class Dashboard extends DashboardModule {

	public function config(): ?array {
		return [
			'slug'       => 'feed-posts',
			'title'      => __( 'Beiträge', 'sk-core' ),
			'icon'       => '<i class="fas fa-rss"></i>',
			'pos'        => 56,
			'permission' => 'sk_view_overview_menu',
			'template'   => [ $this, 'render_dashboard' ],
		];
	}

	protected function register_extras(): void {
		add_action( 'init', [ $this, 'add_endpoint' ] );
	}

	public function add_endpoint() {
		add_rewrite_endpoint( 'feed-posts', EP_PAGES );
	}

	public function render_dashboard( $query_vars ): void {
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
