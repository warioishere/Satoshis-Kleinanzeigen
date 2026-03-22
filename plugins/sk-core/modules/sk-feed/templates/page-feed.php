<?php
/**
 * Community Feed — eigenständiges Template.
 * Three-column layout: Compose left, Feed center, Trending right.
 */

defined( 'ABSPATH' ) || exit;

$filter       = sanitize_text_field( $_GET['feed_filter'] ?? 'all' );
$is_logged_in = is_user_logged_in();
$query        = \SK\Modules\Feed\FeedPage::get_feed_query( $filter );

get_header();
?>

<div class="sk-feed-page">
	<div class="sk-feed-layout sk-feed-layout--three">

		<aside class="sk-feed-sidebar sk-feed-sidebar--left sk-feed-sidebar--feed">
			<?php if ( $is_logged_in && function_exists( 'sk_is_user_seller' ) && sk_is_user_seller( get_current_user_id() ) ) : ?>
				<?php include SK_FEED_TEMPLATES . '/sidebar-compose.php'; ?>
			<?php else : ?>
				<?php include SK_FEED_TEMPLATES . '/sidebar-cta.php'; ?>
			<?php endif; ?>
		</aside>

		<div class="sk-feed-main">
			<h1 class="sk-feed-page-title">Community</h1>
			<?php
			$vendor_id = 0;
			include SK_FEED_TEMPLATES . '/feed-loop.php';
			?>
		</div>

		<aside class="sk-feed-sidebar sk-feed-sidebar--feed">
			<?php include SK_FEED_TEMPLATES . '/sidebar-trending.php'; ?>
		</aside>

	</div>
</div>

<?php get_footer(); ?>
