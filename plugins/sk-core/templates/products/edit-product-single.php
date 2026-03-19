<?php

use SK\Core\ProductCategory\Helper;

// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
global $post;

$from_shortcode = false;
$new_product    = false;

if (
    ! isset( $post->ID )
    && (
        ! isset( $_GET['_sk_edit_product_nonce'] ) ||
        ! wp_verify_nonce( sanitize_key( $_GET['_sk_edit_product_nonce'] ), 'sk_edit_product_nonce' ) ||
        ! isset( $_GET['product_id'] )
    )
) {
    wp_die( esc_html__( 'Access Denied, No product found', 'sk-core' ) );
}

if ( isset( $post->ID ) && $post->ID && 'product' === $post->post_type ) {
    $post_id      = $post->ID;
    $post_title   = $post->post_title;
    $post_content = $post->post_content;
    $post_excerpt = $post->post_excerpt;
    $post_status  = $post->post_status;
    $product      = wc_get_product( $post_id );
}

if ( isset( $_GET['product_id'] ) ) {
    $post_id = intval( $_GET['product_id'] );
    $post    = get_post( $post_id );

    if ( ! $post instanceof WP_Post ) {
        sk_get_template_part(
            'global/sk-error',
            '',
            array(
                'deleted' => false,
                'message' => __( 'This product is no longer available', 'sk-core' ),
            )
        );
        return;
    }

    $post_status    = $post->post_status;
    $auto_draft     = 'auto-draft' === $post_status;
    $post_title     = $auto_draft ? '' : $post->post_title;
    $post_content   = $auto_draft ? '' : $post->post_content;
    $post_excerpt   = $post->post_excerpt;
    $product        = wc_get_product( $post_id );
    $from_shortcode = true;
}

if ( isset( $_GET['product_id'] ) && 0 === absint( $_GET['product_id'] ) ) {
    $post_id      = intval( $_GET['product_id'] );
    $post_title   = '';
    $post_content = '';
    $post_excerpt = '';
    $post_status  = 'auto-draft';
    $product      = new WC_Product( $post_id );

    $product->set_name( $post_title );
    $product->set_status( $post_status );
    $post_id = $product->save();
    wp_update_post(
        [
            'ID'          => $post_id,
            'post_author' => sk_get_current_user_id(),
        ]
    );

    $post           = get_post( $post_id );
    $from_shortcode = true;
    $new_product    = true;
}

if ( ! sk_is_product_author( $post_id ) ) {
    wp_die( esc_html__( 'Access Denied', 'sk-core' ) );
}

$_regular_price         = get_post_meta( $post_id, '_regular_price', true );
$_sale_price            = get_post_meta( $post_id, '_sale_price', true );
$is_discount            = ! empty( $_sale_price );
$_sale_price_dates_from = get_post_meta( $post_id, '_sale_price_dates_from', true );
$_sale_price_dates_to   = get_post_meta( $post_id, '_sale_price_dates_to', true );

$_sale_price_dates_from = ! empty( $_sale_price_dates_from ) ? wp_date( 'Y-m-d', (int) $_sale_price_dates_from ) : '';
$_sale_price_dates_to   = ! empty( $_sale_price_dates_to ) ? wp_date( 'Y-m-d', (int) $_sale_price_dates_to ) : '';
$show_schedule          = false;

if ( ! empty( $_sale_price_dates_from ) && ! empty( $_sale_price_dates_to ) ) {
    $show_schedule = true;
}

$_featured        = get_post_meta( $post_id, '_featured', true );
$terms            = wp_get_object_terms( $post_id, 'product_type' );
$product_type     = ( ! empty( $terms ) ) ? sanitize_title( current( $terms )->name ) : 'simple';
$variations_class = ( $product_type === 'simple' ) ? 'sk-hide' : '';
$_visibility      = ( version_compare( WC_VERSION, '2.7', '>' ) ) ? $product->get_catalog_visibility() : get_post_meta( $post_id, '_visibility', true );

if ( ! $from_shortcode ) {
    get_header();
}

if ( ! empty( $_GET['errors'] ) ) {
    sk()->dashboard->templates->products->set_errors( array_map( 'sanitize_text_field', wp_unslash( $_GET['errors'] ) ) );
}

/**
 * Action hook to fire before sk dashboard wrap
 *
 */
do_action( 'sk_dashboard_wrap_before', $post, $post_id );
?>

<?php do_action( 'sk_dashboard_wrap_start' ); ?>

    <div class="sk-dashboard-wrap">

        <?php
        /**
         * Action took to fire before dashboard content.
         *
         *  @hooked get_dashboard_side_navigation
         *
         */
        do_action( 'sk_dashboard_content_before' );

        /**
         * Action hook to fire before product content area.
         *
         */
        do_action( 'sk_before_product_content_area' );
        ?>

        <div class="sk-dashboard-content sk-product-edit sk-layout">

            <?php
            /**
             * Action hook to fire inside product content area before
             *
             */
            do_action( 'sk_product_content_inside_area_before' );

            if ( $new_product ) {
                do_action( 'sk_new_product_before_product_area' );
            }
            ?>

            <header class="sk-pe-header">
                <div class="sk-pe-header__left">
                    <h2>
                        <?php if ( $new_product || 'auto-draft' === $post->post_status ) : ?>
                            <i class="fas fa-plus-circle"></i> Inserat erstellen
                        <?php else : ?>
                            <i class="fas fa-edit"></i> Inserat bearbeiten
                        <?php endif; ?>
                    </h2>
                    <?php do_action( 'sk_before_product_edit_status_label', $product ); ?>
                    <span class="sk-pe-status sk-pe-status--<?php echo esc_attr( $post->post_status ); ?>">
                        <?php echo esc_html( sk_get_post_status( $post->post_status ) ); ?>
                    </span>
                    <?php do_action( 'sk_after_product_edit_status_label', $product ); ?>
                </div>
                <div class="sk-pe-header__right">
                    <?php if ( $_visibility === 'hidden' ) : ?>
                        <span class="sk-pe-hidden-badge"><i class="far fa-eye-slash"></i> Versteckt</span>
                    <?php endif; ?>
                    <?php if ( $post->post_status === 'publish' ) : ?>
                        <a class="sk-btn sk-btn-theme sk-btn-sm" href="<?php echo esc_url( get_permalink( $post->ID ) ); ?>" target="_blank">
                            <i class="fas fa-external-link-alt"></i> Inserat ansehen
                        </a>
                    <?php endif; ?>
                    <?php do_action( 'sk_edit_product_after_view_product_button', $product ); ?>
                </div>
                <div id="ai-prompt-app"></div>
            </header>

            <div class="product-edit-new-container product-edit-container">
                <?php if ( sk()->dashboard->templates->products->has_errors() ) : ?>
                    <div class="sk-alert sk-alert-danger">
                        <a class="sk-close" data-dismiss="alert">&times;</a>

                        <?php foreach ( sk()->dashboard->templates->products->get_errors() as $error ) : ?>
                            <strong><?php esc_html_e( 'Error!', 'sk-core' ); ?></strong> <?php echo esc_html( $error ); ?>.<br>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ( isset( $_GET['message'] ) && $_GET['message'] === 'success' ) : ?>
                    <div class="sk-message">
                        <button type="button" class="sk-close" data-dismiss="alert">&times;</button>
                        <strong><?php esc_html_e( 'Success!', 'sk-core' ); ?></strong> <?php esc_html_e( 'The product has been saved successfully.', 'sk-core' ); ?>

                        <?php if ( $post->post_status === 'publish' ) : ?>
                            <a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" target="_blank"><?php esc_html_e( 'View Product &rarr;', 'sk-core' ); ?></a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ( apply_filters( 'sk_can_post', true ) ) : ?>
                    <?php if ( sk_is_seller_enabled( get_current_user_id() ) ) : ?>
                        <form class="sk-product-edit-form" role="form" method="post" id="post">

                            <?php do_action( 'sk_product_data_panel_tabs' ); ?>
                            <?php do_action( 'sk_product_edit_before_main' ); ?>

                            <div class="sk-form-top-area">

                                <div class="content-half-part sk-product-meta">

                                    <div id="sk-product-title-area" class="sk-form-group">
                                        <input type="hidden" name="sk_product_id" id="sk-edit-product-id" value="<?php echo esc_attr( $post_id ); ?>"/>

                                        <label for="post_title" class="form-label"><?php esc_html_e( 'Title', 'sk-core' ); ?></label>
                                        <?php
                                        sk_post_input_box(
                                            $post_id,
                                            'post_title',
                                            [
                                                'placeholder' => __( 'Product name..', 'sk-core' ),
                                                'value'       => $post_title,
                                            ]
                                        );
                                        ?>
                                        <div class="sk-product-title-alert sk-hide">
                                            <?php esc_html_e( 'Please enter product title!', 'sk-core' ); ?>
                                        </div>

                                        <div id="edit-slug-box" class="hide-if-no-js"></div>
                                        <?php wp_nonce_field( 'samplepermalink', 'samplepermalinknonce', false ); ?>
                                        <input type="hidden" name="editable-post-name" class="sk-hide" id="editable-post-name-full-sk">
                                        <input type="hidden" value="<?php echo esc_attr( $post->post_name ); ?>" name="edited-post-name" class="sk-hide" id="edited-post-name-sk">
                                    </div>

                                    <?php $product_types = apply_filters( 'sk_product_types', [ 'simple' => __( 'Simple', 'sk-core' ) ] ); ?>

                                    <?php if ( is_array( $product_types ) ) : ?>
                                        <?php if ( count( $product_types ) === 1 && array_key_exists( 'simple', $product_types ) ) : ?>
                                            <input type="hidden" id="product_type" name="product_type" value="simple">
                                        <?php else : ?>
                                            <div class="sk-form-group">
                                                <label for="product_type" class="form-label"><?php esc_html_e( 'Product Type', 'sk-core' ); ?> <i class="fas fa-question-circle tips" aria-hidden="true" data-title="<?php esc_html_e( 'Choose Variable if your product has multiple attributes - like sizes, colors, quality etc', 'sk-core' ); ?>"></i></label>
                                                <select name="product_type" class="sk-form-control" id="product_type">
                                                    <?php foreach ( $product_types as $key => $value ) : ?>
                                                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $product_type, $key ); ?>><?php echo esc_html( $value ); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <?php do_action( 'sk_product_edit_after_title', $post, $post_id ); ?>

                                    <div class="sk-form-group">
                                        <?php
                                        $data = Helper::get_saved_products_category( $post_id );
                                        $data['from'] = 'edit_product';

                                        sk_get_template_part( 'products/sk-category-header-ui', '', $data );
                                        ?>
                                    </div>

                                    <div class="show_if_simple sk-clearfix show_if_external">

                                        <div class="sk-form-group sk-clearfix sk-price-container">

                                            <div class="content-half-part regular-price">
                                                <label for="_regular_price" class="form-label"><?php esc_html_e( 'Price', 'sk-core' ); ?></label>
                                                <div class="sk-input-group">
                                                    <span class="sk-input-group-addon"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
                                                    <?php
                                                    sk_post_input_box(
                                                        $post_id,
                                                        '_regular_price',
                                                        [
                                                            'class'       => 'sk-product-regular-price',
															'placeholder' => sprintf( '%.0' . get_option( 'woocommerce_price_num_decimals' ) . 'f', 0 ),
                                                        ],
                                                        'price'
                                                    );
                                                    ?>
                                                </div>
                                            </div>

                                            <?php /* Aktionspreis entfernt */ ?>
                                        </div>

                                        <div class="sk-form-group sk-clearfix sk-price-container">
                                            <div class="sk-product-less-price-alert sk-hide">
                                                <?php esc_html_e( 'Product price can\'t be less than the vendor fee!', 'sk-core' ); ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="sk-form-group">
                                    <?php do_action( 'sk_product_edit_after_pricing', $post, $post_id ); ?>
                                    </div>

                                    <?php do_action( 'sk_product_edit_after_pricing_fields', $post, $post_id ); ?>

                                    <div class="sk-form-group">
                                        <label for="product_tag_edit" class="form-label"><?php esc_html_e( 'Tags', 'sk-core' ); ?></label>
                                        <?php
                                        $terms            = wp_get_post_terms( $post_id, 'product_tag', array( 'fields' => 'all' ) );
                                        $can_create_tags  = sk_get_option( 'product_vendors_can_create_tags', 'sk_selling' );
                                        $tags_placeholder = 'on' === $can_create_tags ? __( 'Select tags/Add tags', 'sk-core' ) : __( 'Select product tags', 'sk-core' );

                                        $drop_down_tags = array(
                                            'hide_empty' => 0,
                                        );
                                        ?>
                                        <select multiple="multiple" id="product_tag_edit" name="product_tag[]" class="product_tag_search sk-form-control sk-tags-select">
                                            <?php if ( ! empty( $terms ) ) : ?>
                                                <?php foreach ( $terms as $tax_term ) : ?>
                                                    <option value="<?php echo esc_attr( $tax_term->term_id ); ?>" selected="selected" ><?php echo esc_html( $tax_term->name ); ?></option>
                                                <?php endforeach ?>
                                            <?php endif ?>
                                        </select>
                                    </div>

                                    <?php do_action( 'sk_product_edit_after_product_tags', $post, $post_id ); ?>
                                </div><!-- .content-half-part -->

                                <div class="content-half-part featured-image">

                                    <div class="sk-feat-image-upload sk-new-product-featured-img">
                                        <?php
                                        $wrap_class        = ' sk-hide';
                                        $instruction_class = '';
                                        $feat_image_id     = 0;

                                        if ( has_post_thumbnail( $post_id ) ) {
                                            $wrap_class        = '';
                                            $instruction_class = ' sk-hide';
                                            $feat_image_id     = get_post_thumbnail_id( $post_id );
                                        }
                                        ?>

                                        <div class="instruction-inside<?php echo esc_attr( $instruction_class ); ?>">
                                            <input type="hidden" name="feat_image_id" class="sk-feat-image-id" value="<?php echo esc_attr( $feat_image_id ); ?>">

                                            <i class="fas fa-cloud-upload-alt"></i>
                                            <a href="#" class="sk-feat-image-btn btn btn-sm"><?php esc_html_e( 'Upload a product cover image', 'sk-core' ); ?></a>
                                        </div>

                                        <div class="image-wrap<?php echo esc_attr( $wrap_class ); ?>">
                                            <a class="close sk-remove-feat-image">&times;</a>
                                            <?php if ( $feat_image_id ) : ?>
                                                <?php
                                                echo get_the_post_thumbnail(
                                                    $post_id,
                                                    apply_filters( 'single_product_large_thumbnail_size', 'shop_single' ),
                                                    [
                                                        'height' => '',
                                                        'width'  => '',
                                                    ]
                                                );
                                                ?>
                                            <?php else : ?>
                                                <img height="" width="" src="" alt="">
                                            <?php endif; ?>
                                        </div>
                                    </div><!-- .sk-feat-image-upload -->

                                        <div class="sk-product-gallery">
                                            <div class="sk-side-body" id="sk-product-images">
                                                <div id="product_images_container">
                                                    <ul class="product_images sk-clearfix">
                                                        <?php
                                                        $product_images = get_post_meta( $post_id, '_product_image_gallery', true );
                                                        $gallery = explode( ',', $product_images );

                                                        if ( $gallery ) :
                                                            foreach ( $gallery as $image_id ) :
                                                                if ( empty( $image_id ) ) :
                                                                    continue;
                                                                endif;

                                                                $attachment_image = wp_get_attachment_image_src( $image_id, 'thumbnail' );
                                                                ?>
                                                                <li class="image" data-attachment_id="<?php echo esc_attr( $image_id ); ?>">
                                                                    <img src="<?php echo esc_url( $attachment_image[0] ); ?>" alt="">
                                                                    <a href="#" class="action-delete" title="<?php esc_attr_e( 'Delete image', 'sk-core' ); ?>">&times;</a>
                                                                </li>
                                                                <?php
                                                            endforeach;
                                                        endif;
                                                        ?>
                                                        <li class="add-image add-product-images tips" data-title="<?php esc_html_e( 'Add gallery image', 'sk-core' ); ?>">
                                                            <a href="#" class="add-product-images"><i class="fas fa-plus" aria-hidden="true"></i></a>
                                                        </li>
                                                    </ul>

                                                    <input type="hidden" id="product_image_gallery" name="product_image_gallery" value="<?php echo esc_attr( $product_images ); ?>">
                                                </div>
                                            </div>
                                        </div> <!-- .product-gallery -->

                                    <?php do_action( 'sk_product_gallery_image_count' ); ?>

                                </div><!-- .content-half-part -->
                            </div><!-- .sk-form-top-area -->

                            <?php
                            $pf = new \SK\Core\Dashboard\Modules\ProductForm();
                            $pf->output_sats_converter_box();
                            $pf->output_shipping_field( $post, $post_id );
                            ?>

                            <div class="sk-edit-row">
                                <div class="sk-section-heading">
                                    <h2><i class="fas fa-align-justify"></i> Beschreibung</h2>
                                </div>
                                <div class="sk-section-content">
                                    <div class="sk-product-description">
                                        <textarea id="post_content" name="post_content" class="sk-pe-textarea sk-pe-textarea--tall" rows="10"
                                                  placeholder="Ausführliche Produktbeschreibung…"><?php echo esc_textarea( $post_content ); ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <?php do_action( 'sk_product_edit_after_main', $post, $post_id ); ?>
                            <?php do_action( 'sk_new_product_form', $post, $post_id ); ?>
                            <?php do_action( 'sk_product_edit_after_inventory_variants', $post, $post_id ); ?>

                            <?php if ( $post_id ) : ?>
                                <?php do_action( 'sk_product_edit_after_options', $post_id ); ?>
                            <?php endif; ?>

                            <?php wp_nonce_field( 'sk_edit_product', 'sk_edit_product_nonce' ); ?>

                            <input type="hidden" name="sk_product_id" id="sk_product_id" value="<?php echo esc_attr( $post_id ); ?>" />
                            <input type="hidden" name="sk_update_product" value="<?php esc_attr_e( 'Save Product', 'sk-core' ); ?>"/>

                            <div class="sk-pe-submit-bar">
                                <input type="submit" name="sk_update_product" id="publish"
                                       class="sk-btn sk-btn-theme sk-btn-lg sk-pe-save-btn"
                                       value="<?php esc_attr_e( 'Inserat speichern', 'sk-core' ); ?>"/>
                            </div>
                            <div class="sk-clearfix"></div>
                        </form>
                    <?php else : ?>
                        <div class="sk-alert sk-alert">
                            <?php echo esc_html( sk_seller_not_enabled_notice() ); ?>
                        </div>
                    <?php endif; ?>

                <?php else : ?>

                    <?php do_action( 'sk_can_post_notice' ); ?>

                <?php endif; ?>
            </div> <!-- #primary .content-area -->

            <?php
            /**
             * Action took to fire inside product content after.
             *
             */
            do_action( 'sk_product_content_inside_area_after' );
            ?>
        </div>

        <?php
        /**
         * Action took to fire after dashboard content.
         *
         */
        do_action( 'sk_dashboard_content_after' );

        /**
         * Action took to fire after product content area.
         *
         */
        do_action( 'sk_after_product_content_area' );
        ?>

    </div><!-- .sk-dashboard-wrap -->

<?php do_action( 'sk_dashboard_wrap_end' ); ?>

<div class="sk-clearfix"></div>

<?php

/**
 * Action hook to fire after sk dashboard wrap
 *
 */
do_action( 'sk_dashboard_wrap_after', $post, $post_id );

wp_reset_postdata();

if ( ! $from_shortcode ) {
    get_footer();
}

// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited
?>
