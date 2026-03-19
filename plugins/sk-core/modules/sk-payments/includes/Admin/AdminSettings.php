<?php

namespace SK\Modules\Payments\Admin;

defined( 'ABSPATH' ) || exit;

class AdminSettings {

    public function __construct() {
        add_filter( 'sk_settings_sections', [ $this, 'add_settings_section' ] );
        add_filter( 'sk_settings_fields', [ $this, 'add_settings_fields' ] );
    }

    public function add_settings_section( $sections ) {
        $sections['sk_lightning'] = [
            'id'                   => 'sk_lightning',
            'title'                => __( 'SK Payments', 'sk' ),
            'icon_url'             => '',
            'description'          => __( 'Non-custodial Lightning-Zahlungen', 'sk' ),
            'settings_title'       => __( 'SK Payments Settings', 'sk' ),
            'settings_description' => __( 'Einstellungen für Lightning-Zahlungen und Reputation.', 'sk' ),
        ];

        return $sections;
    }

    public function add_settings_fields( $settings_fields ) {
        $settings_fields['sk_lightning'] = [
            'sk_payments_enabled' => [
                'name'    => 'sk_payments_enabled',
                'label'   => __( 'Lightning Payments aktivieren', 'sk-core' ),
                'type'    => 'switcher',
                'default' => 'on',
                'desc'    => __( 'Ermöglicht Vendors, Lightning-Zahlungen zu empfangen (NWC, LNDHub, Lightning-Adresse).', 'sk-core' ),
            ],
            'sk_reputation_enabled' => [
                'name'    => 'sk_reputation_enabled',
                'label'   => __( 'Reputation-System aktivieren', 'sk-core' ),
                'type'    => 'switcher',
                'default' => 'on',
                'desc'    => __( 'Sybil-resistentes Reputationssystem basierend auf verifizierten Lightning-Zahlungen. Zeigt Badges und Proof-Pages auf Store-Seiten.', 'sk-core' ),
            ],
            'sk_lightning_chat_integration' => [
                'name'    => 'sk_lightning_chat_integration',
                'label'   => __( 'Chat-Integration', 'sk-core' ),
                'type'    => 'switcher',
                'default' => 'on',
                'desc'    => __( 'Lightning-Kaufanfragen und Invoices im VendorChat. Wenn deaktiviert, erscheint der Lightning-Button direkt auf der Produktseite.', 'sk-core' ),
            ],
        ];

        return $settings_fields;
    }
}
