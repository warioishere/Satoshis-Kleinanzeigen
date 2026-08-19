<?php
/**
 * Sidebar: Participants in this conversation.
 * Shows the post author + all unique commenters.
 *
 * Expected: $post_id, $vendor_id set by page-single.php
 */

defined( 'ABSPATH' ) || exit;

// Collect unique participants: post author + commenters.
$participants = [];

// Post author first.
$participants[ $vendor_id ] = [
	'id'   => $vendor_id,
	'role' => 'author',
];

$all_comments = get_comments( [
	'post_id' => $post_id,
	'status'  => 'approve',
] );

foreach ( $all_comments as $c ) {
	$uid = (int) $c->user_id;
	if ( $uid && ! isset( $participants[ $uid ] ) ) {
		$participants[ $uid ] = [
			'id'   => $uid,
			'role' => 'commenter',
		];
	}
}

if ( empty( $participants ) ) {
	return;
}
?>

<div class="sk-feed-sidebar-card">
	<h3 class="sk-feed-sidebar-title"><i class="fas fa-users"></i> <?php esc_html_e( 'In dieser Unterhaltung', 'sk-core' ); ?></h3>
	<div class="sk-feed-participants-list">
		<?php foreach ( $participants as $p ) :
			$uid       = $p['id'];
			$is_vendor = function_exists( 'sk_is_user_seller' ) && sk_is_user_seller( $uid );
			$name      = '';

			if ( $is_vendor && function_exists( 'sk_get_store_info' ) ) {
				$s_info = sk_get_store_info( $uid );
				$name   = $s_info['store_name'] ?? '';
			}
			if ( ! $name ) {
				$u = get_userdata( $uid );
				$name = $u ? $u->display_name : '';
			}
			if ( ! $name ) {
				continue;
			}

			$link   = $is_vendor && function_exists( 'sk_get_store_url' ) ? sk_get_store_url( $uid ) : '#';
			$avatar = get_avatar( $uid, 32 );
		?>
			<a href="<?php echo esc_url( $link ); ?>" class="sk-feed-participant">
				<div class="sk-feed-participant-avatar"><?php echo $avatar; ?></div>
				<div class="sk-feed-participant-info">
					<span class="sk-feed-participant-name"><?php echo esc_html( $name ); ?></span>
					<?php if ( $p['role'] === 'author' ) : ?>
						<span class="sk-feed-participant-badge"><?php esc_html_e( 'Autor', 'sk-core' ); ?></span>
					<?php elseif ( $is_vendor ) : ?>
						<span class="sk-feed-participant-badge sk-feed-participant-badge--seller"><?php esc_html_e( 'Anbieter', 'sk-core' ); ?></span>
					<?php endif; ?>
				</div>
			</a>
		<?php endforeach; ?>
	</div>
</div>

<?php
// More from this store.
$more_posts = get_posts( [
	'post_type'      => \SK\Modules\Feed\PostType::POST_TYPE,
	'author'         => $vendor_id,
	'post_status'    => 'publish',
	'posts_per_page' => 3,
	'exclude'        => [ $post_id ],
	'orderby'        => 'date',
	'order'          => 'DESC',
] );

if ( ! empty( $more_posts ) ) :
	$s_info = function_exists( 'sk_get_store_info' ) ? sk_get_store_info( $vendor_id ) : [];
	$s_name = $s_info['store_name'] ?? get_the_author_meta( 'display_name', $vendor_id );
?>
<div class="sk-feed-sidebar-card" style="margin-top: 16px;">
	<h3 class="sk-feed-sidebar-title"><i class="fas fa-stream"></i> <?php printf( esc_html__( 'Mehr von %s', 'sk-core' ), esc_html( $s_name ) ); ?></h3>
	<div class="sk-feed-trending-list">
		<?php foreach ( $more_posts as $mp ) :
			$m_excerpt = wp_trim_words( wp_strip_all_tags( $mp->post_content ), 10 );
			$m_ts      = (int) get_post_time( 'U', false, $mp );
			$m_time    = human_time_diff( $m_ts, time() );
			$m_url     = home_url( '/community/post/' . $mp->ID . '/' );
		?>
			<a href="<?php echo esc_url( $m_url ); ?>" class="sk-feed-trending-item">
				<div class="sk-feed-trending-body">
					<span class="sk-feed-trending-text"><?php echo esc_html( $m_excerpt ); ?></span>
					<span class="sk-feed-trending-stats"><i class="far fa-clock"></i> <span class="sk-timeago" data-ts="<?php echo esc_attr( $m_ts ); ?>"><?php printf( esc_html__( 'vor %s', 'sk-core' ), $m_time ); ?></span></span>
				</div>
			</a>
		<?php endforeach; ?>
	</div>
</div>
<?php endif; ?>
