<?php

namespace SK_Lightning;

defined( 'ABSPATH' ) || exit;

class StoreSettings {

    public function __construct() {
        // Add Lightning wallet section to store settings (own section, directly after contact fields).
        add_action( 'sk_settings_after_contact', [ $this, 'render_field' ], 10, 2 );

        // Save Lightning address when store profile is saved.
        add_action( 'sk_store_profile_saved', [ $this, 'save_field' ], 15, 3 );

        // Add Lightning icon to contact icons on product/store pages.
        add_filter( 'dkp_contact_icons_collection', [ $this, 'add_lightning_icon' ], 10, 4 );

        // Reputation badge in shop loop removed — too noisy on product cards.
    }

    /**
     * Render the Lightning address input field in store settings.
     */
    public function render_field( $current_user, $profile_info ) {
        $lightning_address = $profile_info['lightning_address'] ?? '';
        $has_nwc           = ! empty( get_user_meta( $current_user, 'sk_nwc_connection', true ) );
        $nwc_verified      = $profile_info['lightning_nwc'] ?? false;
        $has_lndhub        = ! empty( get_user_meta( $current_user, 'sk_lndhub_connection', true ) );
        $lndhub_verified   = $profile_info['lightning_lndhub'] ?? false;
        $lud21             = $profile_info['lightning_lud21'] ?? false;
        ?>
        <div class="sk-settings-section">
            <div class="sk-settings-section-title">
                <i class="fas fa-bolt"></i> Lightning-Zahlungen empfangen
            </div>

            <!-- NWC -->
            <div class="sk-form-group">
                <label class="sk-w3 sk-control-label">Nostr Wallet Connect</label>
                <div class="sk-w5">
                    <input type="text" class="sk-form-control" name="nwc_connection"
                           value="" autocomplete="off"
                           placeholder="nostr+walletconnect://..." />
                    <p class="description" style="margin-top:6px;font-size:13px;color:#9ca3af;">
                        NWC Connection-String aus deiner Wallet (Alby Hub, LNbits, etc.).
                        Ermöglicht automatische Invoice-Erstellung und Zahlungsverifizierung. Verschlüsselt gespeichert.
                    </p>
                    <div style="margin-top:8px;padding:10px 14px;background:rgba(247,147,26,0.08);border:1px solid rgba(247,147,26,0.2);border-radius:6px;font-size:12px;color:#9ca3af;">
                        ⚠️ Benötigte Berechtigungen: <strong style="color:#5cb85c;">make_invoice</strong> + <strong style="color:#5cb85c;">lookup_invoice</strong>.<br>
                        <strong style="color:#e06c75;">pay_invoice nicht aktivieren</strong> — wird nicht benötigt und wäre ein Sicherheitsrisiko.
                    </div>
                    <?php if ( $has_nwc ) : ?>
                        <?php if ( $nwc_verified ) : ?>
                            <p style="margin-top:6px;font-size:13px;color:#5cb85c;">
                                ✅ NWC verbunden — automatische Verifizierung aktiv.
                                <a href="#" onclick="document.querySelector('[name=nwc_remove]').value='1';this.closest('form').submit();return false;" style="color:#e06c75;margin-left:8px;">Entfernen</a>
                            </p>
                        <?php else : ?>
                            <p style="margin-top:6px;font-size:13px;color:#f7931a;">
                                ⚠️ NWC gespeichert, aber Verbindungstest fehlgeschlagen.
                            </p>
                        <?php endif; ?>
                    <?php endif; ?>
                    <input type="hidden" name="nwc_remove" value="0" />
                </div>
            </div>

            <!-- LNDHub -->
            <div class="sk-form-group">
                <label class="sk-w3 sk-control-label">LNDHub</label>
                <div class="sk-w5">
                    <input type="text" class="sk-form-control" name="lndhub_connection"
                           value="" autocomplete="off"
                           placeholder="lndhub://login:password@https://..." />
                    <p class="description" style="margin-top:6px;font-size:13px;color:#9ca3af;">
                        LNDHub-URL aus BlueWallet, LNbits, Alby oder BTCPay Server. Verschlüsselt gespeichert.
                    </p>
                    <div style="margin-top:8px;padding:10px 14px;background:rgba(247,147,26,0.08);border:1px solid rgba(247,147,26,0.2);border-radius:6px;font-size:12px;color:#9ca3af;">
                        ⚠️ Verwende die <strong style="color:#5cb85c;">Invoice-URL</strong> (lndhub://invoice:...).<br>
                        <strong style="color:#e06c75;">Nicht die Admin-URL verwenden</strong> — diese erlaubt auch Zahlungen zu senden und wäre ein Sicherheitsrisiko.
                    </div>
                    <?php if ( $has_lndhub ) : ?>
                        <?php if ( $lndhub_verified ) : ?>
                            <p style="margin-top:6px;font-size:13px;color:#5cb85c;">
                                ✅ LNDHub verbunden — automatische Verifizierung aktiv.
                                <a href="#" onclick="document.querySelector('[name=lndhub_remove]').value='1';this.closest('form').submit();return false;" style="color:#e06c75;margin-left:8px;">Entfernen</a>
                            </p>
                        <?php else : ?>
                            <p style="margin-top:6px;font-size:13px;color:#f7931a;">
                                ⚠️ LNDHub gespeichert, aber Verbindungstest fehlgeschlagen.
                            </p>
                        <?php endif; ?>
                    <?php endif; ?>
                    <input type="hidden" name="lndhub_remove" value="0" />
                </div>
            </div>

            <!-- Lightning-Adresse -->
            <div class="sk-form-group">
                <label class="sk-w3 sk-control-label">Lightning-Adresse</label>
                <div class="sk-w5">
                    <input type="text" class="sk-form-control" name="lightning_address"
                           value="<?php echo esc_attr( $lightning_address ); ?>"
                           placeholder="user@getalby.com oder lnurl1..." />
                    <p class="description" style="margin-top:6px;font-size:13px;color:#9ca3af;">
                        Wird als Fallback verwendet wenn weder NWC noch LNDHub verbunden ist.
                    </p>
                    <?php if ( ! empty( $lightning_address ) ) : ?>
                        <?php if ( $lud21 ) : ?>
                            <div style="margin-top:8px;padding:10px 14px;background:rgba(92,184,92,0.08);border:1px solid rgba(92,184,92,0.2);border-radius:6px;font-size:12px;color:#5cb85c;">
                                ✅ Automatische Zahlungsverifizierung unterstützt (LUD-21)
                            </div>
                        <?php else : ?>
                            <div style="margin-top:8px;padding:10px 14px;background:rgba(247,147,26,0.08);border:1px solid rgba(247,147,26,0.2);border-radius:6px;font-size:12px;color:#9ca3af;">
                                ⚠️ Keine automatische Verifizierung — Zahlungen müssen manuell bestätigt werden.
                                Für automatische Verifizierung verwende NWC oder LNDHub (oben) oder einen Service der LUD-21 unterstützt (z.B. Alby, LNbits, Coinos).
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Validate and save NWC + Lightning address fields.
     */
    public function save_field( int $store_id, array $sk_settings = [], array $prev_settings = [] ) {
        $settings = get_user_meta( $store_id, 'sk_profile_settings', true );
        if ( ! is_array( $settings ) ) {
            $settings = [];
        }

        // Handle NWC connection string.
        $this->save_nwc( $store_id, $settings );

        // Handle LNDHub connection string.
        $this->save_lndhub( $store_id, $settings );

        if ( ! isset( $_POST['lightning_address'] ) ) {
            return;
        }

        $raw = sanitize_text_field( wp_unslash( $_POST['lightning_address'] ) );

        if ( ! is_array( $settings ) ) {
            $settings = [];
        }

        if ( empty( $raw ) ) {
            $settings['lightning_address'] = '';
            update_user_meta( $store_id, 'sk_profile_settings', $settings );
            return;
        }

        // Validate format.
        if ( ! self::is_valid_lightning_address( $raw ) && ! self::is_valid_lnurl( $raw ) ) {
            $settings['lightning_address'] = $prev_settings['lightning_address'] ?? '';
            update_user_meta( $store_id, 'sk_profile_settings', $settings );
            return;
        }

        $settings['lightning_address'] = $raw;

        // Test if the service supports LUD-21 (verify URL).
        // We do a test resolve — if the LNURL metadata response has 'allowsNostr'
        // or other indicators, that's a modern service. But the real LUD-21 check
        // happens at invoice time (verify field in callback response).
        // For now, we test-resolve and store whether it succeeded.
        $lud21_supported = false;
        $resolve_result = \SK_Lightning\LNURL\Resolver::resolve( $raw );
        if ( ! is_wp_error( $resolve_result ) && ! empty( $resolve_result['callback'] ) ) {
            // Try a minimal invoice request to see if verify URL is returned.
            $min_amount = $resolve_result['minSendable'] ?? 1000;
            $test_invoice = \SK_Lightning\LNURL\Resolver::request_invoice( $resolve_result['callback'], (int) $min_amount );
            if ( ! is_wp_error( $test_invoice ) && ! empty( $test_invoice['verify'] ) ) {
                $lud21_supported = true;
            }
        }
        $settings['lightning_lud21'] = $lud21_supported;

        update_user_meta( $store_id, 'sk_profile_settings', $settings );
    }

    /**
     * Save NWC connection string (encrypted).
     */
    private function save_nwc( int $store_id, array &$settings ) {
        // Handle removal.
        if ( ! empty( $_POST['nwc_remove'] ) && $_POST['nwc_remove'] === '1' ) {
            delete_user_meta( $store_id, 'sk_nwc_connection' );
            $settings['lightning_nwc'] = false;
            update_user_meta( $store_id, 'sk_profile_settings', $settings );
            return;
        }

        $nwc_raw = sanitize_text_field( wp_unslash( $_POST['nwc_connection'] ?? '' ) );
        if ( empty( $nwc_raw ) ) {
            return; // No new NWC string submitted — keep existing.
        }

        // Validate NWC connection string.
        $client = \SK_Lightning\NWC\Client::from_connection_string( $nwc_raw );
        if ( is_wp_error( $client ) ) {
            $settings['lightning_nwc'] = false;
            update_user_meta( $store_id, 'sk_profile_settings', $settings );
            return;
        }

        // Test connection.
        $info = $client->get_info();
        $nwc_works = ! is_wp_error( $info );

        // Encrypt and store.
        $encrypted = \SK_Lightning\NWC\Client::encrypt_connection_string( $nwc_raw );
        update_user_meta( $store_id, 'sk_nwc_connection', $encrypted );
        $settings['lightning_nwc'] = $nwc_works;
        update_user_meta( $store_id, 'sk_profile_settings', $settings );
    }

    /**
     * Get a NWC client for a vendor (if configured).
     *
     * @param int $vendor_id
     * @return \SK_Lightning\NWC\Client|null
     */
    public static function get_nwc_client( int $vendor_id ) {
        $encrypted = get_user_meta( $vendor_id, 'sk_nwc_connection', true );
        if ( empty( $encrypted ) ) {
            return null;
        }

        $connection_string = \SK_Lightning\NWC\Client::decrypt_connection_string( $encrypted );
        if ( empty( $connection_string ) ) {
            return null;
        }

        $client = \SK_Lightning\NWC\Client::from_connection_string( $connection_string );
        return is_wp_error( $client ) ? null : $client;
    }

    /**
     * Check if a vendor has NWC configured and working.
     */
    public static function has_nwc( int $vendor_id ): bool {
        $settings = get_user_meta( $vendor_id, 'sk_profile_settings', true );
        return is_array( $settings ) && ! empty( $settings['lightning_nwc'] );
    }

    /**
     * Save LNDHub connection string (encrypted).
     */
    private function save_lndhub( int $store_id, array &$settings ) {
        if ( ! empty( $_POST['lndhub_remove'] ) && $_POST['lndhub_remove'] === '1' ) {
            delete_user_meta( $store_id, 'sk_lndhub_connection' );
            $settings['lightning_lndhub'] = false;
            update_user_meta( $store_id, 'sk_profile_settings', $settings );
            return;
        }

        $raw = sanitize_text_field( wp_unslash( $_POST['lndhub_connection'] ?? '' ) );
        if ( empty( $raw ) ) {
            return;
        }

        $client = \SK_Lightning\LNDHub\Client::from_connection_string( $raw );
        if ( is_wp_error( $client ) ) {
            $settings['lightning_lndhub'] = false;
            update_user_meta( $store_id, 'sk_profile_settings', $settings );
            return;
        }

        $info = $client->get_info();
        $works = ! is_wp_error( $info );

        $encrypted = \SK_Lightning\LNDHub\Client::encrypt_connection_string( $raw );
        update_user_meta( $store_id, 'sk_lndhub_connection', $encrypted );
        $settings['lightning_lndhub'] = $works;
        update_user_meta( $store_id, 'sk_profile_settings', $settings );
    }

    /**
     * Get a LNDHub client for a vendor (if configured).
     */
    public static function get_lndhub_client( int $vendor_id ) {
        $encrypted = get_user_meta( $vendor_id, 'sk_lndhub_connection', true );
        if ( empty( $encrypted ) ) {
            return null;
        }

        $connection_string = \SK_Lightning\LNDHub\Client::decrypt_connection_string( $encrypted );
        if ( empty( $connection_string ) ) {
            return null;
        }

        $client = \SK_Lightning\LNDHub\Client::from_connection_string( $connection_string );
        return is_wp_error( $client ) ? null : $client;
    }

    /**
     * Check if a vendor has LNDHub configured and working.
     */
    public static function has_lndhub( int $vendor_id ): bool {
        $settings = get_user_meta( $vendor_id, 'sk_profile_settings', true );
        return is_array( $settings ) && ! empty( $settings['lightning_lndhub'] );
    }

    /**
     * Check if value is a valid Lightning address (user@domain).
     */
    public static function is_valid_lightning_address( string $value ): bool {
        return (bool) preg_match( '/^[a-zA-Z0-9_.+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $value );
    }

    /**
     * Check if value is a valid LNURL (starts with lnurl1).
     */
    public static function is_valid_lnurl( string $value ): bool {
        return stripos( $value, 'lnurl1' ) === 0 && strlen( $value ) > 20;
    }

    /**
     * Get the Lightning address for a vendor.
     */
    public static function get_lightning_address( int $vendor_id ): string {
        $settings = get_user_meta( $vendor_id, 'sk_profile_settings', true );
        return ( is_array( $settings ) && ! empty( $settings['lightning_address'] ) )
            ? $settings['lightning_address']
            : '';
    }

    /**
     * Get reputation data for a vendor. Returns null if no data.
     *
     * @param int $vendor_id
     * @return object|null  { valid_transactions, valid_volume_sats, badge, badge_label }
     */
    public static function get_reputation( int $vendor_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'sk_reputation_scores';

        $table_exists = $wpdb->get_var(
            $wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
        );

        if ( ! $table_exists ) {
            return null;
        }

        $rep = $wpdb->get_row(
            $wpdb->prepare( "SELECT valid_transactions, valid_volume_sats FROM {$table} WHERE vendor_id = %d", $vendor_id )
        );

        if ( ! $rep || $rep->valid_transactions < 1 ) {
            return null;
        }

        // Determine badge.
        $rep->badge       = '';
        $rep->badge_label = '';

        if ( $rep->valid_transactions >= 100 ) {
            $rep->badge       = '⚡⚡⚡';
            $rep->badge_label = 'Lightning Veteran';
        } elseif ( $rep->valid_transactions >= 25 ) {
            $rep->badge       = '⚡⚡';
            $rep->badge_label = 'Lightning Händler';
        } elseif ( $rep->valid_transactions >= 5 ) {
            $rep->badge       = '⚡';
            $rep->badge_label = 'Lightning Starter';
        }

        return $rep;
    }

    /**
     * Add Lightning icon to contact icons on product pages.
     */
    public function add_lightning_icon( array $icons, int $vendor_id, int $product_id = 0, string $context = '' ): array {
        $address = self::get_lightning_address( $vendor_id );

        if ( empty( $address ) ) {
            return $icons;
        }

        $icons[] = [
            'href'  => '#',
            'title' => '⚡ Lightning',
            'class' => 'fa-solid fa-bolt sk-lightning-icon',
            'key'   => 'lightning',
            'data'  => [
                'vendor-id'  => $vendor_id,
                'product-id' => $product_id,
            ],
        ];

        return $icons;
    }

}
