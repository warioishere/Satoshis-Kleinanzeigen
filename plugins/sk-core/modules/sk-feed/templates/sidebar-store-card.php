<?php
/**
 * Sidebar: Store card — compact store info for the post author.
 *
 * Expected: $vendor_id set by page-single.php
 */

defined( 'ABSPATH' ) || exit;

$store_info   = function_exists( 'sk_get_store_info' ) ? sk_get_store_info( $vendor_id ) : [];
$store_name   = $store_info['store_name'] ?? get_the_author_meta( 'display_name', $vendor_id );
$store_url    = function_exists( 'sk_get_store_url' ) ? sk_get_store_url( $vendor_id ) : '#';
$avatar       = get_avatar( $vendor_id, 64 );
$banner_id    = $store_info['banner'] ?? 0;
$banner_url   = $banner_id ? wp_get_attachment_url( $banner_id ) : '';

// Follower count.
$follower_count = 0;
if ( function_exists( 'sk_follow_store_get_vendor_followers' ) ) {
	$followers      = sk_follow_store_get_vendor_followers( $vendor_id );
	$follower_count = ! empty( $followers['customers'] ) ? count( $followers['customers'] ) : 0;
}

// Product count.
$product_count = count_user_posts( $vendor_id, 'product' );

// Post count.
$post_count = (int) get_posts( [
	'post_type'   => \SK\Modules\Feed\PostType::POST_TYPE,
	'author'      => $vendor_id,
	'post_status' => 'publish',
	'fields'      => 'ids',
	'numberposts' => -1,
] );
$post_count = count( get_posts( [
	'post_type'      => \SK\Modules\Feed\PostType::POST_TYPE,
	'author'         => $vendor_id,
	'post_status'    => 'publish',
	'fields'         => 'ids',
	'posts_per_page' => -1,
] ) );

// Is following?
$is_following = false;
if ( is_user_logged_in() && function_exists( 'sk_follow_store_is_following_store' ) ) {
	$is_following = sk_follow_store_is_following_store( $vendor_id, get_current_user_id() );
}
?>

<div class="sk-feed-sidebar-card sk-feed-store-card">
	<?php if ( $banner_url ) : ?>
		<div class="sk-feed-store-card-banner">
			<img src="<?php echo esc_url( $banner_url ); ?>" alt="" />
		</div>
	<?php endif; ?>

	<div class="sk-feed-store-card-header">
		<a href="<?php echo esc_url( $store_url ); ?>" class="sk-feed-store-card-avatar">
			<?php echo $avatar; ?>
		</a>
		<a href="<?php echo esc_url( $store_url ); ?>" class="sk-feed-store-card-name"><?php echo esc_html( $store_name ); ?></a>
	</div>

	<div class="sk-feed-store-card-stats">
		<div class="sk-feed-store-card-stat">
			<strong><?php echo esc_html( $product_count ); ?></strong>
			<span><?php esc_html_e( 'Inserate', 'sk-core' ); ?></span>
		</div>
		<div class="sk-feed-store-card-stat">
			<strong><?php echo esc_html( $post_count ); ?></strong>
			<span><?php esc_html_e( 'Beiträge', 'sk-core' ); ?></span>
		</div>
		<div class="sk-feed-store-card-stat">
			<strong><?php echo esc_html( $follower_count ); ?></strong>
			<span><?php esc_html_e( 'Follower', 'sk-core' ); ?></span>
		</div>
	</div>

	<div class="sk-feed-store-card-actions">
		<a href="<?php echo esc_url( $store_url ); ?>" class="sk-feed-store-card-btn">
			<i class="fas fa-store"></i> <?php esc_html_e( 'Store besuchen', 'sk-core' ); ?>
		</a>
		<?php if ( is_user_logged_in() && get_current_user_id() !== $vendor_id && function_exists( 'sk_follow_store_is_following_store' ) ) : ?>
			<button type="button"
				class="sk-feed-store-card-btn sk-follow-store-button<?php echo $is_following ? ' following' : ''; ?>"
				data-vendor-id="<?php echo esc_attr( $vendor_id ); ?>"
				data-status="<?php echo $is_following ? 'following' : ''; ?>"
				data-is-logged-in="<?php echo esc_attr( get_current_user_id() ); ?>">
				<span class="sk-follow-store-button-label-current"><i class="fas fa-heart"></i> <?php echo $is_following ? esc_html__( 'Folge ich', 'sk-core' ) : esc_html__( 'Folgen', 'sk-core' ); ?></span>
				<span class="sk-follow-store-button-label-unfollow"><i class="fas fa-heart-broken"></i> <?php esc_html_e( 'Entfolgen', 'sk-core' ); ?></span>
			</button>
		<?php endif; ?>
	</div>
</div>
