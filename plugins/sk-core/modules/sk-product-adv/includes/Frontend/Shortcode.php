<?php
namespace SK\Modules\ProductAdvertisement\Frontend;

use SK\Modules\ProductAdvertisement\Manager;
use SK\Core\Abstracts\SkShortcode;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class Shortcode
 *
 *
 */
class Shortcode extends SkShortcode {
    /**
     * @var string $shortcode shortcode name
     */
    protected $shortcode = 'sk_product_advertisement';

    /**
     * Render shortcode content
     *
     *
     * @param array $atts
     * @return string
     */
    public function render_shortcode( $atts ) {
        $defaults = [
            'title'     => '',
            'count'     => get_option( 'woocommerce_catalog_columns', 3 ),
            'vendor_id' => '', // comma separated values
            'order'     => 'ASC',
            'orderby'   => 'product_title',
        ];

        $atts = apply_filters( 'sk_product_advertisement_shortcode_args', shortcode_atts( $defaults, $atts ) );

        // get advertisements from database
        $manager  = new Manager();
        $products = $manager->get_advertisement_for_display( $atts );

        if ( false === $products ) {
            return '';
        }

        ob_start();
        ?>
        <div class="sk-advertisement-container">
            <?php if ( ! empty( $atts['title'] ) ) : ?>
                <h2 class="sk-advertisement-heading"><?php echo esc_html( $atts['title'] ); ?></h2>
            <?php endif; ?>
                <ul class="sk-advertisement-list-start">
                    <?php woocommerce_product_loop_start(); ?>

                    <?php while ( $products->have_posts() ) : $products->the_post(); //phpcs:ignore ?>
                        <?php wc_get_template_part( 'content', 'product' ); ?>
                    <?php endwhile; ?>

                    <?php woocommerce_product_loop_end(); ?>

                    <?php wp_reset_postdata(); ?>
                </ul>
        </div>
        <?php
        return ob_get_clean();
    }
}
