<?php
/**
 * SK Widget Content Product Template
 *
 *
 */

$img_kses = apply_filters(
    'sk_product_image_attributes', [
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

?>

<?php if ( $r->have_posts() ) : ?>
    <ul class="sk-bestselling-product-widget product_list_widget">
        <?php
        while ( $r->have_posts() ) :
            $r->the_post();
            global $product;
            ?>
            <li>
                <a href="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>" title="<?php echo esc_attr( $product->get_title() ); ?>">
                    <?php echo wp_kses( $product->get_image(), $img_kses ); ?>
                    <span class="product-title"><?php echo esc_html( $product->get_title() ); ?></span>
                </a>

                <?php
                if ( ! empty( $show_rating ) ) {
                    echo wc_get_rating_html( $product->get_average_rating() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }
                ?>

                <?php echo wp_kses_post( $product->get_price_html() ); ?>
            </li>
        <?php endwhile; ?>
    </ul>
<?php else : ?>
    <p><?php esc_html_e( 'No products found', 'sk-core' ); ?></p>
<?php endif; ?>
