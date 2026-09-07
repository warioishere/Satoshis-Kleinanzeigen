<?php

namespace SK\Modules\Donations;

defined( 'ABSPATH' ) || exit;

/**
 * Where the donation prompt appears. Each placement can be toggled
 * individually, so it's possible to measure one at a time which one works.
 *
 * Background: the /spenden page was viewed 35 times in 90 days and hasn't
 * brought in a donation since September 2025. More links to the same page
 * wouldn't change that — it's about the moment the question is asked.
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
     * Cost bar at the end of the seller dashboard.
     */
    public function dashboard(): void {
        if ( ! self::dashboard_enabled() || ! is_user_logged_in() ) {
            return;
        }

        echo '<div class="sk-donate-dashboard-slot">';
        echo Shortcode::render( true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template escapes itself.
        echo '</div>';
    }

    /**
     * Was a listing just deleted?
     *
     * The deletion redirects to /dashboard/products/?message=product_deleted
     * (Dashboard\Templates\Products::handle_delete_product). That's the most
     * likely moment of a successful sale — and thus the only one in which a
     * donation ask doesn't feel intrusive.
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

        // btcpay.js is already loaded on the seller dashboard by BuyNow;
        // declare it as a dependency so the order is correct if BuyNow
        // is ever disabled.
        $deps = wp_script_is( 'btcpay_gf_modal_js', 'registered' ) ? [ 'btcpay_gf_modal_js' ] : [];

        wp_enqueue_script(
            'sk-donations-sold-modal',
            SK_DONATIONS_URL . '/assets/js/sold-modal.js',
            $deps,
            SK_DONATIONS_VERSION,
            true
        );

        wp_localize_script(
            'sk-donations-sold-modal',
            'skDonate',
            [
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
                'action'  => Donations::AJAX_ACTION,
                'nonce'   => wp_create_nonce( Donations::AJAX_ACTION ),
            ]
        );
    }

    public function sold_modal(): void {
        if ( ! self::sold_modal_enabled() || ! $this->just_deleted_product() ) {
            return;
        }

        include SK_DONATIONS_PATH . '/templates/sold-modal.php';
    }
}
