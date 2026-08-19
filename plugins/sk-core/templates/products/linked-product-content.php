<div class="sk-linked-product-options sk-edit-row sk-clearfix hide_if_external">
    <div class="sk-section-heading" data-togglehandler="sk_linked_product_options">
        <h2><i class="fas fa-link" aria-hidden="true"></i> <?php _e( 'Linked Products', 'sk-core' ); ?></h2>
        <p><?php _e( 'Set your linked products for upsell and cross-sells', 'sk-core' ); ?></p>
        <div class="sk-clearfix"></div>
    </div>

    <div class="sk-section-content">
        <div class="content-half-part sk-form-group hide_if_variation">
            <label for="upsell_ids" class="form-label"><?php _e( 'Upsells', 'sk-core' ); ?> <i class="fas fa-question-circle tips" aria-hidden="true" data-title="<?php _e( 'Upsells are products which you recommend instead of the currently viewed product, for example, products that are more profitable or better quality or more expensive.', 'sk-core' ); ?>"></i></label>
            <select class="sk-form-control sk-product-search" multiple="multiple" style="width: 100%;" id="upsell_ids" name="upsell_ids[]" data-placeholder="<?php esc_attr_e( 'Search for a product&hellip;', 'sk-core' ); ?>" data-action="sk_json_search_products_and_variations" data-exclude="<?php echo intval( $post->ID ); ?>" data-user_ids="<?php echo sk_get_current_user_id(); ?>">
                <?php
                    if ( !empty( $upsells_ids ) ) {
                        foreach ( $upsells_ids as $product_id ) {
                            $product = wc_get_product( $product_id );
                            if ( is_object( $product ) ) {
                                echo '<option value="' . esc_attr( $product_id ) . '"' . selected( true, true, false ) . '>' . wp_kses_post( $product->get_formatted_name() ) . '</option>';
                            }
                        }
                    }
                ?>
            </select>
        </div>

        <div class="content-half-part">
            <label for="crosssell_ids" class="form-label"><?php _e( 'Cross-sells', 'sk-core' ); ?> <i class="fas fa-question-circle tips" aria-hidden="true" data-title="<?php _e( 'Cross-sells are products which you promote in the cart, based on the current product.', 'sk-core' ); ?>"></i></label>
            <select class="sk-form-control sk-product-search" multiple="multiple" style="width: 100%;" id="crosssell_ids" name="crosssell_ids[]" data-placeholder="<?php esc_attr_e( 'Search for a product&hellip;', 'sk-core' ); ?>" data-action="sk_json_search_products_and_variations" data-exclude="<?php echo intval( $post->ID ); ?>" data-user_ids="<?php echo sk_get_current_user_id(); ?>">
                <?php
                    if ( ! empty( $crosssells_ids ) ) {
                        foreach ( $crosssells_ids as $product_id ) {
                            $product = wc_get_product( $product_id );
                            if ( is_object( $product ) ) {
                                echo '<option value="' . esc_attr( $product_id ) . '"' . selected( true, true, false ) . '>' . wp_kses_post( $product->get_formatted_name() ) . '</option>';
                            }
                        }
                    }
                ?>
            </select>
        </div>

        <div class="sk-clearfix"></div>

        <?php do_action( 'sk_after_linked_product_fields', $post, $post_id ); ?>
    </div>
</div>