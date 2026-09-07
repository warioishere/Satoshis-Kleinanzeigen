<?php

namespace SK\Modules\Sponsors;

use SK\Core\Abstracts\SkShortcode;

defined( 'ABSPATH' ) || exit;

/**
 * [sk_sponsors] — sponsor grid for the homepage.
 *
 * Examples:
 *   [sk_sponsors tier="top" limit="3" heading="Top Unterstützer"]
 *   [sk_sponsors tier="standard" heading="Alle Unterstützer"]
 *   [sk_sponsors]                          (all, top tier first)
 */
class Shortcode extends SkShortcode {

    protected $shortcode = 'sk_sponsors';

    public function __construct() {
        parent::__construct();

        add_action( 'wp_enqueue_scripts', [ $this, 'register_style' ] );
    }

    public function register_style(): void {
        wp_register_style(
            'sk-sponsors',
            SK_SPONSORS_URL . '/assets/css/sk-sponsors.css',
            [],
            SK_SPONSORS_VERSION
        );
    }

    public function render_shortcode( $atts ) {
        $atts = shortcode_atts(
            [
                'tier'    => '',
                'limit'   => 0,
                'heading' => '',
                // 0 = as before: both blocks were fixed at three columns
                // (grid-lg-col-3). An explicitly set value wins.
                'columns' => 0,
            ],
            $atts,
            $this->shortcode
        );

        $tier  = in_array( $atts['tier'], [ PostType::TIER_TOP, PostType::TIER_STANDARD ], true ) ? $atts['tier'] : '';
        $limit = (int) $atts['limit'];

        $sponsors = PostType::get_active( $tier, $limit > 0 ? $limit : -1 );

        if ( empty( $sponsors ) ) {
            return '';
        }

        wp_enqueue_style( 'sk-sponsors' );

        $columns = (int) $atts['columns'];
        if ( $columns < 1 ) {
            $columns = 3;
        }
        $columns = max( 1, min( 6, $columns ) );
        $heading = (string) $atts['heading'];

        ob_start();
        include SK_SPONSORS_PATH . '/templates/sponsor-grid.php';

        return ob_get_clean();
    }
}
