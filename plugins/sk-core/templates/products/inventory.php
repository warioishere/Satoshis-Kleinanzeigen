<div class="sk-product-inventory sk-edit-row <?php echo esc_attr( $class ); ?>">
    <div class="sk-section-heading" data-togglehandler="sk_product_inventory">
        <h2><i class="fas fa-cubes" aria-hidden="true"></i> <?php esc_html_e( 'Inventory', 'sk-core' ); ?></h2>
        <p><?php esc_html_e( 'Manage inventory for this product.', 'sk-core' ); ?></p>
        <div class="sk-clearfix"></div>
    </div>

    <div class="sk-section-content">

        <div class="content-half-part sk-form-group">
            <label for="_sku" class="form-label"><?php esc_html_e( 'SKU', 'sk-core' ); ?> <span><?php esc_html_e( '(Stock Keeping Unit)', 'sk-core' ); ?></span></label>
            <?php sk_post_input_box( $post_id, '_sku' ); ?>
        </div>

        <div class="content-half-part hide_if_stock_global">
            <label for="_stock_status" class="form-label"><?php esc_html_e( 'Stock Status', 'sk-core' ); ?></label>

            <?php
            sk_post_input_box(
                $post_id,
                '_stock_status',
                [
                    'options' => [
                        'instock'     => __( 'In Stock', 'sk-core' ),
                        'outofstock'  => __( 'Out of Stock', 'sk-core' ),
                        'onbackorder' => __( 'On Backorder', 'sk-core' ),
                    ],
                ],
                'select'
            );
            ?>
        </div>

        <div class="sk-clearfix"></div>

        <?php if ( 'yes' === get_option( 'woocommerce_manage_stock' ) ) : ?>
        <div class="sk-form-group">
            <?php sk_post_input_box( $post_id, '_manage_stock', array( 'label' => __( 'Enable product stock management', 'sk-core' ) ), 'checkbox' ); ?>
        </div>

        <div class="show_if_stock sk-stock-management-wrapper sk-form-group sk-clearfix">

            <div class="content-half-part">
                <label for="_stock" class="form-label"><?php esc_html_e( 'Stock quantity', 'sk-core' ); ?></label>
                <input type="number" class="sk-form-control" name="_stock" placeholder="<?php esc_attr_e( '1', 'sk-core' ); ?>" value="<?php echo esc_attr( wc_stock_amount( $_stock ) ); ?>" min="0" step="1">
                <!-- Hidden field to store the original stock value at the time the page loads -->
                <input type="hidden" name="_original_stock" value="<?php echo esc_attr( wc_stock_amount( $_stock ) ); ?>">
            </div>

            <?php if ( version_compare( WC_VERSION, '3.4.7', '>' ) ) : ?>
            <div class="content-half-part">
                <label for="_low_stock_amount" class="form-label"><?php esc_html_e( 'Low stock threshold', 'sk-core' ); ?></label>
                <input type="number" class="sk-form-control" name="_low_stock_amount" placeholder="<?php esc_attr_e( '1', 'sk-core' ); ?>" value="<?php echo esc_attr( wc_stock_amount( $_low_stock_amount ) ); ?>" min="0" step="1">
            </div>
            <?php endif; ?>

            <div class="content-half-part last-child">
                <label for="_backorders" class="form-label"><?php esc_html_e( 'Allow Backorders', 'sk-core' ); ?></label>

                <?php
                sk_post_input_box(
                    $post_id,
                    '_backorders',
                    [
                        'options' => [
                            'no'     => __( 'Do not allow', 'sk-core' ),
                            'notify' => __( 'Allow but notify customer', 'sk-core' ),
                            'yes'    => __( 'Allow', 'sk-core' ),
                        ],
                    ],
                    'select'
                );
                ?>
            </div>
            <div class="sk-clearfix"></div>
        </div><!-- .show_if_stock -->
        <?php endif; ?>

        <div class="sk-form-group">
            <?php sk_post_input_box( $post_id, '_sold_individually', array( 'label' => __( 'Allow only one quantity of this product to be bought in a single order', 'sk-core' ) ), 'checkbox' ); ?>
        </div>

        <?php if ( $post_id ) : ?>
            <?php do_action( 'sk_product_edit_after_inventory' ); ?>
        <?php endif; ?>

        <?php do_action( 'sk_product_edit_after_downloadable', $post, $post_id ); ?>
        <?php do_action( 'sk_product_edit_after_sidebar', $post, $post_id ); ?>
        <?php do_action( 'sk_single_product_edit_after_sidebar', $post, $post_id ); ?>

    </div><!-- .sk-side-right -->
</div><!-- .sk-product-inventory -->
