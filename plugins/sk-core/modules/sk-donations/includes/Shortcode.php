<?php

namespace SK\Modules\Donations;

use SK\Core\Abstracts\SkShortcode;

defined( 'ABSPATH' ) || exit;

/**
 * [sk_donation_bar] — Kostendeckung und Betragsknöpfe.
 *
 * Attribute:
 *   compact="yes"  schmalere Variante ohne Einleitungstext
 *   heading="..."  eigene Überschrift
 */
class Shortcode extends SkShortcode {

    protected $shortcode = 'sk_donation_bar';

    public function __construct() {
        parent::__construct();

        add_action( 'wp_enqueue_scripts', [ $this, 'register_style' ] );
    }

    public function register_style(): void {
        wp_register_style(
            'sk-donations',
            SK_DONATIONS_URL . '/assets/css/sk-donations.css',
            [],
            SK_DONATIONS_VERSION
        );
    }

    public function render_shortcode( $atts ) {
        $atts = shortcode_atts(
            [
                'compact' => 'no',
                'heading' => '',
            ],
            $atts,
            $this->shortcode
        );

        return self::render( $atts['compact'] === 'yes', (string) $atts['heading'] );
    }

    /**
     * Auch direkt aufrufbar, damit Platzierungen keinen Shortcode
     * durch do_shortcode schicken müssen.
     */
    public static function render( bool $compact = false, string $heading = '' ): string {
        wp_enqueue_style( 'sk-donations' );

        $goal      = Donations::goal();
        $received  = Donations::received_this_month();
        $coverage  = Donations::coverage();
        $missing   = max( 0, $goal - $received );
        $error     = isset( $_GET['spende'] ) && $_GET['spende'] === 'fehler';
        $presets   = [ 5000, 21000, 100000 ];

        ob_start();
        include SK_DONATIONS_PATH . '/templates/bar.php';

        return ob_get_clean();
    }
}
