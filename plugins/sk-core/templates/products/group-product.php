<?php

/**
 * Group product template
 *
 *
 */
?>

<div class="sk-group-product-content show_if_grouped">
    <label for="crosssell_ids" class="form-label"><?php _e( 'Grouped products', 'sk-core' ); ?> <i class="fas fa-question-circle tips" aria-hidden="true" data-title="<?php _e( 'This lets you choose which products are part of this group.', 'sk-core' ); ?>"></i></label>
    <select class="sk-form-control sk-product-search" multiple="multiple" style="width: 100%;" id="grouped_products" name="grouped_products[]" data-placeholder="<?php esc_attr_e( 'Search for a product&hellip;', 'sk-core' ); ?>" data-action="sk_json_search_products_and_variations" data-exclude="<?php echo intval( $post->ID ); ?>" data-user_ids="<?php echo sk_get_current_user_id(); ?>">
        <?php
            $product_ids = $product->get_children( 'edit' );

            foreach ( $product_ids as $product_id ) {
                $product = wc_get_product( $product_id );
                if ( is_object( $product ) ) {
                    echo '<option value="' . esc_attr( $product_id ) . '"' . selected( true, true, false ) . '>' . wp_kses_post( $product->get_formatted_name() ) . '</option>';
                }
            }

        ?>
    </select>
</div>