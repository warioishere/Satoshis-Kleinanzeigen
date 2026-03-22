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
	}

	public function register() {
		register_post_type( self::POST_TYPE, [
			'labels' => [
				'name'          => __( 'Vendor Posts', 'sk-core' ),
				'singular_name' => __( 'Vendor Post', 'sk-core' ),
			],
			'public'              => true,
			'publicly_queryable'  => true,
			'show_ui'             => false,
			'show_in_menu'        => false,
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

	public function load_template( $template ) {
		$view = get_query_var( 'sk_feed_view' );

		if ( 'feed' === $view ) {
			return SK_FEED_TEMPLATES . '/page-feed.php';
		}

		if ( 'single' === $view ) {
			$post_id = (int) get_query_var( 'sk_feed_post_id' );
			$post    = get_post( $post_id );

			if ( $post && $post->post_type === self::POST_TYPE && $post->post_status === 'publish' ) {
				return SK_FEED_TEMPLATES . '/page-single.php';
			}
		}

		return $template;
	}

	public function enable_comments( $open, $post_id ) {
		if ( get_post_type( $post_id ) === self::POST_TYPE ) {
			return true;
		}
		return $open;
	}
}
