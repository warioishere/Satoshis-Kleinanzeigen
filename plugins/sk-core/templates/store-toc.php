<?php
/**
 * The Template for displaying all reviews.
 *
 */

$vendor       = sk()->vendor->get( get_query_var( 'author' ) );
$vendor_info  = $vendor->get_shop_info();
$map_location = $vendor->get_location();
$store_user   = get_userdata( get_query_var( 'author' ) );
$store_info   = sk_get_store_info( $store_user->ID );
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

    <div id="primary" class="content-area sk-single-store">
        <div id="sk-content" class="site-content store-review-wrap woocommerce" role="main">

            <?php sk_get_template_part( 'store-header' ); ?>

            <div id="store-toc-wrapper">
                <div id="store-toc">
                    <?php if ( ! empty( $vendor->get_store_tnc() ) ) : ?>
                        <h2 class="headline"><?php esc_html_e( 'Terms And Conditions', 'sk-core' ); ?></h2>
                        <div>
                            <?php echo wp_kses_post( wpautop( wptexturize( $vendor->get_store_tnc() ) ) ); ?>
                        </div>
                        <?php
                    endif;
                    ?>
                </div><!-- #store-toc -->
            </div><!-- #store-toc-wrap -->

        </div><!-- #content .site-content -->
    </div><!-- #primary .content-area -->

    <div class="sk-clearfix"></div>

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

<?php get_footer(); ?>
