<?php

namespace SK\Modules\Donations;

defined( 'ABSPATH' ) || exit;

/**
 * Wo die Spendenbitte erscheint. Jede Platzierung ist einzeln abschaltbar,
 * damit sich nacheinander messen lässt, welche etwas bringt.
 *
 * Hintergrund: Die Seite /spenden wurde in 90 Tagen 35 mal aufgerufen und hat
 * seit September 2025 keine Spende mehr gebracht. Mehr Links auf dieselbe
 * Seite ändern daran nichts — es geht um den Moment, in dem gefragt wird.
 */
class Placement {

    const OPTION_DASHBOARD  = 'sk_donations_show_dashboard';
    const OPTION_SOLD_MODAL = 'sk_donations_show_sold_modal';

    public function __construct() {
        add_action( 'sk_dashboard_wrap_end', [ $this, 'dashboard' ], 20 );

        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_modal_assets' ], 20 );
        add_action( 'wp_footer', [ $this, 'sold_modal' ], 100 );
    }

    public static function dashboard_enabled(): bool {
        return (bool) get_option( self::OPTION_DASHBOARD, 0 );
    }

    public static function sold_modal_enabled(): bool {
        return (bool) get_option( self::OPTION_SOLD_MODAL, 1 );
    }

    /**
     * Kostenbalken am Ende des Verkäufer-Dashboards.
     */
    public function dashboard(): void {
        if ( ! self::dashboard_enabled() || ! is_user_logged_in() ) {
            return;
        }

        echo '<div class="sk-donate-dashboard-slot">';
        echo Shortcode::render( true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Vorlage escaped selbst.
        echo '</div>';
    }

    /**
     * Wurde gerade ein Inserat gelöscht?
     *
     * Der Löschvorgang leitet auf /dashboard/products/?message=product_deleted
     * um (Dashboard\Templates\Products::handle_delete_product). Das ist der
     * wahrscheinlichste Moment eines erfolgreichen Verkaufs — und damit der
     * einzige, in dem eine Spendenfrage nicht aufdringlich wirkt.
     */
    private function just_deleted_product(): bool {
        if ( ! is_user_logged_in() ) {
            return false;
        }

        return isset( $_GET['message'] ) && $_GET['message'] === 'product_deleted';
    }

    public function enqueue_modal_assets(): void {
        if ( ! self::sold_modal_enabled() || ! $this->just_deleted_product() ) {
            return;
        }

        wp_enqueue_style(
            'sk-donations',
            SK_DONATIONS_URL . '/assets/css/sk-donations.css',
            [],
            SK_DONATIONS_VERSION
        );

        wp_enqueue_script(
            'sk-donations-sold-modal',
            SK_DONATIONS_URL . '/assets/js/sold-modal.js',
            [],
            SK_DONATIONS_VERSION,
            true
        );
    }

    public function sold_modal(): void {
        if ( ! self::sold_modal_enabled() || ! $this->just_deleted_product() ) {
            return;
        }

        include SK_DONATIONS_PATH . '/templates/sold-modal.php';
    }
}
