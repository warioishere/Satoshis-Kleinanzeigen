<?php

namespace SK\Modules\NostrMarket;

defined( 'ABSPATH' ) || exit;

class MarketplaceSettings {

    public function __construct() {
        add_filter( 'sk_settings_sections', [ $this, 'add_section' ] );
        add_filter( 'sk_settings_fields', [ $this, 'add_fields' ] );
    }

    public function add_section( $sections ) {
        $sections[] = [
            'id'                   => 'sk_nostr_market',
            'title'                => __( 'SK Nostr Market', 'sk' ),
            'icon_url'             => '',
            'description'          => __( 'NIP-15 Nostr Marketplace', 'sk' ),
            'settings_title'       => __( 'Nostr Marketplace', 'sk' ),
            'settings_description' => __( 'Produkte als strukturierte NIP-15 Events auf Nostr publishen. Sichtbar auf Plebeian Market, LNbits NostrMarket und jedem NIP-15 Client.', 'sk' ),
        ];

        return $sections;
    }

    public function add_fields( $settings_fields ) {
        $pubkey = EventSender::get_pubkey();

        $settings_fields['sk_nostr_market'] = [
            'sk_nostr_market_enabled' => [
                'name'    => 'sk_nostr_market_enabled',
                'label'   => __( 'NIP-15 Marketplace aktivieren', 'sk-core' ),
                'type'    => 'switcher',
                'default' => 'off',
                'desc'    => __( 'Produkte automatisch als NIP-15 Marketplace Events auf Nostr Relays publishen.', 'sk-core' ),
            ],
            'sk_nostr_market_pubkey_info' => [
                'name'  => 'sk_nostr_market_pubkey_info',
                'label' => __( 'Marketplace Pubkey', 'sk-core' ),
                'type'  => 'sub_section',
                'desc'  => $pubkey
                    ? '<code>' . esc_html( $pubkey ) . '</code>'
                    : __( 'Kein Private Key konfiguriert. Nutze den Nostr Auto Poster Private Key (NAP_NOSTR_PRIVKEY in wp-config.php).', 'sk-core' ),
            ],
            'sk_nostr_market_currency' => [
                'name'    => 'sk_nostr_market_currency',
                'label'   => __( 'Währung', 'sk-core' ),
                'type'    => 'select',
                'default' => 'sat',
                'options' => [
                    'sat' => 'Satoshis (sat)',
                    'btc' => 'Bitcoin (BTC)',
                ],
                'desc' => __( 'Währung für Preise in NIP-15 Events.', 'sk-core' ),
            ],
            'sk_nostr_market_shipping_regions' => [
                'name'    => 'sk_nostr_market_shipping_regions',
                'label'   => __( 'Shipping-Regionen', 'sk-core' ),
                'type'    => 'text',
                'default' => 'EU,CH',
                'desc'    => __( 'Kommaseparierte Ländercodes für Versand.', 'sk-core' ),
            ],
            'sk_nostr_market_relays' => [
                'name'    => 'sk_nostr_market_relays',
                'label'   => __( 'Relays (optional)', 'sk-core' ),
                'type'    => 'text',
                'default' => '',
                'desc'    => __( 'Eigene Relay URLs für Marketplace Events. Leer = gleiche Relays wie Nostr Auto Poster.', 'sk-core' ),
            ],
        ];

        return $settings_fields;
    }
}
