<?php

namespace SK\Modules\Sponsors;

use SK\Core\Abstracts\SkShortcode;

defined( 'ABSPATH' ) || exit;

/**
 * [sk_sponsor_carousel] — sponsor slider for the shop page and sidebar.
 *
 * Replacement for [post_image_carousel] from the wp-post-image-carousel
 * plugin. That one queries published blog posts; since sponsors are now
 * managed as their own post type and the legacy posts sit in draft, it came
 * up empty.
 *
 * The markup is deliberately identical to the plugin's (wppis-slider,
 * wppis-track, wppis-slide, wppis-link, wppis-figure) and the defaults come
 * from its option — so appearance and behavior stay unchanged, including the
 * slider mechanics from carousel.js.
 *
 * One difference is intentional: the links go through /go/, so they're
 * counted like everywhere else.
 */
class Carousel extends SkShortcode {

    protected $shortcode = 'sk_sponsor_carousel';

    /** Plugin settings, so spacing and sizes stay the same. */
    const PLUGIN_OPTION = 'wppic_settings';

    /**
     * Our own copies of the slider assets.
     *
     * Adopted from wp-post-image-carousel; the responsive files already
     * carried their own customizations, which lived in the plugin folder
     * and wouldn't have survived an update. This makes the plugin obsolete.
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

        // No logo, no tile — otherwise empty slides would appear.
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
