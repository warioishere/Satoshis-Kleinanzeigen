<?php

namespace SK\Modules\Payments;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the "Sofortkauf" button on single product pages.
 * Works independently of VendorChat.
 */
class ProductPage {

    public function __construct() {
        add_action( 'woocommerce_single_product_summary', [ $this, 'render_button' ], 30 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );

        // Onchain AJAX handlers.
        add_action( 'wp_ajax_skp_create_onchain_payment', [ $this, 'ajax_create_onchain_payment' ] );
        add_action( 'wp_ajax_skp_create_lightning_payment', [ $this, 'ajax_create_lightning_payment' ] );
    }

    public function render_button() {
        if ( ! is_user_logged_in() ) {
            return;
        }

        global $product;
        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
            return;
        }

        $vendor_id  = (int) get_post_field( 'post_author', $product->get_id() );
        $buyer_id   = get_current_user_id();

        if ( $buyer_id === $vendor_id ) {
            return;
        }

        $has_ln     = StoreSettings::has_lightning( $vendor_id );
        $has_onchain = StoreSettings::has_onchain( $vendor_id );

        if ( ! $has_ln && ! $has_onchain ) {
            return;
        }

        $price_sats = (int) $product->get_price();
        $product_id = $product->get_id();
        $product_title = $product->get_name();
        $variants   = Variant::all( $product_id );

        ?>
        <div class="skp-buy-wrapper" style="margin:12px 0;">
            <button type="button"
                    class="skp-buy-btn button alt"
                    data-vendor-id="<?php echo esc_attr( $vendor_id ); ?>"
                    data-product-id="<?php echo esc_attr( $product_id ); ?>"
                    data-product-title="<?php echo esc_attr( $product_title ); ?>"
                    data-price-sats="<?php echo esc_attr( $price_sats ); ?>"
                    data-has-ln="<?php echo $has_ln ? '1' : '0'; ?>"
                    data-has-onchain="<?php echo $has_onchain ? '1' : '0'; ?>"
                    data-has-variants="<?php echo $variants ? '1' : '0'; ?>"
                    style="background:#f7931a !important;color:#fff !important;border:none !important;padding:10px 24px !important;font-size:16px !important;border-radius:6px !important;cursor:pointer !important;display:inline-flex !important;align-items:center !important;gap:8px !important;">
                Sofortkauf
            </button>
        </div>

        <!-- Lieferangaben: bezahlt wird sofort, der Anbieter muss trotzdem
             wissen, wohin die Ware geht. -->
        <div id="skp-note-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:99999;align-items:center;justify-content:center;">
            <div style="background:#1a2332;border:1px solid rgba(255,255,255,0.1);border-radius:12px;padding:24px;max-width:420px;width:90%;">
                <h3 style="margin:0 0 8px;color:#e8ecf0;font-size:18px;"><?php esc_html_e( 'Wohin geht die Bestellung?', 'sk-core' ); ?></h3>
                <p style="margin:0 0 14px;color:#5a6a7e;font-size:13px;line-height:1.5;">
                    <?php esc_html_e( 'Diese Angabe geht in den Chat mit dem Anbieter. Bei digitalen Artikeln reicht ein kurzer Hinweis.', 'sk-core' ); ?>
                </p>
                <textarea id="skp-note" rows="4"
                          placeholder="<?php esc_attr_e( 'Name, Strasse, PLZ und Ort — oder ein Hinweis', 'sk-core' ); ?>"
                          style="width:100%;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:#e8ecf0;font-size:14px;padding:10px;resize:vertical;"><?php echo esc_textarea( self::saved_note( get_current_user_id() ) ); ?></textarea>
                <p id="skp-note-error" style="display:none;margin:8px 0 0;color:#e05252;font-size:13px;"></p>
                <button type="button" id="skp-note-confirm"
                        style="display:block;width:100%;padding:12px;margin-top:14px;background:#f7931a;border:none;border-radius:8px;color:#fff;font-size:15px;font-weight:600;cursor:pointer;">
                    <?php esc_html_e( 'Weiter zur Zahlung', 'sk-core' ); ?>
                </button>
                <button type="button" id="skp-note-cancel"
                        style="display:block;width:100%;padding:10px;margin-top:8px;background:none;border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:#5a6a7e;font-size:14px;cursor:pointer;">
                    <?php esc_html_e( 'Abbrechen', 'sk-core' ); ?>
                </button>
            </div>
        </div>

        <!-- Lightning-Zahlung -->
        <div id="skp-lightning-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:99999;align-items:center;justify-content:center;">
            <div style="background:#1a2332;border:1px solid rgba(255,255,255,0.1);border-radius:12px;padding:24px;max-width:400px;width:90%;max-height:85vh;overflow-y:auto;">
                <h3 style="margin:0 0 16px;color:#e8ecf0;font-size:18px;"><i class="fas fa-bolt" style="color:#f7931a;"></i> <?php esc_html_e( 'Lightning-Zahlung', 'sk-core' ); ?></h3>
                <div id="skp-lightning-content"></div>
                <button type="button" id="skp-lightning-close"
                        style="display:block;width:100%;padding:10px;margin-top:12px;background:none;border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:#5a6a7e;font-size:14px;cursor:pointer;">
                    <?php esc_html_e( 'Schliessen', 'sk-core' ); ?>
                </button>
            </div>
        </div>

        <?php if ( $variants ) : ?>
        <!-- Ausfuehrungen: erst waehlen, dann bezahlen -->
        <div id="skp-variant-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:99999;align-items:center;justify-content:center;">
            <div style="background:#1a2332;border:1px solid rgba(255,255,255,0.1);border-radius:12px;padding:24px;max-width:400px;width:90%;max-height:85vh;overflow-y:auto;">
                <h3 style="margin:0 0 16px;color:#e8ecf0;font-size:18px;"><?php esc_html_e( 'Welche Ausführung?', 'sk-core' ); ?></h3>
                <?php foreach ( $variants as $index => $variant ) : ?>
                    <label class="skp-variant-option" style="display:flex;align-items:center;gap:10px;padding:12px 14px;margin-bottom:8px;background:rgba(247,147,26,0.08);border:1px solid rgba(247,147,26,0.25);border-radius:8px;color:#e8ecf0;font-size:15px;cursor:pointer;">
                        <input type="radio" name="skp_variant" value="<?php echo esc_attr( $variant['key'] ); ?>"
                               data-price-sats="<?php echo esc_attr( (int) $variant['sats'] ); ?>"
                               <?php checked( $index, 0 ); ?>
                               style="margin:0;flex:0 0 auto;accent-color:#f7931a;">
                        <span style="flex:1 1 auto;"><?php echo esc_html( $variant['name'] ); ?></span>
                        <span style="flex:0 0 auto;color:#f7931a;font-weight:700;white-space:nowrap;"><?php echo esc_html( Variant::format_sats( (int) $variant['sats'] ) ); ?></span>
                    </label>
                <?php endforeach; ?>
                <button type="button" id="skp-variant-confirm"
                        style="display:block;width:100%;padding:12px;margin-top:14px;background:#f7931a;border:none;border-radius:8px;color:#fff;font-size:15px;font-weight:600;cursor:pointer;">
                    <?php esc_html_e( 'Weiter', 'sk-core' ); ?>
                </button>
                <button type="button" id="skp-variant-cancel"
                        style="display:block;width:100%;padding:10px;margin-top:8px;background:none;border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:#5a6a7e;font-size:14px;cursor:pointer;">
                    <?php esc_html_e( 'Abbrechen', 'sk-core' ); ?>
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Payment Method Modal -->
        <?php if ( $has_ln && $has_onchain ) : ?>
        <div id="skp-method-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:99999;align-items:center;justify-content:center;">
            <div style="background:#1a2332;border:1px solid rgba(255,255,255,0.1);border-radius:12px;padding:24px;max-width:360px;width:90%;">
                <h3 style="margin:0 0 16px;color:#e8ecf0;font-size:18px;">Wie möchtest du bezahlen?</h3>
                <button type="button" class="skp-method-choice" data-method="lightning"
                        style="display:block;width:100%;padding:14px;margin-bottom:10px;background:rgba(247,147,26,0.1);border:1px solid rgba(247,147,26,0.3);border-radius:8px;color:#f7931a;font-size:15px;font-weight:600;cursor:pointer;text-align:left;">
                    <i class="fas fa-bolt"></i> Lightning (sofort, niedrige Gebühren)
                </button>
                <button type="button" class="skp-method-choice" data-method="onchain"
                        style="display:block;width:100%;padding:14px;margin-bottom:10px;background:rgba(247,147,26,0.1);border:1px solid rgba(247,147,26,0.3);border-radius:8px;color:#f7931a;font-size:15px;font-weight:600;cursor:pointer;text-align:left;">
                    <i class="fab fa-bitcoin"></i> Onchain (Bitcoin-Adresse)
                </button>
                <button type="button" id="skp-method-cancel"
                        style="display:block;width:100%;padding:10px;background:none;border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:#5a6a7e;font-size:14px;cursor:pointer;">
                    Abbrechen
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Onchain Payment Modal -->
        <div id="skp-onchain-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:99999;align-items:center;justify-content:center;">
            <div style="background:#1a2332;border:1px solid rgba(255,255,255,0.1);border-radius:12px;padding:24px;max-width:400px;width:90%;">
                <h3 style="margin:0 0 16px;color:#e8ecf0;font-size:18px;"><i class="fab fa-bitcoin" style="color:#f7931a;"></i> Onchain-Zahlung</h3>
                <div id="skp-onchain-content"></div>
                <button type="button" id="skp-onchain-close"
                        style="display:block;width:100%;padding:10px;margin-top:12px;background:none;border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:#5a6a7e;font-size:14px;cursor:pointer;">
                    Schliessen
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * Wie oft darf ein Konto einen Kauf anstossen?
     *
     * Jeder Anlauf kostet etwas: Onchain eine frisch abgeleitete Adresse,
     * Lightning eine Anfrage an die Wallet des Anbieters.
     */
    private static function purchase_rate_allows( int $buyer_id ): bool {
        return ! function_exists( 'sk_rate_limit' ) || sk_rate_limit( 'sk_buy:' . $buyer_id, 10 );
    }

    /**
     * Nur veroeffentlichte Inserate sind kaufbar.
     *
     * Ein Entwurf ist fuer niemanden sichtbar; ueber die Kennnummer liesse er
     * sich sonst trotzdem bestellen und taucht dann samt Titel im Chat auf.
     */
    private static function is_purchasable( int $product_id ): bool {
        return get_post_status( $product_id ) === 'publish';
    }

    /**
     * Notiz aus der Anfrage — Lieferadresse oder Hinweis fuer den Anbieter.
     */
    private static function posted_note(): string {
        $note = isset( $_POST['note'] ) // phpcs:ignore WordPress.Security.NonceVerification
            ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) // phpcs:ignore WordPress.Security.NonceVerification
            : '';

        return trim( mb_substr( $note, 0, 500 ) );
    }

    /**
     * Zuletzt genutzte Angabe, damit sie beim naechsten Kauf schon dasteht.
     */
    public static function saved_note( int $user_id ): string {
        return (string) get_user_meta( $user_id, 'sk_last_delivery_note', true );
    }

    private static function remember_note( int $user_id, string $note ): void {
        update_user_meta( $user_id, 'sk_last_delivery_note', $note );
    }

    /**
     * Lieferangabe und Ausfuehrung an der Zahlungszeile ablegen.
     *
     * In die vorhandene metadata-Spalte, damit die Verkaufsuebersicht die
     * Angaben zeigen kann, ohne dass der Anbieter den Chat durchsuchen muss.
     */
    private static function store_order_details( string $payment_hash, string $note, string $variant_name ): void {
        global $wpdb;

        $table = $wpdb->prefix . 'sk_lightning_payments';

        // Ergaenzen, nicht ersetzen: in derselben Spalte liegt spaeter auch
        // eine Problemmeldung.
        $existing = $wpdb->get_var(
            $wpdb->prepare( "SELECT metadata FROM {$table} WHERE payment_hash = %s", $payment_hash )
        );

        $meta = $existing ? json_decode( $existing, true ) : [];
        if ( ! is_array( $meta ) ) {
            $meta = [];
        }

        $meta['delivery_note'] = $note;
        $meta['variant']       = $variant_name;

        $wpdb->update(
            $table,
            [ 'metadata' => wp_json_encode( $meta ) ],
            [ 'payment_hash' => $payment_hash ],
            [ '%s' ],
            [ '%s' ]
        );
    }

    /**
     * @return array{delivery_note:string,variant:string}
     */
    public static function order_details( ?string $metadata ): array {
        $data = $metadata ? json_decode( $metadata, true ) : null;

        return [
            'delivery_note' => is_array( $data ) ? (string) ( $data['delivery_note'] ?? '' ) : '',
            'variant'       => is_array( $data ) ? (string) ( $data['variant'] ?? '' ) : '',
        ];
    }

    /**
     * Name der gewaehlten Ausfuehrung, leer wenn es keine gibt.
     */
    private static function variant_name( int $product_id, string $key ): string {
        $variant = Variant::find( $product_id, $key );

        return $variant ? (string) $variant['name'] : '';
    }

    /**
     * Bestellung samt Lieferangabe in den Chat schreiben.
     */
    private static function post_order_note( int $chat_id, int $buyer_id, string $title, int $sats, string $note ): void {
        $text = sprintf(
            /* translators: 1: product, 2: amount in sats, 3: delivery note */
            __( "Bestellung: %1\$s (%2\$s Sats)\n\n%3\$s", 'sk-core' ),
            $title,
            number_format_i18n( $sats ),
            $note
        );

        Chat\ChatIntegration::add_chat_message_static( $chat_id, $buyer_id, $text );
    }

    /**
     * Sofortkauf ueber Lightning.
     *
     * Die Invoice entsteht in der Wallet des Anbieters ueber denselben Weg,
     * den der Anbieter im Chat nutzt — der Kaeufer loest sie nur aus. Deshalb
     * kann hier keine fremde Invoice untergeschoben werden.
     */
    public function ajax_create_lightning_payment() {
        check_ajax_referer( 'sk_lightning_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'Nicht eingeloggt.' ] );
        }

        $product_id = absint( $_POST['product_id'] ?? 0 );
        $buyer_id   = get_current_user_id();
        $product    = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;

        if ( ! $product ) {
            wp_send_json_error( [ 'message' => 'Inserat nicht gefunden.' ] );
        }

        if ( ! self::is_purchasable( $product_id ) ) {
            wp_send_json_error( [ 'message' => 'Dieses Inserat ist nicht kaufbar.' ] );
        }

        if ( ! self::purchase_rate_allows( $buyer_id ) ) {
            wp_send_json_error( [ 'message' => 'Zu viele Versuche. Bitte kurz warten.' ] );
        }

        $vendor_id   = (int) get_post_field( 'post_author', $product_id );
        $variant_key = Variant::posted();

        if ( Variant::all( $product_id ) && ! Variant::find( $product_id, $variant_key ) ) {
            wp_send_json_error( [ 'message' => 'Bitte eine Ausführung wählen.' ] );
        }

        $amount_sats = Variant::price( $product, $variant_key );
        $title       = Variant::title( $product, $variant_key );

        if ( ! $vendor_id || $amount_sats < 1 ) {
            wp_send_json_error( [ 'message' => 'Inserat hat keinen gültigen Preis.' ] );
        }

        if ( $buyer_id === $vendor_id ) {
            wp_send_json_error( [ 'message' => 'Du kannst nicht bei dir selbst kaufen.' ] );
        }

        if ( ! StoreSettings::has_lightning( $vendor_id ) ) {
            wp_send_json_error( [ 'message' => 'Anbieter akzeptiert keine Lightning-Zahlungen.' ] );
        }

        $note = self::posted_note();
        if ( $note === '' ) {
            wp_send_json_error( [ 'message' => 'Bitte Lieferadresse oder Hinweis angeben.' ] );
        }

        $chat_id = Chat\ChatIntegration::find_or_create_chat_static( $buyer_id, $vendor_id, $product_id, $title );
        if ( is_wp_error( $chat_id ) ) {
            wp_send_json_error( [ 'message' => $chat_id->get_error_message() ] );
        }

        $request = new \WP_REST_Request( 'POST', '/sk/v1/lightning/invoice' );
        $request->set_param( 'vendor_id', $vendor_id );
        $request->set_param( 'amount_sats', $amount_sats );
        $request->set_param( 'product_id', $product_id );
        $request->set_param( 'chat_id', (int) $chat_id );

        $response = rest_do_request( $request );

        if ( $response->is_error() ) {
            $error = $response->as_error();
            wp_send_json_error( [ 'message' => $error->get_error_message() ] );
        }

        $data = $response->get_data();

        self::remember_note( $buyer_id, $note );
        self::store_order_details( $data['payment_hash'], $note, self::variant_name( $product_id, $variant_key ) );
        self::post_order_note( (int) $chat_id, $buyer_id, $title, $amount_sats, $note );

        // Dieselbe Karte wie im Chat, damit die Invoice auffindbar bleibt,
        // wenn der Kaeufer das Fenster schliesst.
        $card = wp_json_encode( [
            'type'         => 'lightning_invoice',
            'payment_hash' => $data['payment_hash'],
        ] );
        Chat\ChatIntegration::add_chat_message_static(
            (int) $chat_id,
            $buyer_id,
            "[lightning_invoice]{$card}[/lightning_invoice]",
            [
                'card_type'    => 'lightning_invoice',
                // Ohne den Hash findet die Karte ihre Zahlungszeile nicht und
                // wird stillschweigend gar nicht gezeigt.
                'payment_hash' => $data['payment_hash'],
            ]
        );

        // Die Bestellung ist damit vollstaendig: Ausfuehrung, Lieferangabe und
        // Zahlungszeile stehen. Ob bezahlt wurde, entscheidet sich spaeter.
        do_action( 'sk_order_placed', (string) $data['payment_hash'] );

        $dashboard_url = sk_get_navigation_url( 'vendor-chat' );

        wp_send_json_success( [
            'payment_request' => $data['payment_request'],
            'payment_hash'    => $data['payment_hash'],
            'qr'              => $data['qr_data_uri'],
            'deeplink'        => $data['deeplink'],
            'amount_sats'     => $amount_sats,
            'product_title'   => $title,
            // Sagt dem Fenster, ob sich der Eingang von selbst pruefen laesst.
            'has_verify'      => ! empty( $data['has_verify'] ),
            'chat_url'        => add_query_arg( 'chat_id', $chat_id, $dashboard_url ),
        ] );
    }

    public function enqueue_assets() {
        if ( ! is_user_logged_in() || ! is_product() ) {
            return;
        }

        wp_enqueue_style(
            'sk-payments-css',
            SK_PAYMENTS_ASSETS . '/css/sk-lightning.css',
            [],
            SK_PAYMENTS_VERSION
        );

        wp_enqueue_script(
            'sk-payments-product',
            SK_PAYMENTS_ASSETS . '/js/sk-payments-product.js',
            [ 'jquery' ],
            SK_PAYMENTS_VERSION,
            true
        );

        wp_localize_script( 'sk-payments-product', 'skPayments', [
            'ajaxurl'   => admin_url( 'admin-ajax.php' ),
            'resturl'   => rest_url( 'sk/v1/lightning/' ),
            'nonce'     => wp_create_nonce( 'sk_lightning_nonce' ),
            'restNonce' => wp_create_nonce( 'wp_rest' ),
            'userId'    => get_current_user_id(),
        ] );
    }

    public function ajax_create_onchain_payment() {
        check_ajax_referer( 'sk_lightning_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'Nicht eingeloggt.' ] );
        }

        $product_id = absint( $_POST['product_id'] ?? 0 );
        $buyer_id   = get_current_user_id();

        if ( ! $product_id || get_post_type( $product_id ) !== 'product' ) {
            wp_send_json_error( [ 'message' => 'Inserat nicht gefunden.' ] );
        }

        // Vendor, title and price come from the product, not from the request.
        $product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
        if ( ! $product ) {
            wp_send_json_error( [ 'message' => 'Inserat nicht gefunden.' ] );
        }

        if ( ! self::is_purchasable( $product_id ) ) {
            wp_send_json_error( [ 'message' => 'Dieses Inserat ist nicht kaufbar.' ] );
        }

        if ( ! self::purchase_rate_allows( $buyer_id ) ) {
            wp_send_json_error( [ 'message' => 'Zu viele Versuche. Bitte kurz warten.' ] );
        }

        $vendor_id     = (int) get_post_field( 'post_author', $product_id );
        $variant_key   = Variant::posted();
        $product_title = Variant::title( $product, $variant_key );
        $price_sats    = Variant::price( $product, $variant_key );

        // Wer eine Ausfuehrung waehlen kann, muss es auch tun: sonst ginge die
        // Bestellung zum "ab"-Preis der guenstigsten durch.
        if ( Variant::all( $product_id ) && ! Variant::find( $product_id, $variant_key ) ) {
            wp_send_json_error( [ 'message' => 'Bitte eine Ausführung wählen.' ] );
        }

        if ( ! $vendor_id || $price_sats < 1 ) {
            wp_send_json_error( [ 'message' => 'Inserat hat keinen gültigen Preis.' ] );
        }

        if ( $buyer_id === $vendor_id ) {
            wp_send_json_error( [ 'message' => 'Du kannst nicht bei dir selbst kaufen.' ] );
        }

        if ( ! StoreSettings::has_onchain( $vendor_id ) ) {
            wp_send_json_error( [ 'message' => 'Anbieter akzeptiert keine Onchain-Zahlungen.' ] );
        }

        // Auch hier: bezahlt wird sofort, der Anbieter braucht trotzdem eine
        // Lieferangabe.
        $note = self::posted_note();
        if ( $note === '' ) {
            wp_send_json_error( [ 'message' => 'Bitte Lieferadresse oder Hinweis angeben.' ] );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'sk_lightning_payments';

        /*
         * Offene Bestellung wiederverwenden statt eine neue Adresse abzuleiten.
         *
         * Jede Ableitung schiebt den Zaehler am xpub des Anbieters eine Stelle
         * weiter. Wer den Knopf wiederholt drueckt, treibt ihn sonst ueber die
         * Luecke, die Wallets beim Wiederherstellen abtasten (ueblich 20) —
         * eine spaetere Zahlung taucht in der Wallet dann nicht mehr auf. Beim
         * zweiten Anlauf auf dieselbe Ware bekommt der Kaeufer daher dieselbe
         * Adresse; das ist ohnehin das erwartete Verhalten.
         */
        $open = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT payment_hash, verify_url FROM {$table}
                 WHERE buyer_id = %d AND product_id = %d AND vendor_id = %d
                   AND amount_sats = %d AND context = 'onchain' AND status = 'pending'
                 ORDER BY id DESC LIMIT 1",
                $buyer_id,
                $product_id,
                $vendor_id,
                $price_sats
            )
        );

        if ( $open && ! empty( $open->verify_url ) ) {
            $address      = (string) $open->verify_url;
            $payment_hash = (string) $open->payment_hash;
            $btc_amount   = number_format( $price_sats / 100000000, 8, '.', '' );
            $bip21        = 'bitcoin:' . $address . '?amount=' . $btc_amount;

            wp_send_json_success( [
                'address'       => $address,
                'amount_sats'   => $price_sats,
                'btc_amount'    => $btc_amount,
                'payment_hash'  => $payment_hash,
                'product_title' => $product_title,
                'chat_url'      => '',
                'bip21'         => $bip21,
                'qr'            => QrImage::data_uri( $bip21 ),
            ] );
        }

        // Derive a fresh address for this buyer.
        $address = StoreSettings::get_next_onchain_address( $vendor_id );
        if ( empty( $address ) ) {
            wp_send_json_error( [ 'message' => 'Keine Empfangsadresse verfügbar.' ] );
        }

        // Convert sats to BTC for display.
        $btc_amount = number_format( $price_sats / 100000000, 8, '.', '' );

        $payment_hash = hash( 'sha256', $address . $buyer_id . $price_sats . microtime( true ) . random_bytes( 8 ) );

        $rate          = LNURL\ExchangeRate::get_btc_eur_rate();
        $exchange_rate = is_wp_error( $rate ) ? null : $rate;

        $wpdb->insert( $table, [
            'vendor_id'       => $vendor_id,
            'buyer_id'        => $buyer_id,
            'product_id'      => $product_id,
            'amount_sats'     => $price_sats,
            'payment_hash'    => $payment_hash,
            'payment_request' => 'bitcoin:' . $address . '?amount=' . $btc_amount,
            'status'          => 'pending',
            'context'         => 'onchain',
            // Kurs mitschreiben wie beim Lightning-Weg: was ein Verkauf in
            // Franken oder Euro wert war, laesst sich spaeter nicht mehr
            // rekonstruieren.
            'exchange_rate'   => $exchange_rate,
            'verify_url'      => $address,
            'buyer_ip_hash'   => ClientIp::hash(),
            'created_at'      => current_time( 'mysql' ),
        ], [
            '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s',
        ] );

        self::store_order_details( $payment_hash, $note, self::variant_name( $product_id, $variant_key ) );

        // If VendorChat is available, send a message.
        $chat_enabled = sk_get_option( 'sk_lightning_chat_integration', 'sk_lightning', 'on' ) === 'on';
        $chat_url = '';

        if ( $chat_enabled && class_exists( 'SK\Core\Dashboard\Modules\VendorChat' ) ) {
            $chat_id = Chat\ChatIntegration::find_or_create_chat_static( $buyer_id, $vendor_id, $product_id, $product_title );
            if ( ! is_wp_error( $chat_id ) ) {
                self::remember_note( $buyer_id, $note );
                self::post_order_note( (int) $chat_id, $buyer_id, $product_title, $price_sats, $note );

                // Only the reference is stored — address and amount are rebuilt
                // from the payment row by PaymentCard on every render.
                $message_data = wp_json_encode( [
                    'type'         => 'onchain_payment',
                    'payment_hash' => $payment_hash,
                    'amount_sats'  => $price_sats,
                ] );
                $message_text = "[onchain_payment]{$message_data}[/onchain_payment]";
                Chat\ChatIntegration::add_chat_message_static( $chat_id, $buyer_id, $message_text );

                $wpdb->update(
                    $table,
                    [ 'chat_id' => $chat_id ],
                    [ 'payment_hash' => $payment_hash ],
                    [ '%d' ],
                    [ '%s' ]
                );

                $dashboard_url = sk_get_navigation_url( 'vendor-chat' );
                $chat_url = add_query_arg( 'chat_id', $chat_id, $dashboard_url );
            }
        }

        do_action( 'sk_order_placed', $payment_hash );

        $bip21 = 'bitcoin:' . $address . '?amount=' . $btc_amount;

        wp_send_json_success( [
            'address'       => $address,
            'amount_sats'   => $price_sats,
            'btc_amount'    => $btc_amount,
            'payment_hash'  => $payment_hash,
            'product_title' => $product_title,
            'chat_url'      => $chat_url,
            'bip21'         => $bip21,
            'qr'            => QrImage::data_uri( $bip21 ),
        ] );
    }

}
