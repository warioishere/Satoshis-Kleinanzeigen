<?php
    use SK\Core\ProductCategory\Helper;
?>
<tr class="sk-product-list-inline-edit-form sk-hide" id="post-<?php echo esc_attr( $product_id ); ?>">
    <td colspan="11">
        <fieldset>
            <div class="sk-clearfix">
                <div class="sk-w6 sk-inline-edit-column">
                    <strong class="sk-inline-edit-section-title"><?php esc_html_e( 'Quick Edit', 'sk-core' ); ?></strong>

                    <div class="inline-edit-col sk-clearfix">
                        <label class="sk-w3">
                            <?php esc_html_e( 'Title', 'sk-core' ); ?>
                        </label>
                        <div class="sk-w9">
                            <input type="text" class="sk-form-control" data-field-name="post_title" value="<?php echo esc_html( $post_title ); ?>">
                        </div>
                    </div>

                    <label>
                        <?php esc_html_e( 'Product categories', 'sk-core' ); ?>
                    </label>
                    <?php
                        $data = Helper::get_saved_products_category( $product_id );
                        $data['hide_cat_title'] = 'yes';
                        $data['from'] = 'quick_edit';
                        sk_get_template_part( 'products/sk-category-header-ui', '', $data );
                    ?>
                    <?php
                    /**
                     * Vendor quick edit form after column one ends
                     *
                     *
                     * @param int $product_id
                     */
                    do_action( 'sk_quick_edit_before_column_1_ends', $product_id );
                    ?>
                </div>
                <div class="sk-w6 sk-inline-edit-column">
                    <label>
                        <?php esc_html_e( 'Product Tags', 'sk-core' ); ?>
                    </label>

                    <select multiple="multiple" data-field-name="product_tag" class="product_tag_search product_tags sk-form-control sk-select2" data-placeholder="<?php esc_attr_e( 'Select tags', 'sk-core' ); ?>">
                        <?php if ( ! empty( $product_tag ) ) { ?>
                            <?php foreach ( $product_tag as $tax_term ) { ?>
                                <option value="<?php echo esc_attr( $tax_term->term_id ); ?>" selected="selected" ><?php echo esc_html( $tax_term->name ); ?></option>
                            <?php } ?>
                        <?php } ?>
                    </select>

                    <label>
                        <input type="checkbox" data-field-name="reviews_allowed" value="open" <?php checked( $reviews_allowed, true ); ?>> &nbsp;
                        <?php esc_html_e( 'Enable Reviews', 'sk-core' ); ?>
                    </label>

                    <label>
                        <?php esc_html_e( 'Status', 'sk-core' ); ?>

                        <?php if ( 'pending' === $post_status ) { ?>
                            <span class="sk-label sk-label-danger">
                                <?php esc_html_e( 'Pending Review', 'sk-core' ); ?>
                                <input type="hidden" data-field-name="post_status" value="<?php echo esc_attr( 'pending' ); ?>">
                            </span>
                        <?php } else { ?>
                            <select data-field-name="post_status" style="min-width: 100px;">
                                <?php foreach ( $options['post_statuses'] as $post_status_slug => $post_status_label ) { ?>
                                    <option value="<?php echo esc_attr( $post_status_slug ); ?>" <?php selected( $post_status, $post_status_slug ); ?>>
                                        <?php echo esc_html( $post_status_label ); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        <?php } ?>
                    </label>

                    <hr>

                    <strong class="sk-inline-edit-section-title"><?php esc_html_e( 'Product Data', 'sk-core' ); ?></strong>

                    <?php if ( $options['is_sku_enabled'] ) { ?>
                        <div class="sk-inline-edit-field-row sk-clearfix">
                            <label class="sk-w3">
                                <?php esc_html_e( 'SKU', 'sk-core' ); ?>
                            </label>
                            <div class="sk-w9">
                                <input type="text" class="sk-form-control" data-field-name="sku" value="<?php echo esc_html( $sku ); ?>">
                            </div>
                        </div>
                    <?php } ?>

                    <?php if ( 'simple' === $product_type || 'external' === $product_type || 'subscription' === $product_type ) { ?>
                        <div class="sk-inline-edit-field-row sk-clearfix">
                            <label class="sk-w3">
                                <?php esc_html_e( 'Price', 'sk-core' ); ?>
                            </label>
                            <div class="sk-w9">
                                <input type="text" class="sk-form-control" data-field-name="_regular_price" value="<?php echo esc_html( $_regular_price ); ?>">
                            </div>
                        </div>

                        <div class="sk-inline-edit-field-row sk-clearfix">
                            <label class="sk-w3">
                                <?php esc_html_e( 'Sale', 'sk-core' ); ?>
                            </label>
                            <div class="sk-w9">
                                <input type="text" class="sk-form-control" data-field-name="_sale_price" value="<?php echo esc_html( $_sale_price ); ?>">
                            </div>
                        </div>
                    <?php } ?>

                    <?php if ( $options['is_weight_enabled'] ) { ?>
                        <div class="sk-inline-edit-field-row sk-clearfix">
                            <label class="sk-w3">
                                <?php esc_html_e( 'Weight', 'sk-core' ); ?>
                            </label>
                            <div class="sk-w9">
                                <input type="text" class="sk-form-control" data-field-name="weight" value="<?php echo esc_html( $weight ); ?>">
                            </div>
                        </div>
                    <?php } ?>

                    <?php if ( $options['is_dimensions_enabled'] ) { ?>
                        <div class="sk-inline-edit-field-row sk-clearfix">
                            <label class="sk-w3">
                                <?php esc_html_e( 'L/W/H', 'sk-core' ); ?>
                            </label>
                            <div class="sk-w9 sk-clearfix">
                                <div class="sk-w4">
                                    <input type="text" class="sk-form-control" data-field-name="length" value="<?php echo esc_html( $length ); ?>" placeholder="<?php esc_html_e( 'Length', 'sk-core' ); ?>">
                                </div>
                                <div class="sk-w4">
                                    <input type="text" class="sk-form-control" data-field-name="width" value="<?php echo esc_html( $width ); ?>" placeholder="<?php esc_html_e( 'Width', 'sk-core' ); ?>">
                                </div>
                                <div class="sk-w4">
                                    <input type="text" class="sk-form-control" data-field-name="height" value="<?php echo esc_html( $height ); ?>" placeholder="<?php esc_html_e( 'Height', 'sk-core' ); ?>">
                                </div>
                            </div>
                        </div>
                    <?php } ?>

                    <div class="sk-inline-edit-field-row sk-clearfix">
                        <label class="sk-w3">
                            <?php esc_html_e( 'Visibility', 'sk-core' ); ?>
                        </label>
                        <div class="sk-w9">
                            <select data-field-name="_visibility" class="sk-form-control">
                                <?php foreach ( $options['visibilities'] as $visibility_slug => $visibility_name ) { ?>
                                    <option value="<?php echo esc_attr( $visibility_slug ); ?>"<?php selected( $_visibility, $visibility_slug ); ?>>
                                        <?php echo esc_html( $visibility_name ); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>

                    <hr>

                    <?php if ( ( 'simple' === $product_type || 'variable' === $product_type ) && $options['can_manage_stock'] ) { ?>
                        <label>
                            <input type="checkbox" data-field-name="manage_stock" value="open" data-field-toggler <?php checked( $manage_stock, true ); ?>> &nbsp;
                            <?php esc_html_e( 'Manage Stock', 'sk-core' ); ?>
                        </label>

                        <div class="sk-inline-edit-field-row sk-clearfix<?php echo $manage_stock ? '' : ' sk-hide'; ?>" data-field-toggle="manage_stock" data-field-show-on="true">
                            <label class="sk-w3">
                                <?php esc_html_e( 'Stock Qty', 'sk-core' ); ?>
                            </label>
                            <div class="sk-w9">
                                <input type="text" class="sk-form-control" data-field-name="stock_quantity" value="<?php echo esc_html( $stock_quantity ); ?>">
                            </div>
                        </div>

                        <div class="sk-inline-edit-field-row sk-clearfix<?php echo $manage_stock ? ' sk-hide' : ''; ?>" data-field-toggle="manage_stock" data-field-show-on="false">
                            <label class="sk-w3">
                                <?php esc_html_e( 'In Stock?', 'sk-core' ); ?>
                            </label>
                            <div class="sk-w9">
                                <select data-field-name="stock_status" class="sk-form-control">
                                    <?php foreach ( $options['stock_statuses'] as $stock_status_slug => $stock_status_name ) { ?>
                                        <option value="<?php echo esc_attr( $stock_status_slug ); ?>"<?php selected( $stock_status, $stock_status_slug ); ?>>
                                            <?php echo esc_html( $stock_status_name ); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>

                        <div class="sk-inline-edit-field-row sk-clearfix<?php echo $manage_stock ? '' : ' sk-hide'; ?>" data-field-toggle="manage_stock" data-field-show-on="true">
                            <label class="sk-w3">
                                <?php esc_html_e( 'Backorders?', 'sk-core' ); ?>
                            </label>
                            <div class="sk-w9">
                                <select data-field-name="backorders" class="sk-form-control" style="width: 100%;">
                                    <?php foreach ( $options['backorder_options'] as $backorders_slug => $backorders_name ) { ?>
                                        <option value="<?php echo esc_attr( $backorders_slug ); ?>"<?php selected( $backorders, $backorders_slug ); ?>>
                                            <?php echo esc_html( $backorders_name ); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                    <?php } ?>

                    <?php
                    /**
                     * Action to add custom fields to product quick edit form
                     *
                     *        to be removed at a later release
                     *
                     * @deprecated @3.12.0 3.11.1 for hook naming convention
                     *
                     * @args $product_id int
                     * @args $options array
                     */
                    do_action_deprecated(
                        'sk_quick_edit_before_column2_ends',
                        [ $product_id, $options ],
                        '3.11.1',
                        'sk_quick_edit_before_column_2_ends'
                    );

                    /**
                     * Action to add custom fields to product quick edit form
                     *
                     */
                    do_action( 'sk_quick_edit_before_column_2_ends', $product_id, $options );
                    ?>
                </div>
            </div>

            <div class="sk-clearfix quick-edit-submit-wrap">
                <button type="button" class="sk-btn sk-btn-default inline-edit-cancel">
                    <?php esc_html_e( 'Cancel', 'sk-core' ); ?>
                </button>

                <div class="sk-right inline-edit-submit-button">
                    <div class="sk-spinner"></div>
                    <button type="button" class="sk-btn sk-btn-default sk-btn-theme sk-right inline-edit-update">
                        <?php esc_html_e( 'Update', 'sk-core' ); ?>
                    </button>
                </div>
            </div>
            <?php
                /**
                 * Do any action after product quick edit fields.
                 *
                 * @parm \WC_Product $product Woocommerce product object
                 *
                 */
                do_action( 'sk_after_quick_edit_form_fields', $product_id );
            ?>
            <input type="hidden" data-field-name="ID" value="<?php echo esc_attr( $product_id ); ?>">
            <input type="hidden" data-field-name="product_type" value="<?php echo esc_attr( $product_type ); ?>">
        </fieldset>
    </td>
</tr>
