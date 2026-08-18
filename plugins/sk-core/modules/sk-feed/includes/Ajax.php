<?php

namespace SK\Modules\Feed;

defined( 'ABSPATH' ) || exit;

class Ajax {

	public function __construct() {
		add_action( 'wp_ajax_sk_feed_create_post', [ $this, 'create_post' ] );
		add_action( 'wp_ajax_sk_feed_edit_post', [ $this, 'edit_post' ] );
		add_action( 'wp_ajax_sk_feed_delete_post', [ $this, 'delete_post' ] );
		add_action( 'wp_ajax_sk_feed_toggle_like', [ $this, 'toggle_like' ] );
		add_action( 'wp_ajax_sk_feed_get_likers', [ $this, 'get_likers' ] );
		add_action( 'wp_ajax_nopriv_sk_feed_get_likers', [ $this, 'get_likers' ] );
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
		$image_error = '';
		if ( ! empty( $_FILES['image'] ) && ! empty( $_FILES['image']['tmp_name'] ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';

			$attach_id = media_handle_upload( 'image', $post_id );

			if ( is_wp_error( $attach_id ) ) {
				$image_error = $attach_id->get_error_message();
			} else {
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

		wp_send_json_success( [ 'post_id' => $post_id, 'html' => $html, 'image_error' => $image_error ] );
	}

	/**
	 * Drop a feed post's image.
	 *
	 * Announcement posts reuse the product's own attachment, so the file is
	 * only deleted when it was uploaded for this post. Otherwise it is merely
	 * detached and the product keeps its image.
	 */
	private static function release_thumbnail( int $post_id ): void {
		$thumb_id = (int) get_post_thumbnail_id( $post_id );

		delete_post_thumbnail( $post_id );

		if ( $thumb_id && (int) wp_get_post_parent_id( $thumb_id ) === $post_id ) {
			wp_delete_attachment( $thumb_id, true );
		}
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
		$image_error  = '';
		$remove_image = isset( $_POST['remove_image'] ) && $_POST['remove_image'] === '1';
		if ( $remove_image ) {
			self::release_thumbnail( $post_id );
		}

		// Upload new image.
		if ( ! $remove_image && ! empty( $_FILES['image'] ) && ! empty( $_FILES['image']['tmp_name'] ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';

			$attach_id = media_handle_upload( 'image', $post_id );

			if ( is_wp_error( $attach_id ) ) {
				$image_error = $attach_id->get_error_message();
			} else {
				self::release_thumbnail( $post_id );
				set_post_thumbnail( $post_id, $attach_id );
			}
		}

		$thumb_url = get_the_post_thumbnail_url( $post_id, 'thumbnail' );

		// Nostr: republish Kind 1 with updated content.
		$nostr_user_id = get_current_user_id();
		$nostr_edit_id = $post_id;
		register_shutdown_function( function () use ( $nostr_edit_id, $nostr_user_id ) {
			if ( ! class_exists( 'SK\Modules\Auth\NostrIdentity' ) || ! \SK\Modules\Auth\NostrIdentity::has_identity( $nostr_user_id ) ) {
				return;
			}
			$post = get_post( $nostr_edit_id );
			if ( ! $post ) return;

			$content   = wp_strip_all_tags( $post->post_content );
			$permalink = home_url( '/community/post/' . $nostr_edit_id . '/' );
			$tags      = [ [ 'r', $permalink ] ];

			if ( preg_match_all( '/#([A-Za-z0-9À-ÿ_]{2,30})/', $content, $m ) ) {
				foreach ( $m[1] as $t ) $tags[] = [ 't', strtolower( $t ) ];
			}

			$event_id = \SK\Modules\Auth\NostrIdentity::publish( $nostr_user_id, 1, $content, $tags );
			if ( $event_id ) {
				update_post_meta( $nostr_edit_id, '_sk_nostr_event_id', $event_id );
			}
		} );

		wp_send_json_success( [
			'content'     => wp_kses_post( $content ),
			'thumb_url'   => $thumb_url ?: '',
			'image_error' => $image_error,
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

		// NIP-09: Delete the Nostr event before deleting the WP post.
		// Only the author can sign a valid deletion, an admin removing someone
		// else's post would produce an event every relay rejects.
		$nostr_event_id = get_post_meta( $post_id, '_sk_nostr_event_id', true );
		$nostr_user_id  = (int) $post->post_author;

		wp_delete_post( $post_id, true );

		if ( $nostr_user_id === get_current_user_id() && $nostr_event_id && class_exists( 'SK\Modules\Auth\NostrIdentity' ) && \SK\Modules\Auth\NostrIdentity::has_identity( $nostr_user_id ) ) {
			register_shutdown_function( function () use ( $nostr_event_id, $nostr_user_id ) {
				\SK\Modules\Auth\NostrIdentity::publish( $nostr_user_id, 5, '', [ [ 'e', $nostr_event_id ] ] );
			} );
		}

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

		$user_id = get_current_user_id();
		$liked   = Likes::toggle( $post_id, $user_id );

		// NIP-25 + NIP-09: Publish/delete Kind 7 Reaction on Nostr.
		$nostr_event_id = get_post_meta( $post_id, '_sk_nostr_event_id', true );
		if ( $nostr_event_id && class_exists( 'SK\Modules\Auth\NostrIdentity' ) && \SK\Modules\Auth\NostrIdentity::has_identity( $user_id ) ) {
			if ( $liked ) {
				$author_pubkey = \SK\Modules\Auth\NostrIdentity::get_public_key( (int) $post->post_author );
				$tags = [ [ 'e', $nostr_event_id ] ];
				if ( $author_pubkey ) {
					$tags[] = [ 'p', $author_pubkey ];
				}
				register_shutdown_function( function () use ( $user_id, $post_id, $tags ) {
					$reaction_id = \SK\Modules\Auth\NostrIdentity::publish( $user_id, 7, '+', $tags );
					if ( $reaction_id ) {
						update_user_meta( $user_id, '_sk_nostr_reaction_' . $post_id, $reaction_id );
					}
				} );
			} else {
				// Unlike: delete the reaction event.
				$reaction_id = get_user_meta( $user_id, '_sk_nostr_reaction_' . $post_id, true );
				if ( $reaction_id ) {
					register_shutdown_function( function () use ( $user_id, $reaction_id, $post_id ) {
						\SK\Modules\Auth\NostrIdentity::publish( $user_id, 5, '', [ [ 'e', $reaction_id ] ] );
						delete_user_meta( $user_id, '_sk_nostr_reaction_' . $post_id );
					} );
				}
			}
		}

		wp_send_json_success( [
			'liked' => $liked,
			'count' => Likes::get_count( $post_id ),
		] );
	}

	public function get_likers() {
		check_ajax_referer( 'sk_feed', '_nonce' );

		global $wpdb;
		$post_id = (int) ( $_POST['post_id'] ?? 0 );
		if ( ! $post_id ) {
			wp_send_json_error();
		}

		$table = $wpdb->prefix . 'sk_feed_likes';
		// Most-recent likers first, cap at 100 for UI. If more, we just show the most recent.
		$user_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT user_id FROM {$table} WHERE post_id = %d ORDER BY created_at DESC LIMIT 100",
			$post_id
		) );

		$users = [];
		foreach ( $user_ids as $uid ) {
			$uid = (int) $uid;
			$store_info = function_exists( 'sk_get_store_info' ) ? sk_get_store_info( $uid ) : [];
			$store_name = ! empty( $store_info['store_name'] )
				? $store_info['store_name']
				: ( get_user_by( 'ID', $uid )->display_name ?? '' );
			$store_url  = function_exists( 'sk_get_store_url' ) ? sk_get_store_url( $uid ) : '';
			$avatar     = get_avatar_url( $uid, [ 'size' => 48 ] );
			$users[]    = [
				'id'     => $uid,
				'name'   => $store_name,
				'url'    => $store_url,
				'avatar' => $avatar,
			];
		}

		wp_send_json_success( [ 'users' => $users ] );
	}

	public function report_post() {
		check_ajax_referer( 'sk_feed', '_nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => __( 'Bitte anmelden.', 'sk-core' ) ] );
		}

		$post_id = (int) ( $_POST['post_id'] ?? 0 );
		// The reason column is VARCHAR(255), cut it here so the insert cannot fail.
		$reason  = mb_substr( sanitize_text_field( wp_unslash( $_POST['reason'] ?? '' ) ), 0, 255 );
		$post    = get_post( $post_id );

		if ( ! $post || $post->post_type !== PostType::POST_TYPE ) {
			wp_send_json_error( [ 'message' => __( 'Beitrag nicht gefunden.', 'sk-core' ) ] );
		}

		$user_id = get_current_user_id();
		$added   = Reports::add( $post_id, $user_id, $reason );

		if ( ! $added ) {
			wp_send_json_error( [ 'message' => __( 'Bereits gemeldet.', 'sk-core' ) ] );
		}

		// NIP-56: Publish Kind 1984 Report on Nostr.
		$nostr_event_id = get_post_meta( $post_id, '_sk_nostr_event_id', true );
		if ( $nostr_event_id && class_exists( 'SK\Modules\Auth\NostrIdentity' ) && \SK\Modules\Auth\NostrIdentity::has_identity( $user_id ) ) {
			$author_pubkey = \SK\Modules\Auth\NostrIdentity::get_public_key( (int) $post->post_author );
			$report_type = 'other';
			$reason_lower = strtolower( $reason );
			if ( preg_match( '/spam|werbung|junk/', $reason_lower ) ) {
				$report_type = 'spam';
			} elseif ( preg_match( '/scam|betrug|illegal|abzocke|diebstahl|stolen/', $reason_lower ) ) {
				$report_type = 'illegal';
			} elseif ( preg_match( '/nackt|nude|nudity|porn|nsfw|sexu/', $reason_lower ) ) {
				$report_type = 'nudity';
			} elseif ( preg_match( '/malware|virus|trojan|phishing|hack/', $reason_lower ) ) {
				$report_type = 'malware';
			} elseif ( preg_match( '/beleidigung|profanity|hate|hass|rassist|beschimpf/', $reason_lower ) ) {
				$report_type = 'profanity';
			} elseif ( preg_match( '/fake|impersonat|identität|ausgeben|fälsch/', $reason_lower ) ) {
				$report_type = 'impersonation';
			}
			$tags = [
				[ 'e', $nostr_event_id, $report_type ],
			];
			if ( $author_pubkey ) {
				$tags[] = [ 'p', $author_pubkey, $report_type ];
			}
			register_shutdown_function( function () use ( $user_id, $reason, $tags ) {
				\SK\Modules\Auth\NostrIdentity::publish( $user_id, 1984, $reason, $tags );
			} );
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

		// "Gesuche" — search request announcements.
		if ( 'gesuche' === $filter ) {
			$args['meta_query'] = [
				[ 'key' => '_sk_feed_type', 'value' => 'gesuch_announce' ],
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

		if ( mb_strlen( $text ) > 2000 ) {
			wp_send_json_error( [ 'message' => __( 'Maximal 2000 Zeichen.', 'sk-core' ) ] );
		}

		$user = wp_get_current_user();

		// A reply must hang off a comment of this very post.
		if ( $parent_id ) {
			$parent = get_comment( $parent_id );

			if ( ! $parent || (int) $parent->comment_post_ID !== $post_id ) {
				wp_send_json_error( [ 'message' => __( 'Kommentar nicht gefunden.', 'sk-core' ) ] );
			}
		}

		if ( ! self::comment_rate_allows( $user->ID ) ) {
			wp_send_json_error( [ 'message' => __( 'Bitte kurz warten, bevor du wieder kommentierst.', 'sk-core' ) ] );
		}

		if ( self::is_duplicate_comment( $post_id, $user->ID, $text ) ) {
			wp_send_json_error( [ 'message' => __( 'Das hast du gerade schon geschrieben.', 'sk-core' ) ] );
		}

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
			'comment_author_IP'    => function_exists( 'sk_get_client_ip' ) ? sk_get_client_ip() : '',
			'comment_agent'        => substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ), 0, 254 ),
			'comment_type'         => 'comment',
			'comment_approved'     => 1,
		];

		$comment_id = wp_insert_comment( $comment_data );

		if ( ! $comment_id ) {
			wp_send_json_error( [ 'message' => __( 'Kommentar konnte nicht gespeichert werden.', 'sk-core' ) ] );
		}

		// Nostr: Publish comment as Kind 1 reply.
		$nostr_event_id = get_post_meta( $post_id, '_sk_nostr_event_id', true );
		if ( $nostr_event_id && class_exists( 'SK\Modules\Auth\NostrIdentity' ) && \SK\Modules\Auth\NostrIdentity::has_identity( $user->ID ) ) {
			$author_pubkey = \SK\Modules\Auth\NostrIdentity::get_public_key( (int) $post->post_author );
			$reply_tags = [
				[ 'e', $nostr_event_id, '', 'root' ],
			];
			if ( $author_pubkey ) {
				$reply_tags[] = [ 'p', $author_pubkey ];
			}
			$nostr_comment_user = $user->ID;
			$nostr_comment_text = $text;
			register_shutdown_function( function () use ( $nostr_comment_user, $nostr_comment_text, $reply_tags ) {
				\SK\Modules\Auth\NostrIdentity::publish( $nostr_comment_user, 1, $nostr_comment_text, $reply_tags );
			} );
		}

		$comment    = get_comment( $comment_id );
		$is_vendor  = function_exists( 'sk_is_user_seller' ) && sk_is_user_seller( $user->ID );
		$comment_ts = (int) strtotime( $comment->comment_date );
		$time_ago   = human_time_diff( $comment_ts, time() );

		ob_start();
		?>
		<div class="sk-feed-comment<?php echo $parent_id ? ' sk-feed-comment--reply' : ''; ?>" data-comment-id="<?php echo esc_attr( $comment_id ); ?>">
			<div class="sk-feed-comment-avatar"><?php echo get_avatar( $user->ID, 36 ); ?></div>
			<div class="sk-feed-comment-body">
				<div class="sk-feed-comment-header">
					<strong class="sk-feed-comment-author">
						<?php echo esc_html( $author_name ); ?>
						<?php if ( $is_vendor ) : ?>
							<span class="sk-feed-comment-badge"><?php esc_html_e( 'Verkäufer', 'sk-core' ); ?></span>
						<?php endif; ?>
					</strong>
					<span class="sk-feed-comment-time sk-timeago" data-ts="<?php echo esc_attr( $comment_ts ); ?>"><?php printf( esc_html__( 'vor %s', 'sk-core' ), $time_ago ); ?></span>
				</div>
				<div class="sk-feed-comment-text">
					<?php echo FeedPage::render_content( $comment->comment_content ); ?>
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

	/**
	 * At most one comment every 15 seconds and 10 per 10 minutes per user.
	 */
	private static function comment_rate_allows( int $user_id ): bool {
		global $wpdb;

		$sql = "SELECT COUNT(*) FROM {$wpdb->comments} c
		        INNER JOIN {$wpdb->posts} p ON p.ID = c.comment_post_ID
		        WHERE c.user_id = %d AND p.post_type = %s AND c.comment_date_gmt > %s";

		$recent = (int) $wpdb->get_var( $wpdb->prepare(
			$sql,
			$user_id,
			PostType::POST_TYPE,
			gmdate( 'Y-m-d H:i:s', time() - 15 )
		) );

		if ( $recent > 0 ) {
			return false;
		}

		$burst = (int) $wpdb->get_var( $wpdb->prepare(
			$sql,
			$user_id,
			PostType::POST_TYPE,
			gmdate( 'Y-m-d H:i:s', time() - 600 )
		) );

		return $burst < 10;
	}

	/**
	 * Same user posting the same text on the same post within five minutes.
	 */
	private static function is_duplicate_comment( int $post_id, int $user_id, string $text ): bool {
		global $wpdb;

		return (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT 1 FROM {$wpdb->comments}
			 WHERE comment_post_ID = %d AND user_id = %d AND comment_content = %s
			   AND comment_date_gmt > %s
			 LIMIT 1",
			$post_id,
			$user_id,
			$text,
			gmdate( 'Y-m-d H:i:s', time() - 300 )
		) );
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

		// Match in the database, not on a fixed slice of users — otherwise the
		// search only ever sees the first handful of vendors.
		$sellers = get_users( [
			'role__in'   => [ 'seller', 'administrator' ],
			'number'     => 6,
			'orderby'    => 'meta_value',
			'meta_key'   => 'sk_store_name',
			'order'      => 'ASC',
			'meta_query' => [
				[
					'key'     => 'sk_store_name',
					'value'   => $term,
					'compare' => 'LIKE',
				],
			],
		] );

		$results = [];
		foreach ( $sellers as $user ) {
			$store_info = function_exists( 'sk_get_store_info' ) ? sk_get_store_info( $user->ID ) : [];
			$store_name = ! empty( $store_info['store_name'] ) ? $store_info['store_name'] : $user->display_name;
			$store_url  = function_exists( 'sk_get_store_url' ) ? sk_get_store_url( $user->ID ) : '';

			$results[] = [
				'id'     => $user->ID,
				'name'   => $store_name,
				'url'    => $store_url,
				'avatar' => get_avatar_url( $user->ID, [ 'size' => 32 ] ),
			];
		}

		wp_send_json_success( $results );
	}

	/**
	 * Count a zap against a feed post.
	 *
	 * The amount is never taken from the request. The caller supplies the
	 * payment hash of the invoice, we look it up on the author's wallet and
	 * read both the settled state and the amount from there. Each invoice is
	 * counted once.
	 */
	public function track_zap() {
		check_ajax_referer( 'sk_feed', '_nonce' );

		$post_id      = (int) ( $_POST['post_id'] ?? 0 );
		$payment_hash = strtolower( sanitize_text_field( wp_unslash( $_POST['payment_hash'] ?? '' ) ) );

		if ( ! $post_id || ! preg_match( '/^[0-9a-f]{64}$/', $payment_hash ) ) {
			wp_send_json_error( [ 'message' => __( 'Zahlung nicht nachweisbar.', 'sk-core' ) ] );
		}

		$post = get_post( $post_id );
		if ( ! $post || $post->post_type !== PostType::POST_TYPE ) {
			wp_send_json_error();
		}

		// One meta row per invoice, so a replayed request cannot count twice.
		if ( ! add_post_meta( $post_id, '_sk_zap_hash_' . $payment_hash, 0, true ) ) {
			wp_send_json_success( [ 'total' => (int) get_post_meta( $post_id, '_sk_zap_total_sats', true ) ] );
		}

		// Every accepted call costs the author's wallet a lookup, so cap how
		// often one user can trigger that.
		$budget_key = 'sk_feed_zapchk_' . get_current_user_id();
		$lookups    = (int) get_transient( $budget_key );

		if ( $lookups >= 10 ) {
			delete_post_meta( $post_id, '_sk_zap_hash_' . $payment_hash );
			wp_send_json_error( [ 'message' => __( 'Zu viele Anfragen.', 'sk-core' ) ] );
		}

		set_transient( $budget_key, $lookups + 1, MINUTE_IN_SECONDS );

		$amount = self::verify_settled_zap( (int) $post->post_author, $payment_hash );

		if ( $amount < 1 ) {
			delete_post_meta( $post_id, '_sk_zap_hash_' . $payment_hash );
			wp_send_json_error( [ 'message' => __( 'Zahlung nicht bestätigt.', 'sk-core' ) ] );
		}

		update_post_meta( $post_id, '_sk_zap_hash_' . $payment_hash, $amount );

		$total = (int) get_post_meta( $post_id, '_sk_zap_total_sats', true ) + $amount;
		update_post_meta( $post_id, '_sk_zap_total_sats', $total );

		wp_send_json_success( [ 'total' => $total ] );
	}

	/**
	 * Look the invoice up on the vendor's wallet. Returns the paid amount in
	 * sats, or 0 when it is unsettled or cannot be checked.
	 */
	private static function verify_settled_zap( int $vendor_id, string $payment_hash ): int {
		if ( ! $vendor_id || ! class_exists( 'SK\Modules\Payments\StoreSettings' ) ) {
			return 0;
		}

		$client = \SK\Modules\Payments\StoreSettings::get_nwc_client( $vendor_id );

		if ( ! $client ) {
			$client = \SK\Modules\Payments\StoreSettings::get_lndhub_client( $vendor_id );
		}

		if ( ! $client ) {
			return 0;
		}

		$result = $client->lookup_invoice( $payment_hash );

		if ( is_wp_error( $result ) || empty( $result['settled'] ) ) {
			return 0;
		}

		return max( 0, (int) ( $result['amount_sats'] ?? 0 ) );
	}
}
