<?php

use SK\Core\ProductCategory\Helper;

$created_product      = null;
$feat_image_id        = null;
$regular_price        = '';
$sale_price           = '';
$sale_price_date_from = '';
$sale_price_date_to   = '';
$post_content         = '';
$post_excerpt         = '';
$product_images       = '';
$post_title           = '';
$terms                = [];
$currency_symbol      = get_woocommerce_currency_symbol();

if ( isset( $_REQUEST['_sk_add_product_nonce'] ) && wp_verify_nonce( sanitize_key( $_REQUEST['_sk_add_product_nonce'] ), 'sk_add_product_nonce' ) ) {
    if ( ! empty( $_REQUEST['created_product'] ) ) {
        $created_product = intval( $_REQUEST['created_product'] );
    }

    if ( ! empty( $_REQUEST['feat_image_id'] ) ) {
        $feat_image_id = intval( $_REQUEST['feat_image_id'] );
    }

    if ( ! empty( $_REQUEST['_regular_price'] ) ) {
        $regular_price = floatval( $_REQUEST['_regular_price'] );
    }

    if ( ! empty( $_REQUEST['_sale_price'] ) ) {
        $sale_price = floatval( $_REQUEST['_sale_price'] );
    }

    if ( ! empty( $_REQUEST['_sale_price_dates_from'] ) ) {
        $sale_price_date_from = sanitize_text_field( wp_unslash( $_REQUEST['_sale_price_dates_from'] ) );
    }

    if ( ! empty( $_REQUEST['_sale_price_dates_to'] ) ) {
        $sale_price_date_to = sanitize_text_field( wp_unslash( $_REQUEST['_sale_price_dates_to'] ) );
    }

    if ( ! empty( $_REQUEST['post_content'] ) ) {
        $post_content = wp_kses_post( wp_unslash( $_REQUEST['post_content'] ) );
    }

    if ( ! empty( $_REQUEST['post_excerpt'] ) ) {
        $post_excerpt = sanitize_textarea_field( wp_unslash( $_REQUEST['post_excerpt'] ) );
    }

    if ( ! empty( $_REQUEST['post_title'] ) ) {
        $post_title = sanitize_text_field( wp_unslash( $_REQUEST['post_title'] ) );
    }

    if ( ! empty( $_REQUEST['product_image_gallery'] ) ) {
        $product_images = sanitize_text_field( wp_unslash( $_REQUEST['product_image_gallery'] ) );
    }

    if ( ! empty( $_REQUEST['product_tag'] ) ) {
        $terms = array_map( 'intval', (array) wp_unslash( $_REQUEST['product_tag'] ) );
    }
}

/**
 * Action hook to fire before new product wrap.
 *
 */
do_action( 'sk_new_product_wrap_before' );
?>

<?php do_action( 'sk_dashboard_wrap_start' ); ?>

    <div class="sk-dashboard-wrap">
        <?php
        /**
         * Action hook to fire before rendering dashboard content.
         *
         *  @hooked get_dashboard_side_navigation
         *
         */
        do_action( 'sk_dashboard_content_before' );

        /**
         * Action hook to fire before rendering product content
         *
         */
        do_action( 'sk_before_new_product_content_area' );
        ?>


        <div class="sk-dashboard-content">

            <?php
            /**
             *  Action hook to fire inside new product content before
             *
             */
            do_action( 'sk_before_new_product_inside_content_area' );
            ?>

            <header class="sk-dashboard-header sk-clearfix">
                <h1 class="entry-title">
                    <?php esc_html_e( 'Add New Product', 'sk-core' ); ?>
                </h1>
            </header><!-- .entry-header -->

            <?php do_action( 'sk_new_product_before_product_area' ); ?>

            <div class="sk-new-product-area">
                <?php if ( sk()->dashboard->templates->products->has_errors() ) : ?>
                    <div class="sk-alert sk-alert-danger">
                        <a class="sk-close" data-dismiss="alert">&times;</a>

                        <?php foreach ( sk()->dashboard->templates->products->get_errors() as $error_msg ) : ?>
                            <strong><?php esc_html_e( 'Error!', 'sk-core' ); ?></strong> <?php echo wp_kses_post( $error_msg ); ?>.<br>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ( ! empty( $created_product ) ) : ?>
                    <div class="sk-alert sk-alert-success">
                        <a class="sk-close" data-dismiss="alert">&times;</a>
                        <strong><?php esc_html_e( 'Success!', 'sk-core' ); ?></strong>
                        <?php
                        printf(
                            /* translators: %s: product title with edit link */
                            esc_html__( 'You have successfully created %s product', 'sk-core' ),
                            sprintf(
                                '<a href="%s"><strong>%s</strong></a>',
                                esc_url( sk_edit_product_url( $created_product ) ),
                                esc_html( get_the_title( $created_product ) )
                            )
                        );
                        ?>
                    </div>
                <?php endif ?>

                <?php
                if ( apply_filters( 'sk_can_post', true ) ) :
                    $feat_image_url   = '';
                    $hide_instruction = '';
                    $hide_img_wrap    = 'sk-hide';

                    if ( ! empty( $feat_image_id ) ) {
                        $feat_image_url   = wp_get_attachment_url( $image_id );
                        $hide_instruction = 'sk-hide';
                        $hide_img_wrap    = '';
                    }

                    if ( sk_is_seller_enabled( get_current_user_id() ) ) :
                        ?>
                        <form class="sk-form-container" method="post">
                            <div class="product-edit-container sk-clearfix">
                                <div class="content-half-part featured-image">
                                    <div class="featured-image">
                                        <div class="sk-feat-image-upload">
                                            <div class="instruction-inside <?php echo esc_attr( $hide_instruction ); ?>">
                                                <input type="hidden" name="feat_image_id" class="sk-feat-image-id" value="<?php echo esc_attr( $feat_image_id ); ?>">
                                                <i class="fas fa-cloud-upload-alt"></i>
                                                <a href="#" class="sk-feat-image-btn sk-btn"><?php esc_html_e( 'Upload Product Image', 'sk-core' ); ?></a>
                                            </div>

                                            <div class="image-wrap <?php echo esc_attr( $hide_img_wrap ); ?>">
                                                <a class="close sk-remove-feat-image">&times;</a>
                                                    <img src="<?php echo esc_url( $feat_image_url ); ?>" alt="">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="sk-product-gallery">
                                        <div class="sk-side-body" id="sk-product-images">
                                            <div id="product_images_container">
                                                <ul class="product_images sk-clearfix">
                                                    <?php
                                                    if ( ! empty( $product_images ) ) :
                                                        $gallery = explode( ',', $product_images );
                                                        if ( $gallery ) :
                                                            foreach ( $gallery as $image_id ) :
                                                                if ( empty( $image_id ) ) :
                                                                    continue;
                                                                endif;

                                                                $attachment_image = wp_get_attachment_image_src( $image_id );

                                                                if ( ! $attachment_image ) :
                                                                    continue;
                                                                endif;
                                                                ?>
                                                                <li class="image" data-attachment_id="<?php echo esc_attr( $image_id ); ?>">
                                                                    <img src="<?php echo esc_url( $attachment_image[0] ); ?>" alt="">
                                                                    <a href="#" class="action-delete" title="<?php esc_attr_e( 'Delete image', 'sk-core' ); ?>">&times;</a>
                                                                </li>
                                                                <?php
                                                            endforeach;
                                                        endif;
                                                    endif;
                                                    ?>
                                                    <li class="add-image add-product-images tips" data-title="<?php esc_attr_e( 'Add gallery image', 'sk-core' ); ?>">
                                                        <a href="#" class="add-product-images"><i class="fas fa-plus" aria-hidden="true"></i></a>
                                                    </li>
                                                </ul>
                                                <input type="hidden" id="product_image_gallery" name="product_image_gallery" value="">
                                            </div>
                                        </div>
                                    </div> <!-- .product-gallery -->
                                    <?php do_action( 'sk_product_gallery_image_count' ); ?>
                                </div>

                                <div class="content-half-part sk-product-meta">
                                    <div class="sk-form-group">
                                        <input class="sk-form-control" name="post_title" id="post-title" type="text" placeholder="<?php esc_attr_e( 'Product name..', 'sk-core' ); ?>" value="<?php echo esc_attr( $post_title ); ?>">
                                    </div>

                                    <div class="sk-form-group">
                                        <div class="sk-form-group sk-clearfix sk-price-container">
                                            <div class="content-half-part">
                                                <label for="_regular_price" class="sk-form-label"><?php esc_html_e( 'Price', 'sk-core' ); ?></label>
                                                <div class="sk-input-group">
                                                    <span class="sk-input-group-addon"><?php echo esc_attr( $currency_symbol ); ?></span>
                                                    <input type="text" class="sk-form-control wc_input_price sk-product-regular-price" name="_regular_price" placeholder="0.00" id="_regular_price" value="<?php echo esc_attr( $regular_price ); ?>">
                                                </div>
                                            </div>

                                            <?php /* Aktionspreis entfernt */ ?>
                                        </div>
                                    </div>

                                    <div class="sk-form-group">
                                        <textarea name="post_excerpt" id="post-excerpt" rows="5" class="sk-form-control" placeholder="<?php esc_attr_e( 'Short description of the product...', 'sk-core' ); ?>"><?php echo esc_attr( $post_excerpt ); ?></textarea>
                                    </div>

                                    <?php
                                    $can_create_tags        = 'on' === sk_get_option( 'product_vendors_can_create_tags', 'sk_selling' );
                                    $saved_product_cat_data = array_merge( (array) Helper::get_saved_products_category(), [ 'from' => 'new_product' ] );
                                    sk_get_template_part( 'products/sk-category-header-ui', '', $saved_product_cat_data );
                                    ?>

                                    <?php do_action( 'sk_new_product_after_product_category' ); ?>

                                    <div class="sk-form-group">
                                        <label for="product_tag" class="form-label"><?php esc_html_e( 'Tags', 'sk-core' ); ?></label>
                                        <select multiple="multiple" name="product_tag[]" id="product_tag_search" class="product_tag_search product_tags sk-form-control sk-select2" data-placeholder="<?php echo $can_create_tags ? esc_attr__( 'Select tags/Add tags', 'sk-core' ) : esc_attr__( 'Select product tags', 'sk-core' ); ?>">
                                            <?php if ( ! empty( $terms ) ) : ?>
                                                <?php foreach ( $terms as $product_term_id ) : ?>
                                                    <?php $product_term = get_term( $product_term_id ); ?>
                                                    <option value="<?php echo esc_attr( $product_term->term_id ); ?>" selected="selected" ><?php echo esc_html( $product_term->name ); ?></option>
                                                <?php endforeach ?>
                                            <?php endif ?>
                                        </select>
                                    </div>

                                    <?php do_action( 'sk_new_product_after_product_tags' ); ?>
                                </div>
                            </div>

                            <div class="sk-edit-row">
                                <div class="sk-section-heading">
                                    <h2><i class="fas fa-align-justify"></i> <?php esc_html_e( 'Beschreibung', 'sk-core' ); ?></h2>
                                </div>
                                <div class="sk-section-content">
                                    <?php
                                    wp_editor(
                                        htmlspecialchars_decode( $post_content, ENT_QUOTES ),
                                        'post_content',
                                        [
                                            'editor_height' => 50,
                                            'quicktags'     => false,
                                            'media_buttons' => false,
                                            'teeny'         => true,
                                            'editor_class'  => 'post_content',
                                        ]
                                    );
                                    ?>
                                </div>
                            </div>

                            <?php do_action( 'sk_new_product_form' ); ?>

                            <hr>

                            <div class="sk-form-group sk-right">
                                <?php
                                wp_nonce_field( 'sk_add_new_product', 'sk_add_new_product_nonce' );

                                $show_add_new_button = ! function_exists( 'sk_ext' ) || ! sk_ext()->module->is_active( 'product_subscription' ) || \SK\Modules\Subscription\Helper::get_vendor_remaining_products( sk_get_current_user_id() ) !== 1;

                                if ( $show_add_new_button ) :
                                    ?>
                                    <button type="submit" name="add_product" class="sk-btn sk-btn-default" value="create_and_add_new">
                                        <?php esc_attr_e( 'Create & Add New', 'sk-core' ); ?>
                                    </button>
                                <?php endif; ?>
                                <button type="submit" name="add_product" class="sk-btn sk-btn-default sk-btn-theme" value="create_new"><?php esc_attr_e( 'Create Product', 'sk-core' ); ?></button>
                            </div>
                        </form>
                    <?php else : ?>
                        <?php sk_seller_not_enabled_notice(); ?>
                    <?php endif; ?>
                <?php else : ?>
                    <?php do_action( 'sk_can_post_notice' ); ?>
                <?php endif; ?>
            </div>
            <?php
            /**
             * Action hook to fire inside new product content after
             *
             */
            do_action( 'sk_after_new_product_inside_content_area' );
            ?>
        </div> <!-- #primary .content-area -->
        <?php
        /**
         * Action hook to fire after rendering dashboard content.
         *
         */
        do_action( 'sk_dashboard_content_after' );

        /**
         * Action hook to fire after rendering product content.
         *
         */
        do_action( 'sk_after_new_product_content_area' );
        ?>
    </div><!-- .sk-dashboard-wrap -->
<?php
/**
 * Action hook to fire at end of the dahboard wrap.
 *
 */
do_action( 'sk_dashboard_wrap_end' );

/**
 * Action hook to fire after new product wrap.
 *
 */
do_action( 'sk_new_product_wrap_after' );
?>
