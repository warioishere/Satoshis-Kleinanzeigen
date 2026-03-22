<?php
/**
 * Sidebar: Trending Posts (last 24h, sorted by likes + comments).
 */

defined( 'ABSPATH' ) || exit;

$trending = get_posts( [
	'post_type'      => \SK\Modules\Feed\PostType::POST_TYPE,
	'post_status'    => 'publish',
	'posts_per_page' => 5,
	'date_query'     => [
		[ 'after' => '24 hours ago' ],
	],
	'meta_key'       => '_sk_feed_like_count',
	'orderby'        => 'meta_value_num',
	'order'          => 'DESC',
] );

// If not enough trending in 24h, fill with recent.
if ( count( $trending ) < 3 ) {
	$trending = get_posts( [
		'post_type'      => \SK\Modules\Feed\PostType::POST_TYPE,
		'post_status'    => 'publish',
		'posts_per_page' => 5,
		'orderby'        => 'date',
		'order'          => 'DESC',
	] );
}

if ( empty( $trending ) ) {
	return;
}
?>

<div class="sk-feed-sidebar-card">
	<h3 class="sk-feed-sidebar-title"><i class="fas fa-fire"></i> Trending</h3>
	<div class="sk-feed-trending-list">
		<?php foreach ( $trending as $tp ) :
			$tv_id      = (int) $tp->post_author;
			$ts_info    = function_exists( 'sk_get_store_info' ) ? sk_get_store_info( $tv_id ) : [];
			$ts_name    = $ts_info['store_name'] ?? get_the_author_meta( 'display_name', $tv_id );
			$t_avatar   = get_avatar( $tv_id, 28 );
			$t_excerpt  = wp_trim_words( wp_strip_all_tags( $tp->post_content ), 12 );
			$t_likes    = (int) get_post_meta( $tp->ID, '_sk_feed_like_count', true );
			$t_comments = (int) get_comments_number( $tp->ID );
			$t_url      = home_url( '/community/post/' . $tp->ID . '/' );
		?>
			<a href="<?php echo esc_url( $t_url ); ?>" class="sk-feed-trending-item">
				<div class="sk-feed-trending-avatar"><?php echo $t_avatar; ?></div>
				<div class="sk-feed-trending-body">
					<span class="sk-feed-trending-name"><?php echo esc_html( $ts_name ); ?></span>
					<span class="sk-feed-trending-text"><?php echo esc_html( $t_excerpt ); ?></span>
					<span class="sk-feed-trending-stats">
						<?php if ( $t_likes ) : ?><i class="far fa-heart"></i> <?php echo $t_likes; ?><?php endif; ?>
						<?php if ( $t_comments ) : ?><i class="far fa-comment"></i> <?php echo $t_comments; ?><?php endif; ?>
					</span>
				</div>
			</a>
		<?php endforeach; ?>
	</div>
</div>

<?php
// Top Stores — vendors with most followers.
if ( function_exists( 'sk_follow_store_get_vendor_followers' ) ) :
	$sellers = get_users( [
		'role__in' => [ 'seller' ],
		'number'   => 20,
	] );

	$store_scores = [];
	foreach ( $sellers as $seller ) {
		$f = sk_follow_store_get_vendor_followers( $seller->ID );
		$count = ! empty( $f['customers'] ) ? count( $f['customers'] ) : 0;
		if ( $count > 0 ) {
			$store_scores[ $seller->ID ] = $count;
		}
	}
	arsort( $store_scores );
	$top_stores = array_slice( $store_scores, 0, 5, true );

	if ( ! empty( $top_stores ) ) :
?>
<div class="sk-feed-sidebar-card" style="margin-top: 16px;">
	<h3 class="sk-feed-sidebar-title"><i class="fas fa-crown"></i> <?php esc_html_e( 'Top Stores', 'sk-core' ); ?></h3>
	<div class="sk-feed-participants-list">
		<?php foreach ( $top_stores as $sid => $fcount ) :
			$s_info = function_exists( 'sk_get_store_info' ) ? sk_get_store_info( $sid ) : [];
			$s_name = $s_info['store_name'] ?? get_the_author_meta( 'display_name', $sid );
			$s_url  = function_exists( 'sk_get_store_url' ) ? sk_get_store_url( $sid ) : '#';
		?>
			<a href="<?php echo esc_url( $s_url ); ?>" class="sk-feed-participant">
				<div class="sk-feed-participant-avatar"><?php echo get_avatar( $sid, 32 ); ?></div>
				<div class="sk-feed-participant-info">
					<span class="sk-feed-participant-name"><?php echo esc_html( $s_name ); ?></span>
					<span class="sk-feed-trending-stats"><?php echo esc_html( $fcount ); ?> Follower</span>
				</div>
			</a>
		<?php endforeach; ?>
	</div>
</div>
<?php
	endif;
endif;
?>
