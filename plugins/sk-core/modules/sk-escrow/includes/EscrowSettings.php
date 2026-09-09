<?php

namespace SK\Modules\Escrow;

defined( 'ABSPATH' ) || exit;

/**
 * The module's settings section, replacing the old Settings page under the
 * "Treuhand" menu (WEO_Settings::render, option group weo_settings).
 *
 * The escrow code itself is left alone: it keeps reading everything through
 * weo_get_option() from the weo_options array and from
 * weo_vendor_payout_fallback. This section only renders those values and
 * writes them back on save, through WEO_Settings' own sanitize callbacks —
 * so the xpub, the payout address and the numeric bounds are validated
 * exactly as before. Deactivating the module therefore changes nothing
 * about the stored configuration.
 */
class EscrowSettings {

    const SECTION = 'sk_escrow';

    /** The array option the escrow code reads through weo_get_option(). */
    const LEGACY_OPTION = 'weo_options';

    /** Payout fallback lives in its own option, not in the array above. */
    const FALLBACK_OPTION = 'weo_vendor_payout_fallback';

    public function __construct() {
        add_filter( 'sk_settings_sections', [ $this, 'add_section' ] );
        add_filter( 'sk_settings_fields', [ $this, 'add_fields' ] );
        add_action( 'sk_after_saving_settings', [ $this, 'sync_escrow_options' ], 10, 3 );
    }

    private function legacy( $key, $default = '' ) {
        $opts = get_option( self::LEGACY_OPTION, [] );

        return is_array( $opts ) && isset( $opts[ $key ] ) ? $opts[ $key ] : $default;
    }

    public function add_section( $sections ) {
        $sections[] = [
            'id'                   => self::SECTION,
            'title'                => __( 'Treuhand', 'sk-core' ),
            'icon_url'             => '',
            'description'          => __( 'On-Chain-Treuhand über 2-von-3-Multisig', 'sk-core' ),
            'settings_title'       => __( 'Treuhand (Escrow)', 'sk-core' ),
            'settings_description' => __( 'Nicht-verwahrende Treuhand für Bestellungen: Käufer, Verkäufer und Marktplatz halten je einen Schlüssel, ausgezahlt wird mit zwei von drei Signaturen über einen PSBT-Ablauf. Setzt eine erreichbare Escrow-API voraus.', 'sk-core' ),
        ];

        return $sections;
    }

    public function add_fields( $settings_fields ) {
        $settings_fields[ self::SECTION ] = [
            'weo_api_header' => [
                'name'  => 'weo_api_header',
                'label' => __( 'Escrow-API', 'sk-core' ),
                'type'  => 'sub_section',
                'desc'  => __( 'Zugang zum Escrow-Dienst, der die Multisig-Adressen und PSBTs erzeugt.', 'sk-core' ),
            ],
            'api_base' => [
                'name'    => 'api_base',
                'label'   => __( 'Escrow-API Base URL', 'sk-core' ),
                'type'    => 'text',
                'default' => $this->legacy( 'api_base', '' ),
                'desc'    => __( 'Beispiel: https://escrow.example.com/api', 'sk-core' ),
            ],
            'api_key' => [
                'name'    => 'api_key',
                'label'   => __( 'API Key', 'sk-core' ),
                'type'    => 'text',
                'default' => $this->legacy( 'api_key', '' ),
            ],
            'hmac_secret' => [
                'name'    => 'hmac_secret',
                'label'   => __( 'Webhook HMAC Secret', 'sk-core' ),
                'type'    => 'text',
                'default' => $this->legacy( 'hmac_secret', '' ),
                'desc'    => __( 'Signiert die Rückmeldungen der Escrow-API.', 'sk-core' ),
            ],
            'weo_keys_header' => [
                'name'  => 'weo_keys_header',
                'label' => __( 'Schlüssel und Auszahlung', 'sk-core' ),
                'type'  => 'sub_section',
            ],
            'escrow_xpub' => [
                'name'    => 'escrow_xpub',
                'label'   => __( 'Escrow xpub (eigener Schlüssel)', 'sk-core' ),
                'type'    => 'text',
                'default' => $this->legacy( 'escrow_xpub', '' ),
                'desc'    => __( 'Der dritte Schlüssel im 2-von-3-Multisig.', 'sk-core' ),
            ],
            'vendor_payout_fallback' => [
                'name'    => 'vendor_payout_fallback',
                'label'   => __( 'Fallback Vendor-Payout-Adresse', 'sk-core' ),
                'type'    => 'text',
                'default' => (string) get_option( self::FALLBACK_OPTION, '' ),
                'desc'    => __( 'Adresse für Verkäufer-Auszahlungen, wenn kein eigener Empfang hinterlegt ist. Ohne gültige Adresse werden Auszahlungen abgebrochen.', 'sk-core' ),
            ],
            'weo_flow_header' => [
                'name'  => 'weo_flow_header',
                'label' => __( 'Ablauf', 'sk-core' ),
                'type'  => 'sub_section',
            ],
            'min_conf' => [
                'name'    => 'min_conf',
                'label'   => __( 'Min. Bestätigungen', 'sk-core' ),
                'type'    => 'number',
                'min'     => '0',
                'max'     => '6',
                'default' => (string) $this->legacy( 'min_conf', '2' ),
            ],
            'timeout_days' => [
                'name'    => 'timeout_days',
                'label'   => __( 'Signatur-Timeout (Tage)', 'sk-core' ),
                'type'    => 'number',
                'min'     => '1',
                'default' => (string) $this->legacy( 'timeout_days', '7' ),
            ],
            'vendor_escrow_enabled' => [
                'name'    => 'vendor_escrow_enabled',
                'label'   => __( 'Treuhand für Verkäufer aktiv', 'sk-core' ),
                'type'    => 'switcher',
                'default' => '1' === $this->legacy( 'vendor_escrow_enabled', '1' ) ? 'on' : 'off',
                'desc'    => __( 'Verkäufer dürfen Treuhand nutzen. Aus: Treuhand-Funktionen im Dashboard und auf Produktseiten sind für Verkäufer nicht sichtbar.', 'sk-core' ),
            ],
            'vendor_escrow_admin_only' => [
                'name'    => 'vendor_escrow_admin_only',
                'label'   => __( 'Treuhand nur für Administratoren', 'sk-core' ),
                'type'    => 'switcher',
                'default' => '1' === $this->legacy( 'vendor_escrow_admin_only', '' ) ? 'on' : 'off',
                'desc'    => __( 'Blendet Treuhand-Seite, Einstellungen und Produktaktivierung für Verkäufer aus, während Administratoren sie weiter sehen.', 'sk-core' ),
            ],
        ];

        return $settings_fields;
    }

    /**
     * Write the section back into the options the escrow code reads.
     *
     * Sanitizing goes through WEO_Settings so the xpub and the payout
     * address are validated by the same code as before; an invalid payout
     * address is rejected there and would otherwise silently break payouts.
     */
    public function sync_escrow_options( $section, $new_values, $old_values ) {
        if ( self::SECTION !== $section || ! class_exists( '\WEO_Settings' ) ) {
            return;
        }

        $current = get_option( self::LEGACY_OPTION, [] );
        $current = is_array( $current ) ? $current : [];

        /*
         * Start from what is stored and only overwrite what was actually
         * submitted. Handing sanitize() a partial array would reset every
         * missing key to its default — that would silently wipe the API key
         * or the xpub if a field ever stops being posted.
         */
        $merged = $current;

        foreach ( [ 'api_base', 'escrow_xpub', 'min_conf', 'api_key', 'hmac_secret', 'timeout_days' ] as $key ) {
            if ( isset( $new_values[ $key ] ) ) {
                $merged[ $key ] = $new_values[ $key ];
            }
        }

        foreach ( [ 'vendor_escrow_enabled', 'vendor_escrow_admin_only' ] as $key ) {
            if ( isset( $new_values[ $key ] ) ) {
                $merged[ $key ] = 'on' === $new_values[ $key ] ? '1' : '';
            }
        }

        $weo = new \WEO_Settings();

        update_option( self::LEGACY_OPTION, $weo->sanitize( $merged ) );

        if ( ! isset( $new_values['vendor_payout_fallback'] ) ) {
            return;
        }

        /*
         * Payouts go to this address, so it is validated properly rather than
         * just trimmed: weo_sanitize_btc_address() only runs sanitize_text_field
         * over the input and would happily store any non-empty string.
         * Rejecting a bad value keeps the previous working address in place.
         */
        $address = weo_sanitize_btc_address( $new_values['vendor_payout_fallback'] );

        if ( '' === $address || ! weo_validate_btc_address( $address ) ) {
            // add_settings_error() lives in wp-admin/includes and is not loaded
            // on every path that can save settings.
            if ( function_exists( 'add_settings_error' ) ) {
                add_settings_error(
                    self::FALLBACK_OPTION,
                    'invalid',
                    __( 'Die Fallback-Auszahlungsadresse ist keine gültige Bech32-Adresse (bc1…) und wurde nicht übernommen.', 'sk-core' )
                );
            }

            return;
        }

        update_option( self::FALLBACK_OPTION, $address );
    }
}
