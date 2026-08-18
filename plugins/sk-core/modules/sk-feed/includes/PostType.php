<?php

namespace SK\Modules\Feed;

defined( 'ABSPATH' ) || exit;

class PostType {

	const POST_TYPE = 'sk_vendor_post';

	public function __construct() {
		add_action( 'init', [ $this, 'register' ] );
		add_action( 'init', [ $this, 'rewrite_rules' ] );
		add_filter( 'query_vars', [ $this, 'query_vars' ] );
		add_filter( 'template_include', [ $this, 'load_template' ], 200 );
		add_filter( 'comments_open', [ $this, 'enable_comments' ], 10, 2 );
		add_action( 'template_redirect', [ $this, 'maybe_404' ], 1 );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', [ $this, 'admin_columns' ] );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', [ $this, 'admin_column' ], 10, 2 );
	}

	public function register() {
		// Moderators need a way to look at reported posts and put them back or
		// delete them, so the post type gets an admin screen for them only.
		$can_moderate = is_admin() && current_user_can( 'moderate_comments' );

		register_post_type( self::POST_TYPE, [
			'labels' => [
				'name'          => __( 'Community-Beiträge', 'sk-core' ),
				'singular_name' => __( 'Community-Beitrag', 'sk-core' ),
				'menu_name'     => __( 'Community-Beiträge', 'sk-core' ),
			],
			'public'              => true,
			'publicly_queryable'  => true,
			'show_ui'             => $can_moderate,
			'show_in_menu'        => $can_moderate,
			'menu_icon'           => 'dashicons-rss',
			'has_archive'         => false,
			'rewrite'             => false,
			'supports'            => [ 'title', 'editor', 'author', 'thumbnail', 'comments' ],
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'exclude_from_search' => true,
		] );
	}

	public function rewrite_rules() {
		// /community/post/123/ → single post view
		add_rewrite_rule(
			'community/post/([0-9]+)/?$',
			'index.php?sk_feed_view=single&sk_feed_post_id=$matches[1]',
			'top'
		);

		// /community/ → feed list view
		add_rewrite_rule(
			'community/?$',
			'index.php?sk_feed_view=feed',
			'top'
		);
	}

	public function query_vars( $vars ) {
		$vars[] = 'sk_feed_view';
		$vars[] = 'sk_feed_post_id';
		return $vars;
	}

	/**
	 * A hidden or missing post must not answer with the blog index and a 200.
	 */
	public function maybe_404() {
		if ( 'single' !== get_query_var( 'sk_feed_view' ) ) {
			return;
		}

		if ( $this->get_viewable_post() ) {
			return;
		}

		global $wp_query;

		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}

	/**
	 * The requested feed post, if it exists and may be shown.
	 */
	private function get_viewable_post(): ?\WP_Post {
		$post = get_post( (int) get_query_var( 'sk_feed_post_id' ) );

		if ( ! $post || $post->post_type !== self::POST_TYPE ) {
			return null;
		}

		if ( 'publish' === $post->post_status ) {
			return $post;
		}

		// Authors and moderators still see their hidden post.
		if ( (int) $post->post_author === get_current_user_id() || current_user_can( 'moderate_comments' ) ) {
			return $post;
		}

		return null;
	}

	public function load_template( $template ) {
		$view = get_query_var( 'sk_feed_view' );

		if ( 'feed' === $view ) {
			return SK_FEED_TEMPLATES . '/page-feed.php';
		}

		if ( 'single' === $view && $this->get_viewable_post() ) {
			return SK_FEED_TEMPLATES . '/page-single.php';
		}

		return $template;
	}

	public function admin_columns( $columns ) {
		$columns['sk_feed_reports'] = __( 'Meldungen', 'sk-core' );

		return $columns;
	}

	public function admin_column( $column, $post_id ) {
		if ( 'sk_feed_reports' !== $column ) {
			return;
		}

		$count = Reports::get_count( (int) $post_id );

		echo $count ? '<strong>' . esc_html( $count ) . '</strong>' : '—';
	}

	public function enable_comments( $open, $post_id ) {
		if ( get_post_type( $post_id ) === self::POST_TYPE ) {
			return true;
		}
		return $open;
	}
}
