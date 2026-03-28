<?php
/**
 * Vendor Settings Form — self-contained dark card layout.
 *
 * All sections are rendered inline (no hook-injection dependencies).
 * Save handlers remain in their original plugin files via sk_store_profile_saved.
 *
 * Sections (in order):
 *   1. Profil (banner, gravatar)
 *   2. Shop-Informationen (store name, store category, address, map)
 *   3. Kontaktdaten (email, telegram, twitter, phone, nostr, paywall)
 *   4. Biografie
 *   5. Katalog-Modus (conditional)
 *   6. Store-Link (slug)
 *   7. Öffnungszeiten (conditional)
 *   8. AGB (conditional)
 */

$current_user_obj = get_userdata( $current_user );

$gravatar_id  = ! empty( $profile_info['gravatar'] ) ? $profile_info['gravatar'] : 0;
$gravatar_url = $gravatar_id ? wp_get_attachment_url( $gravatar_id ) : $default_avatar_url;
$banner_id    = ! empty( $profile_info['banner'] ) ? $profile_info['banner'] : 0;
$banner_url   = $banner_id ? wp_get_attachment_url( $banner_id ) : $default_banner_url;
$storename    = $profile_info['store_name'] ?? '';

$address_street1 = $profile_info['address']['street_1'] ?? '';
$address_city    = $profile_info['address']['city']     ?? '';
$address_zip     = $profile_info['address']['zip']      ?? '';
$address_country = $profile_info['address']['country']  ?? '';
$address_state   = $profile_info['address']['state']    ?? '';

$map_location = $profile_info['location']     ?? '';
$map_address  = $profile_info['find_address'] ?? '';
$enable_tnc   = $profile_info['enable_tnc']   ?? '';
$store_tnc    = $profile_info['store_tnc']    ?? '';

$banner_width  = sk_get_vendor_store_banner_width();
$banner_height = sk_get_vendor_store_banner_height();

$tnc_enable = sk_get_option( 'seller_enable_terms_and_conditions', 'sk_general', 'off' );

/* --- Contact details variables --- */
if ( isset( $_POST['setting_email'] ) ) {
    $cd_email = esc_attr( wp_unslash( $_POST['setting_email'] ) );
} elseif ( ! empty( $profile_info['setting_email'] ) ) {
    $cd_email = esc_attr( $profile_info['setting_email'] );
} elseif ( $current_user_obj instanceof WP_User ) {
    $cd_email = esc_attr( $current_user_obj->user_email ?? '' );
} else {
    $cd_email = '';
}
$show_email_val = $profile_info['show_email'] ?? '';
if ( is_array( $show_email_val ) ) {
    $show_email_val = end( $show_email_val ) ?: '';
}
$cd_show_email = in_array( strtolower( (string) $show_email_val ), [ '1', 'yes', 'on', 'true' ], true ) ? 'checked' : '';
$cd_telegram   = esc_attr( $profile_info['telegram']     ?? '' );
$cd_show_tele  = ! empty( $profile_info['show_telegram'] )     ? 'checked' : '';
$cd_twitter    = esc_attr( $profile_info['twitter']       ?? '' );
$cd_show_tw    = ! empty( $profile_info['show_twitter'] )       ? 'checked' : '';
$cd_phone      = esc_attr( $profile_info['phone_number']  ?? '' );
$cd_show_phone = ! empty( $profile_info['show_phone_number'] )  ? 'checked' : '';
$cd_nostr      = esc_attr( $profile_info['nostr']         ?? '' );
$cd_show_nostr = ! empty( $profile_info['show_nostr'] )         ? 'checked' : '';
$cd_feewall_available = class_exists( 'Contact_Details_Feewall' ) && get_option( 'cdf_enabled', 'yes' ) === 'yes';
$cd_feewall_enabled   = $cd_feewall_available && isset( $profile_info['cdf_enabled'] ) && $profile_info['cdf_enabled'] === '1';

/* --- Biography --- */
$vendor_biography = ! empty( $profile_info['vendor_biography'] ) ? $profile_info['vendor_biography'] : '';

/* --- Store category (sk-pro) --- */
$store_categories_on = function_exists( 'sk_is_store_categories_feature_on' ) && sk_is_store_categories_feature_on();
if ( $store_categories_on ) {
    if ( function_exists( 'sk_get_default_store_category_id' ) ) {
        sk_get_default_store_category_id();
    }
    $sc_categories      = get_terms( [ 'taxonomy' => 'store_category', 'hide_empty' => false ] );
    $sc_store_categories = wp_get_object_terms( $current_user, 'store_category', [ 'fields' => 'ids' ] );
    $sc_category_type   = sk_get_option( 'store_category_type', 'sk_general', 'none' );
    $sc_is_multiple     = ( 'multiple' === $sc_category_type );
    $sc_label           = $sc_is_multiple ? __( 'Store Categories', 'sk' ) : __( 'Store Category', 'sk' );
}

/* --- Catalog mode (conditional) --- */
$catalog_mode_on = class_exists( 'SK\Core\CatalogMode\Helper' ) && \SK\Core\CatalogMode\Helper::is_enabled_by_admin();
if ( $catalog_mode_on ) {
    $cm_defaults       = \SK\Core\CatalogMode\Helper::get_defaults();
    $cm_settings       = $profile_info['catalog_mode'] ?? $cm_defaults;
    $cm_hide_cart      = ! empty( $cm_settings['hide_add_to_cart_button'] ) ? $cm_settings['hide_add_to_cart_button'] : $cm_defaults['hide_add_to_cart_button'];
    $cm_hide_price     = ! empty( $cm_settings['hide_product_price'] )     ? $cm_settings['hide_product_price']     : $cm_defaults['hide_product_price'];
    $cm_hide_cart_enabled  = class_exists( 'SK\Core\CatalogMode\Helper' ) && \SK\Core\CatalogMode\Helper::hide_add_to_cart_button_option_is_enabled_by_admin();
    $cm_hide_price_enabled = class_exists( 'SK\Core\CatalogMode\Helper' ) && \SK\Core\CatalogMode\Helper::hide_product_price_option_is_enabled_by_admin();
}

/* --- Store slug --- */
$store_slug = $current_user_obj ? $current_user_obj->user_nicename : '';
?>
<?php do_action( 'sk_settings_before_form', $current_user, $profile_info ); ?>

<form method="post" id="sk-store-form" action="" class="sk-settings-form" novalidate>
    <?php wp_nonce_field( 'sk_store_settings_nonce' ); ?>

    <!-- ======================================================
         SECTION 1: Profil
    ====================================================== -->
    <div class="sk-settings-section">
        <div class="sk-settings-section-title">
            <i class="fas fa-image"></i> <?php esc_html_e( 'Profil', 'sk' ); ?>
        </div>

        <?php /* Banner */ ?>
        <div class="sk-settings-field sk-settings-field--media">
            <div class="sk-settings-label"><?php esc_html_e( 'Banner', 'sk-core' ); ?></div>
            <div class="sk-settings-input">
                <div id="sk-banner-wrapper" class="sk-banner">
                    <div class="image-wrap<?php echo $banner_url ? '' : ' sk-hide'; ?>">
                        <input type="hidden" class="sk-file-field" value="<?php echo esc_attr( $banner_id ); ?>" name="sk_banner">
                        <img alt="banner" class="sk-banner-img" src="<?php echo esc_url( $banner_url ); ?>">
                        <a class="sk-remove-banner-image">&times;</a>
                    </div>
                    <div class="button-area<?php echo $banner_url ? ' sk-hide' : ''; ?>">
                        <a href="#" class="sk-banner-drag sk-btn sk-btn-default">
                            <i class="fas fa-cloud-upload-alt"></i> <?php esc_html_e( 'Banner hochladen', 'sk-core' ); ?>
                        </a>
                        <p class="sk-settings-hint">
                            <?php printf( esc_html__( 'Empfohlen: %1$s × %2$s Pixel', 'sk-core' ), $banner_width, $banner_height ); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <?php do_action( 'sk_settings_after_banner', $current_user, $profile_info ); ?>

        <?php /* Profile photo */ ?>
        <div class="sk-settings-field">
            <div class="sk-settings-label"><?php esc_html_e( 'Profilbild', 'sk-core' ); ?></div>
            <div id="sk-profile-picture-wrapper" class="sk-settings-input sk-gravatar">
                <div class="sk-left gravatar-wrap<?php echo $gravatar_url ? '' : ' sk-hide'; ?>">
                    <input type="hidden" class="sk-file-field" value="<?php echo esc_attr( $gravatar_id ); ?>" name="sk_gravatar">
                    <img alt="gravatar" class="sk-gravatar-img" src="<?php echo esc_url( $gravatar_url ); ?>">
                    <a class="sk-remove-gravatar-image">&times;</a>
                </div>
                <div class="gravatar-button-area<?php echo $gravatar_url ? ' sk-hide' : ''; ?>">
                    <a href="#" class="sk-pro-gravatar-drag sk-btn sk-btn-default">
                        <i class="fas fa-cloud-upload-alt"></i> <?php esc_html_e( 'Foto hochladen', 'sk-core' ); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- ======================================================
         SECTION 2: Shop-Informationen
    ====================================================== -->
    <div class="sk-settings-section">
        <div class="sk-settings-section-title">
            <i class="fas fa-store"></i> <?php esc_html_e( 'Shop-Informationen', 'sk' ); ?>
        </div>

        <div class="sk-settings-field">
            <label class="sk-settings-label" for="sk_store_name"><?php esc_html_e( 'Anzeigename', 'sk-core' ); ?></label>
            <div class="sk-settings-input">
                <input id="sk_store_name" type="text" class="sk-form-control" name="sk_store_name"
                       value="<?php echo esc_attr( $storename ); ?>"
                       placeholder="<?php esc_attr_e( 'Dein Anzeigename', 'sk-core' ); ?>" required>
            </div>
        </div>

        <?php /* Store Category (sk-pro, conditional) */ ?>
        <?php if ( $store_categories_on && ! empty( $sc_categories ) && ! is_wp_error( $sc_categories ) ) : ?>
        <div class="sk-settings-field">
            <label class="sk-settings-label" for="sk_store_categories"><?php echo esc_html( $sc_label ); ?></label>
            <div class="sk-settings-input">
                <select class="sk-select2 sk-form-control" name="sk_store_categories[]" id="sk_store_categories"
                        data-placeholder="<?php echo esc_attr( $sc_label ); ?>" <?php echo $sc_is_multiple ? 'multiple' : ''; ?>>
                    <?php foreach ( $sc_categories as $category ) : ?>
                        <option value="<?php echo esc_attr( $category->term_id ); ?>"
                            <?php echo in_array( $category->term_id, $sc_store_categories ) ? 'selected' : ''; ?>>
                            <?php echo esc_html( $category->name ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php endif; ?>

        <?php do_action( 'sk_settings_after_store_name', $current_user, $profile_info ); ?>

        <?php /* Address */ ?>
        <?php do_action( 'sk_settings_before_store_map', $current_user, $profile_info ); ?>

        <?php if ( sk_has_map_api_key() ) : ?>
        <div class="sk-settings-field">
            <label class="sk-settings-label"><?php esc_html_e( 'Kartenposition', 'sk-core' ); ?></label>
            <div class="sk-settings-input">
                <?php sk_get_template( 'maps/sk-maps-with-search.php', [ 'map_location' => $map_location, 'map_address' => $map_address ] ); ?>
                <p class="sk-settings-hint">💡 <?php esc_html_e( 'Bitte einfach nur den Ort angeben (z. B. Zürich). Straße & Hausnummer sind nicht nötig.', 'sk-core' ); ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ======================================================
         SECTION 3: Kontaktdaten
    ====================================================== -->
    <div class="sk-settings-section">
        <div class="sk-settings-section-title">
            <i class="fas fa-address-card"></i> <?php esc_html_e( 'Kontaktdaten', 'sk' ); ?>
        </div>

        <div class="sk-form-group sk-contact-email">
            <label class="sk-w3 sk-control-label">E-Mail-Adresse</label>
            <div class="sk-w5">
                <input type="email" class="sk-form-control" name="setting_email" value="<?php echo $cd_email; ?>" />
                <input type="hidden" name="setting_show_email" value="no" />
                <label><input type="checkbox" name="setting_show_email" value="yes" <?php echo $cd_show_email; ?>> Öffentlich anzeigen</label>
            </div>
        </div>
        <div class="sk-form-group">
            <label class="sk-w3 sk-control-label">Telegram Handle</label>
            <div class="sk-w5">
                <input type="text" class="sk-form-control" name="telegram" value="<?php echo $cd_telegram; ?>" />
                <label><input type="checkbox" name="show_telegram" value="1" <?php echo $cd_show_tele; ?>> Öffentlich anzeigen</label>
            </div>
        </div>
        <div class="sk-form-group">
            <label class="sk-w3 sk-control-label">Twitter / X Handle</label>
            <div class="sk-w5">
                <input type="text" class="sk-form-control" name="twitter" value="<?php echo $cd_twitter; ?>" />
                <label><input type="checkbox" name="show_twitter" value="1" <?php echo $cd_show_tw; ?>> Öffentlich anzeigen</label>
            </div>
        </div>
        <div class="sk-form-group">
            <label class="sk-w3 sk-control-label">Handynummer</label>
            <div class="sk-w5">
                <input type="text" class="sk-form-control" name="phone_number" value="<?php echo $cd_phone; ?>" />
                <label><input type="checkbox" name="show_phone_number" value="1" <?php echo $cd_show_phone; ?>> Öffentlich anzeigen</label>
            </div>
        </div>
        <div class="sk-form-group">
            <label class="sk-w3 sk-control-label">Nostr Public Key (npub...)</label>
            <div class="sk-w5">
                <input type="text" class="sk-form-control" name="nostr" value="<?php echo $cd_nostr; ?>" />
                <label><input type="checkbox" name="show_nostr" value="1" <?php echo $cd_show_nostr; ?>> Öffentlich anzeigen</label>
            </div>
        </div>
        <?php if ( $cd_feewall_available ) : ?>
        <div class="sk-form-group">
            <label class="sk-w3 sk-control-label"><strong>⚡ Kontaktdetails Paywall</strong></label>
            <div class="sk-w5">
                <label style="display:block;margin-bottom:10px;">
                    <input type="checkbox" name="cdf_enabled" value="1" <?php checked( $cd_feewall_enabled, true ); ?>>
                    <strong>Paywall aktivieren (21 Sats)</strong>
                </label>
                <p class="description" style="margin-top:10px;font-size:14px;color:#ccc;">Interessenten zahlen 21 Sats via BTCPay, um deine Kontaktdaten zu sehen.</p>
            </div>
        </div>
        <?php endif; ?>

        <?php /* Extension point for additional contact fields */ ?>
        <?php do_action( 'sk_settings_contact_fields', $current_user, $profile_info ); ?>
    </div>

    <?php do_action( 'sk_settings_after_contact', $current_user, $profile_info ); ?>

    <!-- ======================================================
         SECTION 3b: Onchain + Lightning (conditional: sk-payments Modul aktiv)
    ====================================================== -->
    <?php if ( class_exists( 'SK\Modules\Payments\Module' ) && \SK\Modules\Payments\Module::is_enabled() ) :
        $oc_btc_address = $profile_info['btc_address'] ?? '';
        $oc_xpub        = ! empty( get_user_meta( $current_user, 'sk_xpub', true ) );
        $oc_xpub_ok     = $profile_info['btc_xpub_verified'] ?? false;
        $ln_address     = $profile_info['lightning_address'] ?? '';
        $ln_has_nwc     = ! empty( get_user_meta( $current_user, 'sk_nwc_connection', true ) );
        $ln_nwc_ok      = $profile_info['lightning_nwc'] ?? false;
        $ln_has_lndhub  = ! empty( get_user_meta( $current_user, 'sk_lndhub_connection', true ) );
        $ln_lndhub_ok   = $profile_info['lightning_lndhub'] ?? false;
        $ln_lud21       = $profile_info['lightning_lud21'] ?? false;
    ?>

    <!-- Onchain -->
    <div class="sk-settings-section">
        <div class="sk-settings-section-title">
            <i class="fab fa-bitcoin"></i> Onchain-Zahlungen empfangen
        </div>

        <div class="sk-form-group">
            <label class="sk-w3 sk-control-label">BTC-Adresse</label>
            <div class="sk-w5">
                <input type="text" class="sk-form-control" name="btc_address"
                       value="<?php echo esc_attr( $oc_btc_address ); ?>"
                       placeholder="bc1q... oder 1... oder 3..." />
                <p class="description" style="margin-top:6px;font-size:13px;color:#9ca3af;">
                    Statische Bitcoin-Adresse. Achtung: Alle Käufer sehen dieselbe Adresse (kein Privacy-Vorteil).
                </p>
                <button type="button" class="sk-btn sk-btn-default skp-test-btn" id="skp-test-btcaddr" style="margin-top:8px;font-size:13px;" disabled>
                    <i class="fas fa-check-circle"></i> Adresse prüfen
                </button>
                <span id="skp-test-btcaddr-result" style="margin-left:8px;font-size:13px;"></span>
            </div>
        </div>

        <div class="sk-form-group">
            <label class="sk-w3 sk-control-label">Extended Public Key (xpub)</label>
            <div class="sk-w5">
                <input type="text" class="sk-form-control<?php echo $oc_xpub ? ' skp-saved' : ''; ?>" name="btc_xpub"
                       value="" autocomplete="off"
                       placeholder="<?php echo $oc_xpub ? 'xpub/ypub/zpub******** (gespeichert — leer lassen um beizubehalten)' : 'xpub6... / ypub6... / zpub6...'; ?>" />
                <p class="description" style="margin-top:6px;font-size:13px;color:#9ca3af;">
                    Für jeden Kauf wird automatisch eine neue Adresse abgeleitet (BIP32). Empfohlen für bessere Privacy.
                    Exportiere den xpub aus deiner Wallet (z.B. Sparrow, Electrum, BlueWallet).
                </p>
                <div style="margin-top:8px;padding:10px 14px;background:rgba(247,147,26,0.08);border:1px solid rgba(247,147,26,0.2);border-radius:6px;font-size:12px;color:#9ca3af;">
                    Der xpub erlaubt <strong style="color:#5cb85c;">nur das Generieren von Empfangsadressen</strong>.<br>
                    <strong style="color:#5cb85c;">Keine Ausgaben möglich</strong> — dein Guthaben ist sicher.
                </div>
                <?php if ( $oc_xpub ) : ?>
                    <?php if ( $oc_xpub_ok ) : ?>
                        <p style="margin-top:6px;font-size:13px;color:#5cb85c;">
                            xpub gespeichert — Adress-Derivation aktiv.
                            <a href="#" onclick="document.querySelector('[name=xpub_remove]').value='1';jQuery(this.closest('form')).trigger('submit');return false;" style="color:#e06c75;margin-left:8px;">Entfernen</a>
                        </p>
                    <?php else : ?>
                        <p style="margin-top:6px;font-size:13px;color:#f7931a;">
                            xpub gespeichert, aber Validierung fehlgeschlagen.
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
                <input type="hidden" name="xpub_remove" value="0" />
                <button type="button" class="sk-btn sk-btn-default skp-test-btn" id="skp-test-xpub" style="margin-top:8px;font-size:13px;" disabled>
                    <i class="fas fa-plug"></i> xpub testen
                </button>
                <span id="skp-test-xpub-result" style="margin-left:8px;font-size:13px;"></span>
            </div>
        </div>
    </div>

    <!-- Lightning -->
    <div class="sk-settings-section">
        <div class="sk-settings-section-title">
            <i class="fas fa-bolt"></i> Lightning-Zahlungen empfangen
        </div>

        <!-- NWC -->
        <div class="sk-form-group">
            <label class="sk-w3 sk-control-label">Nostr Wallet Connect</label>
            <div class="sk-w5">
                <input type="text" class="sk-form-control<?php echo $ln_has_nwc ? ' skp-saved' : ''; ?>" name="nwc_connection"
                       value="" autocomplete="off"
                       placeholder="<?php echo $ln_has_nwc ? 'nostr+walletconnect://******** (gespeichert — leer lassen um beizubehalten)' : 'nostr+walletconnect://...'; ?>" />
                <p class="description" style="margin-top:6px;font-size:13px;color:#9ca3af;">
                    NWC Connection-String aus deiner Wallet (Alby Hub, LNbits, etc.).
                    Ermöglicht automatische Invoice-Erstellung und Zahlungsverifizierung. Verschlüsselt gespeichert.
                </p>
                <div style="margin-top:8px;padding:10px 14px;background:rgba(247,147,26,0.08);border:1px solid rgba(247,147,26,0.2);border-radius:6px;font-size:12px;color:#9ca3af;">
                    Benötigte Berechtigungen: <strong style="color:#5cb85c;">make_invoice</strong> + <strong style="color:#5cb85c;">lookup_invoice</strong>.<br>
                    <strong style="color:#e06c75;">pay_invoice nicht aktivieren</strong> — wird nicht benötigt und wäre ein Sicherheitsrisiko.
                </div>
                <?php if ( $ln_has_nwc ) : ?>
                    <?php if ( $ln_nwc_ok ) : ?>
                        <p style="margin-top:6px;font-size:13px;color:#5cb85c;">
                            NWC verbunden — automatische Verifizierung aktiv.
                            <a href="#" onclick="document.querySelector('[name=nwc_remove]').value='1';jQuery(this.closest('form')).trigger('submit');return false;" style="color:#e06c75;margin-left:8px;">Entfernen</a>
                        </p>
                    <?php else : ?>
                        <p style="margin-top:6px;font-size:13px;color:#f7931a;">
                            NWC gespeichert, aber Verbindungstest fehlgeschlagen.
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
                <input type="hidden" name="nwc_remove" value="0" />
                <button type="button" class="sk-btn sk-btn-default skp-test-btn" id="skp-test-nwc" style="margin-top:8px;font-size:13px;" disabled>
                    <i class="fas fa-plug"></i> Verbindung testen
                </button>
                <span id="skp-test-nwc-result" style="margin-left:8px;font-size:13px;"></span>
            </div>
        </div>

        <!-- LNDHub -->
        <div class="sk-form-group">
            <label class="sk-w3 sk-control-label">LNDHub</label>
            <div class="sk-w5">
                <input type="text" class="sk-form-control<?php echo $ln_has_lndhub ? ' skp-saved' : ''; ?>" name="lndhub_connection"
                       value="" autocomplete="off"
                       placeholder="<?php echo $ln_has_lndhub ? 'lndhub://******** (gespeichert — leer lassen um beizubehalten)' : 'lndhub://login:password@https://...'; ?>" />
                <p class="description" style="margin-top:6px;font-size:13px;color:#9ca3af;">
                    LNDHub-URL aus BlueWallet, LNbits, Alby oder BTCPay Server. Verschlüsselt gespeichert.
                </p>
                <div style="margin-top:8px;padding:10px 14px;background:rgba(247,147,26,0.08);border:1px solid rgba(247,147,26,0.2);border-radius:6px;font-size:12px;color:#9ca3af;">
                    Verwende die <strong style="color:#5cb85c;">Invoice-URL</strong> (lndhub://invoice:...).<br>
                    <strong style="color:#e06c75;">Nicht die Admin-URL verwenden</strong> — diese erlaubt auch Zahlungen zu senden und wäre ein Sicherheitsrisiko.
                </div>
                <?php if ( $ln_has_lndhub ) : ?>
                    <?php if ( $ln_lndhub_ok ) : ?>
                        <p style="margin-top:6px;font-size:13px;color:#5cb85c;">
                            LNDHub verbunden — automatische Verifizierung aktiv.
                            <a href="#" onclick="document.querySelector('[name=lndhub_remove]').value='1';jQuery(this.closest('form')).trigger('submit');return false;" style="color:#e06c75;margin-left:8px;">Entfernen</a>
                        </p>
                    <?php else : ?>
                        <p style="margin-top:6px;font-size:13px;color:#f7931a;">
                            LNDHub gespeichert, aber Verbindungstest fehlgeschlagen.
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
                <input type="hidden" name="lndhub_remove" value="0" />
                <button type="button" class="sk-btn sk-btn-default skp-test-btn" id="skp-test-lndhub" style="margin-top:8px;font-size:13px;" disabled>
                    <i class="fas fa-plug"></i> Verbindung testen
                </button>
                <span id="skp-test-lndhub-result" style="margin-left:8px;font-size:13px;"></span>
            </div>
        </div>

        <!-- Lightning-Adresse -->
        <div class="sk-form-group">
            <label class="sk-w3 sk-control-label">Lightning-Adresse</label>
            <div class="sk-w5">
                <input type="text" class="sk-form-control" name="lightning_address"
                       value="<?php echo esc_attr( $ln_address ); ?>"
                       placeholder="user@getalby.com oder lnurl1..." />
                <p class="description" style="margin-top:6px;font-size:13px;color:#9ca3af;">
                    Wird als Fallback verwendet wenn weder NWC noch LNDHub verbunden ist.
                </p>
                <?php if ( ! empty( $ln_address ) ) : ?>
                    <?php if ( $ln_lud21 ) : ?>
                        <div style="margin-top:8px;padding:10px 14px;background:rgba(92,184,92,0.08);border:1px solid rgba(92,184,92,0.2);border-radius:6px;font-size:12px;color:#5cb85c;">
                            Automatische Zahlungsverifizierung unterstützt (LUD-21)
                        </div>
                    <?php else : ?>
                        <div style="margin-top:8px;padding:10px 14px;background:rgba(247,147,26,0.08);border:1px solid rgba(247,147,26,0.2);border-radius:6px;font-size:12px;color:#9ca3af;">
                            Keine automatische Verifizierung — Zahlungen müssen manuell bestätigt werden.
                            Für automatische Verifizierung verwende NWC oder LNDHub (oben) oder einen Service der LUD-21 unterstützt (z.B. Alby, LNbits, Coinos).
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                <button type="button" class="sk-btn sk-btn-default skp-test-btn" id="skp-test-lnaddr" style="margin-top:8px;font-size:13px;" disabled>
                    <i class="fas fa-plug"></i> Adresse testen
                </button>
                <span id="skp-test-lnaddr-result" style="margin-left:8px;font-size:13px;"></span>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ======================================================
         SECTION 3c: Nostr Settings
    ====================================================== -->
    <?php
        $ns_nostr_pubkey = get_user_meta( $current_user, 'nostr_public_key', true );
        $ns_has_pubkey   = ! empty( $ns_nostr_pubkey );
        $ns_identity_src = get_user_meta( $current_user, 'sk_nostr_identity_source', true );
        $ns_has_market   = class_exists( 'SK\Modules\NostrMarket\Module' ) && sk_get_option( 'sk_nostr_market_enabled', 'sk_nostr_market', 'off' ) === 'on';
        $ns_post_enabled = $profile_info['nostr_market_enabled'] ?? ( $ns_has_pubkey ? '1' : '0' );

        // Convert pubkey to npub for display.
        $ns_npub = '';
        if ( $ns_has_pubkey && class_exists( '\swentel\nostr\Key\Key' ) ) {
            try {
                $ns_key  = new \swentel\nostr\Key\Key();
                $ns_npub = $ns_key->convertPublicKeyToBech32( $ns_nostr_pubkey );
            } catch ( \Throwable $e ) {}
        }

        // Import result feedback (set via sk_store_profile_saved handler).
        $ns_import_result = get_transient( 'sk_nsec_import_result_' . $current_user );
        if ( $ns_import_result ) {
            delete_transient( 'sk_nsec_import_result_' . $current_user );
        }
    ?>
    <div class="sk-settings-section">
        <div class="sk-settings-section-title">
            <i class="sk-nostr-icon sk-nostr-icon--inline"></i> Nostr Settings
        </div>

        <?php /* ── Current Nostr Identity ── */ ?>
        <div class="sk-form-group">
            <label class="sk-w3 sk-control-label">Nostr Identitaet</label>
            <div class="sk-w5">
                <?php if ( $ns_has_pubkey ) : ?>
                    <p style="font-size:13px;color:#5cb85c;margin-bottom:6px;">
                        <i class="fas fa-check-circle"></i>
                        <?php if ( $ns_identity_src === 'imported' ) : ?>
                            Importierter Nostr Key aktiv
                        <?php else : ?>
                            Generierter Nostr Key aktiv
                        <?php endif; ?>
                    </p>
                    <?php if ( $ns_npub ) : ?>
                    <p style="margin-bottom:0;font-size:13px;">
                        <code style="background:#0f1923;padding:2px 6px;border-radius:3px;font-size:11px;color:#e8ecf0;"><?php echo esc_html( $ns_npub ); ?></code>
                    </p>
                    <?php endif; ?>
                <?php else : ?>
                    <p class="description" style="font-size:13px;color:#5a6a7e;">
                        Kein Nostr Key vorhanden. Erstelle eine Nostr-Identitaet im <a href="<?php echo esc_url( function_exists( 'sk_get_navigation_url' ) ? sk_get_navigation_url( 'auth-connector' ) : '#' ); ?>">Auth Connector</a> oder importiere unten einen bestehenden Key.
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <?php /* ── nsec Import ── */ ?>
        <div class="sk-form-group">
            <label class="sk-w3 sk-control-label">Nostr Key importieren</label>
            <div class="sk-w5">
                <div id="sk-nsec-import-toggle">
                    <button type="button" class="button button-small" id="sk-nsec-show-import" style="font-size:13px;">
                        <i class="fas fa-key"></i> nsec importieren
                    </button>
                    <p class="description" style="margin-top:6px;font-size:13px;color:#9ca3af;">
                        Verwende einen bestehenden Nostr Key (z.B. aus der Einundzwanzig Meetup App, Amethyst oder einer anderen Nostr-Wallet).
                        <?php if ( $ns_has_pubkey ) : ?>
                            <br><strong style="color:#f7931a;">Achtung:</strong> Dein aktueller SK-Key wird dabei ersetzt.
                        <?php endif; ?>
                    </p>
                </div>
                <div id="sk-nsec-import-field" style="display:none;">
                    <input type="password" class="sk-form-control" name="nostr_import_nsec" id="sk-nsec-input"
                           value="" autocomplete="off"
                           placeholder="nsec1..."
                           style="font-family:monospace;font-size:12px;margin-bottom:6px;" />
                    <p class="description" style="font-size:12px;color:#dc3545;margin-bottom:8px;">
                        <i class="fas fa-exclamation-triangle"></i>
                        Dein nsec verlässt niemals diesen Server. Er wird verschluesselt gespeichert (AES-256).
                    </p>
                    <label style="display:block;margin-bottom:6px;">
                        <input type="checkbox" name="nostr_import_confirm" id="sk-nsec-confirm" value="1" />
                        Ich verstehe, dass mein aktueller Nostr Key ersetzt wird und die Aenderung nicht rueckgaengig gemacht werden kann.
                    </label>
                </div>

                <?php if ( $ns_import_result === 'success' ) : ?>
                    <p style="margin-top:8px;font-size:13px;color:#5cb85c;">
                        <i class="fas fa-check-circle"></i> Nostr Key erfolgreich importiert!
                    </p>
                <?php elseif ( $ns_import_result === 'invalid' ) : ?>
                    <p style="margin-top:8px;font-size:13px;color:#dc3545;">
                        <i class="fas fa-times-circle"></i> Ungueltiger nsec oder Key bereits vergeben.
                    </p>
                <?php elseif ( $ns_import_result === 'not_confirmed' ) : ?>
                    <p style="margin-top:8px;font-size:13px;color:#f7931a;">
                        <i class="fas fa-exclamation-circle"></i> Bitte die Bestaetigung ankreuzen.
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <?php /* ── Nostr Marketplace (if module active) ── */ ?>
        <?php if ( $ns_has_market ) : ?>
        <div class="sk-form-group">
            <label class="sk-w3 sk-control-label">Inserate auf Nostr posten</label>
            <div class="sk-w5">
                <label>
                    <input type="hidden" name="nostr_market_enabled" value="0" />
                    <input type="checkbox" name="nostr_market_enabled" value="1" <?php checked( $ns_post_enabled, '1' ); ?>>
                    Deine Produkte werden als Inserate auf dem Nostr Netzwerk veroeffentlicht
                </label>
                <p class="description" style="margin-top:6px;font-size:13px;color:#9ca3af;">
                    Sichtbar auf Amethyst, Shopstr, Coracle und anderen Nostr Clients.
                </p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
    jQuery(function($){
        $('#sk-nsec-show-import').on('click', function(){
            $('#sk-nsec-import-toggle').hide();
            $('#sk-nsec-import-field').show();
            $('#sk-nsec-input').focus();
        });
    });
    </script>

    <!-- ======================================================
         SECTION 4: Biografie
    ====================================================== -->
    <?php if ( function_exists( 'sk_ext' ) ) : ?>
    <div class="sk-settings-section">
        <div class="sk-settings-section-title">
            <i class="fas fa-pen"></i> <?php esc_html_e( 'Biografie', 'sk' ); ?>
        </div>
        <div class="sk-settings-field sk-settings-field--bio">
            <div class="sk-settings-input">
                <textarea name="vendor_biography" id="vendor_biography" rows="8" class="sk-form-control" style="width:100%;background:#1f2732;border:1px solid #384355;color:#e8ecf0;border-radius:6px;padding:8px 12px;font-size:15px;resize:vertical;"><?php echo esc_textarea( $vendor_biography ); ?></textarea>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ======================================================
         SECTION 5: Katalog-Modus (conditional)
    ====================================================== -->
    <?php if ( $catalog_mode_on && $cm_hide_cart_enabled ) : ?>
    <div class="sk-settings-section">
        <div class="sk-settings-section-title">
            <i class="fas fa-eye-slash"></i> <?php esc_html_e( 'Katalog-Modus', 'sk-core' ); ?>
        </div>
        <?php wp_nonce_field( 'sk_catalog_mode_settings_action', '_sk_catalog_mode_nonce' ); ?>
        <div class="sk-form-group">
            <label class="sk-w3 sk-control-label" for="catalog_mode_hide_add_to_cart_button"><?php esc_html_e( 'Remove Add to Cart Button', 'sk-core' ); ?></label>
            <div class="sk-w5 sk-text-left">
                <label for="catalog_mode_hide_add_to_cart_button">
                    <input type="checkbox" id="catalog_mode_hide_add_to_cart_button" value="on" name="catalog_mode[hide_add_to_cart_button]"
                        <?php checked( $cm_hide_cart, 'on' ); ?> />
                    <span><?php esc_html_e( 'Check to remove Add to Cart option from your products.', 'sk-core' ); ?></span>
                </label>
            </div>
        </div>
        <div class="catalog_mode_extra_section">
            <?php if ( $cm_hide_price_enabled ) : ?>
            <div class="sk-form-group">
                <label class="sk-w3 sk-control-label" for="catalog_mode_hide_product_price"><?php esc_attr_e( 'Hide Product Price', 'sk-core' ); ?></label>
                <div class="sk-w5 sk-text-left">
                    <label for="catalog_mode_hide_product_price">
                        <input type="checkbox" id="catalog_mode_hide_product_price" value="on" name="catalog_mode[hide_product_price]"
                            <?php checked( $cm_hide_price, 'on' ); ?> />
                        <span><?php esc_html_e( 'Check to hide product price from your products.', 'sk-core' ); ?></span>
                    </label>
                </div>
            </div>
            <?php endif; ?>
            <?php do_action( 'sk_catalog_mode_extra_settings_section', $current_user, $cm_settings ); ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ======================================================
         SECTION 6: Store-Link
    ====================================================== -->
    <div class="sk-settings-section">
        <div class="sk-settings-section-title">
            <i class="fas fa-link"></i> Store-Link
        </div>
        <div class="sk-settings-field">
            <label class="sk-settings-label" for="store_slug">URL-Slug</label>
            <div class="sk-settings-input">
                <input type="text" class="sk-form-control" name="store_slug" id="store_slug" value="<?php echo esc_attr( $store_slug ); ?>" />
                <p class="description" style="margin-top:6px">
                    Dein Shop ist erreichbar unter:
                    <strong><?php echo esc_url( site_url( '/store/' ) ); ?><span id="store_slug_preview"><?php echo esc_attr( $store_slug ); ?></span></strong>
                </p>
            </div>
        </div>
    </div>

    <?php /* Extension points for additional sections */ ?>
    <?php do_action( 'sk_settings_after_store_more_products', $current_user, $profile_info ); ?>
    <?php do_action( 'sk_settings_form_bottom', $current_user, $profile_info ); ?>

    <?php if ( $tnc_enable === 'on' ) : ?>
    <div class="sk-settings-section">
        <div class="sk-settings-section-title">
            <i class="fas fa-file-contract"></i> <?php esc_html_e( 'AGB', 'sk-core' ); ?>
        </div>
        <div class="sk-settings-field">
            <div class="sk-settings-label"><?php esc_html_e( 'AGB anzeigen', 'sk-core' ); ?></div>
            <div class="sk-settings-input">
                <label class="sk-settings-check-row">
                    <input type="checkbox" id="sk_store_tnc_enable" value="on" name="sk_store_tnc_enable"
                           <?php echo $enable_tnc === 'on' ? 'checked' : ''; ?>>
                    <?php esc_html_e( 'AGB auf Shop-Seite anzeigen', 'sk-core' ); ?>
                </label>
                <div id="sk_tnc_text" style="margin-top:12px;">
                    <textarea name="sk_store_tnc" id="sk_store_tnc" rows="6" class="sk-form-control" style="width:100%;background:#1f2732;border:1px solid #384355;color:#e8ecf0;border-radius:6px;padding:8px 12px;font-size:15px;resize:vertical;"><?php echo esc_textarea( $store_tnc ); ?></textarea>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Submit -->
    <div class="sk-settings-actions">
        <input type="submit" name="sk_update_store_settings" class="sk-btn sk-btn-btc sk-settings-save-btn"
               value="<?php esc_attr_e( 'Einstellungen speichern', 'sk-core' ); ?>">
    </div>

</form>

<?php do_action( 'sk_settings_after_form', $current_user, $profile_info ); ?>

<style>
.sk-settings-content .sk-settings-area .sk-banner {
    max-width: <?php echo esc_attr( $banner_width ); ?>px;
    max-height: <?php echo esc_attr( $banner_height ); ?>px;
}
.sk-settings-content .sk-settings-area .sk-banner .sk-remove-banner-image {
    height: <?php echo esc_attr( $banner_height ); ?>px;
}
.skp-saved::placeholder { color: #e8ecf0 !important; opacity: 1; }
</style>

<script type="text/javascript">
function skStoreToast(message, type) {
    type = type || 'info';
    var existing = document.querySelector('.dcg-toast[data-toast-id="sk-store"]');
    if (existing && existing.parentNode) existing.parentNode.removeChild(existing);

    var toast = document.createElement('div');
    toast.className = 'dcg-toast dcg-toast--' + type;
    toast.dataset.toastId = 'sk-store';

    var icon = type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle';
    toast.innerHTML =
        '<i class="fas ' + icon + '"></i>' +
        '<span>' + message + '</span>' +
        '<button class="close-toast" type="button" aria-label="Schlie\u00dfen">&times;</button>';

    document.body.appendChild(toast);

    toast.querySelector('.close-toast').addEventListener('click', function() {
        if (toast.parentNode) toast.parentNode.removeChild(toast);
    });

    setTimeout(function() {
        if (!toast.parentNode) return;
        toast.style.transition = 'opacity 0.3s, transform 0.3s';
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(420px)';
        setTimeout(function() { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 350);
    }, 5000);
}

(function($) {
    $(function() {
        var savedState = '<?php echo esc_js( $address_state ); ?>';
        if (!savedState || savedState === 'N/A') $('#sk-states-box').hide();
    });

    // Save via AJAX
    $('#sk-store-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $btn  = $form.find('[name="sk_update_store_settings"]');
        var originalVal = $btn.val();
        $btn.prop('disabled', true).val('<?php echo esc_js( __( 'Wird gespeichert…', 'sk-core' ) ); ?>');
        $('.sk-store-ajax-msg').remove();
        var data = $form.serialize() + '&action=sk_settings&form_id=store-form';
        $.post('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', data)
            .done(function(res) {
                $btn.prop('disabled', false).val(originalVal);
                if (res && res.success) {
                    window.onbeforeunload = null;
                    $form.data('submitted', true);
                    skStoreToast('<?php echo esc_js( __( 'Einstellungen gespeichert.', 'sk-core' ) ); ?>', 'info');
                } else {
                    var errText = (res && res.data) ? (Array.isArray(res.data) ? res.data.join(', ') : res.data) : 'Fehler beim Speichern.';
                    skStoreToast(errText, 'error');
                }
            })
            .fail(function() {
                $btn.prop('disabled', false).val(originalVal);
            });
    });

    // Banner upload
    var skBannerFrame;
    $('.sk-banner-drag').on('click', function(e) {
        e.preventDefault();
        if (skBannerFrame) { skBannerFrame.open(); return; }
        skBannerFrame = wp.media({ title: '<?php echo esc_js( __( 'Banner auswählen', 'sk-core' ) ); ?>', button: { text: '<?php echo esc_js( __( 'Auswählen', 'sk-core' ) ); ?>' }, multiple: false });
        skBannerFrame.on('select', function() {
            var att = skBannerFrame.state().get('selection').first().toJSON();
            $('#sk-banner-wrapper .sk-file-field').val(att.id);
            $('#sk-banner-wrapper .sk-banner-img').attr('src', att.url);
            $('#sk-banner-wrapper .image-wrap').removeClass('sk-hide');
            $('#sk-banner-wrapper .button-area').addClass('sk-hide');
        });
        skBannerFrame.open();
    });
    $('.sk-remove-banner-image').on('click', function(e) {
        e.preventDefault();
        $('#sk-banner-wrapper .sk-file-field').val('');
        $('#sk-banner-wrapper .image-wrap').addClass('sk-hide');
        $('#sk-banner-wrapper .button-area').removeClass('sk-hide');
    });

    // Gravatar upload
    var skGravatarFrame;
    $('.sk-pro-gravatar-drag').on('click', function(e) {
        e.preventDefault();
        if (skGravatarFrame) { skGravatarFrame.open(); return; }
        skGravatarFrame = wp.media({ title: '<?php echo esc_js( __( 'Profilbild auswählen', 'sk-core' ) ); ?>', button: { text: '<?php echo esc_js( __( 'Auswählen', 'sk-core' ) ); ?>' }, multiple: false });
        skGravatarFrame.on('select', function() {
            var att = skGravatarFrame.state().get('selection').first().toJSON();
            $('#sk-profile-picture-wrapper .sk-file-field').val(att.id);
            $('#sk-profile-picture-wrapper .sk-gravatar-img').attr('src', att.url);
            $('#sk-profile-picture-wrapper .gravatar-wrap').removeClass('sk-hide');
            $('#sk-profile-picture-wrapper .gravatar-button-area').addClass('sk-hide');
        });
        skGravatarFrame.open();
    });
    $('.sk-remove-gravatar-image').on('click', function(e) {
        e.preventDefault();
        $('#sk-profile-picture-wrapper .sk-file-field').val('');
        $('#sk-profile-picture-wrapper .gravatar-wrap').addClass('sk-hide');
        $('#sk-profile-picture-wrapper .gravatar-button-area').removeClass('sk-hide');
    });

    // Store slug preview
    (function() {
        var input = document.getElementById('store_slug');
        var preview = document.getElementById('store_slug_preview');
        if (input && preview) {
            input.addEventListener('input', function() {
                preview.textContent = input.value.trim().toLowerCase().replace(/\s+/g, '-');
            });
        }
    })();

    // Catalog mode toggle
    $('#catalog_mode_hide_add_to_cart_button').on('change', function() {
        if ($(this).is(':checked')) {
            $('div.catalog_mode_extra_section').show();
        } else {
            $('div.catalog_mode_extra_section').hide();
            $('#catalog_mode_hide_product_price').prop('checked', false);
        }
    }).trigger('change');

    /* ── Onchain + Lightning Connection Test Buttons ── */
    var skpAjax = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
    var skpNonce = '<?php echo wp_create_nonce( 'skp_test_connection' ); ?>';

    // BTC Address: enable button when looks like a valid bitcoin address
    $('input[name="btc_address"]').on('input', function() {
        var v = $(this).val().trim();
        var ok = /^(bc1[a-z0-9]{25,90}|[13][a-km-zA-HJ-NP-Z1-9]{25,34})$/i.test(v);
        $('#skp-test-btcaddr').prop('disabled', !ok);
        $('#skp-test-btcaddr-result').text('');
    });

    // xpub: enable button when looks like xpub/ypub/zpub
    $('input[name="btc_xpub"]').on('input', function() {
        var v = $(this).val().trim();
        var ok = /^[xyz]pub[a-km-zA-HJ-NP-Z1-9]{100,120}$/i.test(v);
        $('#skp-test-xpub').prop('disabled', !ok);
        $('#skp-test-xpub-result').text('');
    });

    // NWC: enable button when input matches protocol
    $('input[name="nwc_connection"]').on('input', function() {
        var v = $(this).val().trim();
        $('#skp-test-nwc').prop('disabled', !/^nostr\+walletconnect:\/\/[0-9a-f]{64}\?/.test(v));
        $('#skp-test-nwc-result').text('');
    });

    // LNDHub: enable button when input matches protocol
    $('input[name="lndhub_connection"]').on('input', function() {
        var v = $(this).val().trim();
        $('#skp-test-lndhub').prop('disabled', !/^lndhub:\/\/.+@https?:\/\//.test(v));
        $('#skp-test-lndhub-result').text('');
    });

    // Lightning Address: enable button when looks like email or lnurl
    $('input[name="lightning_address"]').on('input', function() {
        var v = $(this).val().trim();
        var ok = /^[a-zA-Z0-9_.+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/.test(v) || /^lnurl1/i.test(v);
        $('#skp-test-lnaddr').prop('disabled', !ok);
        $('#skp-test-lnaddr-result').text('');
    });

    // Trigger on load (for pre-filled values)
    $('input[name="btc_address"]').trigger('input');
    $('input[name="lightning_address"]').trigger('input');

    function skpTest(btn, resultEl, action, dataFn) {
        btn.on('click', function() {
            var $b = $(this), $r = $(resultEl);
            $b.prop('disabled', true);
            $r.html('<i class="fas fa-spinner fa-spin" style="color:#9ca3af;"></i> Teste...');
            $.post(skpAjax, $.extend({ action: action, nonce: skpNonce }, dataFn()), function(res) {
                $b.prop('disabled', false);
                if (res.success) {
                    $r.html('<span style="color:#5cb85c;">' + (res.data.message || 'OK') + '</span>');
                } else {
                    $r.html('<span style="color:#e06c75;">' + (res.data && res.data.message ? res.data.message : 'Fehler') + '</span>');
                }
            }).fail(function() {
                $b.prop('disabled', false);
                $r.html('<span style="color:#e06c75;">Netzwerkfehler</span>');
            });
        });
    }

    skpTest($('#skp-test-btcaddr'), '#skp-test-btcaddr-result', 'skp_test_btcaddr', function() {
        return { value: $('input[name="btc_address"]').val() };
    });
    skpTest($('#skp-test-xpub'), '#skp-test-xpub-result', 'skp_test_xpub', function() {
        return { value: $('input[name="btc_xpub"]').val() };
    });
    skpTest($('#skp-test-nwc'), '#skp-test-nwc-result', 'skp_test_nwc', function() {
        return { value: $('input[name="nwc_connection"]').val() };
    });
    skpTest($('#skp-test-lndhub'), '#skp-test-lndhub-result', 'skp_test_lndhub', function() {
        return { value: $('input[name="lndhub_connection"]').val() };
    });
    skpTest($('#skp-test-lnaddr'), '#skp-test-lnaddr-result', 'skp_test_lnaddr', function() {
        return { value: $('input[name="lightning_address"]').val() };
    });
})(jQuery);
</script>
