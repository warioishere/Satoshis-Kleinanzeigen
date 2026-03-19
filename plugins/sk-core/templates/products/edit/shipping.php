<?php
global $post;

$user_id                 = get_current_user_id();
$processing_time         = sk_get_shipping_processing_times();

$sk_shipping_option   = get_option( 'woocommerce_sk_product_shipping_settings' );
$sk_shipping_enabled  = ( isset( $sk_shipping_option['enabled'] ) ) ? $sk_shipping_option['enabled'] : 'yes';
$store_shipping          = get_user_meta( get_current_user_id(), '_dps_shipping_enable', true );
$_disable_shipping       = get_post_meta( $post_id, '_disable_shipping', true ) ? get_post_meta( $post_id, '_disable_shipping', true ) : 'no';
$_additional_price       = get_post_meta( $post->ID, '_additional_price', true );
$_additional_qty         = get_post_meta( $post->ID, '_additional_qty', true );
$_processing_time        = get_post_meta( $post->ID, '_dps_processing_time', true );

$dps_shipping_type_price = get_user_meta( $user_id, '_dps_shipping_type_price', true );
$dps_additional_qty      = get_user_meta( $user_id, '_dps_additional_qty', true );
$dps_pt                  = get_user_meta( $user_id, '_dps_pt', true );
$porduct_shipping_pt     = ( $_processing_time ) ? $_processing_time : $dps_pt;
?>

<?php do_action( 'sk_product_options_shipping_before' ); ?>

<div class="sk-form-horizontal sk-product-shipping">
    <input type="hidden" name="product_shipping_class" value="0">
    <?php if ( 'yes' === get_option( 'woocommerce_calc_shipping' ) ) : ?>
        <div class="sk-form-group">
            <label class="sk-w4 sk-control-label" for="_disable_shipping"><?php esc_html_e( 'Disable Shipping', 'sk' ); ?></label>
            <div class="sk-w8 sk-text-left">
                <input type="checkbox" id="_disable_shipping" name="_disable_shipping"  value="yes" <?php checked( $_disable_shipping, 'yes' ); ?>>
                <?php esc_html_e( 'Disable shipping for this product', 'sk' ); ?>
            </div>
        </div>
    <?php endif ?>

    <div class="sk-form-group">
        <label class="sk-w4 sk-control-label" for="_backorders"><?php echo __( 'Weight', 'sk' ) . ' (' . get_option( 'woocommerce_weight_unit' ) . ')'; ?></label>
        <div class="sk-w4 sk-text-left">
            <?php sk_post_input_box( $post->ID, '_weight' ); ?>
        </div>
    </div>

    <div class="sk-form-group">
        <label class="sk-w4 sk-control-label" for="_backorders"><?php echo __( 'Dimensions', 'sk' ) . ' (' . get_option( 'woocommerce_dimension_unit' ) . ')'; ?></label>
        <div class="sk-w8 sk-text-left product-dimension">
            <?php
            sk_post_input_box(
                $post->ID,
                '_length',
                array(
                    'class'       => 'form-control col-sm-1',
                    'placeholder' => esc_html__( 'length', 'sk' ),
                ),
                'number'
            );
            sk_post_input_box(
                $post->ID,
                '_width',
                array(
                    'class'       => 'form-control col-sm-1',
                    'placeholder' => esc_html__( 'width', 'sk' ),
                ),
                'number'
            );
            sk_post_input_box(
                $post->ID,
                '_height',
                array(
                    'class'       => 'form-control col-sm-1',
                    'placeholder' => esc_html__( 'height', 'sk' ),
                ),
                'number'
            );
            ?>
        </div>
    </div>

    <?php if ( 'yes' === get_option( 'woocommerce_calc_shipping' ) ) : ?>
        <div class="hide_if_disable">

            <div class="sk-form-group">
                <label class="sk-w4 sk-control-label" for="product_shipping_class">
                    <?php esc_html__( 'Shipping Class', 'sk' ); ?>
                </label>
                <div class="sk-w4 sk-text-left">
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
                        'class'            => 'sk-form-control'
                    );
                    ?>

                    <?php wp_dropdown_categories( $args ); ?>
                    <p class="help-block"><?php echo $product_shipping_help_block; ?></p>
                </div>
            </div>

            <?php if ( $sk_shipping_enabled === 'yes' && $store_shipping === 'yes' ) : ?>
                <div class="sk-form-group hide_if_disable">
                    <label class="sk-w4 sk-control-label" for="_overwrite_shipping"><?php _e( 'Override Shipping', 'sk' ); ?></label>
                    <div class="sk-w8 sk-text-left">
                        <?php sk_post_input_box( $post->ID, '_overwrite_shipping', array( 'label' => __( 'Override your store\'s default shipping cost for this product', 'sk' ) ), 'checkbox' ); ?>
                    </div>
                </div>

                <div class="sk-form-group sk-shipping-price sk-shipping-type-price show_if_override">
                    <label class="sk-w4 sk-control-label" for="shipping_type_price"><?php _e( 'Additional cost', 'sk' ); ?></label>

                    <div class="sk-w4 sk-text-left">
                        <input id="shipping_type_price" value="<?php echo $_additional_price; ?>" name="_additional_price" placeholder="0.00" class="sk-form-control" type="number" step="any">
                    </div>
                </div>

                <div class="sk-form-group sk-shipping-price sk-shipping-add-qty show_if_override">
                    <label class="sk-w4 sk-control-label" for="dps_additional_qty"><?php _e( 'Per Qty Additional Price', 'sk' ); ?></label>

                    <div class="sk-w4 sk-text-left">
                        <input id="additional_qty" value="<?php echo ( $_additional_qty ) ? $_additional_qty : $dps_additional_qty; ?>" name="_additional_qty" placeholder="1.99" class="sk-form-control" type="number" step="any">
                    </div>
                </div>

                <div class="sk-form-group sk-shipping-price sk-shipping-add-qty show_if_override">
                    <label class="sk-w4 sk-control-label" for="dps_additional_qty"><?php _e( 'Processing Time', 'sk' ); ?></label>

                    <div class="sk-w4 sk-text-left">
                        <select name="_dps_processing_time" id="_dps_processing_time" class="sk-form-control">
                            <?php foreach ( $processing_time as $processing_key => $processing_value ): ?>
                                <option value="<?php echo $processing_key; ?>" <?php selected( $porduct_shipping_pt, $processing_key ); ?>><?php echo $processing_value; ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    <?php endif ?>

    <?php do_action( 'sk_product_options_shipping' ); ?>
</div>
