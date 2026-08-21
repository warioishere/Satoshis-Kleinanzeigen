<?php

namespace SK\Core\Dashboard\Modules;

/**
 * Contact details (Telegram, Twitter/X, Phone, Nostr, Email) for vendor profiles.
 * Ported from plugin: sk-add-contact-details
 */
class ContactDetails {

    public function __construct() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ], 20 );

        // Save store settings + account fields
        add_action( 'sk_store_profile_saved', [ $this, 'save_account_fields' ], 25, 1 );
        add_action( 'woocommerce_save_account_details', [ $this, 'sync_email_on_account_save' ], 30 );
        add_filter( 'sk_get_store_info',             [ $this, 'normalize_on_load' ], 10, 2 );

        // Store page header
        add_action( 'sk_store_header_info_fields', [ $this, 'output_store_header_contacts' ], 20 );

        // Product tab
        add_filter( 'woocommerce_product_tab_content_seller', [ $this, 'output_product_tab_contacts' ] );

        // Dashboard hint banner
        add_action( 'sk_dashboard_content_inside_before', [ $this, 'output_dashboard_hint' ] );

        // Font Awesome
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_font_awesome' ] );

        // Contact icons in product loops
        add_action( 'woocommerce_after_shop_loop_item',   [ $this, 'output_loop_icons' ], 99 );
        add_action( 'woocommerce_single_product_summary', [ $this, 'output_single_icons' ], 25 );

        // AJAX validation
        add_action( 'wp_ajax_sk_settings', [ $this, 'ajax_validate_settings' ], 0 );

        // Publish blocking
        add_action( 'sk_new_product_added', [ $this, 'maybe_force_draft' ], 20, 2 );
        add_action( 'sk_product_updated',   [ $this, 'maybe_force_draft' ], 20, 2 );
        add_action( 'sk_bulk_product_status_change', [ $this, 'maybe_force_bulk_draft' ], 99, 2 );
        add_filter( 'sk_get_default_product_status', [ $this, 'maybe_filter_default_status' ], 20, 3 );
        add_filter( 'sk_post_status',               [ $this, 'maybe_filter_post_statuses' ], 99, 2 );
    }

    public function enqueue(): void {
        // CSS merged into sk-theme.css (CSS consolidation)
    }

    public function enqueue_font_awesome(): void {
        if ( ! wp_style_is( 'fontawesome-free', 'enqueued' ) ) {
            wp_enqueue_style( 'fontawesome-free', SK_CORE_ASSETS . '/vendors/font-awesome-6/css/all.min.css', [], '6.5.0' );
        }
    }

    /* ---- Normalization helpers ---- */

    private function strip_url_prefixes( string $value, array $patterns ): string {
        if ( $value === '' ) return '';
        do {
            $before = $value;
            foreach ( $patterns as $pattern ) {
                $value = preg_replace( $pattern, '', $value );
            }
        } while ( $before !== $value );
        return $value;
    }

    private function normalize_contact_value( $value, string $type ): string {
        if ( is_array( $value ) ) $value = reset( $value );
        $value = trim( wp_strip_all_tags( (string) $value ) );
        if ( $value === '' ) return '';
        $value = html_entity_decode( $value, ENT_QUOTES, 'UTF-8' );
        $value = trim( $value );
        if ( $type === 'telegram' ) {
            $value = $this->strip_url_prefixes( $value, [ '#^(?:https?:\/\/)?(?:www\.)?(?:t\.me|telegram\.me|telegram\.dog)\/#i' ] );
        } elseif ( $type === 'twitter' ) {
            $value = $this->strip_url_prefixes( $value, [ '#^(?:https?:\/\/)?(?:www\.)?(?:mobile\.)?(?:twitter\.com|x\.com)\/#i' ] );
        }
        $value = rawurldecode( $value );
        $parts = preg_split( '/[?#]/', $value );
        $value = is_array( $parts ) ? (string) ( $parts[0] ?? '' ) : (string) $value;
        $value = trim( trim( $value, "/\t\n\r\0\x0B" ), '@' );
        $value = preg_replace( '/\s+/', '', $value );
        return sanitize_text_field( $value );
    }

    private function is_truthy_flag( $value ): bool {
        if ( is_array( $value ) ) $value = reset( $value );
        if ( is_bool( $value ) ) return $value;
        return in_array( strtolower( (string) $value ), [ '1', 'yes', 'on', 'true' ], true );
    }

    private function is_placeholder_email( string $email ): bool {
        $email = strtolower( sanitize_email( $email ) );
        if ( $email === '' ) return false;
        foreach ( [ '@satoshiskleinanzeigen.space', '@satoshiskleinanzeigen', '@nostr.local', '@btc.local', '@lightning.local' ] as $suffix ) {
            if ( substr( $email, -strlen( $suffix ) ) === $suffix ) return true;
        }
        return false;
    }

    private function extract_public_email( array $info ): string {
        foreach ( [ 'setting_email', 'email', 'store_email' ] as $key ) {
            if ( ! array_key_exists( $key, $info ) ) continue;
            $raw = is_array( $info[ $key ] ) ? reset( $info[ $key ] ) : $info[ $key ];
            $raw = sanitize_email( (string) $raw );
            if ( $raw !== '' && is_email( $raw ) && ! $this->is_placeholder_email( $raw ) ) return $raw;
        }
        return '';
    }

    private function vendor_has_public_contact( int $vendor_id, ?array $info = null ): bool {
        return self::check_public_contact( $vendor_id, $info );
    }

    /**
     * Public static check: does the vendor have at least one real public contact method?
     * Single source of truth — used by ContactDetails, Telegram, and Nostr plugins.
     *
     * @param int        $vendor_id
     * @param array|null $info  Pre-loaded store info (avoids duplicate DB query when caller already has it).
     */
    public static function has_public_contact( int $vendor_id ): bool {
        return self::check_public_contact( $vendor_id );
    }

    private static function check_public_contact( int $vendor_id, ?array $info = null ): bool {
        if ( $vendor_id <= 0 || ! function_exists( 'sk_get_store_info' ) ) return false;
        if ( ! is_array( $info ) ) $info = sk_get_store_info( $vendor_id );
        if ( ! is_array( $info ) ) $info = [];

        // Email: must be shown, valid, and not a placeholder
        $has_email = false;
        $show_email = $info['show_email'] ?? '';
        if ( is_array( $show_email ) ) $show_email = reset( $show_email );
        if ( in_array( strtolower( (string) $show_email ), [ '1', 'yes', 'on', 'true' ], true ) ) {
            foreach ( [ 'setting_email', 'email', 'store_email' ] as $key ) {
                if ( ! array_key_exists( $key, $info ) ) continue;
                $raw = is_array( $info[ $key ] ) ? reset( $info[ $key ] ) : $info[ $key ];
                $raw = sanitize_email( (string) $raw );
                if ( $raw === '' || ! is_email( $raw ) ) continue;
                $email_lower = strtolower( $raw );
                $is_placeholder = false;
                foreach ( [ '@satoshiskleinanzeigen.space', '@satoshiskleinanzeigen', '@nostr.local', '@btc.local', '@lightning.local' ] as $suffix ) {
                    if ( substr( $email_lower, -strlen( $suffix ) ) === $suffix ) { $is_placeholder = true; break; }
                }
                if ( ! $is_placeholder ) { $has_email = true; break; }
            }
        }

        // Telegram
        $has_telegram = ! empty( $info['telegram'] ) && ! empty( $info['show_telegram'] ) && trim( (string) $info['telegram'] ) !== '';

        // Twitter/X
        $has_twitter = ! empty( $info['twitter'] ) && ! empty( $info['show_twitter'] ) && trim( (string) $info['twitter'] ) !== '';

        // Nostr
        $has_nostr = ! empty( $info['nostr'] ) && ! empty( $info['show_nostr'] ) && trim( (string) $info['nostr'] ) !== '';

        // Phone: must contain a digit, reject "no"/"nein"/"n/a"/"-"
        $has_phone = false;
        if ( ! empty( $info['phone_number'] ) && ! empty( $info['show_phone_number'] ) ) {
            $phone_raw = strtolower( trim( (string) $info['phone_number'] ) );
            if ( ! in_array( $phone_raw, [ 'no', 'nein', 'n/a', '-' ], true ) && preg_match( '/\d/', $phone_raw ) ) {
                $has_phone = true;
            }
        }

        return (bool) apply_filters( 'dkp_contact_details_vendor_has_public_contact', ( $has_email || $has_telegram || $has_twitter || $has_phone || $has_nostr ), $vendor_id, $info );
    }

    private function normalize_settings( array $settings ): array {
        $changed = false;
        foreach ( [ 'telegram' => 'telegram', 'twitter' => 'twitter' ] as $key => $type ) {
            if ( ! array_key_exists( $key, $settings ) ) continue;
            $n = $this->normalize_contact_value( $settings[ $key ], $type );
            if ( $n !== $settings[ $key ] ) { $settings[ $key ] = $n; $changed = true; }
        }
        return $settings;
    }

    public function save_account_fields( int $store_id ): void {
        if ( $store_id <= 0 ) return;
        $user = get_userdata( $store_id );
        if ( ! $user instanceof \WP_User ) return;

        $update  = [ 'ID' => $store_id ];
        $changed = false;

        if ( isset( $_POST['sk_account_first_name'] ) ) {
            $update['first_name']   = sanitize_text_field( wp_unslash( $_POST['sk_account_first_name'] ) );
            $update['display_name'] = $update['first_name'];
            $changed = true;
        }
        if ( isset( $_POST['sk_account_last_name'] ) ) {
            $update['last_name'] = sanitize_text_field( wp_unslash( $_POST['sk_account_last_name'] ) );
            $changed = true;
        }

        // Password change: requires current password verification
        $pw1 = ! empty( $_POST['sk_account_password_1'] ) ? wp_unslash( $_POST['sk_account_password_1'] ) : '';
        $pw2 = ! empty( $_POST['sk_account_password_2'] ) ? wp_unslash( $_POST['sk_account_password_2'] ) : '';
        if ( $pw1 !== '' && $pw1 === $pw2 ) {
            $current_pw = ! empty( $_POST['sk_account_password_current'] ) ? wp_unslash( $_POST['sk_account_password_current'] ) : '';

            // An empty field must not skip the check — that turned every session
            // into a way to set a new password without knowing the old one.
            // Only accounts that never had a password (Nostr/LNURL logins) may
            // set their first one without it.
            $needs_current = sk_account_has_password( $store_id );

            if ( ! $needs_current || wp_check_password( $current_pw, $user->user_pass, $store_id ) ) {
                $update['user_pass'] = $pw1;
                $changed = true;
                update_user_meta( $store_id, 'sk_password_set', 1 );
            } else {
                if ( function_exists( 'sk_add_notice' ) ) {
                    sk_add_notice( __( 'Das aktuelle Passwort ist falsch.', 'sk-core' ), 'error' );
                }
            }
        }

        // Store slug (Shop-URL)
        if ( ! empty( $_POST['store_slug'] ) ) {
            $new_slug = sanitize_title( wp_unslash( $_POST['store_slug'] ) );
            $user_with_slug = get_user_by( 'slug', $new_slug );
            if ( $user_with_slug && $user_with_slug->ID !== $store_id ) {
                if ( function_exists( 'sk_add_notice' ) ) {
                    sk_add_notice( __( 'Dieser Store-Link ist bereits vergeben.', 'sk-core' ), 'error' );
                }
            } else {
                $update['user_nicename'] = $new_slug;
                $changed = true;
            }
        }

        if ( $changed ) {
            wp_update_user( $update );
        }
    }


    public function sync_email_on_account_save( int $user_id ): void {
        if ( ! function_exists( 'sk_is_user_seller' ) || ! sk_is_user_seller( $user_id ) ) return;
        $user     = get_userdata( $user_id );
        if ( ! $user instanceof \WP_User ) return;
        $settings = sk_get_store_info( $user_id );
        if ( ! is_array( $settings ) ) $settings = [];
        $current  = sanitize_email( (string) ( $settings['setting_email'] ?? '' ) );
        $email    = sanitize_email( (string) $user->user_email );
        if ( $email !== '' && ( $current === '' || $this->is_placeholder_email( $current ) || ! $this->is_truthy_flag( $settings['show_email'] ?? '' ) ) ) {
            $settings['setting_email'] = $email;
            update_user_meta( $user_id, 'sk_profile_settings', $settings );
        }
    }

    public function normalize_on_load( array $info, $seller_id ): array {
        if ( ! is_array( $info ) ) $info = [];
        if ( ! empty( $seller_id ) ) $info = $this->normalize_settings( $info );
        if ( empty( $info['setting_email'] ) && ! empty( $seller_id ) ) {
            $user = get_userdata( (int) $seller_id );
            if ( $user instanceof \WP_User && $user->exists() ) $info['setting_email'] = (string) $user->user_email;
        }
        return $info;
    }

    /* ---- Store/product output ---- */

    public function output_store_header_contacts( $store_user ): void {
        $info = sk_get_store_info( $store_user );
        if ( empty( $info['telegram'] ) && empty( $info['twitter'] ) && empty( $info['phone_number'] ) && empty( $info['nostr'] ) ) return;
        echo '<ul class="sk-store-custom-fields" style="margin-top:10px;list-style:none;padding:0;">';
        if ( ! empty( $info['telegram'] ) && ! empty( $info['show_telegram'] ) ) {
            $h = $this->normalize_contact_value( $info['telegram'], 'telegram' );
            if ( $h !== '' ) echo '<li><i class="fab fa-telegram"></i> <strong>Telegram:</strong> <a href="https://t.me/' . esc_attr( $h ) . '" target="_blank" rel="noopener">@' . esc_html( $h ) . '</a></li>';
        }
        if ( ! empty( $info['twitter'] ) && ! empty( $info['show_twitter'] ) ) {
            $h = $this->normalize_contact_value( $info['twitter'], 'twitter' );
            if ( $h !== '' ) echo '<li><i class="fab fa-x-twitter"></i> <strong>Twitter/X:</strong> <a href="https://x.com/' . esc_attr( $h ) . '" target="_blank" rel="noopener">@' . esc_html( $h ) . '</a></li>';
        }
        if ( ! empty( $info['phone_number'] ) && ! empty( $info['show_phone_number'] ) ) {
            echo '<li><i class="fas fa-phone"></i> <strong>Telefon:</strong> ' . esc_html( $info['phone_number'] ) . '</li>';
        }
        if ( ! empty( $info['nostr'] ) && ! empty( $info['show_nostr'] ) ) {
            $clean = preg_replace( '/^nostr:/i', '', (string) $info['nostr'] );
            $short = esc_html( substr( $clean, 0, 10 ) ) . '…';
            echo '<li><i class="sk-nostr-icon sk-nostr-icon--inline"></i> <strong>Nostr:</strong> <a href="https://primal.net/p/' . esc_attr( $clean ) . '" target="_blank" rel="noopener"><code>' . $short . '</code></a></li>';
        }
        echo '</ul>';
    }

    public function output_product_tab_contacts(): void {
        global $product;
        $vendor = sk_get_vendor_by_product( $product );
        if ( ! $vendor ) return;
        $info = sk_get_store_info( $vendor->get_id() );
        if ( empty( $info['telegram'] ) && empty( $info['twitter'] ) && empty( $info['phone_number'] ) && empty( $info['nostr'] ) ) return;
        echo '<ul class="kontakt-info-liste" style="list-style:none;padding:0;">';
        if ( ! empty( $info['telegram'] ) && ! empty( $info['show_telegram'] ) ) {
            $h = $this->normalize_contact_value( $info['telegram'], 'telegram' );
            if ( $h !== '' ) echo '<li><i class="fab fa-telegram"></i> <strong>Telegram:</strong> <a href="https://t.me/' . esc_attr( $h ) . '" target="_blank" rel="noopener">@' . esc_html( $h ) . '</a></li>';
        }
        if ( ! empty( $info['twitter'] ) && ! empty( $info['show_twitter'] ) ) {
            $h = $this->normalize_contact_value( $info['twitter'], 'twitter' );
            if ( $h !== '' ) echo '<li><i class="fab fa-x-twitter"></i> <strong>Twitter/X:</strong> <a href="https://x.com/' . esc_attr( $h ) . '" target="_blank" rel="noopener">@' . esc_html( $h ) . '</a></li>';
        }
        if ( ! empty( $info['phone_number'] ) && ! empty( $info['show_phone_number'] ) ) echo '<li><i class="fas fa-phone"></i> <strong>Telefon:</strong> ' . esc_html( $info['phone_number'] ) . '</li>';
        if ( ! empty( $info['nostr'] ) && ! empty( $info['show_nostr'] ) ) {
            $clean = preg_replace( '/^nostr:/i', '', (string) $info['nostr'] );
            echo '<li><i class="sk-nostr-icon sk-nostr-icon--inline"></i> <strong>Nostr:</strong> <a href="https://primal.net/p/' . esc_attr( $clean ) . '" target="_blank" rel="noopener">' . esc_html( $clean ) . '</a></li>';
        }
        echo '</ul>';
    }

    public function output_dashboard_hint(): void {
        if ( ! is_user_logged_in() ) return;
        $user_id = get_current_user_id();
        if ( ! function_exists( 'sk_is_user_seller' ) || ! sk_is_user_seller( $user_id ) ) return;
        $info = sk_get_store_info( $user_id );
        if ( $this->vendor_has_public_contact( $user_id, $info ) ) return;

        wp_enqueue_style( 'sk-contact-hint' );
        $url = esc_url( site_url( '/dashboard/settings/store/' ) );
        echo '<div class="kontakt-hinweis" role="alert">⚠️ Hinweis: Du hast noch keine Kontaktinformationen hinterlegt oder öffentlich gemacht. Es kann sich sonst niemand bei dir auf dein Inserat melden. <a class="kontakt-hinweis__link" href="' . $url . '">Kontaktdaten jetzt festlegen</a></div>';
    }

    /* ---- Contact icons ---- */

    private function collect_icons( array $info, int $vendor_id = 0, int $product_id = 0, string $context = '' ): array {
        $icons = [];
        if ( $this->is_truthy_flag( $info['show_email'] ?? '' ) ) {
            $email = $this->extract_public_email( $info );
            if ( $email !== '' ) $icons[] = [ 'href' => 'mailto:' . $email, 'title' => 'E-Mail: ' . $email, 'class' => 'fa-solid fa-envelope', 'key' => 'mail' ];
        }
        if ( ! empty( $info['telegram'] ) && ! empty( $info['show_telegram'] ) ) {
            $h = $this->normalize_contact_value( $info['telegram'], 'telegram' );
            if ( $h !== '' ) $icons[] = [ 'href' => 'https://t.me/' . rawurlencode( $h ), 'title' => 'Telegram: @' . $h, 'class' => 'fa-brands fa-telegram', 'key' => 'tg' ];
        }
        if ( ! empty( $info['twitter'] ) && ! empty( $info['show_twitter'] ) ) {
            $h = $this->normalize_contact_value( $info['twitter'], 'twitter' );
            if ( $h !== '' ) $icons[] = [ 'href' => 'https://x.com/' . rawurlencode( $h ), 'title' => 'X/Twitter: @' . $h, 'class' => 'fa-brands fa-x-twitter', 'key' => 'x' ];
        }
        if ( ! empty( $info['phone_number'] ) && ! empty( $info['show_phone_number'] ) ) {
            $tel = preg_replace( '/[^0-9+\s\(\)-]/', '', (string) $info['phone_number'] );
            $tel = preg_replace( '/\s+/', '', $tel );
            if ( $tel !== '' ) $icons[] = [ 'href' => 'tel:' . $tel, 'title' => 'Telefon', 'class' => 'fa-solid fa-phone', 'key' => 'tel' ];
        }
        if ( ! empty( $info['nostr'] ) && ! empty( $info['show_nostr'] ) ) {
            $clean = preg_replace( '/^nostr:/i', '', trim( (string) $info['nostr'] ) );
            if ( $clean !== '' ) $icons[] = [ 'href' => 'https://primal.net/p/' . $clean, 'title' => 'Nostr', 'class' => 'sk-nostr-icon', 'key' => 'nostr' ];
        }
        return apply_filters( 'dkp_contact_icons_collection', $icons, $vendor_id, $product_id, $context );
    }

    private function render_icons( array $icons, string $context = '' ): string {
        if ( empty( $icons ) ) return '';
        $classes = 'dkp-contact-icons' . ( $context !== '' ? ' dkp-contact-icons--' . sanitize_html_class( $context ) : '' );
        $html    = '<div class="' . esc_attr( $classes ) . '" aria-label="' . esc_attr__( 'Kontakt', 'sk-core' ) . '">';
        foreach ( $icons as $ic ) {
            $data_attrs = '';
            if ( isset( $ic['data'] ) && is_array( $ic['data'] ) ) {
                foreach ( $ic['data'] as $k => $v ) $data_attrs .= sprintf( ' data-%s="%s"', esc_attr( $k ), esc_attr( $v ) );
            }
            $onclick     = isset( $ic['key'] ) && $ic['key'] === 'chat' ? ' onclick="event.preventDefault();"' : '';
            $icon_class  = 'dkp-contact-icon dkp-contact-icon--' . esc_attr( $ic['key'] ) . ( ! empty( $ic['cdf_locked'] ) ? ' cdf-locked' : '' );
            $html .= sprintf(
                '<a href="%s" class="%s" title="%s" target="_blank" rel="noopener nofollow" aria-label="%s"%s%s><i class="%s" aria-hidden="true"></i></a>',
                esc_url( $ic['href'] ), esc_attr( $icon_class ), esc_attr( $ic['title'] ), esc_attr( $ic['title'] ), $data_attrs, $onclick, esc_attr( $ic['class'] )
            );
        }
        $html .= '</div>';
        return $html;
    }

    public function output_loop_icons(): void {
        if ( is_product() || ! function_exists( 'sk_get_vendor_by_product' ) ) return;
        global $product;
        if ( ! $product ) return;
        $vendor = sk_get_vendor_by_product( $product );
        if ( ! $vendor ) return;
        $icons = $this->collect_icons( sk_get_store_info( $vendor->get_id() ), $vendor->get_id(), $product->get_id(), 'loop' );
        if ( ! empty( $icons ) ) echo $this->render_icons( $icons, 'loop' ); // phpcs:ignore
    }

    public function output_single_icons(): void {
        if ( ! function_exists( 'sk_get_vendor_by_product' ) ) return;
        global $product;
        if ( ! $product ) return;
        $vendor = sk_get_vendor_by_product( $product );
        if ( ! $vendor ) return;
        $icons = $this->collect_icons( sk_get_store_info( $vendor->get_id() ), $vendor->get_id(), $product->get_id(), 'single' );
        if ( ! empty( $icons ) ) echo $this->render_icons( $icons, 'single' ); // phpcs:ignore
    }

    /* ---- AJAX validation ---- */

    public function ajax_validate_settings(): void {
        if ( ! is_user_logged_in() ) return;
        if ( ! function_exists( 'sk_is_user_seller' ) || ! sk_is_user_seller( get_current_user_id() ) ) return;
        $user_id = get_current_user_id();
        $p = isset( $_POST ) ? wp_unslash( $_POST ) : [];
        if ( isset( $p['sk_profile_settings'] ) && is_array( $p['sk_profile_settings'] ) ) $p = $p['sk_profile_settings'];

        $telegram = isset( $p['telegram'] )     ? $this->normalize_contact_value( $p['telegram'], 'telegram' ) : '';
        $twitter  = isset( $p['twitter'] )      ? $this->normalize_contact_value( $p['twitter'], 'twitter' ) : '';
        $phone    = isset( $p['phone_number'] ) ? trim( (string) $p['phone_number'] ) : '';
        $nostr    = isset( $p['nostr'] )        ? trim( (string) $p['nostr'] ) : '';
        $email_field = trim( (string) ( $p['setting_email'] ?? wp_unslash( $_POST['setting_email'] ?? '' ) ) );
        $email_san   = $email_field !== '' ? sanitize_email( $email_field ) : '';

        $errors = [];
        if ( $email_field !== '' && $email_san === '' ) $errors[] = __( 'Bitte gib eine gültige E-Mail-Adresse ein.', 'sk-core' );
        $has_contact = $telegram !== '' || $twitter !== '' || $phone !== '' || $nostr !== '' || ( $email_san !== '' && ! $this->is_placeholder_email( $email_san ) );

        $stored = sk_get_store_info( $user_id );
        $pic_candidates = [ $p['gravatar'] ?? null, $p['sk_gravatar'] ?? null, $p['icon'] ?? null, $stored['gravatar'] ?? null, $stored['icon'] ?? null ];
        $has_picture = false;
        foreach ( $pic_candidates as $val ) {
            if ( $val === null ) continue;
            $val = is_array( $val ) ? reset( $val ) : $val;
            if ( (string) $val !== '' && (string) $val !== '0' ) { $has_picture = true; break; }
        }
        if ( ! $has_picture ) $errors[] = __( 'Bitte lade ein Profilbild hoch, bevor du speicherst.', 'sk-core' );
        if ( ! $has_contact ) $errors[] = __( 'Bitte gib mindestens eine Kontaktmethode an (Telegram, Twitter/X, Telefonnummer, Nostr oder E-Mail).', 'sk-core' );

        if ( ! empty( $errors ) ) {
            if ( function_exists( 'sk_add_notice' ) ) foreach ( $errors as $msg ) sk_add_notice( $msg, 'error' );
            wp_send_json_error( '⛔️ ' . implode( "\n⛔️ ", $errors ) );
        }
    }

    /* ---- Publish blocking ---- */

    public function maybe_force_draft( int $product_id, array $data = [] ): void {
        if ( VendorChat::is_enabled() ) return;
        if ( $product_id <= 0 || ! is_user_logged_in() ) return;
        $uid = get_current_user_id();
        if ( ! function_exists( 'sk_is_user_seller' ) || ! sk_is_user_seller( $uid ) ) return;
        if ( ! function_exists( 'sk_is_product_author' ) || ! sk_is_product_author( $product_id ) ) return;
        if ( $this->vendor_has_public_contact( $uid ) ) return;
        $status = get_post_status( $product_id );
        if ( ! in_array( $status, [ 'publish', 'pending', 'future', 'private' ], true ) ) return;
        wp_update_post( [ 'ID' => $product_id, 'post_status' => 'draft' ] );
        $this->add_publish_block_notice();
    }

    public function maybe_force_bulk_draft( $status, array $product_ids ): void {
        if ( VendorChat::is_enabled() ) return;
        if ( ! is_user_logged_in() ) return;
        $uid = get_current_user_id();
        if ( ! function_exists( 'sk_is_user_seller' ) || ! sk_is_user_seller( $uid ) ) return;
        if ( $this->vendor_has_public_contact( $uid ) ) return;
        if ( ! in_array( $status, [ 'publish', 'pending', 'future' ], true ) ) return;
        foreach ( (array) $product_ids as $pid ) {
            $pid = (int) $pid;
            if ( $pid <= 0 ) continue;
            if ( ! function_exists( 'sk_is_product_author' ) || ! sk_is_product_author( $pid ) ) continue;
            if ( in_array( get_post_status( $pid ), [ 'publish', 'pending', 'future', 'private' ], true ) ) wp_update_post( [ 'ID' => $pid, 'post_status' => 'draft' ] );
        }
        $this->add_publish_block_notice();
    }

    public function maybe_filter_default_status( $status, $seller_id, $is_trusted ) {
        if ( VendorChat::is_enabled() ) return $status;
        $sid = (int) ( $seller_id ?: get_current_user_id() );
        if ( $sid <= 0 || ! function_exists( 'sk_is_user_seller' ) || ! sk_is_user_seller( $sid ) ) return $status;
        return $this->vendor_has_public_contact( $sid ) ? $status : 'draft';
    }

    public function maybe_filter_post_statuses( array $statuses, int $product_id ): array {
        if ( VendorChat::is_enabled() ) return $statuses;
        if ( ! is_user_logged_in() ) return $statuses;
        $uid = get_current_user_id();
        if ( ! function_exists( 'sk_is_user_seller' ) || ! sk_is_user_seller( $uid ) ) return $statuses;
        if ( $this->vendor_has_public_contact( $uid ) ) return $statuses;
        unset( $statuses['publish'], $statuses['pending'], $statuses['future'] );
        if ( ! isset( $statuses['draft'] ) && function_exists( 'sk_get_post_status' ) ) $statuses['draft'] = sk_get_post_status( 'draft' );
        return $statuses;
    }

    private function add_publish_block_notice(): void {
        if ( ! function_exists( 'sk_add_notice' ) ) return;
        static $notice_added = false;
        if ( $notice_added ) return;
        $url = esc_url( site_url( '/dashboard/settings/store/' ) );
        $message = sprintf( __( 'Veröffentlichung blockiert: Bitte hinterlege in deinem <a href="%s">Shop-Profil</a> mindestens eine Kontaktmethode (z. B. Telegram, Telefonnummer oder eine öffentliche E-Mail-Adresse). Adressen mit @satoshiskleinanzeigen.space zählen nicht.', 'sk-core' ), $url );
        sk_add_notice( wp_kses_post( $message ), 'error' );
        $notice_added = true;
    }
}
