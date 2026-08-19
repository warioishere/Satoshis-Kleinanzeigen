<?php
global $post;

if ( class_exists( 'WC_Tax' ) ) {
    $tax_classes = WC_Tax::get_tax_classes();
} else {
    $tax_classes = array_filter( array_map( 'trim', explode( "\n", get_option( 'woocommerce_tax_classes' ) ) ) );
}

$classes_options     = [];
$classes_options[''] = __( 'Standard', 'sk-core' );

if ( $tax_classes ) {
    foreach ( $tax_classes as $class ) {
        $classes_options[ sanitize_title( $class ) ] = esc_html( $class );
    }
}

?>
<div class="sk-form-horizontal">
    <div class="sk-form-group">
        <label class="sk-w4 sk-control-label" for="_sku"><?php _e( 'SKU', 'sk-core' ); ?></label>
        <div class="sk-w4 sk-text-left">
            <?php sk_post_input_box( $post->ID, '_sku', array( 'placeholder' => __( 'SKU', 'sk-core' ) ) ); ?>
        </div>
    </div>

    <div class="sk-form-group">
        <label class="sk-w4 sk-control-label" for=""><?php _e( 'Manage Stock?', 'sk-core' ); ?></label>
        <div class="sk-w6 sk-text-left">
            <?php sk_post_input_box( $post->ID, '_manage_stock', array('label' => __( 'Enable stock management at product level', 'sk-core' ) ), 'checkbox' ); ?>
        </div>
    </div>

    <div class="sk-form-group show_if_stock">
        <label class="sk-w4 sk-control-label" for="_stock_qty"><?php _e( 'Stock Qty', 'sk-core' ); ?></label>
        <div class="sk-w4 sk-text-left">
            <input type="number" name="_stock" id="_stock" step="any" placeholder="10" value="<?php echo wc_stock_amount( get_post_meta( $post->ID, '_stock', true ) ); ?>">
        </div>
    </div>

    <div class="sk-form-group hide_if_variable <?php echo ( $product_type == 'simple' ) ? 'show_if_stock' : ''; ?>">
        <label class="sk-w4 sk-control-label" for="_stock_status"><?php _e( 'Stock Status', 'sk-core' ); ?></label>
        <div class="sk-w4 sk-text-left">
            <?php sk_post_input_box( $post->ID, '_stock_status', array( 'options' => array(
                'instock' => __( 'In Stock', 'sk-core' ),
                'outofstock' => __( 'Out of Stock', 'sk-core' )
                ) ), 'select'
            ); ?>
        </div>
    </div>

    <div class="sk-form-group show_if_stock">
        <label class="sk-w4 sk-control-label" for="_backorders"><?php _e( 'Allow Backorders', 'sk-core' ); ?></label>
        <div class="sk-w4 sk-text-left">
            <?php sk_post_input_box( $post->ID, '_backorders', array( 'options' => array(
                'no' => __( 'Do not allow', 'sk-core' ),
                'notify' => __( 'Allow but notify customer', 'sk-core' ),
                'yes' => __( 'Allow', 'sk-core' )
                ) ), 'select'
            ); ?>
        </div>
    </div>

    <?php if ( 'yes' == get_option( 'woocommerce_calc_taxes' ) ) { ?>

        <div class="sk-form-group">
            <label class="sk-w4 sk-control-label" for="_tax_status"><?php _e( 'Tax Status', 'sk-core' ); ?></label>
            <div class="sk-w4 sk-text-left">
                <?php sk_post_input_box( $post->ID, '_tax_status', array( 'options' => array(
                    'taxable'   => __( 'Taxable', 'sk-core' ),
                    'shipping'  => __( 'Shipping only', 'sk-core' ),
                    'none'      => _x( 'None', 'Tax status', 'sk-core' )
                    ) ), 'select'
                ); ?>
            </div>
        </div>

        <div class="sk-form-group">
            <label class="sk-w4 sk-control-label" for="_tax_class"><?php _e( 'Tax Class', 'sk-core' ); ?></label>
            <div class="sk-w4 sk-text-left">
                <?php sk_post_input_box( $post->ID, '_tax_class', array( 'options' => $classes_options ), 'select' ); ?>
            </div>
        </div>

    <?php } ?>
</div> <!-- .form-horizontal -->