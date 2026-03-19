<?php

use SK\Core\ProductCategory\Helper;

?>
<script type="text/html" id="tmpl-sk-add-new-product">
    <div id="sk-add-new-product-popup" class="white-popup sk-add-new-product-popup">
        <?php do_action( 'sk_new_product_before_product_area' ); ?>
        <form action="" method="post" id="sk-add-new-product-form">
            <div class="product-form-container">
                <div class="content-half-part sk-feat-image-content">
                    <div class="sk-feat-image-upload">
                        <?php
                        $wrap_class        = ' sk-hide';
                        $instruction_class = '';
                        $feat_image_id     = 0;
                        $can_create_tags   = sk_get_option( 'product_vendors_can_create_tags', 'sk_selling' );
                        $tags_placeholder  = 'on' === $can_create_tags ? __( 'Select tags/Add tags', 'sk-core' ) : __( 'Select product tags', 'sk-core' );
                        ?>
                        <div class="instruction-inside<?php echo esc_attr( $instruction_class ); ?>">
                            <input type="hidden" name="feat_image_id" class="sk-feat-image-id" value="<?php echo esc_attr( $feat_image_id ); ?>">

                            <i class="fas fa-cloud-upload-alt"></i>
                            <a href="#" class="sk-feat-image-btn btn btn-sm"><?php esc_html_e( 'Upload a product cover image', 'sk-core' ); ?></a>
                        </div>

                        <div class="image-wrap<?php echo esc_attr( $wrap_class ); ?>">
                            <a class="close sk-remove-feat-image">&times;</a>
                            <img height="" width="" src="" alt="">
                        </div>
                    </div>

                        <div class="sk-product-gallery">
                            <div class="sk-side-body" id="sk-product-images">
                                <div id="product_images_container">
                                    <ul class="product_images sk-clearfix">

                                        <li class="add-image add-product-images tips" data-title="<?php esc_attr_e( 'Add gallery image', 'sk-core' ); ?>">
                                            <a href="#" class="add-product-images"><i class="fas fa-plus" aria-hidden="true"></i></a>
                                        </li>
                                    </ul>
                                    <input type="hidden" id="product_image_gallery" name="product_image_gallery" value="">
                                </div>
                            </div>
                        </div>

                </div>
                <div class="content-half-part sk-product-field-content">
                    <div class="sk-form-group">
                        <input type="text" class="sk-form-control" name="post_title" placeholder="<?php esc_attr_e( 'Product name..', 'sk-core' ); ?>">
                    </div>

                    <div class="sk-clearfix">
                        <div class="sk-form-group sk-clearfix sk-price-container">
                            <div class="content-half-part">
                                <label for="_regular_price" class="form-label"><?php esc_html_e( 'Price', 'sk-core' ); ?></label>
                                <div class="sk-input-group">
                                    <span class="sk-input-group-addon"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
                                    <input type="text" class="sk-product-regular-price wc_input_price sk-form-control" name="_regular_price" id="_regular_price" placeholder="0.00">
                                </div>
                            </div>

                            <div class="content-half-part sale-price">
                                <label for="_sale_price" class="form-label">
                                    <?php esc_html_e( 'Discounted Price', 'sk-core' ); ?>
                                    <a href="#" class="sale_schedule"><?php esc_html_e( 'Schedule', 'sk-core' ); ?></a>
                                    <a href="#" class="cancel_sale_schedule sk-hide"><?php esc_html_e( 'Cancel', 'sk-core' ); ?></a>
                                </label>

                                <div class="sk-input-group">
                                    <span class="sk-input-group-addon"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
                                    <input type="text" class="sk-product-sales-price wc_input_price sk-form-control"  name="_sale_price" placeholder="0.00" id="_sale_price">
                                </div>
                            </div>
                        </div>

                        <div class="sk-hide sale-schedule-container sale_price_dates_fields sk-clearfix sk-form-group">
                            <div class="content-half-part from">
                                <div class="sk-input-group">
                                    <span class="sk-input-group-addon"><?php esc_html_e( 'From', 'sk-core' ); ?></span>
                                    <input type="text" name="_sale_price_dates_from" class="sk-form-control" value="" maxlength="10" pattern="[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])" placeholder="<?php esc_attr_e( 'YYYY-MM-DD', 'sk-core' ); ?>">
                                </div>
                            </div>

                            <div class="content-half-part to">
                                <div class="sk-input-group">
                                    <span class="sk-input-group-addon"><?php esc_html_e( 'To', 'sk-core' ); ?></span>
                                    <input type="text" name="_sale_price_dates_to" class="sk-form-control" value="" maxlength="10" pattern="[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])" placeholder="<?php esc_attr_e( 'YYYY-MM-DD', 'sk-core' ); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="sk-clearfix"></div>
                <div class="product-full-container">

                    <?php
                        $data = Helper::get_saved_products_category();
                        $data['from'] = 'new_product_popup';
                        sk_get_template_part( 'products/sk-category-header-ui', '', $data );
                    ?>

                    <?php do_action( 'sk_new_product_after_product_category' ); ?>

                    <div class="sk-form-group">
                        <label for="product_tag_search" class="form-label"><?php esc_html_e( 'Tags', 'sk-core' ); ?></label>
                        <select multiple="multiple" name="product_tag[]" id="product_tag_search" class="product_tag_search product_tags" data-placeholder="<?php echo esc_attr( $tags_placeholder ); ?>" style="width: 100%;"></select>
                    </div>

                    <?php do_action( 'sk_new_product_after_product_tags' ); ?>

                    <div class="sk-form-group">
                        <textarea name="post_excerpt" id="" class="sk-form-control" rows="5" placeholder="<?php esc_attr_e( 'Enter some short description about this product...' , 'sk-core' ) ?>"></textarea>
                    </div>
                </d>
            </div>
            <div class="product-container-footer">
                <span class="sk-show-add-product-error"></span>
                <span class="sk-show-add-product-success"></span>
                <span class="sk-spinner sk-add-new-product-spinner sk-hide"></span>
                <input type="submit" id="sk-create-new-product-btn" class="sk-btn sk-btn-default" data-btn_id="create_new" value="<?php esc_attr_e( 'Create product', 'sk-core' ) ?>">
                <?php
                $display_create_and_add_new_button = true;
                if ( function_exists( 'sk_ext' ) && sk_ext()->module->is_active( 'product_subscription' ) ) {
                    if ( \SK\Modules\Subscription\Helper::get_vendor_remaining_products( sk_get_current_user_id() ) === 1 ) {
                        $display_create_and_add_new_button = false;
                    }
                }
                if ( $display_create_and_add_new_button ) :
                ?>
                <input type="submit" id="sk-create-and-add-new-product-btn" class="sk-btn sk-btn-theme" data-btn_id="create_and_new" value="<?php esc_attr_e( 'Create & add new', 'sk-core' ) ?>">
                <?php endif; ?>
            </div>
        </form>
    </div>
    <style>
        .select2-container--open .select2-dropdown--below {
            margin-top: 0px;
        }

        .select2-container--open .select2-dropdown--above {
            margin-top: 0px;
        }
    </style>
</script>
<?php do_action( 'sk_add_product_js_template_end' );?>
