<?php

namespace SK\Modules\Reputation;

defined( 'ABSPATH' ) || exit;

/**
 * The module's own settings section.
 *
 * The switch used to sit in the SK Payments section, which tied the whole
 * reputation system to payments being configured. Reputation now stands on
 * its own — payments are one source of signals among several — so its
 * switch lives here.
 */
class Settings {

    const SECTION = 'sk_reputation';

    public function __construct() {
        add_filter( 'sk_settings_sections', [ $this, 'add_section' ] );
        add_filter( 'sk_settings_fields', [ $this, 'add_fields' ] );
    }

    public function add_section( $sections ) {
        $sections[] = [
            'id'                   => self::SECTION,
            'title'                => __( 'SK Reputation', 'sk-core' ),
            'icon_url'             => '',
            'description'          => __( 'Vertrauenssignale an Anbietern', 'sk-core' ),
            'settings_title'       => __( 'Reputation', 'sk-core' ),
            'settings_description' => __( 'Nachprüfbare Vertrauenssignale neben dem Anbieternamen: Nostr-Schlüsselbindung, Kontakte des Betrachters, die dem Anbieter folgen, und – mit SK Payments – verifizierte Lightning-Zahlungen. Signale stehen nebeneinander und werden nie zu einer Zahl verrechnet.', 'sk-core' ),
        ];

        return $sections;
    }

    public function add_fields( $settings_fields ) {
        $settings_fields[ self::SECTION ] = [
            'sk_reputation_enabled' => [
                'name'    => 'sk_reputation_enabled',
                'label'   => __( 'Reputation aktivieren', 'sk-core' ),
                'type'    => 'switcher',
                'default' => 'on',
                'desc'    => __( 'Zeigt die Vertrauenssignale an Store, Produktseite und im Feed. Ohne Signal bleibt ein Anbieter unverändert – es gibt keine Negativanzeige.', 'sk-core' ),
            ],
        ];

        return $settings_fields;
    }
}
