<?php
wp_enqueue_script( 'sk-seller-search-categories' );
?>
<div class="sk-w4">
    <select
        class="sk-select2 sk-form-control"
        name="sk_seller_category"
    >
        <option value=""><?php echo esc_html( __( 'Store Category', 'sk' ) ); ?></option>
        <?php foreach ( $categories as $category ): ?>
            <option value="<?php echo esc_attr( $category->slug ); ?>" <?php echo ( $category->slug === $category_query ) ? 'selected' : ''; ?>>
                <?php echo esc_html( $category->name ); ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>
