<?php
/**
 * SK Product Brand Template
 *
 *
 *
 * @var array $product_brands List of product brands
 */
?>

<div class="sk-form-group">
    <label for="product_brand" class="form-label"><?php esc_html_e( 'Brand', 'sk-core' ); ?></label>
    <select multiple="multiple" id="product_brand" name="product_brand[]" class="product_brand_search sk-form-control" data-placeholder="<?php esc_attr_e( 'Select brand', 'sk-core' ); ?>" style="width: 100%;">
        <?php if ( ! empty( $product_brands ) ) : ?>
            <?php foreach ( $product_brands as $brand ) : ?>
                <option value="<?php echo esc_attr( $brand->term_id ); ?>" selected="selected">
                    <?php echo esc_html( $brand->name ); ?>
                </option>
            <?php endforeach ?>
        <?php endif ?>
    </select>
</div>
