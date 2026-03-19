<?php
use SK\Core\ProductCategory\Helper;

$from           = ! empty( $from ) ? $from : '-';
$hide_cat_title = ! empty( $hide_cat_title ) && 'yes' === $hide_cat_title;

$initial_category_for_modal = 0;

// If there are no chosen category and also there is a default category set then initial category box set as default category
if ( count( $chosen_cat ) < 1 && ! empty( $default_product_cat->term_id ) ) {
    $initial_category_for_modal = $default_product_cat->term_id;
}

// If no category is set then add a empty category box.
if ( count( $chosen_cat ) < 1 ) {
    $chosen_cat[] = $initial_category_for_modal;
}
?>

<!-- Trigger/Open The Modal -->
<?php if ( ! $hide_cat_title ) : ?>
<div class="sk-form-group sk-new-cat-ui-title">
    <label for="chosen_product_cat" class="form-label"><?php esc_html_e( 'Category', 'sk-core' ); ?></label>
</div>
<?php endif; ?>
<span class="sk-add-new-cat-box cat_box_for_<?php echo esc_attr( $from ); ?>">
    <?php foreach ( $chosen_cat as $key => $term_id ) : ?>
        <div data-activate="no" class="sk-select-product-category-container sk_select_cat_for_<?php echo esc_attr( $from ); ?>_<?php echo esc_attr( $key ); ?>">
            <div data-selectfor="<?php echo esc_attr( $from ); ?>" class="sk-form-group sk-select-product-category sk-category-open-modal" data-sksclevel="<?php echo esc_attr( $key ); ?>" id="sk-category-open-modal">
                <span id="sk_product_cat_res" class="sk-select-product-category-title sk-ssct-level-<?php echo esc_attr( $key ); ?>">
                    <?php echo empty( $term_id ) ? esc_html__( '- Select a category -', 'sk-core' ) : wp_kses_post( Helper::get_ancestors_html( $term_id ) ); ?>
                </span>
                <span class="sk-select-product-category-icon"><i class="fas fa-edit"></i></span>

            </div>
            <?php if ( ! $is_single ) : ?>
                <div class="sk-select-product-category-remove-container">
                    <span class="sk-select-product-category-remove"><i class="fas fa-times"></i></span>
                </div>
            <?php endif; ?>
            <span class="sk-cat-inputs-holder sk-cih-level-<?php echo esc_attr( $key ); ?>" >
                <?php if ( ! empty( $term_id ) ) : ?>
                    <input data-field-name="chosen_product_cat" type="hidden" class="sk_chosen_product_cat sk_chosen_product_cat_<?php echo esc_attr( $term_id ); ?>" name="chosen_product_cat[]" value="<?php echo esc_attr( $term_id ); ?>"/>
                <?php endif; ?>
            </span>
        </div>
    <?php endforeach; ?>
</span>
<?php if ( sk()->is_pro_exists() && ! $is_single ) : ?>
    <div class="sk-form-group sk-add-more-single-cat-container">
        <div class="sk-single-cat-add-btn" data-selectfor="<?php echo esc_attr( $from ); ?>">
            <span><?php esc_html_e( '+ Add new category', 'sk-core' ); ?></span>
        </div>
    </div>
<?php endif; ?>
