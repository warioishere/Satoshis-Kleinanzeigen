<?php
/**
 * Store Feed Tab — displays vendor posts on the store page.
 * Pattern follows store-reviews.php exactly.
 */

defined( 'ABSPATH' ) || exit;

$store_user   = sk()->vendor->get( get_query_var( 'author' ) );
$store_info   = $store_user->get_shop_info();
$map_location = $store_user->get_location();
$layout       = get_theme_mod( 'store_layout', 'left' );

get_header( 'shop' );
?>

<?php do_action( 'woocommerce_before_main_content' ); ?>

<div class="sk-store-wrap layout-<?php echo esc_attr( $layout ); ?>">

	<?php if ( 'left' === $layout ) { ?>
		<?php
		sk_get_template_part(
			'store', 'sidebar', [
				'store_user'   => $store_user,
				'store_info'   => $store_info,
				'map_location' => $map_location,
			]
		);
		?>
	<?php } ?>

	<div id="sk-primary" class="sk-single-store">
		<div id="sk-content" class="site-content store-review-wrap woocommerce" role="main">

			<?php sk_get_template_part( 'store-header' ); ?>

			<?php
			$vendor_id = $store_user->get_id();
			$query = new WP_Query( [
				'post_type'      => \SK\Modules\Feed\PostType::POST_TYPE,
				'post_status'    => 'publish',
				'author'         => $vendor_id,
				'posts_per_page' => 10,
				'paged'          => 1,
				'orderby'        => 'date',
				'order'          => 'DESC',
			] );

			// Show pinned post first.
			$pinned = get_posts( [
				'post_type'      => \SK\Modules\Feed\PostType::POST_TYPE,
				'author'         => $vendor_id,
				'post_status'    => 'publish',
				'meta_key'       => '_sk_feed_pinned',
				'meta_value'     => '1',
				'posts_per_page' => 1,
			] );
			if ( ! empty( $pinned ) ) {
				echo '<div class="sk-feed-pinned-label"><i class="fas fa-thumbtack"></i> ' . esc_html__( 'Angepinnter Beitrag', 'sk-core' ) . '</div>';
				$GLOBALS['post'] = $pinned[0];
				setup_postdata( $pinned[0] );
				include SK_FEED_TEMPLATES . '/feed-card.php';
				wp_reset_postdata();
			}

			$filter      = 'all';
			$is_logged_in = is_user_logged_in();

			include SK_FEED_TEMPLATES . '/feed-loop.php';
			?>

		</div>
	</div>

	<?php if ( 'right' === $layout ) { ?>
		<?php
		sk_get_template_part(
			'store', 'sidebar', [
				'store_user'   => $store_user,
				'store_info'   => $store_info,
				'map_location' => $map_location,
			]
		);
		?>
	<?php } ?>

</div>

<?php do_action( 'woocommerce_after_main_content' ); ?>

<?php get_footer( 'shop' ); ?>
