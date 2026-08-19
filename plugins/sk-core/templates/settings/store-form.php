<?php
/**
 * Vendor Settings Form — self-contained dark card layout.
 *
 * All sections are rendered inline (no hook-injection dependencies).
 * Save handlers remain in their original plugin files via sk_store_profile_saved.
 *
 * Sections (in order):
 *   1. Profil (banner, gravatar)
 *   2. Anbieter-Informationen (store name, store category, address, map)
 *   3. Kontaktdaten (email, telegram, twitter, phone, nostr, paywall)
 *   4. Biografie
 *   5. Katalog-Modus (conditional)
 *   6. Store-Link (slug)
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

$banner_width  = sk_get_vendor_store_banner_width();
$banner_height = sk_get_vendor_store_banner_height();

wp_enqueue_style( 'sk-store-settings' );
wp_enqueue_script( 'sk-store-settings-form' );
wp_localize_script(
    'sk-store-settings-form',
    'skStoreForm',
    [
        'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
        'testNonce'    => wp_create_nonce( 'skp_test_connection' ),
        'addressState' => $address_state,
        'savingLabel'  => __( 'Wird gespeichert…', 'sk-core' ),
        'savedMessage' => __( 'Einstellungen gespeichert.', 'sk-core' ),
        'bannerTitle'  => __( 'Banner auswählen', 'sk-core' ),
        'imageTitle'   => __( 'Bild auswählen', 'sk-core' ),
        'selectLabel'  => __( 'Auswählen', 'sk-core' ),
    ]
);

wp_enqueue_script( 'sk-store-delete-account' );
wp_localize_script(
    'sk-store-delete-account',
    'skStoreDeleteAccount',
    [
        'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
        'homeUrl'       => home_url( '/' ),
        'nonce'         => wp_create_nonce( 'sk_delete_account' ),
        'confirmFirst'  => __( 'Bist du sicher? Alle deine Daten, Produkte und dein Shop werden unwiderruflich gelöscht!', 'sk-core' ),
        'confirmSecond' => __( 'Letzte Warnung: Diese Aktion kann NICHT rückgängig gemacht werden. Wirklich löschen?', 'sk-core' ),
    ]
);

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
    $sc_label           = $sc_is_multiple ? __( 'Store Categories', 'sk-core' ) : __( 'Store Category', 'sk-core' );
}

/* --- Store slug --- */
$store_slug = $current_user_obj ? $current_user_obj->user_nicename : '';
?>

<form method="post" id="sk-store-form" action="" class="sk-settings-form" novalidate
    style="--sk-banner-width: <?php echo esc_attr( $banner_width ); ?>px; --sk-banner-height: <?php echo esc_attr( $banner_height ); ?>px;">
    <?php wp_nonce_field( 'sk_store_settings_nonce' ); ?>

    <!-- ======================================================
         SECTION 1: Profil
    ====================================================== -->
    <div class="sk-settings-section">
        <div class="sk-settings-section-title">
            <i class="fas fa-image"></i> <?php esc_html_e( 'Profil', 'sk-core' ); ?>
        </div>

        <?php
        sk_form_media_upload( [
            'name'          => 'sk_banner',
            'attachment_id' => (int) $banner_id,
            'default_url'   => $default_banner_url ?? '',
            'label'         => __( 'Banner', 'sk-core' ),
            'variant'       => 'banner',
            'upload_label'  => __( 'Banner hochladen', 'sk-core' ),
            'hint'          => sprintf( esc_html__( 'Empfohlen: %1$s × %2$s Pixel', 'sk-core' ), $banner_width, $banner_height ),
        ] );

        sk_form_media_upload( [
            'name'          => 'sk_gravatar',
            'attachment_id' => (int) $gravatar_id,
            'default_url'   => $default_avatar_url ?? '',
            'label'         => __( 'Profilbild', 'sk-core' ),
            'variant'       => 'gravatar',
            'upload_label'  => __( 'Foto hochladen', 'sk-core' ),
        ] );
        ?>
    </div>

    <!-- ======================================================
         SECTION 2: Anbieter-Informationen
    ====================================================== -->
    <div class="sk-settings-section">
        <div class="sk-settings-section-title">
            <i class="fas fa-store"></i> <?php esc_html_e( 'Anbieter Informationen', 'sk-core' ); ?>
        </div>

        <?php
        sk_form_input( [
            'name'        => 'sk_store_name',
            'value'       => $storename,
            'label'       => __( 'Anzeigename', 'sk-core' ),
            'placeholder' => __( 'Dein Anzeigename', 'sk-core' ),
            'required'    => true,
        ] );
        ?>

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


        <?php /* Address */ ?>

        <?php if ( sk_has_map_api_key() ) : ?>
        <div class="sk-settings-field">
            <label class="sk-settings-label"><?php esc_html_e( 'Kartenposition', 'sk-core' ); ?></label>
            <div class="sk-settings-input">
                <?php sk_get_template( 'maps/sk-maps-with-search.php', [ 'map_location' => $map_location, 'map_address' => $map_address ] ); ?>
                <p class="sk-settings-hint">💡 <?php esc_html_e( 'Gib deinen Standort an, falls du Abholung vor Ort anbietest. Grobe Ortsangaben reichen.', 'sk-core' ); ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ======================================================
         SECTION 3: Kontaktdaten
    ====================================================== -->
    <div class="sk-settings-section">
        <div class="sk-settings-section-title">
            <i class="fas fa-address-card"></i> <?php esc_html_e( 'Kontaktdaten', 'sk-core' ); ?>
        </div>

        <?php
        $toggle_label_public = __( 'Öffentlich anzeigen', 'sk-core' );

        /** Build the "public visibility" toggle checkbox appended after each contact field. */
        $public_toggle = function ( string $name, string $value, bool $checked ): string {
            $esc_name    = esc_attr( $name );
            $esc_value   = esc_attr( $value );
            $checked_str = $checked ? ' checked' : '';
            return sprintf(
                ' <label class="sk-settings-checkbox"><input type="checkbox" name="%1$s" value="%2$s"%3$s> %4$s</label>',
                $esc_name,
                $esc_value,
                $checked_str,
                esc_html( __( 'Öffentlich anzeigen', 'sk-core' ) )
            );
        };

        sk_form_input( [
            'type'   => 'email',
            'name'   => 'setting_email',
            'id'     => 'setting_email',
            'value'  => $cd_email,
            'label'  => __( 'E-Mail-Adresse', 'sk-core' ),
            'wrapper_class' => 'sk-contact-email',
            'extras' => '<input type="hidden" name="setting_show_email" value="no" />'
                      . $public_toggle( 'setting_show_email', 'yes', $cd_show_email === 'checked' ),
        ] );

        // Changing the address needs the account password — only accounts that
        // actually have one can be asked for it (Nostr/LNURL logins cannot).
        if ( function_exists( 'sk_account_has_password' ) && sk_account_has_password( $current_user ) ) {
            sk_form_input( [
                'type'  => 'password',
                'name'  => 'sk_email_change_password',
                'id'    => 'sk_email_change_password',
                'value' => '',
                'label' => __( 'Aktuelles Passwort', 'sk-core' ),
                'hint'  => __( 'Nur nötig, wenn du die E-Mail-Adresse änderst.', 'sk-core' ),
                'wrapper_class' => 'sk-contact-email-confirm',
            ] );
        }

        sk_form_input( [
            'name'   => 'telegram',
            'value'  => $cd_telegram,
            'label'  => __( 'Telegram Handle', 'sk-core' ),
            'extras' => $public_toggle( 'show_telegram', '1', $cd_show_tele === 'checked' ),
        ] );

        sk_form_input( [
            'name'   => 'twitter',
            'value'  => $cd_twitter,
            'label'  => __( 'Twitter / X Handle', 'sk-core' ),
            'extras' => $public_toggle( 'show_twitter', '1', $cd_show_tw === 'checked' ),
        ] );

        sk_form_input( [
            'name'   => 'phone_number',
            'value'  => $cd_phone,
            'label'  => __( 'Handynummer', 'sk-core' ),
            'extras' => $public_toggle( 'show_phone_number', '1', $cd_show_phone === 'checked' ),
        ] );

        sk_form_input( [
            'name'   => 'nostr',
            'value'  => $cd_nostr,
            'label'  => __( 'Nostr Public Key (npub...)', 'sk-core' ),
            'extras' => $public_toggle( 'show_nostr', '1', $cd_show_nostr === 'checked' ),
        ] );
        ?>

        <?php if ( $cd_feewall_available ) : ?>
        <div class="sk-settings-field">
            <label class="sk-settings-label"><strong>⚡ <?php esc_html_e( 'Kontaktdetails Paywall', 'sk-core' ); ?></strong></label>
            <div class="sk-settings-input">
                <label class="sk-settings-checkbox">
                    <input type="checkbox" name="cdf_enabled" value="1" <?php checked( $cd_feewall_enabled, true ); ?>>
                    <strong><?php esc_html_e( 'Paywall aktivieren (21 Sats)', 'sk-core' ); ?></strong>
                </label>
                <p class="sk-settings-hint"><?php esc_html_e( 'Interessenten zahlen 21 Sats via BTCPay, um deine Kontaktdaten zu sehen.', 'sk-core' ); ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>


    <!-- ======================================================
         SECTION 3b: Onchain + Lightning (conditional: sk-payments Modul aktiv)
    ====================================================== -->
    <?php if ( sk_module_active( 'sk_payments' ) ) :
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

        <div class="sk-settings-field">
            <label class="sk-settings-label">BTC-Adresse</label>
            <div class="sk-settings-input">
                <input type="text" class="sk-form-control" name="btc_address"
                       value="<?php echo esc_attr( $oc_btc_address ); ?>"
                       placeholder="bc1q... oder 1... oder 3..." />
                <p class="description">
                    Statische Bitcoin-Adresse. Achtung: Alle Käufer sehen dieselbe Adresse (kein Privacy-Vorteil).
                </p>
                <button type="button" class="sk-btn sk-btn-default skp-test-btn" id="skp-test-btcaddr" disabled>
                    <i class="fas fa-check-circle"></i> Adresse prüfen
                </button>
                <span id="skp-test-btcaddr-result" class="skp-test-result"></span>
            </div>
        </div>

        <div class="sk-settings-field">
            <label class="sk-settings-label">Extended Public Key (xpub)</label>
            <div class="sk-settings-input">
                <input type="text" class="sk-form-control<?php echo $oc_xpub ? ' skp-saved' : ''; ?>" name="btc_xpub"
                       value="" autocomplete="off"
                       placeholder="<?php echo $oc_xpub ? 'xpub/ypub/zpub******** (gespeichert — leer lassen um beizubehalten)' : 'xpub6... / ypub6... / zpub6...'; ?>" />
                <p class="description">
                    Für jeden Kauf wird automatisch eine neue Adresse abgeleitet (BIP32). Empfohlen für bessere Privacy.
                    Exportiere den xpub aus deiner Wallet (z.B. Sparrow, Electrum, BlueWallet).
                </p>
                <div class="sk-settings-notice sk-settings-notice--warn">
                    Der xpub erlaubt <strong class="ok">nur das Generieren von Empfangsadressen</strong>.<br>
                    <strong class="ok">Keine Ausgaben möglich</strong> — dein Guthaben ist sicher.
                </div>
                <?php if ( $oc_xpub ) : ?>
                    <?php if ( $oc_xpub_ok ) : ?>
                        <p class="sk-settings-status sk-settings-status--ok">
                            xpub gespeichert — Adress-Derivation aktiv.
                            <a href="#" class="sk-payment-remove-link" data-remove-field="xpub_remove" data-input-field="btc_xpub" data-default-placeholder="xpub6... / ypub6... / zpub6...">Entfernen</a>
                        </p>
                    <?php else : ?>
                        <p class="sk-settings-status sk-settings-status--warn">
                            xpub gespeichert, aber Validierung fehlgeschlagen.
                            <a href="#" class="sk-payment-remove-link" data-remove-field="xpub_remove" data-input-field="btc_xpub" data-default-placeholder="xpub6... / ypub6... / zpub6...">Entfernen</a>
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
                <input type="hidden" name="xpub_remove" value="0" />
                <button type="button" class="sk-btn sk-btn-default skp-test-btn" id="skp-test-xpub" disabled>
                    <i class="fas fa-plug"></i> xpub testen
                </button>
                <span id="skp-test-xpub-result" class="skp-test-result"></span>
            </div>
        </div>
    </div>

    <!-- Lightning -->
    <div class="sk-settings-section">
        <div class="sk-settings-section-title">
            <i class="fas fa-bolt"></i> Lightning-Zahlungen empfangen
        </div>

        <!-- NWC -->
        <div class="sk-settings-field">
            <label class="sk-settings-label">Nostr Wallet Connect</label>
            <div class="sk-settings-input">
                <input type="text" class="sk-form-control<?php echo $ln_has_nwc ? ' skp-saved' : ''; ?>" name="nwc_connection"
                       value="" autocomplete="off"
                       placeholder="<?php echo $ln_has_nwc ? 'nostr+walletconnect://******** (gespeichert — leer lassen um beizubehalten)' : 'nostr+walletconnect://...'; ?>" />
                <p class="description">
                    NWC Connection-String aus deiner Wallet (Alby Hub, LNbits, etc.).
                    Ermöglicht automatische Invoice-Erstellung und Zahlungsverifizierung. Verschlüsselt gespeichert.
                </p>
                <div class="sk-settings-notice sk-settings-notice--warn">
                    Benötigte Berechtigungen: <strong class="ok">make_invoice</strong> + <strong class="ok">lookup_invoice</strong>.<br>
                    <strong class="warn">pay_invoice nicht aktivieren</strong> — wird nicht benötigt und wäre ein Sicherheitsrisiko.
                </div>
                <?php if ( $ln_has_nwc ) : ?>
                    <?php if ( $ln_nwc_ok ) : ?>
                        <p class="sk-settings-status sk-settings-status--ok">
                            NWC verbunden — automatische Verifizierung aktiv.
                            <a href="#" class="sk-payment-remove-link" data-remove-field="nwc_remove" data-input-field="nwc_connection" data-default-placeholder="nostr+walletconnect://...">Entfernen</a>
                        </p>
                    <?php else : ?>
                        <p class="sk-settings-status sk-settings-status--warn">
                            NWC gespeichert, aber Verbindungstest fehlgeschlagen.
                            <a href="#" class="sk-payment-remove-link" data-remove-field="nwc_remove" data-input-field="nwc_connection" data-default-placeholder="nostr+walletconnect://...">Entfernen</a>
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
                <input type="hidden" name="nwc_remove" value="0" />
                <button type="button" class="sk-btn sk-btn-default skp-test-btn" id="skp-test-nwc" disabled>
                    <i class="fas fa-plug"></i> Verbindung testen
                </button>
                <span id="skp-test-nwc-result" class="skp-test-result"></span>
            </div>
        </div>

        <!-- LNDHub -->
        <div class="sk-settings-field">
            <label class="sk-settings-label">LNDHub</label>
            <div class="sk-settings-input">
                <input type="text" class="sk-form-control<?php echo $ln_has_lndhub ? ' skp-saved' : ''; ?>" name="lndhub_connection"
                       value="" autocomplete="off"
                       placeholder="<?php echo $ln_has_lndhub ? 'lndhub://******** (gespeichert — leer lassen um beizubehalten)' : 'lndhub://login:password@https://...'; ?>" />
                <p class="description">
                    LNDHub-URL aus BlueWallet, LNbits, Alby oder BTCPay Server. Verschlüsselt gespeichert.
                </p>
                <div class="sk-settings-notice sk-settings-notice--warn">
                    Verwende die <strong class="ok">Invoice-URL</strong> (lndhub://invoice:...).<br>
                    <strong class="warn">Nicht die Admin-URL verwenden</strong> — diese erlaubt auch Zahlungen zu senden und wäre ein Sicherheitsrisiko.
                </div>
                <?php if ( $ln_has_lndhub ) : ?>
                    <?php if ( $ln_lndhub_ok ) : ?>
                        <p class="sk-settings-status sk-settings-status--ok">
                            LNDHub verbunden — automatische Verifizierung aktiv.
                            <a href="#" class="sk-payment-remove-link" data-remove-field="lndhub_remove" data-input-field="lndhub_connection" data-default-placeholder="lndhub://login:password@https://...">Entfernen</a>
                        </p>
                    <?php else : ?>
                        <p class="sk-settings-status sk-settings-status--warn">
                            LNDHub gespeichert, aber Verbindungstest fehlgeschlagen.
                            <a href="#" class="sk-payment-remove-link" data-remove-field="lndhub_remove" data-input-field="lndhub_connection" data-default-placeholder="lndhub://login:password@https://...">Entfernen</a>
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
                <input type="hidden" name="lndhub_remove" value="0" />
                <button type="button" class="sk-btn sk-btn-default skp-test-btn" id="skp-test-lndhub" disabled>
                    <i class="fas fa-plug"></i> Verbindung testen
                </button>
                <span id="skp-test-lndhub-result" class="skp-test-result"></span>
            </div>
        </div>

        <!-- Lightning-Adresse -->
        <div class="sk-settings-field">
            <label class="sk-settings-label">Lightning-Adresse</label>
            <div class="sk-settings-input">
                <input type="text" class="sk-form-control" name="lightning_address"
                       value="<?php echo esc_attr( $ln_address ); ?>"
                       placeholder="user@getalby.com oder lnurl1..." />
                <p class="description">
                    Wird als Fallback verwendet wenn weder NWC noch LNDHub verbunden ist.
                </p>
                <?php if ( ! empty( $ln_address ) ) : ?>
                    <?php if ( $ln_lud21 ) : ?>
                        <div class="sk-settings-notice sk-settings-notice--ok">
                            Automatische Zahlungsverifizierung unterstützt (LUD-21)
                        </div>
                    <?php else : ?>
                        <div class="sk-settings-notice sk-settings-notice--warn">
                            Keine automatische Verifizierung — Zahlungen müssen manuell bestätigt werden.
                            Für automatische Verifizierung verwende NWC oder LNDHub (oben) oder einen Service der LUD-21 unterstützt (z.B. Alby, LNbits, Coinos).
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                <button type="button" class="sk-btn sk-btn-default skp-test-btn" id="skp-test-lnaddr" disabled>
                    <i class="fas fa-plug"></i> Adresse testen
                </button>
                <span id="skp-test-lnaddr-result" class="skp-test-result"></span>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ======================================================
         SECTION 3c: Nostr Marketplace (conditional: sk-nostr-market Modul aktiv)
    ====================================================== -->
    <?php if ( sk_module_active( 'sk_nostr_market' ) && sk_get_option( 'sk_nostr_market_enabled', 'sk_nostr_market', 'off' ) === 'on' ) :
        $nm_nostr_pubkey = get_user_meta( $current_user, 'nostr_public_key', true );
        $nm_has_pubkey   = ! empty( $nm_nostr_pubkey );
        $nm_post_enabled = $profile_info['nostr_market_enabled'] ?? ( $nm_has_pubkey ? '1' : '0' );
        $nm_self_sign    = $profile_info['nostr_market_self_sign'] ?? '0';

        // Convert pubkey to npub for display.
        $nm_npub = '';
        if ( $nm_has_pubkey && class_exists( '\swentel\nostr\Key\Key' ) ) {
            try {
                $nm_key = new \swentel\nostr\Key\Key();
                $nm_npub = $nm_key->convertPublicKeyToBech32( $nm_nostr_pubkey );
            } catch ( \Throwable $e ) {}
        }
    ?>
    <div class="sk-settings-section">
        <div class="sk-settings-section-title">
            <i class="sk-nostr-icon sk-nostr-icon--inline"></i> Nostr Marketplace
        </div>

        <div class="sk-settings-field">
            <label class="sk-settings-label">Inserate auf Nostr posten</label>
            <div class="sk-settings-input">
                <label>
                    <input type="hidden" name="nostr_market_enabled" value="0" />
                    <input type="checkbox" name="nostr_market_enabled" value="1" <?php checked( $nm_post_enabled, '1' ); ?>>
                    Deine Produkte werden als Inserate auf dem Nostr Netzwerk veröffentlicht
                </label>
                <p class="description">
                    Sichtbar auf Amethyst, Shopstr, Coracle und anderen Nostr Clients.
                </p>
            </div>
        </div>

        <?php if ( $nm_has_pubkey ) : ?>
        <div class="sk-settings-field">
            <label class="sk-settings-label">Nostr Key</label>
            <div class="sk-settings-input">
                <p class="sk-settings-status sk-settings-status--ok">
                    <i class="fas fa-check-circle"></i> Deine Inserate werden automatisch mit deinem Nostr Key signiert.
                </p>
                <?php if ( $nm_npub ) : ?>
                <p class="sk-settings-npub-line">
                    <code class="sk-settings-npub"><?php echo esc_html( substr( $nm_npub, 0, 20 ) . '...' ); ?></code>
                </p>
                <?php endif; ?>
            </div>
        </div>
        <?php else : ?>
        <div class="sk-settings-field">
            <label class="sk-settings-label">Nostr Key</label>
            <div class="sk-settings-input">
                <p class="description">
                    Kein Nostr Key vorhanden. Erstelle eine Nostr-Identität im <a href="<?php echo esc_url( function_exists( 'sk_get_navigation_url' ) ? sk_get_navigation_url( 'auth-connector' ) : '#' ); ?>">Auth Connector</a> um Inserate unter deinem eigenen Profil zu signieren.
                </p>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ======================================================
         SECTION 4: Biografie
    ====================================================== -->
    <?php if ( function_exists( 'sk_ext' ) ) : ?>
    <div class="sk-settings-section">
        <div class="sk-settings-section-title">
            <i class="fas fa-pen"></i> <?php esc_html_e( 'Biografie', 'sk-core' ); ?>
        </div>
        <div class="sk-settings-field sk-settings-field--bio">
            <div class="sk-settings-input">
                <textarea name="vendor_biography" id="vendor_biography" rows="8" class="sk-form-control sk-biography-textarea"><?php echo esc_textarea( $vendor_biography ); ?></textarea>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ======================================================
         SECTION 5: Store-Link
    ====================================================== -->
    <div class="sk-settings-section">
        <div class="sk-settings-section-title">
            <i class="fas fa-link"></i> <?php esc_html_e( 'Store-Link', 'sk-core' ); ?>
        </div>
        <?php
        $slug_hint = sprintf(
            '%s <strong>%s<span id="store_slug_preview">%s</span></strong>',
            esc_html__( 'Deine Anbieter-Seite ist erreichbar unter:', 'sk-core' ),
            esc_url( site_url( '/store/' ) ),
            esc_html( $store_slug )
        );

        sk_form_input( [
            'name'  => 'store_slug',
            'value' => $store_slug,
            'label' => __( 'URL-Slug', 'sk-core' ),
            'hint'  => $slug_hint,
        ] );
        ?>
    </div>

    <?php /* Extension points for additional sections */ ?>

    <!-- Submit -->
    <div class="sk-settings-actions">
        <input type="submit" name="sk_update_store_settings" class="sk-btn sk-btn-btc sk-settings-save-btn"
               value="<?php esc_attr_e( 'Einstellungen speichern', 'sk-core' ); ?>">
    </div>

</form>

<!-- Account löschen -->
<div class="sk-settings-section sk-settings-section--danger">
    <div class="sk-settings-section-title">
        <i class="fas fa-exclamation-triangle"></i> <?php esc_html_e( 'Gefahrenzone', 'sk-core' ); ?>
    </div>
    <div class="sk-settings-field sk-settings-field--flat">
        <p class="sk-settings-danger-text">
            <?php esc_html_e( 'Dein Account, alle Produkte und alle Daten werden unwiderruflich gelöscht. Dieser Vorgang kann nicht rückgängig gemacht werden.', 'sk-core' ); ?>
        </p>
        <button type="button" id="sk-delete-account-btn" class="sk-btn sk-btn-danger">
            <i class="fas fa-trash-alt"></i> <?php esc_html_e( 'Account endgültig löschen', 'sk-core' ); ?>
        </button>
    </div>
</div>



