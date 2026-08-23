<?php

namespace SK\Modules\Sponsors;

use SK\Core\Abstracts\SkShortcode;

defined( 'ABSPATH' ) || exit;

/**
 * [sk_sponsor_carousel] — Sponsorenslider für Shopseite und Seitenleiste.
 *
 * Ersatz für [post_image_carousel] des Plugins wp-post-image-carousel. Jenes
 * fragt veröffentlichte Blogbeiträge ab; seit die Sponsoren als eigener
 * Post-Type geführt werden und die Altbeiträge auf Entwurf stehen, lief es ins
 * Leere.
 *
 * Das Markup ist bewusst identisch zum Plugin (wppis-slider, wppis-track,
 * wppis-slide, wppis-link, wppis-figure) und die Voreinstellungen kommen aus
 * dessen Option — damit bleiben Aussehen und Verhalten unverändert, inklusive
 * der Slider-Mechanik aus carousel.js.
 *
 * Ein Unterschied ist Absicht: Die Links laufen über /go/, werden also gezählt
 * wie überall sonst.
 */
class Carousel extends SkShortcode {

    protected $shortcode = 'sk_sponsor_carousel';

    /** Einstellungen des Plugins, damit Abstände und Größen gleich bleiben. */
    const PLUGIN_OPTION = 'wppic_settings';

    /**
     * Eigene Kopien der Slider-Assets.
     *
     * Uebernommen aus wp-post-image-carousel; die responsive-Dateien trugen
     * bereits eigene Anpassungen, die im Plugin-Ordner lagen und ein Update
     * nicht ueberlebt haetten. Damit ist das Plugin entbehrlich.
     */
    public static function enqueue_assets(): void {
        wp_enqueue_style(
            'sk-sponsor-carousel',
            SK_SPONSORS_URL . '/assets/css/sk-carousel.css',
            [],
            SK_SPONSORS_VERSION
        );

        wp_enqueue_style(
            'sk-sponsor-carousel-responsive',
            SK_SPONSORS_URL . '/assets/css/sk-carousel-responsive.css',
            [ 'sk-sponsor-carousel' ],
            SK_SPONSORS_VERSION
        );

        wp_enqueue_script(
            'sk-sponsor-carousel',
            SK_SPONSORS_URL . '/assets/js/sk-carousel.js',
            [],
            SK_SPONSORS_VERSION,
            true
        );

        wp_enqueue_script(
            'sk-sponsor-carousel-responsive',
            SK_SPONSORS_URL . '/assets/js/sk-carousel-responsive.js',
            [ 'sk-sponsor-carousel' ],
            SK_SPONSORS_VERSION,
            true
        );
    }

    public function render_shortcode( $atts ) {
        $opts = (array) get_option( self::PLUGIN_OPTION, [] );

        $defaults = [
            'posts'     => $opts['posts'] ?? 15,
            'gap'       => $opts['gap'] ?? 0,
            'h_height'  => $opts['h_height'] ?? 300,
            'v_width'   => $opts['v_width'] ?? 300,
            'direction' => $opts['direction'] ?? 'horizontal',
            'arrows'    => ( isset( $opts['arrows'] ) && $opts['arrows'] === '1' ) ? 'true' : 'false',
            'tier'      => '',
            'class'     => '',
        ];

        $a = shortcode_atts( $defaults, $atts, $this->shortcode );

        $direction = strtolower( (string) $a['direction'] ) === 'vertical' ? 'vertical' : 'horizontal';
        $arrows    = in_array( strtolower( (string) $a['arrows'] ), [ 'true', '1' ], true ) ? 'true' : 'false';
        $tier      = in_array( $a['tier'], [ PostType::TIER_TOP, PostType::TIER_STANDARD ], true ) ? $a['tier'] : '';

        $sponsors = PostType::get_active( $tier, max( 1, (int) $a['posts'] ) );

        // Ohne Logo keine Kachel — sonst entstehen leere Slides.
        $sponsors = array_values(
            array_filter( $sponsors, static fn( $s ) => has_post_thumbnail( $s->ID ) )
        );

        if ( empty( $sponsors ) ) {
            return '';
        }

        self::enqueue_assets();

        $uid   = 'wppic-' . wp_generate_uuid4();
        $gap   = max( 0, (int) $a['gap'] );
        $h     = max( 50, (int) $a['h_height'] );
        $v     = max( 50, (int) $a['v_width'] );
        $extra = $a['class'] !== '' ? ' ' . sanitize_html_class( $a['class'] ) : '';

        ob_start();
        include SK_SPONSORS_PATH . '/templates/carousel.php';

        return ob_get_clean();
    }
}
