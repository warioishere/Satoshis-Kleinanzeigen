<div class="sk-form-group">
    <label class="sk-w3 sk-control-label" for="sk_store_categories"><?php echo esc_html( $label ); ?></label>

    <div class="sk-w5 sk-text-left">
        <select
            class="sk-select2 sk-form-control"
            name="sk_store_categories[]"
            id="sk_store_categories"
            data-placeholder="<?php echo esc_html( $label ); ?>"
            <?php echo $is_multiple ? 'multiple': ''; ?>
        >
            <?php foreach ( $categories as $category ): ?>
                <option value="<?php echo esc_attr( $category->term_id ); ?>" <?php echo in_array( $category->term_id, $store_categories ) ? 'selected' : ''; ?>>
                    <?php echo esc_html( $category->name ); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>
