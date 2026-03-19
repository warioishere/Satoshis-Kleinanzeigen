<div class="sk-form-group sk-product-type-container <?php echo esc_attr( $class ); ?>">
        <div class="content-half-part downloadable-checkbox">
            <label>
                <input type="checkbox" <?php checked( $is_downloadable, true ); ?> class="_is_downloadable" name="_downloadable" id="_downloadable"> <?php esc_html_e( 'Downloadable', 'sk-core' ); ?> <i class="fas fa-question-circle tips" aria-hidden="true" data-title="<?php esc_attr_e( 'Downloadable products give access to a file upon purchase.', 'sk-core' ); ?>"></i>
            </label>
        </div>
    <div class="content-half-part virtual-checkbox">
        <label>
            <input type="checkbox" <?php checked( $is_virtual, true ); ?> class="_is_virtual" name="_virtual" id="_virtual"> <?php esc_html_e( 'Virtual', 'sk-core' ); ?> <i class="fas fa-question-circle tips" aria-hidden="true" data-title="<?php esc_attr_e( 'Virtual products are intangible and aren\'t shipped.', 'sk-core' ); ?>"></i>
        </label>
    </div>
    <div class="sk-clearfix"></div>
</div>
