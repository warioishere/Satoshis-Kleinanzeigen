<?php

namespace SK\Modules\Payments\Admin;

use SK\Modules\Payments\NWC\Client as NWCClient;

defined( 'ABSPATH' ) || exit;

class AdminSettings {

    const LNDHUB_OPTION = 'sk_commission_lndhub_encrypted';

    public function __construct() {
        add_filter( 'sk_settings_sections', [ $this, 'add_settings_section' ] );
        add_filter( 'sk_settings_fields', [ $this, 'add_settings_fields' ] );

        // Intercept save to encrypt LNDHub before it hits wp_options in plain text.
        add_action( 'sk_after_saving_settings', [ $this, 'handle_lndhub_save' ], 10, 3 );
    }

    public function add_settings_section( $sections ) {
        $sections[] = [
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
        $has_lndhub = ! empty( get_option( self::LNDHUB_OPTION, '' ) );

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
            'sk_commission_enabled' => [
                'name'    => 'sk_commission_enabled',
                'label'   => __( 'Kommission aktivieren', 'sk-core' ),
                'type'    => 'switcher',
                'default' => 'off',
                'desc'    => __( 'Marketplace-Kommission auf bestätigte Zahlungen. Erstellt automatisch eine Lightning-Invoice an den Vendor.', 'sk-core' ),
            ],
            'sk_commission_rate' => [
                'name'    => 'sk_commission_rate',
                'label'   => __( 'Kommission (%)', 'sk-core' ),
                'type'    => 'number',
                'default' => '2',
                'desc'    => __( 'Prozentsatz der Kommission auf jede bestätigte Zahlung.', 'sk-core' ),
            ],
            'sk_commission_lndhub' => [
                'name'        => 'sk_commission_lndhub',
                'label'       => __( 'Marketplace LNDHub (Kommission)', 'sk-core' ),
                'type'        => 'text',
                'default'     => '',
                'placeholder' => $has_lndhub ? 'lndhub://******** (gespeichert — leer lassen um beizubehalten)' : 'lndhub://login:password@https://...',
                'desc'        => $has_lndhub
                    ? __( 'LNDHub ist verschlüsselt gespeichert. Neuen String eingeben um zu ändern, leer lassen um beizubehalten.', 'sk-core' )
                    : __( 'LNDHub Connection String des Marketplace-Wallets. Wird verschlüsselt gespeichert.', 'sk-core' ),
            ],
            'sk_commission_enforcement' => [
                'name'    => 'sk_commission_enforcement',
                'label'   => __( 'Enforcement aktivieren', 'sk-core' ),
                'type'    => 'switcher',
                'default' => 'off',
                'desc'    => __( 'Erinnerungen + automatische Sperre bei unbezahlten Kommissionen. Wenn deaktiviert, werden Kommissionen nur erstellt aber nicht eingetrieben.', 'sk-core' ),
            ],
            'sk_commission_reminders' => [
                'name'    => 'sk_commission_reminders',
                'label'   => __( 'Erinnerungen vor Sperre', 'sk-core' ),
                'type'    => 'number',
                'default' => '3',
                'desc'    => __( 'Anzahl wöchentlicher Erinnerungen bevor ein Vendor gesperrt wird. Nach der letzten Erinnerung folgt 1 Woche Frist, dann Sperre.', 'sk-core' ),
            ],
        ];

        return $settings_fields;
    }

    /**
     * After settings are saved: encrypt LNDHub and store separately.
     * Remove plain text from the sk_lightning option.
     */
    public function handle_lndhub_save( $section, $new_values, $old_values ) {
        if ( $section !== 'sk_lightning' ) {
            return;
        }

        $lndhub_raw = $new_values['sk_commission_lndhub'] ?? '';

        if ( ! empty( $lndhub_raw ) && strpos( $lndhub_raw, 'lndhub://' ) === 0 ) {
            // Encrypt and store separately.
            $encrypted = NWCClient::encrypt_connection_string( $lndhub_raw );
            update_option( self::LNDHUB_OPTION, $encrypted );
        }

        // Always remove plain text from the settings option.
        $settings = get_option( 'sk_lightning', [] );
        if ( is_array( $settings ) ) {
            $settings['sk_commission_lndhub'] = '';
            update_option( 'sk_lightning', $settings );
        }
    }

    /**
     * Get the decrypted marketplace LNDHub connection string.
     */
    public static function get_marketplace_lndhub(): string {
        $encrypted = get_option( self::LNDHUB_OPTION, '' );
        if ( empty( $encrypted ) ) {
            return '';
        }

        return NWCClient::decrypt_connection_string( $encrypted );
    }
}
