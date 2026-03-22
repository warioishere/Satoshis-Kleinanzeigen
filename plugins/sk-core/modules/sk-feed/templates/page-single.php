<?php
/**
 * Single Feed Post — eigenständiges Template.
 * Kein Kadence-Wrapper, kein Shortcode. Content 100% self-contained.
 */

defined( 'ABSPATH' ) || exit;

$post_id   = (int) get_query_var( 'sk_feed_post_id' );
$post      = get_post( $post_id );
$vendor_id = (int) $post->post_author;

$store_info = function_exists( 'sk_get_store_info' ) ? sk_get_store_info( $vendor_id ) : [];
$store_name = $store_info['store_name'] ?? get_the_author_meta( 'display_name', $vendor_id );
$store_url  = function_exists( 'sk_get_store_url' ) ? sk_get_store_url( $vendor_id ) : '#';
$avatar     = get_avatar( $vendor_id, 56 );
$content    = wp_kses_post( $post->post_content );
$thumb_url  = get_the_post_thumbnail_url( $post_id, 'large' );
$time_full  = get_the_date( 'j. F Y, H:i', $post ) . ' Uhr';
$like_count = \SK\Modules\Feed\Likes::get_count( $post_id );
$comments   = (int) get_comments_number( $post_id );
$user_liked = is_user_logged_in() ? \SK\Modules\Feed\Likes::has_liked( $post_id, get_current_user_id() ) : false;
$feed_type  = get_post_meta( $post_id, '_sk_feed_type', true );
$product_id = (int) get_post_meta( $post_id, '_sk_feed_product_id', true );

get_header();
?>

<div class="sk-feed-page">
	<div class="sk-feed-layout sk-feed-layout--three">

		<aside class="sk-feed-sidebar sk-feed-sidebar--left sk-feed-sidebar--single">
			<?php include SK_FEED_TEMPLATES . '/sidebar-store-card.php'; ?>
		</aside>

		<div class="sk-feed-main">

		<a href="<?php echo esc_url( home_url( '/community/' ) ); ?>" class="sk-feed-back-link">
			<i class="fas fa-arrow-left"></i> <?php esc_html_e( 'Zurück', 'sk-core' ); ?>
		</a>

		<div class="sk-feed-single-card" data-post-id="<?php echo esc_attr( $post_id ); ?>">

			<div class="sk-feed-single-header">
				<a href="<?php echo esc_url( $store_url ); ?>" class="sk-feed-single-avatar">
					<?php echo $avatar; ?>
				</a>
				<div class="sk-feed-single-meta">
					<a href="<?php echo esc_url( $store_url ); ?>" class="sk-feed-single-name"><?php echo esc_html( $store_name ); ?></a>
					<span class="sk-feed-single-time"><?php echo esc_html( $time_full ); ?></span>
				</div>
			</div>

			<div class="sk-feed-single-content">
				<?php echo $content; ?>
			</div>

			<?php if ( $thumb_url ) : ?>
				<div class="sk-feed-single-image">
					<img src="<?php echo esc_url( $thumb_url ); ?>" alt="" loading="lazy" />
				</div>
			<?php endif; ?>

			<?php if ( 'product_announce' === $feed_type && $product_id && get_post_status( $product_id ) === 'publish' ) : ?>
				<?php
				$product       = wc_get_product( $product_id );
				$product_thumb = get_the_post_thumbnail_url( $product_id, 'thumbnail' );
				$product_price = $product ? $product->get_price_html() : '';
				?>
				<a href="<?php echo esc_url( get_permalink( $product_id ) ); ?>" class="sk-feed-product-embed">
					<?php if ( $product_thumb ) : ?>
						<img src="<?php echo esc_url( $product_thumb ); ?>" alt="" class="sk-feed-product-embed-img" />
					<?php endif; ?>
					<div class="sk-feed-product-embed-info">
						<strong><?php echo esc_html( get_the_title( $product_id ) ); ?></strong>
						<?php if ( $product_price ) : ?>
							<span class="sk-feed-product-embed-price"><?php echo $product_price; ?></span>
						<?php endif; ?>
					</div>
				</a>
			<?php endif; ?>

			<div class="sk-feed-single-stats">
				<?php if ( $like_count ) : ?>
					<span><strong><?php echo esc_html( $like_count ); ?></strong> <?php echo $like_count === 1 ? 'Like' : 'Likes'; ?></span>
				<?php endif; ?>
				<?php if ( $comments ) : ?>
					<span class="sk-feed-comment-count-stat"><strong><?php echo esc_html( $comments ); ?></strong> <?php echo $comments === 1 ? 'Kommentar' : 'Kommentare'; ?></span>
				<?php endif; ?>
			</div>

			<div class="sk-feed-card-actions">
				<button type="button" class="sk-feed-like-btn<?php echo $user_liked ? ' active' : ''; ?>" data-post-id="<?php echo esc_attr( $post_id ); ?>">
					<i class="<?php echo $user_liked ? 'fas' : 'far'; ?> fa-heart"></i>
					<span><?php esc_html_e( 'Like', 'sk-core' ); ?></span>
				</button>

				<button type="button" class="sk-feed-comment-btn sk-feed-scroll-to-comments">
					<i class="far fa-comment"></i>
					<span><?php esc_html_e( 'Kommentieren', 'sk-core' ); ?></span>
				</button>

				<?php
				if ( class_exists( 'SK\Modules\Zaps\ZapButton' ) ) {
					$zap_data = \SK\Modules\Zaps\ZapButton::get_vendor_zap_data( $vendor_id );
					if ( $zap_data ) {
						\SK\Modules\Zaps\ZapButton::render_button( $zap_data, $post_id );
					}
				}
				?>

				<button type="button" class="sk-feed-share-btn" data-url="<?php echo esc_attr( home_url( '/community/post/' . $post_id . '/' ) ); ?>" title="<?php esc_attr_e( 'Teilen', 'sk-core' ); ?>">
						<i class="fas fa-share-alt"></i> <span><?php esc_html_e( 'Teilen', 'sk-core' ); ?></span>
					</button>

				<?php if ( is_user_logged_in() && get_current_user_id() !== $vendor_id ) : ?>
					<button type="button" class="sk-feed-report-btn" data-post-id="<?php echo esc_attr( $post_id ); ?>">
						<i class="far fa-flag"></i>
					</button>
				<?php endif; ?>
			</div>
		</div>

		<!-- Comments -->
		<div class="sk-feed-comments" id="sk-feed-comments">

			<?php if ( is_user_logged_in() ) : ?>
				<div class="sk-feed-comment-form">
					<div class="sk-feed-comment-form-avatar">
						<?php echo get_avatar( get_current_user_id(), 40 ); ?>
					</div>
					<div class="sk-feed-comment-form-input">
						<textarea id="sk-feed-comment-text" class="sk-form-control" rows="2" placeholder="<?php esc_attr_e( 'Kommentar schreiben…', 'sk-core' ); ?>"></textarea>
						<button type="button" class="sk-btn sk-btn-btc sk-feed-comment-submit" data-post-id="<?php echo esc_attr( $post_id ); ?>">
							<?php esc_html_e( 'Antworten', 'sk-core' ); ?>
						</button>
					</div>
				</div>
			<?php else : ?>
				<p class="sk-feed-comment-login">
					<a href="<?php echo esc_url( home_url( '/dashboard/' ) ); ?>"><?php esc_html_e( 'Anmelden um zu kommentieren', 'sk-core' ); ?></a>
				</p>
			<?php endif; ?>

			<div class="sk-feed-comment-list" id="sk-feed-comment-list">
				<?php
				require_once SK_FEED_TEMPLATES . '/comment-thread.php';

				$comment_list = get_comments( [
					'post_id'      => $post_id,
					'status'       => 'approve',
					'orderby'      => 'comment_date',
					'order'        => 'ASC',
					'hierarchical' => 'threaded',
				] );

				if ( $comment_list ) {
					sk_feed_render_comments( $comment_list, $post_id );
				} else {
					echo '<p class="sk-feed-no-comments">' . esc_html__( 'Noch keine Kommentare. Sei der Erste!', 'sk-core' ) . '</p>';
				}
				?>
			</div>
		</div>

		</div>

		<aside class="sk-feed-sidebar sk-feed-sidebar--single">
			<?php include SK_FEED_TEMPLATES . '/sidebar-participants.php'; ?>
		</aside>

	</div>
</div>

<?php get_footer(); ?>
