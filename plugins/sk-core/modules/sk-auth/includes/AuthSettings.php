<?php

namespace SK\Modules\Auth;

defined( 'ABSPATH' ) || exit;

/**
 * Unified Auth Settings — SK PHP Dashboard integration.
 *
 * Adds "SK Auth" section to the PHP Dashboard Settings tab
 * with sub-sections for LNURL Auth, Nostr Login, and BTC Login.
 */
class AuthSettings {

    public function __construct() {
        add_filter( 'sk_settings_sections', [ $this, 'add_section' ] );
        add_filter( 'sk_settings_fields', [ $this, 'add_fields' ] );
        add_action( 'sk_after_saving_settings', [ $this, 'sync_legacy_options' ], 10, 3 );
    }

    public function add_section( $sections ) {
        $sections[] = [
            'id'                   => 'sk_auth',
            'title'                => __( 'SK Auth', 'sk-core' ),
            'icon_url'             => '',
            'description'          => __( 'Bitcoin, Lightning und Nostr Login', 'sk-core' ),
            'settings_title'       => __( 'Authentifizierung', 'sk-core' ),
            'settings_description' => __( 'Login-Methoden konfigurieren: BTC Login, LNURL Auth (Lightning), Nostr Login.', 'sk-core' ),
        ];

        return $sections;
    }

    public function add_fields( $settings_fields ) {
        $settings_fields['sk_auth'] = [

            // ── LNURL Auth ──
            'sk_auth_lnurl_header' => [
                'name'  => 'sk_auth_lnurl_header',
                'label' => __( 'LNURL Auth (Lightning)', 'sk-core' ),
                'type'  => 'sub_section',
                'desc'  => __( 'Login via Lightning Wallet QR-Code. Original: Joel Stuedle.', 'sk-core' ),
            ],
            'sk_auth_lnurl_login_mode' => [
                'name'    => 'sk_auth_lnurl_login_mode',
                'label'   => __( 'Login-Modus', 'sk-core' ),
                'type'    => 'select',
                'default' => get_option( 'lnurl-auth-login-options', 'prio-lightning' ),
                'options' => [
                    'prio-lightning'  => __( 'Lightning bevorzugt', 'sk-core' ),
                    'prio-wp'         => __( 'WordPress bevorzugt', 'sk-core' ),
                    'lightning-only'  => __( 'Nur Lightning', 'sk-core' ),
                    'wordpress-only'  => __( 'Nur WordPress', 'sk-core' ),
                ],
                'desc' => __( 'Welche Login-Methode auf der Login-Seite bevorzugt wird.', 'sk-core' ),
            ],
            'sk_auth_lnurl_callback_url' => [
                'name'    => 'sk_auth_lnurl_callback_url',
                'label'   => __( 'Callback URL', 'sk-core' ),
                'type'    => 'text',
                'default' => get_option( 'lnurl-auth-callback-url', home_url() ),
                'desc'    => __( 'URL die Lightning Wallets für die Authentifizierung aufrufen.', 'sk-core' ),
            ],
            'sk_auth_lnurl_redirect_url' => [
                'name'    => 'sk_auth_lnurl_redirect_url',
                'label'   => __( 'Redirect nach Login', 'sk-core' ),
                'type'    => 'text',
                'default' => get_option( 'lnurl-auth-redirect-url', '' ),
                'desc'    => __( 'URL wohin nach erfolgreichem Login weitergeleitet wird. Leer = Standard.', 'sk-core' ),
            ],
            'sk_auth_lnurl_usercreation' => [
                'name'    => 'sk_auth_lnurl_usercreation',
                'label'   => __( 'Benutzer automatisch erstellen', 'sk-core' ),
                'type'    => 'switcher',
                'default' => get_option( 'lnurl-auth-usercreation', 'on' ) === 'on' ? 'on' : 'off',
                'desc'    => __( 'Neue Benutzer automatisch erstellen wenn sie sich zum ersten Mal via Lightning einloggen.', 'sk-core' ),
            ],
            'sk_auth_lnurl_usercreation_prefix' => [
                'name'    => 'sk_auth_lnurl_usercreation_prefix',
                'label'   => __( 'Benutzername-Prefix', 'sk-core' ),
                'type'    => 'text',
                'default' => get_option( 'lnurl-auth-usercreation-prefix', 'LN-' ),
                'desc'    => __( 'Prefix für automatisch erstellte Benutzernamen (z.B. "LN-" ergibt "LN-1").', 'sk-core' ),
            ],

            // ── Nostr Login ──
            'sk_auth_nostr_header' => [
                'name'  => 'sk_auth_nostr_header',
                'label' => __( 'Nostr Login', 'sk-core' ),
                'type'  => 'sub_section',
                'desc'  => __( 'Login via Nostr Browser Extension (NIP-07). Original: Yeghro.', 'sk-core' ),
            ],
            'sk_auth_nostr_relays' => [
                'name'    => 'sk_auth_nostr_relays',
                'label'   => __( 'Nostr Relays', 'sk-core' ),
                'type'    => 'textarea',
                'default' => get_option( 'nostr_login_relays', "wss://purplepag.es\nwss://relay.nostr.band\nwss://relay.primal.net\nwss://relay.damus.io" ),
                'desc'    => __( 'Relay URLs, eine pro Zeile.', 'sk-core' ),
                'rows'    => 5,
            ],
            'sk_auth_nostr_redirect' => [
                'name'    => 'sk_auth_nostr_redirect',
                'label'   => __( 'Redirect nach Login', 'sk-core' ),
                'type'    => 'select',
                'default' => get_option( 'nostr_login_redirect', 'dashboard' ),
                'options' => [
                    'dashboard' => __( 'Vendor Dashboard', 'sk-core' ),
                    'home'      => __( 'Startseite', 'sk-core' ),
                    'profile'   => __( 'Profil', 'sk-core' ),
                    'admin'     => __( 'WP Admin', 'sk-core' ),
                ],
                'desc' => __( 'Wohin nach erfolgreichem Nostr-Login weitergeleitet wird.', 'sk-core' ),
            ],
            'sk_auth_nostr_login_box' => [
                'name'    => 'sk_auth_nostr_login_box',
                'label'   => __( 'Nostr Login Box anzeigen', 'sk-core' ),
                'type'    => 'switcher',
                'default' => get_option( 'show_nostr_login_box', true ) ? 'on' : 'off',
                'desc'    => __( 'Nostr Login Box Shortcode [nostr_login_box] aktivieren.', 'sk-core' ),
            ],

            // ── BTC Login ──
            'sk_auth_btc_header' => [
                'name'  => 'sk_auth_btc_header',
                'label' => __( 'BTC Login', 'sk-core' ),
                'type'  => 'sub_section',
                'desc'  => __( 'Login/Registrierung via Bitcoin-Adresse + Passwort.', 'sk-core' ),
            ],
            'sk_auth_btc_enabled' => [
                'name'    => 'sk_auth_btc_enabled',
                'label'   => __( 'BTC Login aktivieren', 'sk-core' ),
                'type'    => 'switcher',
                'default' => 'on',
                'desc'    => __( 'Shortcode [btc_login] aktivieren. Ermöglicht Login/Registrierung mit Bitcoin-Adresse.', 'sk-core' ),
            ],

            // ── Unified Auth Connector ──
            'sk_auth_connector_header' => [
                'name'  => 'sk_auth_connector_header',
                'label' => __( 'Auth Connector', 'sk-core' ),
                'type'  => 'sub_section',
                'desc'  => __( 'Verknüpft LNURL-Auth und Nostr Login mit einem einzigen WordPress-Account.', 'sk-core' ),
            ],
            'sk_auth_connector_enabled' => [
                'name'    => 'sk_auth_connector_enabled',
                'label'   => __( 'Auth Connector aktivieren', 'sk-core' ),
                'type'    => 'switcher',
                'default' => get_option( 'uac_enabled', 'no' ) === 'yes' ? 'on' : 'off',
                'desc'    => __( 'Ermöglicht Vendors, mehrere Login-Methoden (Lightning + Nostr + BTC) mit einem Account zu verknüpfen.', 'sk-core' ),
            ],
            'sk_auth_connector_linking' => [
                'name'    => 'sk_auth_connector_linking',
                'label'   => __( 'Account Linking erlauben', 'sk-core' ),
                'type'    => 'switcher',
                'default' => get_option( 'uac_enable_account_linking', 'yes' ) === 'yes' ? 'on' : 'off',
                'desc'    => __( 'Vendors können neue Auth-Methoden mit ihrem Account verknüpfen.', 'sk-core' ),
            ],
            'sk_auth_connector_unlinking' => [
                'name'    => 'sk_auth_connector_unlinking',
                'label'   => __( 'Unlinking erlauben', 'sk-core' ),
                'type'    => 'switcher',
                'default' => get_option( 'uac_allow_unlinking', 'yes' ) === 'yes' ? 'on' : 'off',
                'desc'    => __( 'Vendors können verknüpfte Auth-Methoden wieder trennen.', 'sk-core' ),
            ],
        ];

        return $settings_fields;
    }

    /**
     * After saving: sync new sk_auth settings back to legacy option keys
     * so the existing Login/Settings classes continue to work.
     */
    public function sync_legacy_options( $section, $new_values, $old_values ) {
        if ( $section !== 'sk_auth' ) {
            return;
        }

        // LNURL Auth legacy options.
        $lnurl_map = [
            'sk_auth_lnurl_login_mode'           => 'lnurl-auth-login-options',
            'sk_auth_lnurl_callback_url'          => 'lnurl-auth-callback-url',
            'sk_auth_lnurl_redirect_url'          => 'lnurl-auth-redirect-url',
            'sk_auth_lnurl_usercreation_prefix'   => 'lnurl-auth-usercreation-prefix',
        ];

        foreach ( $lnurl_map as $new_key => $legacy_key ) {
            if ( isset( $new_values[ $new_key ] ) ) {
                update_option( $legacy_key, $new_values[ $new_key ] );
            }
        }

        // Switcher → legacy format.
        if ( isset( $new_values['sk_auth_lnurl_usercreation'] ) ) {
            update_option( 'lnurl-auth-usercreation', $new_values['sk_auth_lnurl_usercreation'] === 'on' ? 'on' : 'off' );
        }

        // Nostr Login legacy options.
        if ( isset( $new_values['sk_auth_nostr_relays'] ) ) {
            update_option( 'nostr_login_relays', $new_values['sk_auth_nostr_relays'] );
        }
        if ( isset( $new_values['sk_auth_nostr_redirect'] ) ) {
            update_option( 'nostr_login_redirect', $new_values['sk_auth_nostr_redirect'] );
        }
        if ( isset( $new_values['sk_auth_nostr_login_box'] ) ) {
            update_option( 'show_nostr_login_box', $new_values['sk_auth_nostr_login_box'] === 'on' ? 1 : 0 );
        }

        // Auth Connector legacy options.
        if ( isset( $new_values['sk_auth_connector_enabled'] ) ) {
            update_option( 'uac_enabled', $new_values['sk_auth_connector_enabled'] === 'on' ? 'yes' : 'no' );
        }
        if ( isset( $new_values['sk_auth_connector_linking'] ) ) {
            update_option( 'uac_enable_account_linking', $new_values['sk_auth_connector_linking'] === 'on' ? 'yes' : 'no' );
        }
        if ( isset( $new_values['sk_auth_connector_unlinking'] ) ) {
            update_option( 'uac_allow_unlinking', $new_values['sk_auth_connector_unlinking'] === 'on' ? 'yes' : 'no' );
        }
    }
}
