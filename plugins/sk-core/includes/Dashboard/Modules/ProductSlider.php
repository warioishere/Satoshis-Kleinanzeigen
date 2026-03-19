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

        static $css_injected = false;
        $css = '';
        if ( ! $css_injected ) {
            $css_injected = true;
            $css = '<style>'
                . '.empty-slider-infobox{display:flex;align-items:center;gap:16px;padding:20px;border:1px dashed #cfd6df;border-radius:16px;background:linear-gradient(180deg,#f8fafc,#f4f6f9);box-shadow:0 1px 2px rgba(0,0,0,.04)}'
                . '.empty-slider-infobox .icon{width:44px;height:44px;flex:0 0 44px;border-radius:12px;background:#fff;display:grid;place-items:center;border:1px solid #e6eaf0}'
                . '.empty-slider-infobox .content{flex:1 1 auto}'
                . '.empty-slider-infobox h3{margin:0 0 4px;font-size:18px;line-height:1.3}'
                . '.empty-slider-infobox p{margin:0 0 10px;color:#556070}'
                . '.empty-slider-infobox .actions{display:flex;gap:10px;flex-wrap:wrap}'
                . '.empty-slider-infobox .btn{display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:10px;text-decoration:none;border:1px solid #e6eaf0;background:#fff;transition:.15s}'
                . '.empty-slider-infobox .btn.primary{background:#F7931A;border-color:#F7931A;color:#111}'
                . '.empty-slider-infobox .btn:hover{transform:translateY(-1px)}'
                . '@media(prefers-color-scheme:dark){.empty-slider-infobox{background:linear-gradient(180deg,#1f2630,#1b2230);border-color:#394453}.empty-slider-infobox .icon{background:#222a36;border-color:#394453}.empty-slider-infobox p{color:#b7c0cd}.empty-slider-infobox .btn{background:#232b38;border-color:#394453;color:#e8edf5}}'
                . '</style>';
        }

        $svg_bolt  = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M13 2L3 14h7l-1 8 11-14h-7l0-6z" stroke="currentColor" stroke-width="1.5" fill="currentColor"/></svg>';
        $svg_arrow = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';

        return $css
            . '<div class="empty-slider-infobox ' . esc_attr( $atts['class'] ) . '">'
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
