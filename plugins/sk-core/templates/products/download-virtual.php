<div class="sk-form-group sk-product-type-container <?php echo esc_attr( $class ); ?>">
    <div class="content-half-part virtual-checkbox">
        <label>
            <input type="checkbox" <?php checked( $is_virtual, true ); ?> class="_is_virtual" name="_virtual" id="_virtual"> <?php esc_html_e( 'Digital product, no shipping', 'sk-core' ); ?> <i class="fas fa-question-circle tips" aria-hidden="true" data-title="<?php esc_attr_e( 'Nothing is shipped: the shipping field disappears and a purchase asks for no delivery address.', 'sk-core' ); ?>"></i>
        </label>
    </div>
    <div class="sk-clearfix"></div>
</div>
