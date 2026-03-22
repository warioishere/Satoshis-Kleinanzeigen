<?php

namespace SK\Modules\Zaps;

defined( 'ABSPATH' ) || exit;

class ZapSettings {

    public function __construct() {
        add_filter( 'sk_settings_sections', [ $this, 'add_section' ] );
        add_filter( 'sk_settings_fields', [ $this, 'add_fields' ] );
    }

    public function add_section( $sections ) {
        $sections[] = [
            'id'                   => 'sk_zaps',
            'title'                => __( 'SK Zaps', 'sk' ),
            'icon_url'             => '',
            'description'          => __( 'Lightning Zaps für Vendors', 'sk' ),
            'settings_title'       => __( 'Zap Button', 'sk' ),
            'settings_description' => __( 'Lightning Zap Buttons auf Store- und Produktseiten. Vendors mit Lightning Address und Nostr Key können Zaps empfangen.', 'sk' ),
        ];

        return $sections;
    }

    public function add_fields( $settings_fields ) {
        $settings_fields['sk_zaps'] = [
            'sk_zaps_enabled' => [
                'name'    => 'sk_zaps_enabled',
                'label'   => __( 'Zap Button aktivieren', 'sk-core' ),
                'type'    => 'switcher',
                'default' => 'off',
                'desc'    => __( 'Zeigt einen Zap Button auf Store- und Produktseiten. Funktioniert mit Alby Hub Extension (NIP-57) oder als Lightning Tip Fallback.', 'sk-core' ),
            ],
            'sk_zaps_on_store' => [
                'name'    => 'sk_zaps_on_store',
                'label'   => __( 'Auf Store-Seiten', 'sk-core' ),
                'type'    => 'switcher',
                'default' => 'on',
                'desc'    => __( 'Zap Button auf der Vendor Store-Seite anzeigen.', 'sk-core' ),
            ],
            'sk_zaps_on_product' => [
                'name'    => 'sk_zaps_on_product',
                'label'   => __( 'Auf Produktseiten', 'sk-core' ),
                'type'    => 'switcher',
                'default' => 'on',
                'desc'    => __( 'Zap Button auf Einzelproduktseiten anzeigen.', 'sk-core' ),
            ],
            'sk_zaps_default_amount' => [
                'name'    => 'sk_zaps_default_amount',
                'label'   => __( 'Standard-Betrag (Sats)', 'sk-core' ),
                'type'    => 'number',
                'default' => '21',
                'desc'    => __( 'Vorausgefüllter Betrag für Zaps.', 'sk-core' ),
            ],
        ];

        return $settings_fields;
    }
}
