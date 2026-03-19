<?php
/**
 * The Template for displaying all single posts.
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

$store_user   = sk()->vendor->get( get_query_var( 'author' ) );
$store_info   = $store_user->get_shop_info();
$map_location = $store_user->get_location();
$layout       = get_theme_mod( 'store_layout', 'left' );

get_header( 'shop' );

if ( function_exists( 'yoast_breadcrumb' ) ) {
    yoast_breadcrumb( '<p id="breadcrumbs">', '</p>' );
}
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
        <div id="sk-content" class="store-page-wrap woocommerce" role="main">

            <?php sk_get_template_part( 'store-header' ); ?>

            <?php do_action( 'sk_store_profile_frame_after', $store_user->data, $store_info ); ?>

            <?php if ( have_posts() ) { ?>

                <div class="seller-items">

                    <?php woocommerce_product_loop_start(); ?>

                    <?php
                    while ( have_posts() ) :
                        the_post();
						?>

                        <?php wc_get_template_part( 'content', 'product' ); ?>

                    <?php endwhile; // end of the loop. ?>

                    <?php woocommerce_product_loop_end(); ?>

                </div>

                <?php sk_content_nav( 'nav-below' ); ?>

            <?php } else { ?>

                <p class="sk-info"><?php esc_html_e( 'No products were found of this vendor!', 'sk-core' ); ?></p>

            <?php } ?>
        </div>

    </div><!-- .sk-single-store -->

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

</div><!-- .sk-store-wrap -->

<?php do_action( 'woocommerce_after_main_content' ); ?>

<?php get_footer( 'shop' ); ?>
