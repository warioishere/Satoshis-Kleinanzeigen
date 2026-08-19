<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
?>
<div class="woocommerce_variation wc-metabox closed">
    <h3>
        <button type="button" class="remove_variation btn btn-sm btn-danger"
                rel="<?php echo absint( $variation_id ); ?>"><?php esc_html_e( 'Remove', 'sk-core' ); ?>
        </button>
        <div class="handlediv" title="<?php esc_attr_e( 'Click to toggle', 'sk-core' ); ?>"></div>
        <strong>#<?php echo esc_html( $variation_id ); ?> &mdash; </strong>
        <?php
        foreach ( $parent_data['attributes'] as $attribute ) {

            // Only deal with attributes that are variations
            if ( ! $attribute['is_variation'] ) {
                continue;
            }

            // Get current value for variation (if set)
            $variation_selected_value = isset( $variation_data[ 'attribute_' . sanitize_title( $attribute['name'] ) ][0] ) ? $variation_data[ 'attribute_' . sanitize_title( $attribute['name'] ) ][0] : '';

            // Name will be something like attribute_pa_color
            echo '<select name="attribute_' . sanitize_title( $attribute['name'] ) . '[' . $loop . ']"><option value="">' . __( 'Any', 'sk-core' ) . ' ' . esc_html( wc_attribute_label( $attribute['name'] ) ) . '&hellip;</option>';

            // Get terms for attribute taxonomy or value if its a custom attribute
            if ( $attribute['is_taxonomy'] ) {
                $post_terms = wp_get_post_terms( $parent_data['id'], $attribute['name'] );

                foreach ( $post_terms as $term1 ) {
                    echo '<option ' . selected( $variation_selected_value, $term1->slug, false ) . ' value="' . esc_attr( $term1->slug ) . '">' . apply_filters( 'woocommerce_variation_option_name', esc_html( $term1->name ) ) . '</option>';
                }
            } else {
                $options = array_map( 'trim', explode( WC_DELIMITER, $attribute['value'] ) );

                foreach ( $options as $option ) {
                    echo '<option ' . selected( sanitize_title( $variation_selected_value ), sanitize_title( $option ), false ) . ' value="' . esc_attr( sanitize_title( $option ) ) . '">' . esc_html( apply_filters( 'woocommerce_variation_option_name', $option ) ) . '</option>';
                }
            }

            echo '</select>';
        }
        ?>
        <input type="hidden" name="variable_post_id[<?php echo $loop; ?>]" value="<?php echo esc_attr( $variation_id ); ?>"/>
        <input type="hidden" class="variation_menu_order" name="variation_menu_order[<?php echo $loop; ?>]" value="<?php echo $loop; ?>"/>
    </h3>
    <table cellpadding="0" cellspacing="0" class="woocommerce_variable_attributes wc-metabox-content">
        <tbody>
        <tr>
            <td class="sku" colspan="2">
                <?php if ( wc_product_sku_enabled() ) : ?>
                    <input type="text" size="5" name="variable_sku[<?php echo $loop; ?>]"
                            value="<?php echo isset( $_sku ) ? esc_attr( $_sku ) : ''; ?>"
                            placeholder="<?php echo esc_attr( $parent_data['sku'] ); ?>"/>
                    <label><?php esc_html_e( 'SKU', 'sk-core' ); ?>:
                        <a class="tips" title="<?php esc_attr_e( 'Enter a SKU for this variation or leave blank to use the parent product SKU.', 'sk-core' ); ?>" href="#">
                            <span class="dashicons dashicons-editor-help"></span>
                        </a>
                    </label>
                <?php else : ?>
                    <input type="hidden" name="variable_sku[<?php echo $loop; ?>]" value="<?php echo isset( $_sku ) ? esc_attr( $_sku ) : ''; ?>"/>
                <?php endif; ?>
            </td>
            <td class="data" rowspan="2">
                <table cellspacing="0" cellpadding="0" class="data_table">
                    <?php if ( get_option( 'woocommerce_manage_stock' ) === 'yes' ) : ?>
                        <tr class="show_if_variation_manage_stock">
                            <td>
                                <label><?php esc_html_e( 'Stock Qty:', 'sk-core' ); ?>
                                    <a class="tips" title="<?php esc_attr_e( 'Enter a quantity to enable stock management at variation level, or leave blank to use the parent product\'s options.', 'sk-core' ); ?>" href="#">
                                        <span class="dashicons dashicons-editor-help"></span>
                                    </a>
                                </label>
                                <input type="number" size="5" name="variable_stock[<?php echo $loop; ?>]" value="<?php echo isset( $_stock ) ? wc_stock_amount( $_stock ) : ''; ?>" step="any"/>
                            </td>
                            <td>
                                <label><?php esc_html_e( 'Allow Backorders?', 'sk-core' ); ?></label>
                                <select name="variable_backorders[<?php echo $loop; ?>]">
                                    <option
                                        value="no" <?php selected( $_backorders, 'no' ); ?>><?php esc_html_e( 'Do not allow', 'sk-core' ); ?></option>
                                    <option
                                        value="notify" <?php selected( $_backorders, 'notify' ); ?>><?php esc_html_e( 'Allow but notify customer', 'sk-core' ); ?></option>
                                    <option
                                        value="yes" <?php selected( $_backorders, 'yes' ); ?>><?php esc_html_e( 'Allow', 'sk-core' ); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <label><?php esc_html_e( 'Stock status', 'sk-core' ); ?> <a href="#" class="tips" title="<?php esc_attr_e( 'Controls whether or not the product is listed as "in stock" or "out of stock" on the frontend.', 'sk-core' ); ?>"><span class="dashicons dashicons-editor-help"></span></a></label>
                                <select name="variable_stock_status[<?php echo $loop; ?>]">
                                    <option
                                        value="instock" <?php selected( $_stock_status, 'instock' ); ?>><?php esc_html_e( 'In stock', 'sk-core' ); ?></option>
                                    <option
                                        value="outofstock" <?php selected( $_stock_status, 'outofstock' ); ?>><?php esc_html_e( 'Out of stock', 'sk-core' ); ?></option>
                                </select>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <tr class="variable_pricing">
                        <td>
                            <label><?php echo __( 'Regular Price:', 'sk-core' ) . ' (' . get_woocommerce_currency_symbol() . ')'; ?></label>
                            <input type="text" size="5" name="variable_regular_price[<?php echo $loop; ?>]" value="<?php echo isset( $_regular_price ) ? esc_attr( $_regular_price ) : ''; ?>
                                " class="wc_input_price"
                                    placeholder="<?php esc_attr_e( 'Variation price (required)', 'sk-core' ); ?>"/>
                        </td>
                        <td>
                            <label><?php esc_html_e( 'Sale Price:', 'sk-core' ) . ' (' . get_woocommerce_currency_symbol() . ')'; ?>
                                <a href="#" class="sale_schedule"><?php esc_html_e( 'Schedule', 'sk-core' ); ?></a>
                                <a href="#" class="cancel_sale_schedule" style="display:none"><?php esc_html_e( 'Cancel schedule', 'sk-core' ); ?></a>
                            </label>
                            <input type="text" size="5" name="variable_sale_price[<?php echo $loop; ?>]" value="<?php echo isset( $_sale_price ) ? esc_attr( $_sale_price ) : ''; ?>" class="wc_input_price"/>
                        </td>
                    </tr>

                    <tr class="sale_price_dates_fields" style="display:none">
                        <td>
                            <label><?php esc_html_e( 'Sale start date:', 'sk-core' ); ?></label>
                            <input type="text" class="sale_price_dates_from"
                                    name="variable_sale_price_dates_from[<?php echo $loop; ?>]"
                                    value="<?php echo ! empty( $_sale_price_dates_from ) ? date_i18n( 'Y-m-d', $_sale_price_dates_from ) : ''; ?>"
                                    placeholder="<?php echo _x( 'From&hellip; YYYY-MM-DD', 'placeholder', 'sk-core' ); ?>"
                                    maxlength="10" pattern="[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])"/>
                        </td>
                        <td>
                            <label><?php esc_html_e( 'Sale end date:', 'sk-core' ); ?></label>
                            <input type="text" name="variable_sale_price_dates_to[<?php echo $loop; ?>]"
                                    value="<?php echo ! empty( $_sale_price_dates_to ) ? date_i18n( 'Y-m-d', $_sale_price_dates_to ) : ''; ?>"
                                    placeholder="<?php echo _x( 'To&hellip; YYYY-MM-DD', 'placeholder', 'sk-core' ); ?>"
                                    maxlength="10" pattern="[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])"/>
                        </td>
                    </tr>

                    <?php if ( wc_product_weight_enabled() || wc_product_dimensions_enabled() ) : ?>
                        <tr>
                            <?php if ( wc_product_weight_enabled() ) : ?>
                                <td class="hide_if_variation_virtual">
                                    <label><?php echo esc_html__( 'Weight', 'sk-core' ) . ' (' . esc_html( get_option( 'woocommerce_weight_unit' ) ) . '):'; ?>
                                        <a class="tips"
                                            title="<?php esc_attr_e( 'Enter a weight for this variation or leave blank to use the parent product weight.', 'sk-core' ); ?>" href="#">
                                            <span class="dashicons dashicons-editor-help"></span>
                                        </a>
                                    </label>
                                    <input type="text" size="5" name="variable_weight[<?php echo $loop; ?>]" value="<?php echo isset( $_weight ) ? esc_attr( $_weight ) : ''; ?>" placeholder="<?php echo esc_attr( $parent_data['weight'] ); ?>" class="wc_input_decimal"/>
                                </td>
                            <?php else : ?>
                                <td>&nbsp;</td>
                            <?php endif; ?>
                            <?php if ( wc_product_dimensions_enabled() ) : ?>
                                <td class="dimensions_field hide_if_variation_virtual">
                                    <label for="product_length"><?php echo __( 'Dimensions (L&times;W&times;H)', 'sk-core' ) . ' (' . esc_html( get_option( 'woocommerce_dimension_unit' ) ) . '):'; ?></label>
                                    <input id="product_length" class="input-text wc_input_decimal" size="6" type="text" name="variable_length[<?php echo $loop; ?>]" value="<?php echo isset( $_length ) ? esc_attr( $_length ) : ''; ?>" placeholder="<?php echo esc_attr( $parent_data['length'] ); ?>"/>
                                    <input class="input-text wc_input_decimal" size="6" type="text" name="variable_width[<?php echo $loop; ?>]" value="<?php echo isset( $_width ) ? esc_attr( $_width ) : ''; ?>" placeholder="<?php echo esc_attr( $parent_data['width'] ); ?>"/>
                                    <input class="input-text wc_input_decimal last" size="6" type="text" name="variable_height[<?php echo $loop; ?>]" value="<?php echo isset( $_height ) ? esc_attr( $_height ) : ''; ?>" placeholder="<?php echo esc_attr( $parent_data['height'] ); ?>"/>
                                </td>
                            <?php else : ?>
                                <td>&nbsp;</td>
                            <?php endif; ?>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <td>
                            <?php if ( get_option( 'woocommerce_calc_taxes' ) === 'yes' ) : ?>
                                <label><?php esc_html_e( 'Tax class:', 'sk-core' ); ?></label>
                                <select name="variable_tax_class[<?php echo $loop; ?>]">
                                    <option
                                        value="parent" <?php selected( is_null( $_tax_class ), true ); ?>><?php esc_html_e( 'Same as parent', 'sk-core' ); ?></option>
                                    <?php
                                    foreach ( $parent_data['tax_class_options'] as $key => $value ) {
                                        echo '<option value="' . esc_attr( $key ) . '" ' . selected( $key === $_tax_class, true, false ) . '>' . esc_html( $value ) . '</option>';
                                    }
                                    ?>
                                </select>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr class="show_if_variation_downloadable" style="display:none">
                        <td colspan="2">
                            <div class="form-field downloadable_files">
                                <label><?php esc_html_e( 'Downloadable Files', 'sk-core' ); ?>:</label>
                                <table class="widefat">
                                    <thead>
                                    <tr>
                                        <td>
                                            <?php esc_html_e( 'Name', 'sk-core' ); ?>
                                            <span
                                                class="tips"
                                                title="<?php esc_attr_e( 'This is the name of the download shown to the customer.', 'sk-core' ); ?>">
                                                <span class="dashicons dashicons-editor-help"></span>
                                            </span>
                                        </td>
                                        <td colspan="2"><?php esc_html_e( 'File URL', 'sk-core' ); ?> <span class="tips" title="<?php esc_attr_e( 'This is the URL or absolute path to the file which customers will get access to.', 'sk-core' ); ?>"><span class="dashicons dashicons-editor-help"></span></span></td>
                                        <td>&nbsp;</td>
                                    </tr>
                                    </thead>
                                    <tfoot>
                                    <tr>
                                        <th colspan="4">
                                            <a href="#" class="insert-file-row btn btn-sm btn-success" data-row="
                                                    <?php
													$file = array(
														'file' => '',
														'name' => '',
													);
													ob_start();
													require 'html-product-variation-download.php';
													echo esc_attr( ob_get_clean() );
													?>
                                                    "><?php esc_html_e( 'Add File', 'sk-core' ); ?>
                                            </a>
                                        </th>
                                    </tr>
                                    </tfoot>
                                    <tbody>
                                    <?php
                                    if ( $_downloadable_files ) {
                                        foreach ( $_downloadable_files as $key => $file ) {
                                            if ( ! is_array( $file ) ) {
                                                $file = array(
                                                    'file' => $file,
                                                    'name' => '',
                                                );
                                            }
                                            include 'html-product-variation-download.php';
                                        }
                                    }
                                    ?>
                                    </tbody>
                                </table>
                            </div>
                        </td>
                    </tr>
                    <tr class="show_if_variation_downloadable">
                        <td>
                            <div>
                                <label><?php esc_html_e( 'Download Limit:', 'sk-core' ); ?> <a class="tips" title="<?php esc_attr_e( 'Leave blank for unlimited re-downloads.', 'sk-core' ); ?>" href="#"><span class="dashicons dashicons-editor-help"></span></a></label>
                                <input type="text" size="5" name="variable_download_limit[<?php echo $loop; ?>]" value="<?php echo isset( $_download_limit ) ? esc_attr( $_download_limit ) : ''; ?>" placeholder="<?php esc_attr_e( 'Unlimited', 'sk-core' ); ?>"/>
                            </div>
                        </td>
                        <td>
                            <div>
                                <label><?php esc_html_e( 'Download Expiry:', 'sk-core' ); ?> <a class="tips" title="<?php esc_attr_e( 'Enter the number of days before a download link expires, or leave blank.', 'sk-core' ); ?>" href="#"><span class="dashicons dashicons-editor-help"></span></a></label>
                                <input type="text" size="5" name="variable_download_expiry[<?php echo $loop; ?>]"
                                        value="<?php echo isset( $_download_expiry ) ? esc_attr( $_download_expiry ) : ''; ?>" placeholder="<?php esc_attr_e( 'Unlimited', 'sk-core' ); ?>"/>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <div>
                                <p class="sk-form-group">
                                    <label><?php esc_html_e( 'Variation description', 'sk-core' ); ?></label>
                                    <textarea class="sk-form-control" name="variable_description[<?php echo $loop; ?>]" rows="3" style="width:100%;"><?php echo isset( $_variation_description ) ? esc_textarea( $_variation_description ) : ''; ?></textarea>
                                </p>
                            </div>
                        </td>
                    </tr>
                    <?php do_action( 'woocommerce_product_after_variable_attributes', $loop, $variation_data, $variation ); ?>
                </table>
            </td>
        </tr>
        <tr>
            <td class="upload_image">
                <a href="#" class="upload_image_button <?php echo ( $image_id > 0 ) ? 'remove' : ''; ?>" rel="<?php echo absint( $variation_id ); ?>">
                    <img src="<?php echo ! empty( $image ) ? esc_url_raw( $image ) : esc_url_raw( wc_placeholder_img_src() ); ?>"/>
                    <input type="hidden" name="upload_image_id[<?php echo absint( $loop ); ?>]" class="upload_image_id" value="<?php echo esc_attr( $image_id ); ?>"/>
                    <span class="overlay"></span>
                </a>
            </td>
            <td class="options">
                <label class="checkbox"><input type="checkbox" class="checkbox" name="variable_enabled[<?php echo $loop; ?>]" <?php checked( $variation_post_status, 'publish' ); ?> /> <?php esc_html_e( 'Enabled', 'sk-core' ); ?></label>

                <label class="checkbox"><input type="checkbox" class="checkbox variable_is_downloadable" name="variable_is_downloadable[<?php echo $loop; ?>]" <?php checked( isset( $_downloadable ) ? $_downloadable : '', 'yes' ); ?> /> <?php esc_html_e( 'Downloadable', 'sk-core' ); ?>
                    <a class="tips"
                        title="<?php esc_attr_e( 'Enable this option if access will be given to a downloadable file upon the purchase of a product', 'sk-core' ); ?>"
                        href="#"><span class="dashicons dashicons-editor-help"></span></a>
                </label>

                <label class="checkbox"><input type="checkbox" class="checkbox variable_is_virtual" name="variable_is_virtual[<?php echo $loop; ?>]" <?php checked( isset( $_virtual ) ? $_virtual : '', 'yes' ); ?> /> <?php esc_html_e( 'Virtual', 'sk-core' ); ?>
                    <a class="tips"
                        title="<?php esc_attr_e( 'Enable this option if a product is not shipped or there is no shipping cost', 'sk-core' ); ?>" href="#">
                        <span class="dashicons dashicons-editor-help"></span>
                    </a>
                </label>

                <label class="checkbox"><input type="checkbox" class="checkbox variable_manage_stock" name="variable_manage_stock[<?php echo $loop; ?>]" <?php checked( isset( $_manage_stock ) ? $_manage_stock : '', 'yes' ); ?> /> <?php esc_html_e( 'Manage Stock?', 'sk-core' ); ?>
                    <a class="tips"
                        title="<?php esc_attr_e( 'Enable this option to enable stock management at variation level', 'sk-core' ); ?>" href="#">
                        <span class="dashicons dashicons-editor-help"></span>
                    </a>
                </label>

                <?php do_action( 'woocommerce_variation_options', $loop, $variation_data, $variation ); ?>
            </td>
        </tr>
        </tbody>
    </table>
</div>
