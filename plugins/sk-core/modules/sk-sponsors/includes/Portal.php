<?php

namespace SK\Modules\Sponsors;

defined( 'ABSPATH' ) || exit;

/**
 * Selbstbedienungsseite für Sponsoren unter /sponsor/<token>/.
 *
 * Zeigt bewusst nicht nur ein Zahlformular, sondern zuerst den Gegenwert:
 * Klicks der letzten 30 Tage, Stand des Guthabens, verbleibende Monate. Wer
 * verlängern soll, muss sehen, wofür — ein nacktes Betragsfeld beantwortet
 * diese Frage nicht.
 *
 * Zugang über einen geheimen Token statt über ein Benutzerkonto: Sponsoren
 * sind Firmen, die sich für eine Verlängerung nicht registrieren wollen.
 */
class Portal {

    const QUERY_VAR = 'sk_sponsor_token';
    const PREFIX    = 'sponsor';

    /** Ersatzrate, solange für einen Sponsor noch keine Monatsrate feststeht. */
    const OPTION_DEFAULT_RATE = 'sk_sponsors_default_rate';

    public function __construct() {
        add_action( 'init', [ $this, 'add_rewrite_rule' ] );
        add_filter( 'query_vars', [ $this, 'add_query_var' ] );
        add_action( 'template_redirect', [ $this, 'maybe_handle_post' ], 1 );
        add_action( 'template_redirect', [ $this, 'prepare_page' ], 2 );
        add_filter( 'template_include', [ $this, 'override_template' ], 99 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ], 20 );
    }

    public function add_rewrite_rule(): void {
        add_rewrite_rule(
            '^' . self::PREFIX . '/([A-Za-z0-9]{24,64})/?$',
            'index.php?' . self::QUERY_VAR . '=$matches[1]',
            'top'
        );
    }

    public function add_query_var( $vars ): array {
        $vars[] = self::QUERY_VAR;

        return $vars;
    }

    public static function url_for( int $sponsor_id ): string {
        return home_url( '/' . self::PREFIX . '/' . PostType::token( $sponsor_id ) . '/' );
    }

    public static function default_rate(): int {
        return max( 1000, (int) get_option( self::OPTION_DEFAULT_RATE, 25000 ) );
    }

    private function current_sponsor(): ?\WP_Post {
        $token = (string) get_query_var( self::QUERY_VAR );

        return $token === '' ? null : PostType::by_token( $token );
    }

    /**
     * Betrag entgegennehmen, Rechnung anlegen, zur Zahlung weiterleiten.
     */
    public function maybe_handle_post(): void {
        $sponsor = $this->current_sponsor();
        if ( ! $sponsor ) {
            return;
        }

        if ( strtoupper( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) !== 'POST' ) {
            return;
        }

        $sats = isset( $_POST['sk_topup_sats'] ) ? absint( $_POST['sk_topup_sats'] ) : 0;
        if ( $sats <= 0 ) {
            wp_safe_redirect( add_query_arg( 'fehler', 'betrag', self::url_for( (int) $sponsor->ID ) ) );
            exit;
        }

        $order = TopUp::create_invoice( (int) $sponsor->ID, $sats, (string) get_post_meta( $sponsor->ID, PostType::META_EMAIL, true ) );

        if ( is_wp_error( $order ) ) {
            wp_safe_redirect( add_query_arg( 'fehler', 'rechnung', self::url_for( (int) $sponsor->ID ) ) );
            exit;
        }

        // Direkt in die BTCPay-Bezahlseite der Bestellung.
        wp_safe_redirect( $order->get_checkout_payment_url() );
        exit;
    }

    /**
     * Statuscode geradeziehen.
     *
     * Zu dieser Adresse gibt es keinen Beitrag, WordPress hielte sie sonst fuer
     * eine 404 — das wuerde Suchmaschinen und Caching-Schichten in die Irre
     * fuehren. Passt der Token zu niemandem, landet der Aufruf auf der
     * Startseite statt auf einer beliebigen anderen Seite mit Status 200;
     * dasselbe Verhalten wie bei einem unbekannten /go/-Slug.
     */
    public function prepare_page(): void {
        $token = (string) get_query_var( self::QUERY_VAR );
        if ( $token === '' ) {
            return;
        }

        if ( ! $this->current_sponsor() ) {
            wp_safe_redirect( home_url( '/' ), 302 );
            exit;
        }

        global $wp_query;
        $wp_query->is_404 = false;
        status_header( 200 );
        nocache_headers();
    }

    public function enqueue(): void {
        if ( ! $this->current_sponsor() ) {
            return;
        }

        wp_enqueue_style(
            'sk-sponsors',
            SK_SPONSORS_URL . '/assets/css/sk-sponsors.css',
            [],
            SK_SPONSORS_VERSION
        );
    }

    public function override_template( $template ) {
        $sponsor = $this->current_sponsor();
        if ( ! $sponsor ) {
            return $template;
        }

        $own = SK_SPONSORS_PATH . '/templates/portal.php';

        return file_exists( $own ) ? $own : $template;
    }
}
