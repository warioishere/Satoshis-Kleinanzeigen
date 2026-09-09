<?php

namespace SK\Modules\ContactFeewall;

defined( 'ABSPATH' ) || exit;

/**
 * The module's settings section: one switch, the global on/off that used
 * to live under Settings → Contact Details Feewall (`add_options_page`,
 * option `cdf_enabled`, values "yes"/"no").
 *
 * That option is migrated into this section once: if it still exists when
 * the section is read for the first time, its value is copied in as
 * "on"/"off" and the old option is dropped. After that this class never
 * looks at it again.
 */
class Settings {

    const SECTION = 'sk_contact_feewall';

    /** The option this section replaces (Settings → Contact Details Feewall). */
    const LEGACY_OPTION = 'cdf_enabled';

    public function __construct() {
        $this->migrate_legacy_option();

        add_filter( 'sk_settings_sections', [ $this, 'add_section' ] );
        add_filter( 'sk_settings_fields', [ $this, 'add_fields' ] );
    }

    private function migrate_legacy_option(): void {
        $legacy = get_option( self::LEGACY_OPTION, null );

        if ( null === $legacy ) {
            return;
        }

        $section = get_option( self::SECTION );
        $section = is_array( $section ) ? $section : [];

        if ( ! isset( $section['cdf_enabled'] ) ) {
            $section['cdf_enabled'] = 'yes' === $legacy ? 'on' : 'off';
            update_option( self::SECTION, $section );
        }

        delete_option( self::LEGACY_OPTION );
    }

    public function add_section( $sections ) {
        $sections[] = [
            'id'                   => self::SECTION,
            'title'                => __( 'Kontakt-Feewall', 'sk-core' ),
            'icon_url'             => '',
            'description'          => __( 'Sats-Paywall auf Kontaktdaten', 'sk-core' ),
            'settings_title'       => __( 'Kontakt-Feewall', 'sk-core' ),
            'settings_description' => __( 'Anbieter können ihre Kontaktdaten mit einer 21-Sats-Paywall über BTCPay schützen: Interessenten zahlen einmalig, um Telefon, E-Mail, Telegram, Nostr und Adressen zu sehen. Der Schalter hier steht global über allen Anbieter-Einstellungen — ist er aus, sehen Anbieter die Option in ihren Store-Einstellungen gar nicht erst.', 'sk-core' ),
        ];

        return $sections;
    }

    public function add_fields( $settings_fields ) {
        $settings_fields[ self::SECTION ] = [
            'cdf_enabled' => [
                'name'    => 'cdf_enabled',
                'label'   => __( 'Kontakt-Feewall aktivieren', 'sk-core' ),
                'type'    => 'switcher',
                'default' => 'on',
                'desc'    => __( 'Erlaubt Anbietern, ihre Kontaktdaten hinter einer 21-Sats-Zahlung zu verstecken. Setzt einen konfigurierten BTCPay-Server (WooCommerce-Greenfield-Gateway) voraus.', 'sk-core' ),
            ],
        ];

        return $settings_fields;
    }
}
