<li class="product-attribute-list <?php echo esc_attr( implode( ' ', $metabox_class ) ); ?>" data-taxonomy="<?php echo esc_attr( $taxonomy ); ?>">
    <div class="sk-product-attribute-heading">
        <span><i class="fas fa-bars" aria-hidden="true"></i>&nbsp;&nbsp;<strong><?php echo ! empty( $attribute_label ) ? esc_html( $attribute_label ) : esc_html__( 'Attribute Name', 'sk-core' ); ?></strong></span>
        <a href="#" class="sk-product-remove-attribute"><?php esc_html_e( 'Remove', 'sk-core' ); ?></a>
    </div>

    <div class="sk-product-attribute-item sk-clearfix">
        <div class="content-half-part">
            <label class="form-label" for=""><?php esc_html_e( 'Name', 'sk-core' ); ?></label>
            <?php if ( $attribute['is_taxonomy'] ) : ?>
				<strong><?php echo esc_html( $attribute_label ); ?></strong>
				<input type="hidden" name="attribute_names[<?php echo $i; ?>]" value="<?php echo esc_attr( $taxonomy ); ?>" />
			<?php else : ?>
            	<input type="text" class="attribute_name sk-form-control sk-product-attribute-name" name="attribute_names[<?php echo $i; ?>]" value="<?php echo esc_attr( $attribute['name'] ); ?>">
			<?php endif; ?>

			<input type="hidden" name="attribute_position[<?php echo $i; ?>]" class="attribute_position" value="<?php echo $position; ?>" />
			<input type="hidden" name="attribute_is_taxonomy[<?php echo $i; ?>]" value="<?php echo $attribute['is_taxonomy'] ? 1 : 0; ?>" />

			<label class="checkbox-item form-label">
				<input type="checkbox" <?php checked( $attribute['is_visible'], 1 ); ?> name="attribute_visibility[<?php echo $i; ?>]" value="1" /> <?php esc_html_e( 'Visible on the product page', 'sk-core' ); ?>
			</label>

			<label class="checkbox-item form-label show_if_variable show_if_variable-subscription">
				<input type="checkbox" <?php checked( $attribute['is_variation'], 1 ); ?> name="attribute_variation[<?php echo $i; ?>]" value="1" /> <?php esc_html_e( 'Used for variations', 'sk-core' ); ?>
			</label>
        </div>

        <div class="content-half-part sk-attribute-values">
            <label for="" class="form-label"><?php esc_html_e( 'Value(s)', 'sk-core' ); ?></label>
			<?php if ( $attribute['is_taxonomy'] ) : ?>
				<?php
				$attribute_types = array( 'select', 'text' );
				if ( ! in_array( $attribute_taxonomy->attribute_type, $attribute_types, true ) ) {
					$attribute_taxonomy->attribute_type = 'select';
				}
				?>
				<?php if ( 'select' === $attribute_taxonomy->attribute_type ) : ?>
					<select multiple="multiple" style="width:100%" data-placeholder="<?php esc_attr_e( 'Select terms', 'sk-core' ); ?>" class="sk_attribute_values sk-select2" name="attribute_values[<?php echo $i; ?>][]">
						<?php
						$args = array(
							'orderby'    => 'name',
							'hide_empty' => 0,
						);
						$all_terms = get_terms( $taxonomy, apply_filters( 'sk_product_attribute_terms', $args ) );
						if ( $all_terms ) {
							foreach ( $all_terms as $term ) { // phpcs:ignore
								echo '<option value="' . esc_attr( $term->slug ) . '" ' . selected( has_term( absint( $term->term_id ), $taxonomy, $thepostid ), true, false ) . '>' . esc_attr( apply_filters( 'woocommerce_product_attribute_term_name', $term->name, $term ) ) . '</option>';
							}
						}
						?>
					</select>
					<div class="sk-pre-defined-attribute-btn-group">
						<button class="sk-btn sk-btn-default plus sk-select-all-attributes"><?php esc_html_e( 'Select all', 'sk-core' ); ?></button>
						<button class="sk-btn sk-btn-default minus sk-select-no-attributes"><?php esc_html_e( 'Select none', 'sk-core' ); ?></button>

						<?php if ( sk_get_option( 'add_new_attribute', 'sk_selling', 'off' ) !== 'off' ) : ?>

						<button class="sk-btn sk-btn-default fr plus sk-add-new-attribute sk-right"><?php esc_html_e( 'Add new', 'sk-core' ); ?></button>

						<?php endif; ?>
					</div>
				<?php elseif ( 'text' === $attribute_taxonomy->attribute_type ) : ?>
					<?php // translators: %s:  WC DELIMITER ?>
                    <select name="attribute_values[<?php echo $i; ?>][]" id="" multiple style="width:100%" class="sk-select2" data-placeholder="<?php echo esc_attr( sprintf( __( 'Enter some text, or some attributes by "%s" separating values.', 'sk-core' ), WC_DELIMITER ) ); ?>" data-tags="true" data-allow-clear="true" data-token-separators="['|']">
                        <?php
                            $attr_val = wp_get_post_terms( $thepostid, $taxonomy, array( 'fields' => 'names' ) );
						if ( $attr_val ) :
							?>
                            <?php foreach ( $attr_val  as $key => $value ) : ?>
                                <option value="<?php echo esc_attr( $value ); ?>" selected><?php echo esc_html( $value ); ?></option>
                            <?php endforeach ?>
                        <?php endif ?>
                    </select>
				<?php endif; ?>

				<?php do_action( 'sk_product_option_terms', $attribute_taxonomy, $i ); ?>

			<?php else : ?>
				<?php // translators: %s:  WC DELIMITER ?>
            	<select name="attribute_values[<?php echo $i; ?>][]" id="" multiple style="width:100%" class="sk-select2" data-placeholder="<?php echo esc_attr( sprintf( __( 'Enter some text, or some attributes by "%s" separating values.', 'sk-core' ), WC_DELIMITER ) ); ?>" data-tags="true" data-allow-clear="true" data-token-separators="['|']" data-values="[ 'Red', 'Green' ]">
                    <?php if ( $attribute['value'] ) : ?>
                        <?php foreach ( explode( WC_DELIMITER, $attribute['value'] )  as $key => $value ) : ?>
                            <option value="<?php echo esc_attr( $value ); ?>" selected><?php echo esc_html( $value ); ?></option>
                        <?php endforeach ?>
                    <?php endif ?>
                </select>
			<?php endif; ?>

        </div>
    </div>
</li>
