<?php

namespace SK\Modules\Feed;

defined( 'ABSPATH' ) || exit;

class Ajax {

	public function __construct() {
		add_action( 'wp_ajax_sk_feed_create_post', [ $this, 'create_post' ] );
		add_action( 'wp_ajax_sk_feed_edit_post', [ $this, 'edit_post' ] );
		add_action( 'wp_ajax_sk_feed_delete_post', [ $this, 'delete_post' ] );
		add_action( 'wp_ajax_sk_feed_toggle_like', [ $this, 'toggle_like' ] );
		add_action( 'wp_ajax_sk_feed_report_post', [ $this, 'report_post' ] );
		add_action( 'wp_ajax_sk_feed_load_more', [ $this, 'load_more' ] );
		add_action( 'wp_ajax_nopriv_sk_feed_load_more', [ $this, 'load_more' ] );
		add_action( 'wp_ajax_sk_feed_add_comment', [ $this, 'add_comment' ] );
		add_action( 'wp_ajax_sk_feed_search_stores', [ $this, 'search_stores' ] );
		add_action( 'wp_ajax_sk_feed_toggle_pin', [ $this, 'toggle_pin' ] );
		add_action( 'wp_ajax_sk_feed_track_zap', [ $this, 'track_zap' ] );
	}

	public function create_post() {
		check_ajax_referer( 'sk_feed', '_nonce' );

		if ( ! is_user_logged_in() || ! function_exists( 'sk_is_user_seller' ) || ! sk_is_user_seller( get_current_user_id() ) ) {
			wp_send_json_error( [ 'message' => __( 'Nur Verkäufer können Beiträge erstellen.', 'sk-core' ) ] );
		}

		$content = sanitize_textarea_field( wp_unslash( $_POST['content'] ?? '' ) );
		if ( empty( $content ) ) {
			wp_send_json_error( [ 'message' => __( 'Bitte Text eingeben.', 'sk-core' ) ] );
		}

		if ( mb_strlen( $content ) > 2000 ) {
			wp_send_json_error( [ 'message' => __( 'Maximal 2000 Zeichen.', 'sk-core' ) ] );
		}

		$post_id = wp_insert_post( [
			'post_type'    => PostType::POST_TYPE,
			'post_status'  => 'publish',
			'post_content' => wp_kses_post( $content ),
			'post_author'  => get_current_user_id(),
		], true );

		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( [ 'message' => $post_id->get_error_message() ] );
		}

		update_post_meta( $post_id, '_sk_feed_type', 'posting' );

		// Handle image upload.
		if ( ! empty( $_FILES['image'] ) && ! empty( $_FILES['image']['tmp_name'] ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';

			$attach_id = media_handle_upload( 'image', $post_id );
			if ( ! is_wp_error( $attach_id ) ) {
				set_post_thumbnail( $post_id, $attach_id );
			}
		}

		// Render the card HTML for prepending.
		ob_start();
		$post = get_post( $post_id );
		setup_postdata( $GLOBALS['post'] = $post );
		include SK_FEED_TEMPLATES . '/feed-card.php';
		wp_reset_postdata();
		$html = ob_get_clean();

		// Publish as Kind 1 Nostr event (deferred to shutdown so response isn't delayed).
		$nostr_post_id = $post_id;
		$nostr_user_id = get_current_user_id();
		register_shutdown_function( function () use ( $nostr_post_id, $nostr_user_id ) {
			if ( ! class_exists( 'SK\Modules\Auth\NostrIdentity' ) ) {
				return;
			}
			if ( ! \SK\Modules\Auth\NostrIdentity::has_identity( $nostr_user_id ) ) {
				return;
			}

			$post = get_post( $nostr_post_id );
			if ( ! $post ) {
				return;
			}

			$content = wp_strip_all_tags( $post->post_content );
			$permalink = home_url( '/community/post/' . $nostr_post_id . '/' );

			$tags = [ [ 'r', $permalink ] ];

			// Extract hashtags from content.
			if ( preg_match_all( '/#([A-Za-z0-9À-ÿ_]{2,30})/', $content, $matches ) ) {
				foreach ( $matches[1] as $tag ) {
					$tags[] = [ 't', strtolower( $tag ) ];
				}
			}

			$event_id = \SK\Modules\Auth\NostrIdentity::publish( $nostr_user_id, 1, $content, $tags );

			if ( $event_id ) {
				update_post_meta( $nostr_post_id, '_sk_nostr_event_id', $event_id );
			}
		} );

		wp_send_json_success( [ 'post_id' => $post_id, 'html' => $html ] );
	}

	public function edit_post() {
		check_ajax_referer( 'sk_feed', '_nonce' );

		$post_id = (int) ( $_POST['post_id'] ?? 0 );
		$post    = get_post( $post_id );

		if ( ! $post || $post->post_type !== PostType::POST_TYPE ) {
			wp_send_json_error( [ 'message' => __( 'Beitrag nicht gefunden.', 'sk-core' ) ] );
		}

		if ( (int) $post->post_author !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Keine Berechtigung.', 'sk-core' ) ] );
		}

		$content = sanitize_textarea_field( wp_unslash( $_POST['content'] ?? '' ) );
		if ( empty( $content ) ) {
			wp_send_json_error( [ 'message' => __( 'Bitte Text eingeben.', 'sk-core' ) ] );
		}

		if ( mb_strlen( $content ) > 2000 ) {
			wp_send_json_error( [ 'message' => __( 'Maximal 2000 Zeichen.', 'sk-core' ) ] );
		}

		wp_update_post( [
			'ID'           => $post_id,
			'post_content' => wp_kses_post( $content ),
		] );

		// Remove image.
		$remove_image = isset( $_POST['remove_image'] ) && $_POST['remove_image'] === '1';
		if ( $remove_image ) {
			$old_thumb = get_post_thumbnail_id( $post_id );
			if ( $old_thumb ) {
				wp_delete_attachment( $old_thumb, true );
			}
			delete_post_thumbnail( $post_id );
		}

		// Upload new image.
		if ( ! $remove_image && ! empty( $_FILES['image'] ) && ! empty( $_FILES['image']['tmp_name'] ) ) {
			// Remove old thumbnail first.
			$old_thumb = get_post_thumbnail_id( $post_id );
			if ( $old_thumb ) {
				wp_delete_attachment( $old_thumb, true );
			}

			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';

			$attach_id = media_handle_upload( 'image', $post_id );
			if ( ! is_wp_error( $attach_id ) ) {
				set_post_thumbnail( $post_id, $attach_id );
			}
		}

		$thumb_url = get_the_post_thumbnail_url( $post_id, 'thumbnail' );

		wp_send_json_success( [
			'content'   => wp_kses_post( $content ),
			'thumb_url' => $thumb_url ?: '',
		] );
	}

	public function delete_post() {
		check_ajax_referer( 'sk_feed', '_nonce' );

		$post_id = (int) ( $_POST['post_id'] ?? 0 );
		$post    = get_post( $post_id );

		if ( ! $post || $post->post_type !== PostType::POST_TYPE ) {
			wp_send_json_error( [ 'message' => __( 'Beitrag nicht gefunden.', 'sk-core' ) ] );
		}

		if ( (int) $post->post_author !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Keine Berechtigung.', 'sk-core' ) ] );
		}

		wp_delete_post( $post_id, true );
		wp_send_json_success();
	}

	public function toggle_like() {
		check_ajax_referer( 'sk_feed', '_nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => __( 'Bitte anmelden.', 'sk-core' ) ] );
		}

		$post_id = (int) ( $_POST['post_id'] ?? 0 );
		$post    = get_post( $post_id );

		if ( ! $post || $post->post_type !== PostType::POST_TYPE ) {
			wp_send_json_error( [ 'message' => __( 'Beitrag nicht gefunden.', 'sk-core' ) ] );
		}

		$liked = Likes::toggle( $post_id, get_current_user_id() );

		wp_send_json_success( [
			'liked' => $liked,
			'count' => Likes::get_count( $post_id ),
		] );
	}

	public function report_post() {
		check_ajax_referer( 'sk_feed', '_nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => __( 'Bitte anmelden.', 'sk-core' ) ] );
		}

		$post_id = (int) ( $_POST['post_id'] ?? 0 );
		$reason  = sanitize_text_field( $_POST['reason'] ?? '' );
		$post    = get_post( $post_id );

		if ( ! $post || $post->post_type !== PostType::POST_TYPE ) {
			wp_send_json_error( [ 'message' => __( 'Beitrag nicht gefunden.', 'sk-core' ) ] );
		}

		$added = Reports::add( $post_id, get_current_user_id(), $reason );

		if ( ! $added ) {
			wp_send_json_error( [ 'message' => __( 'Bereits gemeldet.', 'sk-core' ) ] );
		}

		wp_send_json_success( [ 'message' => __( 'Danke für die Meldung.', 'sk-core' ) ] );
	}

	public function load_more() {
		check_ajax_referer( 'sk_feed', '_nonce' );

		$page      = max( 1, (int) ( $_GET['page'] ?? $_POST['page'] ?? 1 ) );
		$vendor_id = (int) ( $_GET['vendor_id'] ?? $_POST['vendor_id'] ?? 0 );
		$filter    = sanitize_text_field( $_GET['filter'] ?? $_POST['filter'] ?? 'all' );

		$args = [
			'post_type'      => PostType::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => 10,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		if ( $vendor_id ) {
			$args['author'] = $vendor_id;
		}

		// "Meine Stores" — only posts from followed vendors.
		if ( 'following' === $filter && is_user_logged_in() ) {
			$following_ids = self::get_following_vendor_ids( get_current_user_id() );
			if ( empty( $following_ids ) ) {
				wp_send_json_success( [ 'html' => '', 'has_more' => false ] );
			}
			$args['author__in'] = $following_ids;
		}

		// "Posts" — only manual posts.
		if ( 'posts' === $filter ) {
			$args['meta_query'] = [
				[ 'key' => '_sk_feed_type', 'value' => 'posting' ],
			];
		}

		// "Inserate" — product announcements from vendor dashboard.
		if ( 'inserate' === $filter ) {
			$args['meta_query'] = [
				[ 'key' => '_sk_feed_type', 'value' => 'product_announce' ],
			];
		}

		// "Trending" — last 24h sorted by likes.
		if ( 'trending' === $filter ) {
			$args['date_query'] = [ [ 'after' => '24 hours ago' ] ];
			$args['meta_key']   = '_sk_feed_like_count';
			$args['orderby']    = 'meta_value_num';
			$args['order']      = 'DESC';
		}

		$query = new \WP_Query( $args );

		ob_start();
		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				include SK_FEED_TEMPLATES . '/feed-card.php';
			}
			wp_reset_postdata();
		}
		$html = ob_get_clean();

		wp_send_json_success( [
			'html'     => $html,
			'has_more' => $page < $query->max_num_pages,
		] );
	}

	/**
	 * Get vendor IDs the current user follows.
	 */
	private static function get_following_vendor_ids( int $user_id ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'sk_follow_store_followers';

		if ( ! $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) ) {
			return [];
		}

		return $wpdb->get_col( $wpdb->prepare(
			"SELECT vendor_id FROM {$table} WHERE follower_id = %d AND unfollowed_at IS NULL",
			$user_id
		) );
	}

	public function add_comment() {
		check_ajax_referer( 'sk_feed', '_nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => __( 'Bitte anmelden.', 'sk-core' ) ] );
		}

		$post_id   = (int) ( $_POST['post_id'] ?? 0 );
		$parent_id = (int) ( $_POST['parent_id'] ?? 0 );
		$text      = sanitize_textarea_field( wp_unslash( $_POST['comment'] ?? '' ) );
		$post      = get_post( $post_id );

		if ( ! $post || $post->post_type !== PostType::POST_TYPE ) {
			wp_send_json_error( [ 'message' => __( 'Beitrag nicht gefunden.', 'sk-core' ) ] );
		}

		if ( empty( $text ) ) {
			wp_send_json_error( [ 'message' => __( 'Bitte Kommentar eingeben.', 'sk-core' ) ] );
		}

		$user = wp_get_current_user();

		// Use store name for vendors, display_name for others.
		$author_name = $user->display_name;
		if ( function_exists( 'sk_is_user_seller' ) && sk_is_user_seller( $user->ID ) ) {
			$store_info = function_exists( 'sk_get_store_info' ) ? sk_get_store_info( $user->ID ) : [];
			if ( ! empty( $store_info['store_name'] ) ) {
				$author_name = $store_info['store_name'];
			}
		}

		$comment_data = [
			'comment_post_ID'      => $post_id,
			'comment_content'      => $text,
			'comment_parent'       => $parent_id,
			'user_id'              => $user->ID,
			'comment_author'       => $author_name,
			'comment_author_email' => $user->user_email,
			'comment_approved'     => 1,
		];

		$comment_id = wp_insert_comment( $comment_data );

		if ( ! $comment_id ) {
			wp_send_json_error( [ 'message' => __( 'Kommentar konnte nicht gespeichert werden.', 'sk-core' ) ] );
		}

		$comment   = get_comment( $comment_id );
		$is_vendor = function_exists( 'sk_is_user_seller' ) && sk_is_user_seller( $user->ID );
		$time_ago  = human_time_diff( strtotime( $comment->comment_date ), current_time( 'timestamp' ) );

		ob_start();
		?>
		<div class="sk-feed-comment<?php echo $parent_id ? ' sk-feed-comment--reply' : ''; ?>" data-comment-id="<?php echo esc_attr( $comment_id ); ?>">
			<div class="sk-feed-comment-avatar"><?php echo get_avatar( $user->ID, 36 ); ?></div>
			<div class="sk-feed-comment-body">
				<div class="sk-feed-comment-header">
					<strong class="sk-feed-comment-author">
						<?php echo esc_html( $user->display_name ); ?>
						<?php if ( $is_vendor ) : ?>
							<span class="sk-feed-comment-badge"><?php esc_html_e( 'Verkäufer', 'sk-core' ); ?></span>
						<?php endif; ?>
					</strong>
					<span class="sk-feed-comment-time"><?php printf( esc_html__( 'vor %s', 'sk-core' ), $time_ago ); ?></span>
				</div>
				<div class="sk-feed-comment-text">
					<?php echo wp_kses_post( wpautop( $comment->comment_content ) ); ?>
				</div>
			</div>
		</div>
		<?php
		$html = ob_get_clean();

		wp_send_json_success( [
			'comment_id' => $comment_id,
			'html'       => $html,
			'count'      => (int) get_comments_number( $post_id ),
		] );
	}

	public function toggle_pin() {
		check_ajax_referer( 'sk_feed', '_nonce' );

		$post_id = (int) ( $_POST['post_id'] ?? 0 );
		$post    = get_post( $post_id );

		if ( ! $post || $post->post_type !== PostType::POST_TYPE ) {
			wp_send_json_error();
		}

		if ( (int) $post->post_author !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$is_pinned = (int) get_post_meta( $post_id, '_sk_feed_pinned', true );

		if ( $is_pinned ) {
			delete_post_meta( $post_id, '_sk_feed_pinned' );
		} else {
			// Unpin any previously pinned post by this vendor.
			$old_pinned = get_posts( [
				'post_type'   => PostType::POST_TYPE,
				'author'      => $post->post_author,
				'meta_key'    => '_sk_feed_pinned',
				'meta_value'  => '1',
				'fields'      => 'ids',
				'numberposts' => -1,
			] );
			foreach ( $old_pinned as $old_id ) {
				delete_post_meta( $old_id, '_sk_feed_pinned' );
			}
			update_post_meta( $post_id, '_sk_feed_pinned', '1' );
		}

		wp_send_json_success( [ 'pinned' => ! $is_pinned ] );
	}

	public function search_stores() {
		check_ajax_referer( 'sk_feed', '_nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error();
		}

		$term = sanitize_text_field( wp_unslash( $_GET['term'] ?? $_POST['term'] ?? '' ) );
		if ( strlen( $term ) < 1 ) {
			wp_send_json_success( [] );
		}

		$sellers = get_users( [
			'role__in'   => [ 'seller', 'administrator' ],
			'number'     => 8,
			'orderby'    => 'display_name',
			'order'      => 'ASC',
			'meta_query' => [
				[
					'key'     => 'sk_profile_settings',
					'compare' => 'EXISTS',
				],
			],
		] );

		$results = [];
		foreach ( $sellers as $user ) {
			$store_info = function_exists( 'sk_get_store_info' ) ? sk_get_store_info( $user->ID ) : [];
			$store_name = $store_info['store_name'] ?? $user->display_name;

			if ( mb_stripos( $store_name, $term ) === false ) {
				continue;
			}

			$store_url = function_exists( 'sk_get_store_url' ) ? sk_get_store_url( $user->ID ) : '';

			$results[] = [
				'id'     => $user->ID,
				'name'   => $store_name,
				'url'    => $store_url,
				'avatar' => get_avatar_url( $user->ID, [ 'size' => 32 ] ),
			];

			if ( count( $results ) >= 6 ) {
				break;
			}
		}

		wp_send_json_success( $results );
	}

	public function track_zap() {
		check_ajax_referer( 'sk_feed', '_nonce' );

		$post_id = (int) ( $_POST['post_id'] ?? 0 );
		$amount  = (int) ( $_POST['amount'] ?? 0 );

		if ( ! $post_id || ! $amount || $amount < 1 ) {
			wp_send_json_error();
		}

		$post = get_post( $post_id );
		if ( ! $post || $post->post_type !== PostType::POST_TYPE ) {
			wp_send_json_error();
		}

		// Atomic increment.
		$current = (int) get_post_meta( $post_id, '_sk_zap_total_sats', true );
		update_post_meta( $post_id, '_sk_zap_total_sats', $current + $amount );

		$new_total = $current + $amount;

		wp_send_json_success( [
			'total' => $new_total,
		] );
	}
}
