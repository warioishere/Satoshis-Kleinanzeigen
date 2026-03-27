<?php

namespace SK\Modules\AntiFraud;

defined( 'ABSPATH' ) || exit;

class AntifraudSettings {

    public function __construct() {
        add_filter( 'sk_settings_sections', [ $this, 'add_section' ] );
        add_filter( 'sk_settings_fields', [ $this, 'add_fields' ] );
    }

    public function add_section( $sections ) {
        $sections[] = [
            'id'                   => 'sk_antifraud',
            'title'                => __( 'Anti-Fraud', 'sk-core' ),
            'icon_url'             => '',
            'description'          => __( 'Scam-Schutz für den Marketplace', 'sk-core' ),
            'settings_title'       => __( 'Anti-Fraud', 'sk-core' ),
            'settings_description' => __( 'Fingerprinting, Käufer-Warnungen, Verkaufslimits und Report-Auto-Suspend.', 'sk-core' ),
        ];
        return $sections;
    }

    public function add_fields( $fields ) {
        $fields['sk_antifraud'] = [

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
                'label'   => __( 'Report-Schwelle', 'sk-core' ),
                'type'    => 'text',
                'default' => '3',
                'desc'    => __( 'Anzahl Reports von verschiedenen IPs für Auto-Delist.', 'sk-core' ),
            ],
        ];

        return $fields;
    }
}
