<?php
/**
 * Single feed card.
 *
 * Available: $post (global), expects setup_postdata() called.
 */

defined( 'ABSPATH' ) || exit;

$post_id    = get_the_ID();
$vendor_id  = (int) get_the_author_meta( 'ID' );
$store_info = function_exists( 'sk_get_store_info' ) ? sk_get_store_info( $vendor_id ) : [];
$store_name = $store_info['store_name'] ?? get_the_author();
$store_url  = function_exists( 'sk_get_store_url' ) ? sk_get_store_url( $vendor_id ) : '#';
$avatar     = get_avatar( $vendor_id, 48 );
$content    = wp_kses_post( get_the_content() );
$thumb_url  = get_the_post_thumbnail_url( $post_id, 'large' );
if ( ! $thumb_url ) {
	$thumb_url = get_post_meta( $post_id, '_sk_feed_external_image', true );
}
$time_ago   = human_time_diff( get_the_time( 'U' ), current_time( 'timestamp' ) );
$like_count = \SK\Modules\Feed\Likes::get_count( $post_id );
$comments   = (int) get_comments_number( $post_id );
$user_liked = is_user_logged_in() ? \SK\Modules\Feed\Likes::has_liked( $post_id, get_current_user_id() ) : false;
$feed_type  = get_post_meta( $post_id, '_sk_feed_type', true );
$product_id = (int) get_post_meta( $post_id, '_sk_feed_product_id', true );
$gesuch_id  = (int) get_post_meta( $post_id, '_sk_feed_gesuch_id', true );
?>

<div class="sk-feed-card" data-post-id="<?php echo esc_attr( $post_id ); ?>">

	<div class="sk-feed-card-header">
		<a href="<?php echo esc_url( $store_url ); ?>" class="sk-feed-card-avatar">
			<?php echo $avatar; ?>
		</a>
		<div class="sk-feed-card-meta">
			<a href="<?php echo esc_url( $store_url ); ?>" class="sk-feed-card-name"><?php echo esc_html( $store_name ); ?></a>
			<span class="sk-feed-card-time">
				<?php if ( 'product_announce' === $feed_type ) : ?>
					<span class="sk-feed-type-badge"><i class="fas fa-tag"></i> <?php esc_html_e( 'Inserat', 'sk-core' ); ?></span> ·
				<?php elseif ( 'gesuch_announce' === $feed_type ) : ?>
					<span class="sk-feed-type-badge" style="color:#f59e0b;"><i class="fas fa-search"></i> <?php esc_html_e( 'Gesuch', 'sk-core' ); ?></span> ·
				<?php elseif ( 'nostr_import' === $feed_type ) : ?>
					<span class="sk-feed-type-badge" style="color:#8b5cf6;"><i class="fas fa-bolt"></i> <?php esc_html_e( 'via Nostr', 'sk-core' ); ?></span> ·
				<?php endif; ?>
				<?php printf( esc_html__( 'vor %s', 'sk-core' ), $time_ago ); ?>
			</span>
		</div>
		<?php
		// Follow button — only show if logged in and not own store.
		if ( is_user_logged_in() && get_current_user_id() !== $vendor_id && function_exists( 'sk_follow_store_is_following_store' ) ) :
			$is_following = sk_follow_store_is_following_store( $vendor_id, get_current_user_id() );
		?>
			<button type="button"
				class="sk-feed-follow-btn sk-follow-store-button<?php echo $is_following ? ' following' : ''; ?>"
				data-vendor-id="<?php echo esc_attr( $vendor_id ); ?>"
				data-status="<?php echo $is_following ? 'following' : ''; ?>"
				data-is-logged-in="<?php echo esc_attr( get_current_user_id() ); ?>">
				<span class="sk-follow-store-button-label-current"><?php echo $is_following ? esc_html__( 'Folge ich', 'sk-core' ) : esc_html__( 'Folgen', 'sk-core' ); ?></span>
				<span class="sk-follow-store-button-label-unfollow"><?php esc_html_e( 'Entfolgen', 'sk-core' ); ?></span>
			</button>
		<?php endif; ?>
	</div>

	<div class="sk-feed-card-content">
		<?php echo $content; ?>
	</div>

	<?php if ( $thumb_url ) : ?>
		<div class="sk-feed-card-image">
			<img src="<?php echo esc_url( $thumb_url ); ?>" alt="" loading="lazy" />
		</div>
	<?php endif; ?>

	<?php if ( 'gesuch_announce' === $feed_type && $gesuch_id && get_post_status( $gesuch_id ) === 'publish' ) : ?>
		<?php
		$gesuch_thumb   = get_the_post_thumbnail_url( $gesuch_id, 'thumbnail' );
		$gesuch_excerpt = wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $gesuch_id ) ), 20, '…' );
		?>
		<a href="<?php echo esc_url( get_permalink( $gesuch_id ) ); ?>" class="sk-feed-product-embed">
			<?php if ( $gesuch_thumb ) : ?>
				<img src="<?php echo esc_url( $gesuch_thumb ); ?>" alt="" class="sk-feed-product-embed-img" />
			<?php endif; ?>
			<div class="sk-feed-product-embed-info">
				<strong><?php echo esc_html( get_the_title( $gesuch_id ) ); ?></strong>
				<?php if ( $gesuch_excerpt ) : ?>
					<span class="sk-feed-product-embed-price"><?php echo esc_html( $gesuch_excerpt ); ?></span>
				<?php endif; ?>
			</div>
		</a>
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

	<div class="sk-feed-card-actions">
		<button type="button" class="sk-feed-like-btn<?php echo $user_liked ? ' active' : ''; ?>" data-post-id="<?php echo esc_attr( $post_id ); ?>">
			<i class="<?php echo $user_liked ? 'fas' : 'far'; ?> fa-heart"></i>
			<span class="sk-feed-like-count"><?php echo $like_count > 0 ? esc_html( $like_count ) : ''; ?></span>
		</button>

		<a href="<?php echo esc_url( home_url( '/community/post/' . $post_id . '/' ) ); ?>" class="sk-feed-comment-btn">
			<i class="far fa-comment"></i>
			<span><?php echo $comments > 0 ? esc_html( $comments ) : ''; ?></span>
		</a>

		<?php
		// Zap button (reuse sk-zaps if available).
		if ( class_exists( 'SK\Modules\Zaps\ZapButton' ) ) {
			$zap_data = \SK\Modules\Zaps\ZapButton::get_vendor_zap_data( $vendor_id );
			if ( $zap_data ) {
				\SK\Modules\Zaps\ZapButton::render_button( $zap_data, $post_id );
			}
		}
		?>

		<button type="button" class="sk-feed-share-btn" data-url="<?php echo esc_attr( home_url( '/community/post/' . $post_id . '/' ) ); ?>" title="<?php esc_attr_e( 'Teilen', 'sk-core' ); ?>">
			<i class="fas fa-share-alt"></i>
		</button>

		<?php if ( is_user_logged_in() && (int) get_current_user_id() !== $vendor_id ) : ?>
			<button type="button" class="sk-feed-report-btn" data-post-id="<?php echo esc_attr( $post_id ); ?>" title="<?php esc_attr_e( 'Melden', 'sk-core' ); ?>">
				<i class="far fa-flag"></i>
			</button>
		<?php endif; ?>
	</div>

</div>
