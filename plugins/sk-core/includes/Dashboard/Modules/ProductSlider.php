<?php

namespace SK\Core\Dashboard\Modules;

/**
 * WCPS product slider enhancements:
 * - [wcps_featured_first id="..."] — shows featured products first, sorted by featured date.
 * - [wcps_or_info id="..."]        — WCPS slider with fallback infobox when empty.
 *
 * Ported from mu-plugins: wcps-featured-first.php, boost-your-product.php
 */
class ProductSlider {

    private bool $featured_sort_active = false;

    public function __construct() {
        add_shortcode( 'wcps_featured_first', [ $this, 'shortcode_featured_first' ] );
        add_shortcode( 'wcps_or_info', [ $this, 'shortcode_or_info' ] );
    }

    /* ---- [wcps_featured_first id="..."] ---- */

    public function shortcode_featured_first( $atts ): string {
        $atts = shortcode_atts( [ 'id' => '' ], $atts, 'wcps_featured_first' );
        if ( empty( $atts['id'] ) ) return '<!-- wcps_featured_first: id fehlt -->';

        $this->featured_sort_active = true;
        add_filter( 'the_posts', [ $this, 'reorder_featured_first' ], 99, 2 );

        $out = do_shortcode( '[wcps id="' . esc_attr( $atts['id'] ) . '"]' );

        remove_filter( 'the_posts', [ $this, 'reorder_featured_first' ], 99 );
        $this->featured_sort_active = false;

        return $out;
    }

    public function reorder_featured_first( array $posts, \WP_Query $query ): array {
        if ( ! $this->featured_sort_active ) return $posts;

        $has_product = false;
        foreach ( $posts as $p ) {
            if ( isset( $p->post_type ) && $p->post_type === 'product' ) { $has_product = true; break; }
        }
        if ( ! $has_product ) return $posts;

        $orig = [];
        foreach ( $posts as $i => $p ) $orig[ $p->ID ] = $i;

        usort( $posts, function ( $a, $b ) use ( $orig ) {
            $fa = $this->is_featured( $a->ID ) ? 1 : 0;
            $fb = $this->is_featured( $b->ID ) ? 1 : 0;

            if ( $fa !== $fb ) return $fb <=> $fa;

            if ( $fa === 1 && $fb === 1 ) {
                $sa = $this->featured_since_ts( $a->ID );
                $sb = $this->featured_since_ts( $b->ID );
                if ( $sa !== $sb ) return $sb <=> $sa;
            }

            return $orig[ $a->ID ] <=> $orig[ $b->ID ];
        } );

        return $posts;
    }

    private function is_featured( int $post_id ): bool {
        if ( function_exists( 'wc_get_product' ) ) {
            $prod = wc_get_product( $post_id );
            if ( $prod ) return (bool) $prod->is_featured();
        }
        if ( function_exists( 'has_term' ) && has_term( 'featured', 'product_visibility', $post_id ) ) return true;
        return get_post_meta( $post_id, '_featured', true ) === 'yes';
    }

    private function featured_since_ts( int $post_id ): int {
        $raw = get_post_meta( $post_id, '_featured_since', true );
        if ( $raw ) { $t = strtotime( $raw ); if ( $t ) return $t; }
        $mod = get_post_field( 'post_modified', $post_id );
        if ( $mod ) { $t = strtotime( $mod ); if ( $t ) return $t; }
        $date = get_post_field( 'post_date', $post_id );
        return $date ? (int) strtotime( $date ) : 0;
    }

    /* ---- [wcps_or_info id="..." title="..." text="..." btn_label="..."] ---- */

    public function shortcode_or_info( $atts = [] ): string {
        $atts = shortcode_atts( [
            'id'        => '',
            'title'     => 'Hier könnte dein Inserat stehen',
            'text'      => 'Booste Dein Inserat, damit es hier im Slider erscheint.',
            'btn_label' => 'Zum Dashboard',
            'class'     => '',
        ], $atts, 'wcps_or_info' );

        $slider_html = do_shortcode( sprintf( "[wcps id='%s']", esc_attr( $atts['id'] ) ) );
        if ( trim( wp_strip_all_tags( $slider_html ) ) !== '' ) {
            return $slider_html;
        }

        $dashboard_url = site_url( '/dashboard/products/' );
        $hilfe_url     = site_url( '/faq/#wie-kann-ich-mein-angebot-hervorheben-boost' );

        wp_enqueue_style( 'sk-empty-slider' );

        $svg_bolt  = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M13 2L3 14h7l-1 8 11-14h-7l0-6z" stroke="currentColor" stroke-width="1.5" fill="currentColor"/></svg>';
        $svg_arrow = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';

        return '<div class="empty-slider-infobox ' . esc_attr( $atts['class'] ) . '">'
            . '<div class="icon" aria-hidden="true" style="color:#F7931A">' . $svg_bolt . '</div>'
            . '<div class="content">'
            . '<h3>' . esc_html( $atts['title'] ) . '</h3>'
            . '<p>' . esc_html( $atts['text'] ) . '</p>'
            . '<div class="actions">'
            . '<a class="btn primary" href="' . esc_url( $dashboard_url ) . '">' . $svg_bolt . ' <span>' . esc_html( $atts['btn_label'] ) . '</span></a>'
            . '<a class="btn" href="' . esc_url( $hilfe_url ) . '">' . $svg_arrow . ' <span>Mehr erfahren</span></a>'
            . '</div></div></div>';
    }
}
