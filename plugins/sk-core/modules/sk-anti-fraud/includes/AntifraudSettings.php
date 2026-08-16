<?php

namespace SK\Modules\AntiFraud;

defined( 'ABSPATH' ) || exit;

class AntifraudSettings {

    /** Option name the settings are stored under. */
    const OPTION = 'sk_antifraud';

    public function __construct() {
        // The settings live in their own SK tab (SK → Anti-Fraud), not in the
        // generic settings screen — the tab also hosts the suspension list.
    }

    /**
     * Field definitions, rendered by \SK\Core\Admin\PhpDashboard\AntiFraudPage.
     */
    public static function get_fields(): array {
        return self::fields();
    }

    /**
     * Defaults for every field.
     */
    public static function get_defaults(): array {
        $defaults = [];

        foreach ( self::fields() as $name => $field ) {
            if ( 'sub_section' === $field['type'] ) {
                continue;
            }
            $defaults[ $name ] = $field['default'] ?? '';
        }

        return $defaults;
    }

    public static function get_options(): array {
        return wp_parse_args( (array) get_option( self::OPTION, [] ), self::get_defaults() );
    }

    private static function fields(): array {
        return [

            // ── Master Switch ──
            'sk_antifraud_header' => [
                'name'  => 'sk_antifraud_header',
                'label' => __( 'Anti-Fraud System', 'sk-core' ),
                'type'  => 'sub_section',
                'desc'  => __( 'Alle Features sind standardmässig deaktiviert.', 'sk-core' ),
            ],
            'sk_antifraud_enabled' => [
                'name'    => 'sk_antifraud_enabled',
                'label'   => __( 'Anti-Fraud aktivieren', 'sk-core' ),
                'type'    => 'switcher',
                'default' => 'off',
                'desc'    => __( 'Master-Schalter für alle Anti-Fraud Features.', 'sk-core' ),
            ],

            // ── Fingerprinting ──
            'sk_antifraud_fp_header' => [
                'name'  => 'sk_antifraud_fp_header',
                'label' => __( 'Browser-Fingerprinting', 'sk-core' ),
                'type'  => 'sub_section',
                'desc'  => __( 'Erkennt wiederkehrende Scammer anhand von Browser- und Geräte-Merkmalen.', 'sk-core' ),
            ],
            'sk_antifraud_fingerprint' => [
                'name'    => 'sk_antifraud_fingerprint',
                'label'   => __( 'Fingerprinting aktivieren', 'sk-core' ),
                'type'    => 'switcher',
                'default' => 'off',
                'desc'    => __( 'Sammelt Browser-Fingerprints und gleicht sie mit gebannten Usern ab.', 'sk-core' ),
            ],
            'sk_antifraud_flag_score' => [
                'name'    => 'sk_antifraud_flag_score',
                'label'   => __( 'Flag Score', 'sk-core' ),
                'type'    => 'text',
                'default' => '50',
                'desc'    => __( 'Ab diesem Score wird der Admin benachrichtigt.', 'sk-core' ),
            ],
            'sk_antifraud_autosuspend_score' => [
                'name'    => 'sk_antifraud_autosuspend_score',
                'label'   => __( 'Auto-Suspend Score', 'sk-core' ),
                'type'    => 'text',
                'default' => '70',
                'desc'    => __( 'Ab diesem Score wird der Account automatisch suspendiert.', 'sk-core' ),
            ],

            // ── Buyer Warnings ──
            'sk_antifraud_bw_header' => [
                'name'  => 'sk_antifraud_bw_header',
                'label' => __( 'Käufer-Warnungen', 'sk-core' ),
                'type'  => 'sub_section',
                'desc'  => __( 'Warnt Käufer bei Vendoren mit wenig Track Record.', 'sk-core' ),
            ],
            'sk_antifraud_buyer_warning' => [
                'name'    => 'sk_antifraud_buyer_warning',
                'label'   => __( 'Käufer-Warnung aktivieren', 'sk-core' ),
                'type'    => 'switcher',
                'default' => 'off',
                'desc'    => __( 'Zeigt Warnbanner auf Produktseiten von neuen Vendoren.', 'sk-core' ),
            ],
            'sk_antifraud_warning_threshold' => [
                'name'    => 'sk_antifraud_warning_threshold',
                'label'   => __( 'Warnung-Schwelle', 'sk-core' ),
                'type'    => 'text',
                'default' => '5',
                'desc'    => __( 'Ab wie vielen bestätigten Transaktionen keine Warnung mehr angezeigt wird.', 'sk-core' ),
            ],

            // ── Sale Limits ──
            'sk_antifraud_sl_header' => [
                'name'  => 'sk_antifraud_sl_header',
                'label' => __( 'Verkaufslimit', 'sk-core' ),
                'type'  => 'sub_section',
                'desc'  => __( 'Begrenzt den maximalen Produktpreis für neue Vendoren.', 'sk-core' ),
            ],
            'sk_antifraud_sale_limit' => [
                'name'    => 'sk_antifraud_sale_limit',
                'label'   => __( 'Verkaufslimit aktivieren', 'sk-core' ),
                'type'    => 'switcher',
                'default' => 'off',
                'desc'    => __( 'Neue Vendoren können nur Produkte bis zum Limit listen.', 'sk-core' ),
            ],
            'sk_antifraud_sale_limit_sats' => [
                'name'    => 'sk_antifraud_sale_limit_sats',
                'label'   => __( 'Max. Sats pro Produkt', 'sk-core' ),
                'type'    => 'text',
                'default' => '50000',
                'desc'    => __( 'Maximaler Produktpreis in Sats für neue Vendoren.', 'sk-core' ),
            ],
            'sk_antifraud_sale_limit_threshold' => [
                'name'    => 'sk_antifraud_sale_limit_threshold',
                'label'   => __( 'Limit-Schwelle', 'sk-core' ),
                'type'    => 'text',
                'default' => '5',
                'desc'    => __( 'Ab wie vielen bestätigten Lieferungen das Limit aufgehoben wird.', 'sk-core' ),
            ],

            // ── Keyword Review ──
            'sk_antifraud_kw_header' => [
                'name'  => 'sk_antifraud_kw_header',
                'label' => __( 'Inserate-Prüfung nach Stichwörtern', 'sk-core' ),
                'type'  => 'sub_section',
                'desc'  => __( 'Hält Inserate mit riskanten Stichwörtern zur manuellen Freigabe zurück. Funktioniert unabhängig vom Reputationssystem.', 'sk-core' ),
            ],
            'sk_antifraud_keyword_review' => [
                'name'    => 'sk_antifraud_keyword_review',
                'label'   => __( 'Stichwort-Prüfung aktivieren', 'sk-core' ),
                'type'    => 'switcher',
                'default' => 'off',
                'desc'    => __( 'Betroffene Inserate werden beim Veröffentlichen auf Entwurf gesetzt, der Anbieter informiert und eine E-Mail an den Admin geschickt.', 'sk-core' ),
            ],
            'sk_antifraud_keywords' => [
                'name'        => 'sk_antifraud_keywords',
                'label'       => __( 'Stichwörter', 'sk-core' ),
                'type'        => 'text',
                'default'     => 'ticket,tickets,eintrittskarte,eintrittskarten,konzertkarte,konzertkarten',
                'placeholder' => 'ticket,tickets,eintrittskarte',
                'desc'        => __( 'Kommagetrennt. Wird in Titel, Beschreibung, Schlagwörtern und Kategorien gesucht, Gross-/Kleinschreibung egal. Teiltreffer zählen — „ticket" erfasst auch „Tickets".', 'sk-core' ),
            ],

            // ── Report Auto-Suspend ──
            'sk_antifraud_rs_header' => [
                'name'  => 'sk_antifraud_rs_header',
                'label' => __( 'Report Auto-Suspend', 'sk-core' ),
                'type'  => 'sub_section',
                'desc'  => __( 'Suspendiert Vendoren automatisch bei mehreren Reports.', 'sk-core' ),
            ],
            'sk_antifraud_report_suspend' => [
                'name'    => 'sk_antifraud_report_suspend',
                'label'   => __( 'Auto-Suspend aktivieren', 'sk-core' ),
                'type'    => 'switcher',
                'default' => 'off',
                'desc'    => __( 'Automatisch alle Produkte delisten bei genug Reports.', 'sk-core' ),
            ],
            'sk_antifraud_report_threshold' => [
                'name'    => 'sk_antifraud_report_threshold',
                'label'   => __( 'Melde-Schwelle', 'sk-core' ),
                'type'    => 'text',
                'default' => '3',
                'desc'    => __( 'Ab so vielen unterschiedlichen Meldern gehen alle Inserate des Anbieters offline.', 'sk-core' ),
            ],
            'sk_antifraud_report_window_days' => [
                'name'    => 'sk_antifraud_report_window_days',
                'label'   => __( 'Zeitfenster (Tage)', 'sk-core' ),
                'type'    => 'text',
                'default' => '30',
                'desc'    => __( 'Nur Meldungen aus diesem Zeitraum zählen zur Schwelle. 0 = alle.', 'sk-core' ),
            ],

            // ── Ban-Signale ──
            'sk_antifraud_ban_header' => [
                'name'  => 'sk_antifraud_ban_header',
                'label' => __( 'Gesperrte Merkmale', 'sk-core' ),
                'type'  => 'sub_section',
                'desc'  => __( 'Beim Sperren eines Anbieters werden Wallet, npub, Lightning-Adresse, Telegram-Handle, E-Mail und Telefon eingefroren. Meldet sich eins davon auf einem neuen Account an, wirst du benachrichtigt — das greift auch über Tor und neue IPs, weil er sich bezahlen lassen und erreichbar sein muss.', 'sk-core' ),
            ],
            'sk_antifraud_ban_autosuspend' => [
                'name'    => 'sk_antifraud_ban_autosuspend',
                'label'   => __( 'Bei Treffer automatisch sperren', 'sk-core' ),
                'type'    => 'switcher',
                'default' => 'off',
                'desc'    => __( 'Aus: du bekommst nur eine E-Mail und entscheidest selbst. Empfohlen, denn ein Fehltreffer nimmt sonst sofort einen ehrlichen Anbieter offline.', 'sk-core' ),
            ],

            // ── Melde-Schutz ──
            'sk_antifraud_guard_header' => [
                'name'  => 'sk_antifraud_guard_header',
                'label' => __( 'Schutz vor Melde-Missbrauch', 'sk-core' ),
                'type'  => 'sub_section',
                'desc'  => __( 'Meldungen können Anbieter offline nehmen — diese Grenzen verhindern, dass das als Waffe gegen Konkurrenz benutzt wird. Gilt sobald Anti-Fraud aktiv ist.', 'sk-core' ),
            ],
            'sk_antifraud_reports_per_hour' => [
                'name'    => 'sk_antifraud_reports_per_hour',
                'label'   => __( 'Meldungen pro Stunde und IP', 'sk-core' ),
                'type'    => 'text',
                'default' => '5',
                'desc'    => __( 'Mehr Meldungen aus derselben IP werden abgewiesen. 0 = kein Limit. Zusätzlich gilt immer: eine Meldung pro Nutzer und Inserat.', 'sk-core' ),
            ],
            'sk_antifraud_reporter_min_age' => [
                'name'    => 'sk_antifraud_reporter_min_age',
                'label'   => __( 'Mindestalter des Melders (Tage)', 'sk-core' ),
                'type'    => 'text',
                'default' => '14',
                'desc'    => __( 'Meldungen jüngerer Accounts werden gespeichert, zählen aber nicht zur Schwelle. Verhindert Delisting per Wegwerf-Accounts. 0 = kein Mindestalter.', 'sk-core' ),
            ],
        ];
    }
}
