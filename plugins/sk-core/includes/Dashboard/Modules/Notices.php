<?php

namespace SK\Core\Dashboard\Modules;

/**
 * Dashboard Welcome Box — rendered once on the dashboard landing page via the
 * `sk_dashboard_before_widgets` action (which only fires in the main
 * dashboard.php template, not on subpages like /dashboard/subscription/).
 *
 * No JS needed — the HTML only appears where we want it.
 */
class Notices {

    public function __construct() {
        add_action( 'sk_dashboard_before_widgets', [ $this, 'output_welcome_box' ] );
        add_action( 'wp_enqueue_scripts',          [ $this, 'enqueue_styles' ] );
    }

    public function enqueue_styles(): void {
        $is_dashboard         = function_exists( 'sk_is_seller_dashboard' ) && sk_is_seller_dashboard();
        $is_subscription_page = function_exists( 'is_page' ) && is_page( 'inserate-abos' );
        if ( ! $is_dashboard && ! $is_subscription_page ) {
            return;
        }
        wp_enqueue_style( 'sk-notices' );
    }

    public function output_welcome_box(): void {
        $abo_url      = esc_url( home_url( '/dashboard/subscription/' ) );
        $feedback_url = 'https://new.satoshiskleinanzeigen.space/feedback/';
        ?>
        <div id="welcome-box">
            <h2>Willkommen im Anbieter-Dashboard!</h2>
            <p>Hier kannst du deine Inserate verwalten, neue Gesuche einstellen und deine Angebote organisieren.</p>
            <p>Standardmäßig kannst du bis zu <strong>6 Inserate kostenlos</strong> einstellen und bearbeiten.
               Wenn du mehr Inserate gleichzeitig online haben möchtest, kannst du uns mit einem
               <a href="<?php echo $abo_url; ?>">Abo</a> unterstützen.</p>
            <p>Wir möchten bewusst <strong>keine Verkaufsgebühren</strong> erheben – denn damit würden in vielen Ländern rechtliche KYC-Pflichten greifen.
               Um weiterhin <strong>KYC-frei</strong> zu bleiben und deine <strong>Privatsphäre</strong> zu schützen, finanzieren wir die Plattform über Abos statt über Gebühren.</p>
            <p>So stellen wir sicher, dass SatoshisKleinanzeigen langfristig bestehen bleibt – unabhängig, nutzerfreundlich und mit maximalem Fokus auf Privatsphäre.</p>
            <p>Wenn du Probleme oder Anregungen hast, lass uns gerne ein
               <a class="feedback-link" href="<?php echo esc_url( $feedback_url ); ?>">Feedback</a> da.</p>
            <p>Dein Satoshis Kleinanzeigen Team</p>
        </div>
        <?php
    }
}
