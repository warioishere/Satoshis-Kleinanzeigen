<?php
/**
 * SK Seller Single product tab Template
 *
 *
 *
 * @var WP_User $author     Vendor user object
 * @var array   $store_info Vendor store data
 */
?>

<h2><?php esc_html_e( 'Vendor Information', 'sk-core' ); ?></h2>

<ul class="list-unstyled">
    <?php do_action( 'sk_product_seller_tab_start', $author, $store_info ); ?>

    <?php if ( ! empty( $store_info['store_name'] ) ) : ?>
        <li class="store-name">
            <span><?php esc_html_e( 'Store Name:', 'sk-core' ); ?></span>
            <span class="details">
                <?php echo esc_html( $store_info['store_name'] ); ?>
            </span>
        </li>
        <li class="seller-name">
            <span>
                <?php esc_html_e( 'Vendor:', 'sk-core' ); ?>
            </span>

            <span class="details">
                <?php printf( '<a href="%1$s">%2$s</a>', esc_url( sk_get_store_url( $author->ID ) ), esc_html( $store_info['store_name'] ) ); ?>
            </span>
        </li>
    <?php endif; ?>
    <?php if ( ! sk_is_vendor_info_hidden( 'address' ) && ! empty( $store_info['address'] ) ) { ?>
        <li class="store-address">
            <span><b><?php esc_html_e( 'Address:', 'sk-core' ); ?></b></span>
            <span class="details">
                <?php echo wp_kses_post( sk_get_seller_address( $author->ID ) ); ?>
            </span>
        </li>
    <?php } ?>
    <?php if ( ( sk()->is_pro_exists() && sk_ext()->module->is_active( 'store_reviews' ) ) || ( 'yes' === get_option( 'woocommerce_enable_reviews' ) ) ) : ?>
    <li class="clearfix">
        <?php echo wp_kses_post( sk_get_readable_seller_rating( $author->ID ) ); ?>
    </li>
    <?php endif; ?>

    <?php do_action( 'sk_product_seller_tab_end', $author, $store_info ); ?>
</ul>
