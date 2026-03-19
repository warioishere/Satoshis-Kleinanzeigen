<?php

/**
 * Template Name: Product listing row template
 *
 * @var WP_Post $post
 * @var WC_Product $product
 * @var string $tr_class
 * @var string $row_actions
 */

$img_kses = apply_filters(
    'sk_product_image_attributes',
    [
        'img' => [
            'alt'         => [],
            'class'       => [],
            'height'      => [],
            'src'         => [],
            'width'       => [],
            'srcset'      => [],
            'data-srcset' => [],
            'data-src'    => [],
        ],
    ]
);

$row_actions_kses = apply_filters(
    'sk_row_actions_kses',
    [
        'span' => [ 'class' => [] ],
        'a'    => [ 'href' => [], 'onclick' => [] ],
    ]
);

// Date display
if ( '0000-00-00 00:00:00' === $post->post_date ) {
    $post_date_label = __( 'Unveröffentlicht', 'sk-core' );
} else {
    $current_time    = sk_current_datetime();
    $gmt_time        = $current_time->setTimezone( new DateTimeZone( 'UTC' ) );
    $post_time_gmt   = $gmt_time->modify( $post->post_date_gmt );
    $time_diff       = $current_time->getTimestamp() - $post_time_gmt->getTimestamp();
    if ( $time_diff > 0 && $time_diff < 24 * 60 * 60 ) {
        $post_date_label = sprintf( __( 'vor %s', 'sk-core' ), human_time_diff( $post_time_gmt->getTimestamp() ) );
    } else {
        $post_date_label = sk_format_date( $post_time_gmt->getTimestamp() );
    }
}
?>
<tr class="<?php echo esc_attr( $tr_class ); ?>">
    <td class="col-thumb" data-title="<?php esc_attr_e( 'Inserat', 'sk-core' ); ?>">
        <div class="sk-product-row-inner">
            <?php if ( current_user_can( 'sk_edit_product' ) ) : ?>
                <a class="sk-product-thumb" href="<?php echo esc_url( sk_edit_product_url( $post->ID ) ); ?>">
                    <?php echo wp_kses( $product->get_image( 'thumbnail' ), $img_kses ); ?>
                </a>
            <?php else : ?>
                <span class="sk-product-thumb"><?php echo wp_kses( $product->get_image( 'thumbnail' ), $img_kses ); ?></span>
            <?php endif; ?>
            <div class="sk-product-info">
                <?php if ( current_user_can( 'sk_edit_product' ) ) : ?>
                    <a class="sk-product-title" href="<?php echo esc_url( sk_edit_product_url( $post->ID ) ); ?>">
                        <?php echo esc_html( $product->get_title() ); ?>
                    </a>
                <?php else : ?>
                    <span class="sk-product-title"><?php echo esc_html( $product->get_title() ); ?></span>
                <?php endif; ?>
                <?php if ( ! empty( $row_actions ) ) : ?>
                    <div class="row-actions"><?php echo wp_kses( $row_actions, $row_actions_kses ); ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php do_action( 'sk_product_list_table_after_column_content_image', $product ); ?>
        <?php do_action( 'sk_product_list_table_after_column_content_name', $product ); ?>
        <?php do_action( 'sk_product_list_table_after_column_content_name_row_actions', $product ); ?>
        <button type="button" class="toggle-row"></button>
    </td>

    <td class="col-status post-status" data-title="<?php esc_attr_e( 'Status', 'sk-core' ); ?>">
        <label class="sk-label <?php echo esc_attr( sk_get_post_status_label_class( $post->post_status ) ); ?>">
            <?php echo esc_html( sk_get_post_status( $post->post_status ) ); ?>
        </label>
        <?php do_action( 'sk_product_list_table_after_column_content_status', $product ); ?>
    </td>

    <?php do_action( 'sk_product_list_table_after_status_table_data', $post, $product, $tr_class, $row_actions ); ?>

    <td class="col-price" data-title="<?php esc_attr_e( 'Preis', 'sk-core' ); ?>">
        <?php
        if ( $product->get_price_html() ) {
            echo wp_kses_post( $product->get_price_html() );
        } else {
            echo '<span class="na">&ndash;</span>';
        }
        do_action( 'sk_product_list_table_after_column_content_price', $product );
        ?>
    </td>

    <td class="col-date post-date" data-title="<?php esc_attr_e( 'Datum', 'sk-core' ); ?>">
        <?php echo esc_html( $post_date_label ); ?>
        <?php do_action( 'sk_product_list_table_after_column_content_date', $product ); ?>
    </td>
</tr>
