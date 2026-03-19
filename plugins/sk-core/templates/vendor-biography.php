<?php
/**
 * The Template for displaying vendor biography.
 *
 */

$store_user = get_userdata( get_query_var( 'author' ) );
$store_info   = sk_get_store_info( $store_user->ID );
$map_location = $store_user->get_location();
$layout       = get_theme_mod( 'store_layout', 'left' );

get_header( 'shop' );
?>

<?php do_action( 'woocommerce_before_main_content' ); ?>

<div class="sk-store-wrap layout-<?php echo esc_attr( $layout ); ?>">

    <?php if ( 'left' === $layout ) { ?>
        <?php
        sk_get_template_part(
            'store',
            'sidebar',
            array(
                'store_user' => $store_user,
                'store_info' => $store_info,
                'map_location' => $map_location,
            )
        );
        ?>
    <?php } ?>

<div id="sk-primary" class="sk-single-store">
    <div id="sk-content" class="store-review-wrap woocommerce" role="main">

        <?php sk_get_template_part( 'store-header' ); ?>

        <div id="vendor-biography">
            <div id="comments">
            <?php do_action( 'sk_vendor_biography_tab_before', $store_user, $store_info ); ?>

            <h2 class="headline"><?php echo apply_filters( 'sk_vendor_biography_title', __( 'Vendor Biography', 'sk' ) ); ?></h2>

            <?php
            if ( ! empty( $store_info['vendor_biography'] ) ) {
                printf(
                    '%s',
                    apply_filters(
                        'the_content',
                        apply_filters(
                            'sk_get_vendor_biography_text',
                            $store_info['vendor_biography'],
                            $store_user->ID
                        )
                    )
                );
            }
            ?>

            <?php do_action( 'sk_vendor_biography_tab_after', $store_user, $store_info ); ?>
            </div>
        </div>

    </div><!-- #content .site-content -->
</div><!-- #primary .content-area -->

    <?php if ( 'right' === $layout ) { ?>
        <?php
        sk_get_template_part(
            'store',
            'sidebar',
            array(
                'store_user' => $store_user,
                'store_info' => $store_info,
                'map_location' => $map_location,
            )
        );
        ?>
    <?php } ?>

</div><!-- .sk-store-wrap -->

<?php do_action( 'woocommerce_after_main_content' ); ?>

<?php get_footer(); ?>
