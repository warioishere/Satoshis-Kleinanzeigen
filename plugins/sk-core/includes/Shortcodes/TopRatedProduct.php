<?php

namespace SK\Core\Shortcodes;

use SK\Core\Abstracts\SkShortcode;

class TopRatedProduct extends SkShortcode {
    protected $shortcode = 'sk-top-rated-product';

    /**
     * Render top rated products via shortcode
     *
     * @param array $atts
     *
     * @return string
     */
    public function render_shortcode( $atts ) {

        /**
         * Filter return the number of top rated product per page.
         *
         *
         * @param array
         */
        $per_page = shortcode_atts(
            apply_filters(
                'sk_top_rated_product_per_page',
                [
                    'no_of_product' => 8,
                ],
                $atts
            ),
            $atts
        );

        ob_start(); ?>
        <ul class="products">
            <?php
            $best_selling_query = sk_get_top_rated_products();

            while ( $best_selling_query->have_posts() ) {
                $best_selling_query->the_post();

                wc_get_template_part( 'content', 'product' );
            }
            ?>
        </ul>
        <?php
        wp_reset_postdata();
        return ob_get_clean();
    }
}
