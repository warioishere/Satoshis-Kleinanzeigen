<?php
/**
 * Render feed comments recursively (threaded).
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'sk_feed_render_comments' ) ) {

	function sk_feed_render_comments( $comments, $post_id, $depth = 0 ) {
		foreach ( $comments as $comment ) :
			$author_id   = (int) $comment->user_id;
			$author_name = $comment->comment_author;
			$is_vendor   = $author_id && function_exists( 'sk_is_user_seller' ) && sk_is_user_seller( $author_id );

			if ( $is_vendor && function_exists( 'sk_get_store_info' ) ) {
				$s_info = sk_get_store_info( $author_id );
				if ( ! empty( $s_info['store_name'] ) ) {
					$author_name = $s_info['store_name'];
				}
			}

			$avatar      = get_avatar( $author_id ?: $comment->comment_author_email, 36 );
			$comment_ts  = (int) strtotime( $comment->comment_date );
			$time_ago    = human_time_diff( $comment_ts, time() );
			?>
			<div class="sk-feed-comment<?php echo $depth > 0 ? ' sk-feed-comment--reply' : ''; ?>" data-comment-id="<?php echo esc_attr( $comment->comment_ID ); ?>">
				<div class="sk-feed-comment-avatar"><?php echo $avatar; ?></div>
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
						<?php echo wp_kses_post( wpautop( $comment->comment_content ) ); ?>
					</div>
					<?php if ( is_user_logged_in() && $depth < 3 ) : ?>
						<button type="button" class="sk-feed-reply-btn" data-comment-id="<?php echo esc_attr( $comment->comment_ID ); ?>" data-post-id="<?php echo esc_attr( $post_id ); ?>">
							<i class="fas fa-reply"></i> <?php esc_html_e( 'Antworten', 'sk-core' ); ?>
						</button>
					<?php endif; ?>

					<div class="sk-feed-reply-form" style="display:none;" data-parent-id="<?php echo esc_attr( $comment->comment_ID ); ?>">
						<textarea class="sk-form-control sk-feed-reply-textarea" rows="2" placeholder="<?php esc_attr_e( 'Antwort schreiben…', 'sk-core' ); ?>"></textarea>
						<div class="sk-feed-reply-actions">
							<button type="button" class="sk-btn sk-btn-btc sk-feed-reply-submit" data-post-id="<?php echo esc_attr( $post_id ); ?>" data-parent-id="<?php echo esc_attr( $comment->comment_ID ); ?>"><?php esc_html_e( 'Antworten', 'sk-core' ); ?></button>
							<button type="button" class="sk-feed-reply-cancel"><?php esc_html_e( 'Abbrechen', 'sk-core' ); ?></button>
						</div>
					</div>

					<?php
					$children = $comment->get_children();
					if ( $children ) {
						sk_feed_render_comments( $children, $post_id, $depth + 1 );
					}
					?>
				</div>
			</div>
		<?php endforeach;
	}
}
