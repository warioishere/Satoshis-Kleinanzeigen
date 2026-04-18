<?php

namespace SK\Core\Frontend;

use SK\Core\Traits\ChainableContainer;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Frontend {

    use ChainableContainer;

    public function __construct() {
        $this->set_controllers();
    }

    private function set_controllers() {
        add_action( 'woocommerce_after_shop_loop_item_title', [ $this, 'product_category_badges' ], 9 );
        add_shortcode( 'sk_footer', [ $this, 'render_sk_footer' ] );
    }

    /**
     * Show category badges above the price on product cards.
     */
    public function product_category_badges() {
        if ( ! function_exists( 'wc_get_product' ) ) {
            return;
        }

        global $product;
        if ( ! $product ) {
            return;
        }

        $terms = get_the_terms( $product->get_id(), 'product_cat' );
        if ( empty( $terms ) || is_wp_error( $terms ) ) {
            return;
        }

        $terms = array_slice( $terms, 0, 3 );

        echo '<div class="product-card-cats product-card-cats--above-price">';
        foreach ( $terms as $term ) {
            $url = get_term_link( $term );
            if ( is_wp_error( $url ) ) {
                continue;
            }
            echo '<a class="product-card__cat" href="' . esc_url( $url ) . '">' . esc_html( $term->name ) . '</a>';
        }
        echo '</div>';
    }

    /**
     * [sk_footer] shortcode — site credit line.
     */
    public function render_sk_footer(): string {
        $version = defined( 'SK_CORE_VERSION' ) ? SK_CORE_VERSION : '';

        return '<span class="sk-footer-credit" style="display:block;text-align:center;font-size:13px;color:#8b949e;letter-spacing:0.3px;">'
            . 'Made with <span style="color:#f7931a;">&hearts;</span> by the SK-Team &middot; v'
            . esc_html( $version ) . '</span>';
    }
}
