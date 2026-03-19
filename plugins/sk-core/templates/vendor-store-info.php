<?php
/**
 * SK vendor information template on product page
 *
 *
 * @param Object $vendor
 * @param Array  $store_info
 * @param Array  $store_rating
 *
 */
?>

<div class="sk-vendor-info-wrap">
    <div class="sk-vendor-image">
        <img src="<?php echo esc_url( $vendor->get_avatar() ); ?>" alt="<?php echo esc_attr( $store_info['store_name'] ); ?>">
    </div>
    <div class="sk-vendor-info">
        <div class="sk-vendor-name">
            <a href="<?php echo esc_attr( $vendor->get_shop_url() ); ?>"><h5><?php echo esc_html( $store_info['store_name'] ); ?></h5></a>
            <?php do_action( 'sk_product_single_after_store_name', $vendor ); ?>
        </div>
        <div class="sk-vendor-rating">
            <?php if ( $store_rating['count'] ) : ?>
                <p><?php echo esc_html( $store_rating['rating'] ); ?></p>
            <?php endif; ?>
            <?php echo wp_kses_post( sk_generate_ratings( $store_rating['rating'], 5 ) ); ?>
        </div>
        <?php if ( $store_rating['count'] ) : ?>
            <?php // translators: %d reviews count ?>
            <p class="sk-ratings-count">(<?php echo esc_html( sprintf( _n( '%s Review', '%s Reviews', $store_rating['count'], 'sk-core' ), esc_html( number_format_i18n( $store_rating['count'] ) ) ) ); ?>)</p>
        <?php endif; ?>
    </div>
</div>
