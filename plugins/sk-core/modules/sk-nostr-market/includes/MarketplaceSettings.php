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
            'settings_description' => __( 'Produkte als NIP-99 Classified Listings auf Nostr publishen. Sichtbar auf Amethyst, Shopstr, Coracle, Plebeian Market und jedem NIP-99 Client.', 'sk' ),
        ];

        return $sections;
    }

    public function add_fields( $settings_fields ) {
        $pubkey = EventSender::get_pubkey();

        $settings_fields['sk_nostr_market'] = [
            'sk_nostr_market_enabled' => [
                'name'    => 'sk_nostr_market_enabled',
                'label'   => __( 'Nostr Marketplace aktivieren', 'sk-core' ),
                'type'    => 'switcher',
                'default' => 'off',
                'desc'    => __( 'Produkte automatisch als NIP-99 Classified Listings auf Nostr Relays publishen. Sichtbar auf Amethyst, Shopstr, Coracle, Plebeian Market.', 'sk-core' ),
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

            // ── Nostr DM Bridge ──
            'sk_nostr_market_bridge_header' => [
                'name'  => 'sk_nostr_market_bridge_header',
                'label' => __( 'Nostr DM Bridge', 'sk-core' ),
                'type'  => 'sub_section',
                'desc'  => __( 'Leitet Nostr-Nachrichten von Käufern an Vendors weiter und Vendor-Antworten zurück an den Nostr User.', 'sk-core' ),
            ],
            'sk_nostr_market_bridge_enabled' => [
                'name'    => 'sk_nostr_market_bridge_enabled',
                'label'   => __( 'DM Bridge aktivieren', 'sk-core' ),
                'type'    => 'switcher',
                'default' => 'off',
                'desc'    => __( 'Pollt Nostr Relays alle 2 Minuten nach eingehenden DMs und leitet sie als VendorChat-Nachrichten weiter.', 'sk-core' ),
            ],
        ];

        return $settings_fields;
    }
}
