<?php
/**
 * SK Dashboard Product Listing
 * filter template — search only
 *
 * @var string $product_search_name
 * @var string $post_status
 *
 */

do_action( 'sk_product_listing_filter_before_form' );
do_action( 'sk_product_listing_filter_from_start', [] );
?>

    <form method="get" class="sk-product-search-form">

        <?php wp_nonce_field( 'product_listing_filter', '_product_listing_filter_nonce', false ); ?>

        <div class="sk-product-search-inner">
            <input type="text" class="sk-form-control" name="product_search_name"
                   placeholder="<?php esc_attr_e( 'Inserate durchsuchen …', 'sk-core' ); ?>"
                   value="<?php echo esc_attr( $product_search_name ); ?>">
            <button type="submit" name="product_listing_search" value="ok" class="sk-btn sk-btn-theme">
                <i class="fas fa-search"></i>
            </button>
        </div>

        <?php if ( ! empty( $post_status ) ) : ?>
            <input type="hidden" name="post_status" value="<?php echo esc_attr( $post_status ); ?>">
        <?php endif; ?>

    </form>

    <?php do_action( 'sk_product_listing_filter_after_form' ); ?>
