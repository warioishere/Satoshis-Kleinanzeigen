<?php
/**
 * The Template for displaying all reviews.
 *
 */

$store_user = get_userdata( get_query_var( 'author' ) );
$store_info = sk_get_store_info( $store_user->ID );
$map_location = isset( $store_info['location'] ) ? esc_attr( $store_info['location'] ) : '';
$layout       = get_theme_mod( 'store_layout', 'left' );

get_header( 'shop' );
?>

<?php do_action( 'woocommerce_before_main_content' ); ?>

<div class="sk-store-wrap layout-<?php echo esc_attr( $layout ); ?>">

    <?php if ( 'left' === $layout ) { ?>
        <?php sk_get_template_part( 'store', 'sidebar', array( 'store_user' => $store_user, 'store_info' => $store_info, 'map_location' => $map_location ) ); ?>
    <?php } ?>

<div id="sk-primary" class="sk-single-store">
    <div id="sk-content" class="store-review-wrap woocommerce" role="main">

        <?php sk_get_template_part( 'store-header' ); ?>


        <?php
        $sk_template_reviews = sk_ext()->review;
        $id                     = $store_user->ID;
        $post_type              = 'product';
        $limit                  = 20;
        $status                 = '1';
        $comments               = $sk_template_reviews->comment_query( $id, $post_type, $limit, $status );
        ?>

        <div class="sk-store-review-iziModal"></div>
        <div id="reviews">
            <div id="comments">
                <?php do_action( 'sk_review_tab_before_comments' ); ?>
                <ol class="commentlist">
                    <?php echo $sk_template_reviews->render_store_tab_comment_list( $comments, $store_user->ID ); ?>
                </ol>
            </div>
        </div>

        <?php
        if ( sk_ext()->module->is_active( 'store_reviews' ) ) {
            echo $sk_template_reviews->review_pagination( $store_user->ID, $post_type, $limit, $status );
        } else {
            $pagenum = isset( $_REQUEST['pagenum'] ) ? absint( $_REQUEST['pagenum'] ) : 1; // phpcs:ignore
            echo $sk_template_reviews->review_pagination_with_query( $store_user->ID, $post_type, $limit, $status, $pagenum );
        }
        ?>

    </div><!-- #content .site-content -->
</div><!-- #primary .content-area -->

    <?php if ( 'right' === $layout ) { ?>
        <?php sk_get_template_part( 'store', 'sidebar', array( 'store_user' => $store_user, 'store_info' => $store_info, 'map_location' => $map_location ) ); ?>
    <?php } ?>

</div><!-- .sk-store-wrap -->

<?php do_action( 'woocommerce_after_main_content' ); ?>

<?php get_footer(); ?>
