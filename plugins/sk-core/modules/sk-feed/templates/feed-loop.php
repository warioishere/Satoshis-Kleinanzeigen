<?php
/**
 * Feed loop with filter bar and load-more.
 *
 * Expected variables: $query (WP_Query), $filter (string), $is_logged_in (bool).
 * Optional: $vendor_id (int) — if set, hides the filter bar.
 */

defined( 'ABSPATH' ) || exit;

$filter      = $filter ?? 'all';
$is_logged_in = $is_logged_in ?? is_user_logged_in();
$vendor_id    = $vendor_id ?? 0;
$show_filters = ! $vendor_id;
?>

<div class="sk-feed-wrapper" data-vendor-id="<?php echo esc_attr( $vendor_id ); ?>">

	<?php if ( $show_filters ) : ?>
		<div class="sk-feed-filter-bar">
			<button type="button" class="sk-feed-filter-btn<?php echo 'all' === $filter ? ' active' : ''; ?>" data-filter="all">
				<?php esc_html_e( 'Alle', 'sk-core' ); ?>
			</button>
			<button type="button" class="sk-feed-filter-btn<?php echo 'trending' === $filter ? ' active' : ''; ?>" data-filter="trending">
				<i class="fas fa-fire"></i> <?php esc_html_e( 'Trending', 'sk-core' ); ?>
			</button>
			<?php if ( $is_logged_in ) : ?>
				<button type="button" class="sk-feed-filter-btn<?php echo 'following' === $filter ? ' active' : ''; ?>" data-filter="following">
					<?php esc_html_e( 'Meine Stores', 'sk-core' ); ?>
				</button>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="sk-feed-list" id="sk-feed-list">
		<?php if ( $query->have_posts() ) : ?>
			<?php
			while ( $query->have_posts() ) {
				$query->the_post();
				include SK_FEED_TEMPLATES . '/feed-card.php';
			}
			wp_reset_postdata();
			?>
		<?php else : ?>
			<div class="sk-feed-empty">
				<i class="fas fa-rss"></i>
				<p><?php esc_html_e( 'Noch keine Beiträge.', 'sk-core' ); ?></p>
			</div>
		<?php endif; ?>
	</div>

	<?php if ( $query->max_num_pages > 1 ) : ?>
		<div class="sk-feed-scroll-sentinel" data-page="2" data-max="<?php echo esc_attr( $query->max_num_pages ); ?>"></div>
	<?php endif; ?>

</div>
