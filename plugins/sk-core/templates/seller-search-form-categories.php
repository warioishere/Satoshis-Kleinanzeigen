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

<script>
    jQuery( document ).ready( function ( $ ) {
        var form = $( '.sk-seller-search-form' ),
            category = form.find( '[name="sk_seller_category"]' );

        form.on( 'sk_seller_search_populate_data', function ( e, data ) {
            data.store_categories = category.val();
        } );

        category.on( 'change', function () {
            form.trigger( 'sk_seller_search' );
        } );
    } );
</script>
