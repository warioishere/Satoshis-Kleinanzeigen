<?php
/**
 * SK Dashboard Product shipping Content
 *
 *
 */
?>

<?php do_action( 'sk_product_options_shipping_before', $post_id ); ?>
<?php
$sk_shipping_option  = get_option( 'woocommerce_sk_product_shipping_settings' );
$sk_shipping_enabled = ( isset( $sk_shipping_option['enabled'] ) ) ? $sk_shipping_option['enabled'] : 'yes';
$sk_shipping_enabled = $sk_shipping_enabled === 'yes' ? true : false;
$store_shipping_enabled = get_user_meta( get_current_user_id(), '_dps_shipping_enable', true ) === 'yes' ? true : false;
$wc_shipping_enabled    = get_option( 'woocommerce_calc_shipping' ) === 'yes' ? true : false;
$wc_tax_enabled         = get_option( 'woocommerce_calc_taxes' ) === 'yes' ? true : false;
$tab_title              = $is_shipping_disabled ? __( 'Tax', 'sk' ) : __( 'Shipping and Tax', 'sk' );
$tab_desc               = $is_shipping_disabled ? __( 'Manage tax for this product', 'sk' ) : __( 'Manage shipping and tax for this product', 'sk' );
?>
<?php if ( ( $wc_shipping_enabled && ! $is_shipping_disabled ) || $wc_tax_enabled ) : ?>
<div class="hide_if_variable-subscription sk-product-shipping-tax hide_if_grouped hide_if_external sk-edit-row sk-clearfix sk-border-top <?php echo ! $wc_shipping_enabled ? 'woocommerce-no-shipping' : ''; ?> <?php echo ! $wc_tax_enabled ? 'woocommerce-no-tax' : ''; ?> ">
    <div class="sk-section-heading" data-togglehandler="sk_product_shipping_tax">
        <h2 class="hide_if_virtual"><i class="fas fa-truck" aria-hidden="true"></i> <?php echo esc_html( $tab_title ); ?></h2>
        <h2 class="show_if_virtual"><i class="fas fa-truck" aria-hidden="true"></i> <?php esc_html_e( 'Tax', 'sk' ); ?></h2>
        <p class="hide_if_virtual"><?php echo esc_html( $tab_desc ); ?></p>
        <p class="show_if_virtual"><?php esc_html_e( 'Manage tax for this product', 'sk' ); ?></p>
        <a href="#" class="sk-section-toggle">
            <i class="fas fa-sort-down fa-flip-vertical" aria-hidden="true"></i>
        </a>
        <div class="sk-clearfix"></div>
    </div>

    <div class="sk-section-content">
        <?php if ( $wc_shipping_enabled && ! $is_shipping_disabled ) : ?>
            <div class="sk-clearfix sk-shipping-container hide_if_virtual">
                <input type="hidden" name="product_shipping_class" value="0">
                <div class="sk-form-group">
                    <label class="sk-checkbox-inline" for="_disable_shipping">
                        <input type="hidden" name="_disable_shipping" value="yes">
                        <input type="checkbox" id="_disable_shipping" name="_disable_shipping" value="no" <?php checked( $_disable_shipping, 'no' ); ?>>
                        <?php esc_html_e( 'This product requires shipping', 'sk' ); ?>
                    </label>
                </div>

                <div class="show_if_needs_shipping sk-shipping-dimention-options">
                    <?php
                    sk_post_input_box(
                        $post_id, '_weight', array(
                            'class' => 'sk-form-control',
                            // translators: %s: Show weight
                            'placeholder' => sprintf( __( 'weight (%s)', 'sk' ), esc_html( get_option( 'woocommerce_weight_unit' ) ) ),
                        ), 'number'
                    );
                    ?>
                    <?php
                    sk_post_input_box(
                        $post_id, '_length', array(
                            'class' => 'sk-form-control',
                            // translators: %s: Show length
                            'placeholder' => sprintf( __( 'length (%s)', 'sk' ), esc_html( get_option( 'woocommerce_dimension_unit' ) ) ),
                        ), 'number'
                    );
                    ?>
                    <?php
                    sk_post_input_box(
                        $post_id, '_width', array(
                            'class' => 'sk-form-control',
                            // translators: %s: Show width
                            'placeholder' => sprintf( __( 'width (%s)', 'sk' ), esc_html( get_option( 'woocommerce_dimension_unit' ) ) ),
                        ), 'number'
                    );
                    ?>
                    <?php
                    sk_post_input_box(
                        $post_id, '_height', array(
                            'class' => 'sk-form-control',
                            // translators: %s: Show height
                            'placeholder' => sprintf( __( 'height (%s)', 'sk' ), esc_html( get_option( 'woocommerce_dimension_unit' ) ) ),
                        ), 'number'
                    );
                    ?>
                    <div class="sk-clearfix"></div>
                </div>

                <?php if ( $post_id ) : ?>
                    <?php do_action( 'sk_product_options_shipping' ); ?>
                <?php endif; ?>
                <div class="show_if_needs_shipping sk-form-group">
                    <label class="control-label" for="product_shipping_class"><?php esc_html_e( 'Shipping Class', 'sk' ); ?></label>
                    <div class="sk-text-left">
                        <?php
                        // Shipping Class
                        $classes                = get_the_terms( $post->ID, 'product_shipping_class' );
                        $shipping_settings_link = sprintf( "<a href='%s'>", sk_get_navigation_url( 'settings/shipping', true ) );

                        /* translators: %1$s is replaced with "HTML open entities", %2$s is replaced with "HTML close entities"*/
                        $product_shipping_help_block = sprintf( esc_html__( 'Shipping classes are used by certain shipping methods to group similar products. Before adding a product, please configure the %1$s shipping settings %2$s', 'sk' ), $shipping_settings_link, '</a>' );

                        if ( $classes && ! is_wp_error( $classes ) ) {
                            $current_shipping_class = current( $classes )->term_id;
                        } else {
                            $current_shipping_class = '';
                        }

                        $args = array(
                            'taxonomy'         => 'product_shipping_class',
                            'hide_empty'       => 0,
                            'show_option_none' => sprintf( __( 'No shipping class (%s0)', 'sk' ), get_woocommerce_currency_symbol() ),
                            'name'             => 'product_shipping_class',
                            'id'               => 'product_shipping_class',
                            'selected'         => $current_shipping_class,
                            'class'            => 'sk-form-control',
                        );
                        ?>

                        <?php wp_dropdown_categories( $args ); ?>
                        <p class="help-block"><?php echo $product_shipping_help_block; ?></p>
                    </div>
                </div>
                <?php if ( $sk_shipping_enabled && $store_shipping_enabled ) : ?>
                    <div class="show_if_needs_shipping sk-shipping-product-options">

                        <div class="sk-form-group">
                            <?php sk_post_input_box( $post_id, '_overwrite_shipping', array( 'label' => __( 'Override your store\'s default shipping cost for this product', 'sk' ) ), 'checkbox' ); ?>
                        </div>

                        <div class="sk-additional-shipping-wrap show_if_override">
                            <div class="sk-form-group sk-w3">
                                <label class="sk-control-label" for="_additional_product_price"><?php esc_html_e( 'Additional cost', 'sk' ); ?></label>
                                <input id="_additional_product_price" value="<?php echo $_additional_price; ?>" name="_additional_price" placeholder="9.99" class="sk-form-control" type="number" step="any">
                            </div>

                            <div class="sk-form-group sk-w3">
                                <label class="sk-control-label" for="dps_additional_qty"><?php esc_html_e( 'Per Qty Additional Price', 'sk' ); ?></label>
                                <input id="additional_qty" value="<?php echo ( $_additional_qty ) ? $_additional_qty : $dps_additional_qty; ?>" name="_additional_qty" placeholder="1.99" class="sk-form-control" type="number" step="any">
                            </div>

                            <div class="sk-form-group sk-w3 last-child">
                                <label class="sk-control-label" for="_dps_processing_time"><?php esc_html_e( 'Processing Time', 'sk' ); ?></label>
                                <select name="_dps_processing_time" id="_dps_processing_time" class="sk-form-control">
                                    <?php foreach ( $processing_time as $processing_key => $processing_value ) : ?>
                                        <option value="<?php echo $processing_key; ?>" <?php selected( $porduct_shipping_pt, $processing_key ); ?>><?php echo $processing_value; ?></option>
                                    <?php endforeach ?>
                                </select>
                            </div>
                            <div class="sk-clearfix"></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ( $wc_tax_enabled ) { ?>
        <div class="sk-clearfix sk-tax-container show_if_variable show_if_simple show_if_subscription">
            <div class="sk-tax-product-options">
                <div class="sk-form-group content-half-part">
                    <label class="sk-control-label" for="_tax_status"><?php esc_html_e( 'Tax Status', 'sk' ); ?></label>
                    <div class="sk-text-left">
                        <?php
                        sk_post_input_box(
                            $post_id, '_tax_status', array(
                                'options' => array(
                                    'taxable'   => __( 'Taxable', 'sk' ),
                                    'shipping'  => __( 'Shipping only', 'sk' ),
                                    'none'      => _x( 'None', 'Tax status', 'sk' ),
                                ),
                            ), 'select'
                        );
                        ?>
                    </div>
                </div>

                <div class="sk-form-group content-half-part">
                    <label class="sk-control-label" for="_tax_class"><?php esc_html_e( 'Tax Class', 'sk' ); ?></label>
                    <div class="sk-text-left">
                        <?php sk_post_input_box( $post_id, '_tax_class', array( 'options' => $classes_options ), 'select' ); ?>
                    </div>
                </div>

                <div class="sk-clearfix"></div>
            </div>
        </div>
        <?php } ?>
    </div><!-- .sk-side-right -->
</div><!-- .sk-product-inventory -->
<?php endif; ?>

<?php do_action( 'sk_product_edit_after_shipping', $post_id ); ?>
