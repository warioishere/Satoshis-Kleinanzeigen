<?php

namespace SK\Core\Dashboard\Modules;

/**
 * Dashboard notice boxes: welcome message + subscription/verification info banners.
 *
 * The welcome-box HTML is rendered inside the dashboard wrap via action hook.
 * All JS + CSS are shipped as real asset files (assets/js/notices/*.js,
 * assets/css/notices.css) and enqueued only on the seller dashboard.
 */
class Notices {

    public function __construct() {
        add_action( 'sk_dashboard_before_widgets', [ $this, 'output_welcome_box' ] );
        add_action( 'wp_enqueue_scripts',         [ $this, 'enqueue_notice_assets' ] );
    }

    public function enqueue_notice_assets(): void {
        if ( ! function_exists( 'sk_is_seller_dashboard' ) || ! sk_is_seller_dashboard() ) {
            return;
        }

        wp_enqueue_style(  'sk-notices' );
        wp_enqueue_script( 'sk-notices-welcome' );
        wp_enqueue_script( 'sk-notices-subscription' );
        wp_enqueue_script( 'sk-notices-verification' );
    }

    public function output_welcome_box(): void {
        $abo_url      = esc_url( home_url( '/dashboard/subscription/' ) );
        $feedback_url = 'https://new.satoshiskleinanzeigen.space/feedback/';
        ?>
        <div id="welcome-box">
            <h2>Willkommen im Verkäufer-Dashboard!</h2>
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
