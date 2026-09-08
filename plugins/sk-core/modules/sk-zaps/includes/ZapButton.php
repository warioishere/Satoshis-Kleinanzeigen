<?php

namespace SK\Modules\Zaps;

defined( 'ABSPATH' ) || exit;

/**
 * Renders Zap buttons on store and product pages.
 * Enqueues the JS that handles NIP-57 zapping via Nostr extension.
 */
class ZapButton {

    /** Wallet lookups a single IP may trigger per minute on the public verify endpoint. */
    const MAX_LOOKUPS_PER_MINUTE = 20;

    public function __construct() {
        // Store page — next to follow button in tab bar.
        if ( sk_get_option( 'sk_zaps_on_store', 'sk_zaps', 'on' ) === 'on' ) {
            add_action( 'sk_after_store_tabs', [ $this, 'render_store_button' ], 98, 1 );
        }

        // Product page.
        if ( sk_get_option( 'sk_zaps_on_product', 'sk_zaps', 'on' ) === 'on' ) {
            add_action( 'woocommerce_single_product_summary', [ $this, 'render_product_button' ], 35 );
        }

        add_action( 'wp_ajax_sk_zap_check_payment', [ __CLASS__, 'ajax_check_payment' ] );
        add_action( 'wp_ajax_nopriv_sk_zap_check_payment', [ __CLASS__, 'ajax_check_payment' ] );

        // The QR for the invoice, rendered here so it works without sk_payments.
        add_action( 'rest_api_init', [ __CLASS__, 'register_qr_route' ] );
        add_action( 'sk_zaps_fetch_lud16', [ __CLASS__, 'fetch_lud16' ], 10, 2 );
    }

    /**
     * Render zap button on vendor store page.
     */
    public function render_store_button( $store_id ): void {
        $data = self::get_vendor_zap_data( (int) $store_id );
        if ( ! $data ) {
            return;
        }

        echo '<li>';
        self::render_button( $data );
        echo '</li>';
    }

    /**
     * Render zap button on single product page.
     */
    public function render_product_button(): void {
        global $product;
        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
            return;
        }

        $vendor_id = (int) get_post_field( 'post_author', $product->get_id() );
        $data = self::get_vendor_zap_data( $vendor_id );
        if ( ! $data ) {
            return;
        }

        self::render_button( $data );
    }

    /**
     * Get zap data for a vendor (Lightning Address + Nostr pubkey).
     * Returns null if vendor can't receive zaps.
     */
    /**
     * The Nostr key a vendor is zapped under, as lowercase hex.
     *
     * Only a key whose holder has proven control of it towards this site
     * (see VendorKey): the login key, a generated identity, the marketplace
     * key for the platform account, or a typed npub once the extension has
     * signed the binding. A merely typed npub gets no zap button.
     */
    public static function vendor_pubkey( int $vendor_id ): string {
        return \SK\Core\Trust\VendorKey::bound( $vendor_id );
    }

    public static function get_vendor_zap_data( int $vendor_id ): ?array {
        $settings = get_user_meta( $vendor_id, 'sk_profile_settings', true );
        if ( ! is_array( $settings ) ) {
            return null;
        }

        $lightning_address = $settings['lightning_address'] ?? '';
        $nostr_pubkey      = self::vendor_pubkey( $vendor_id );

        // Fallback: our own LNURL-Pay endpoint, for vendors who connected a
        // wallet but never typed a Lightning Address. The local part is the
        // store slug — a slash in there is not a valid address for any wallet
        // but ours.
        if ( empty( $lightning_address ) && class_exists( 'SK\Modules\Payments\StoreSettings' ) ) {
            $can_invoice = \SK\Modules\Payments\StoreSettings::has_nwc( $vendor_id )
                || \SK\Modules\Payments\StoreSettings::has_lndhub( $vendor_id );

            if ( $can_invoice ) {
                $user = get_user_by( 'ID', $vendor_id );

                if ( $user && $user->user_nicename ) {
                    $lightning_address = $user->user_nicename . '@' . wp_parse_url( home_url(), PHP_URL_HOST );
                }
            }
        }

        // Last resort: the address from the vendor's Nostr profile. That lookup
        // hits a third party, so it never happens while a page is rendering —
        // it is queued and the button appears once the answer is in.
        if ( empty( $lightning_address ) && ! empty( $nostr_pubkey ) ) {
            $lightning_address = (string) get_user_meta( $vendor_id, 'sk_zap_lud16', true );

            if ( $lightning_address === '' ) {
                self::queue_lud16_lookup( $vendor_id, $nostr_pubkey );
            }
        }

        // No way to receive payment → no zap button. And no Nostr key → no
        // zap either: a zap is a Nostr receipt on a payment, and the button
        // is only for recipients who are on Nostr themselves.
        if ( empty( $lightning_address ) || empty( $nostr_pubkey ) ) {
            return null;
        }

        $store_info = function_exists( 'sk_get_store_info' ) ? sk_get_store_info( $vendor_id ) : [];
        $store_name   = $store_info['store_name'] ?? '';

        return [
            'vendor_id'         => $vendor_id,
            'store_name'        => $store_name,
            'lightning_address' => $lightning_address,
            'nostr_pubkey'      => $nostr_pubkey ?: '',
            'has_nostr'         => ! empty( $nostr_pubkey ),
        ];
    }

    /**
     * AJAX: Check if a zap invoice was paid via vendor's LNDHub/NWC.
     */
    public static function ajax_check_payment() {
        $vendor_id    = absint( $_POST['vendor_id'] ?? 0 );
        $payment_hash = strtolower( sanitize_text_field( wp_unslash( $_POST['payment_hash'] ?? '' ) ) );

        // This is the LUD-21 verify URL, so wallets call it unauthenticated.
        // That means it must not become a way to hammer or probe vendor wallets
        // with arbitrary hashes: strict format, then rate limits.
        if ( ! $vendor_id || ! preg_match( '/^[0-9a-f]{64}$/', $payment_hash ) ) {
            wp_send_json_error( [ 'settled' => false ] );
        }

        if ( ! class_exists( 'SK\Modules\Payments\StoreSettings' ) ) {
            wp_send_json_error( [ 'settled' => false ] );
        }

        // One wallet lookup per hash per second is plenty for real polling.
        $throttle_key = 'sk_zapchk_' . $payment_hash;
        if ( get_transient( $throttle_key ) ) {
            wp_send_json_success( [ 'settled' => false, 'throttled' => true ] );
        }
        set_transient( $throttle_key, 1, 1 );

        // Per-IP budget so nobody can use the endpoint to flood vendor wallets.
        $ip      = function_exists( 'sk_get_client_ip' ) ? sk_get_client_ip() : '';
        $ip_key  = 'sk_zapip_' . md5( $ip !== '' ? $ip : 'unknown' );
        $lookups = (int) get_transient( $ip_key );

        if ( $lookups >= self::MAX_LOOKUPS_PER_MINUTE ) {
            wp_send_json_error( [ 'settled' => false, 'message' => 'Zu viele Anfragen.' ] );
        }
        set_transient( $ip_key, $lookups + 1, MINUTE_IN_SECONDS );

        // Try NWC first, then LNDHub.
        $client = \SK\Modules\Payments\StoreSettings::get_nwc_client( $vendor_id );
        if ( ! $client ) {
            $client = \SK\Modules\Payments\StoreSettings::get_lndhub_client( $vendor_id );
        }

        if ( ! $client ) {
            wp_send_json_error( [ 'settled' => false ] );
        }

        $result = $client->lookup_invoice( $payment_hash );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'settled' => false ] );
        }

        $settled = ! empty( $result['settled'] );

        // On first settlement detection, publish Kind 9735 Zap Receipt.
        if ( $settled ) {
            $receipt_key = 'sk_zap_receipt_' . $payment_hash;
            if ( ! get_transient( $receipt_key ) ) {
                set_transient( $receipt_key, 1, DAY_IN_SECONDS );
                self::publish_zap_receipt( $vendor_id, $payment_hash, $result );
            }

            // The vendor's total moves with the payment, once per hash.
            if ( class_exists( 'SK\Modules\Zaps\ZapStats' ) ) {
                ZapStats::add_received( $vendor_id, $payment_hash, max( 0, (int) ( $result['amount_sats'] ?? 0 ) ) );
            }
        }

        wp_send_json_success( [ 'settled' => $settled ] );
    }

    /**
     * Publish Kind 9735 Zap Receipt on Nostr relays (NIP-57 compliant).
     */
    private static function publish_zap_receipt( int $vendor_id, string $payment_hash, array $invoice_result ) {
        if ( ! class_exists( 'SK\Modules\Auth\NostrIdentity' ) ) {
            return;
        }

        $vendor_pubkey = \SK\Modules\Auth\NostrIdentity::get_public_key( $vendor_id );
        if ( empty( $vendor_pubkey ) ) {
            return;
        }

        $bolt11   = $invoice_result['payment_request'] ?? $invoice_result['pr'] ?? '';
        $preimage = $invoice_result['preimage'] ?? '';

        // Retrieve the original Zap Request (Kind 9734) stored at invoice creation.
        $zap_request_json = get_transient( 'sk_zap_req_' . $payment_hash );
        delete_transient( 'sk_zap_req_' . $payment_hash );

        $tags = [
            [ 'p', $vendor_pubkey ],
        ];

        // NIP-57: include the original zap request as description tag.
        if ( $zap_request_json ) {
            $tags[] = [ 'description', $zap_request_json ];

            // Extract 'e' and 'P' tags from zap request for the receipt.
            $zap_req = json_decode( $zap_request_json, true );
            if ( is_array( $zap_req ) ) {
                foreach ( $zap_req['tags'] ?? [] as $ztag ) {
                    if ( 'e' === ( $ztag[0] ?? '' ) && ! empty( $ztag[1] ) ) {
                        $tags[] = [ 'e', $ztag[1] ];
                    }
                    if ( 'a' === ( $ztag[0] ?? '' ) && ! empty( $ztag[1] ) ) {
                        $tags[] = [ 'a', $ztag[1] ];
                    }
                }
                // Zapper's pubkey as 'P' tag.
                if ( ! empty( $zap_req['pubkey'] ) ) {
                    $tags[] = [ 'P', $zap_req['pubkey'] ];
                }
            }
        }

        if ( $bolt11 ) {
            $tags[] = [ 'bolt11', $bolt11 ];
        }
        if ( $preimage ) {
            $tags[] = [ 'preimage', $preimage ];
        }

        // Use marketplace key to sign (LNURL provider role per NIP-57).
        $privkey = \SK\Core\Nostr\Keys::marketplace_privkey();

        if ( '' === $privkey ) {
            return;
        }

        try {
            \SK\Core\Nostr\Relays::publish( \SK\Core\Nostr\Events::sign( 9735, '', $tags, $privkey ), null, $privkey );
        } catch ( \Throwable $e ) {
            error_log( '[SK Zaps] Failed to publish zap receipt: ' . $e->getMessage() );
        }
    }

    /**
     * Queue the profile lookup once per day per vendor.
     */
    private static function queue_lud16_lookup( int $vendor_id, string $nostr_pubkey ): void {
        $marker = 'sk_lud16_asked_' . $vendor_id;

        if ( false !== get_transient( $marker ) ) {
            return;
        }

        set_transient( $marker, 1, DAY_IN_SECONDS );

        wp_schedule_single_event( time() + 30, 'sk_zaps_fetch_lud16', [ $vendor_id, $nostr_pubkey ] );
    }

    /**
     * Fetch lud16 from the vendor's Nostr profile and store it under its own
     * meta key. Writing into sk_profile_settings from here would race with the
     * vendor saving their profile and drop whatever they just changed.
     *
     * Runs on cron, never during a page render.
     */
    public static function fetch_lud16( int $vendor_id, string $nostr_pubkey ): void {
        $lud16  = '';
        $events = null;

        // Primal's cache (REST, no WebSocket needed). It answers with a 502
        // on and off; one attempt left the address unknown for a day.
        for ( $attempt = 1; $attempt <= ZapStats::PRIMAL_ATTEMPTS; $attempt++ ) {
            $response = wp_remote_post( 'https://cache.primal.net/api', [
                'timeout' => 5,
                'body'    => wp_json_encode( [ 'user_profile', [ 'pubkey' => $nostr_pubkey ] ] ),
                'headers' => [ 'Content-Type' => 'application/json' ],
            ] );

            if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
                $events = json_decode( wp_remote_retrieve_body( $response ), true );

                if ( is_array( $events ) ) {
                    break;
                }
            }

            if ( $attempt < ZapStats::PRIMAL_ATTEMPTS ) {
                sleep( 1 );
            }
        }

        foreach ( (array) $events as $event ) {
            if ( isset( $event['kind'] ) && 0 === (int) $event['kind'] && ! empty( $event['content'] ) ) {
                $profile = json_decode( $event['content'], true );
                if ( ! empty( $profile['lud16'] ) ) {
                    $lud16 = sanitize_text_field( $profile['lud16'] );
                }
                break;
            }
        }

        if ( $lud16 !== '' ) {
            update_user_meta( $vendor_id, 'sk_zap_lud16', $lud16 );
        }
    }

    /**
     * GET /sk/v1/zaps/qr?data=<bolt11>
     *
     * The zap flow without WebLN shows the invoice as a QR code. That image
     * used to come from the payments module's endpoint, and where that
     * module is off the QR never appeared — the payer sat in front of
     * "QR wird erzeugt…" for good. The renderer itself only needs the QR
     * library, so it is used from here directly.
     */
    public static function register_qr_route(): void {
        register_rest_route( 'sk/v1', '/zaps/qr', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ __CLASS__, 'rest_qr' ],
                'permission_callback' => '__return_true',
                'args'                => [
                    'data' => [ 'required' => true, 'type' => 'string' ],
                ],
            ],
        ] );
    }

    public static function rest_qr( \WP_REST_Request $request ) {
        $ip = function_exists( 'sk_get_client_ip' ) ? sk_get_client_ip() : '';

        if ( function_exists( 'sk_rate_limit' ) && ! sk_rate_limit( 'zap-qr:' . md5( $ip ?: 'unknown' ), 30 ) ) {
            return new \WP_Error( 'qr_rate', 'Zu viele Anfragen.', [ 'status' => 429 ] );
        }

        $data = trim( (string) $request->get_param( 'data' ) );

        if ( strlen( $data ) > 1000 || ! preg_match( '/^ln[a-z0-9]{20,}$/i', $data ) ) {
            return new \WP_Error( 'qr_invalid', 'Nur bolt11-Invoices werden gerendert.', [ 'status' => 400 ] );
        }

        if ( ! class_exists( 'SK\Modules\Payments\QrImage' ) ) {
            $file = dirname( SK_ZAPS_PATH ) . '/sk-payments/includes/QrImage.php';

            if ( file_exists( $file ) ) {
                require_once $file;
            }
        }

        if ( ! class_exists( 'SK\Modules\Payments\QrImage' ) ) {
            return new \WP_Error( 'qr_failed', 'QR-Code konnte nicht erzeugt werden.', [ 'status' => 500 ] );
        }

        $uri = \SK\Modules\Payments\QrImage::bolt11( $data );

        if ( '' === $uri ) {
            return new \WP_Error( 'qr_failed', 'QR-Code konnte nicht erzeugt werden.', [ 'status' => 500 ] );
        }

        return new \WP_REST_Response( [ 'qr' => $uri ], 200 );
    }

    /**
     * Are zaps switched on?
     *
     * Two switches used to be read in two places: the templates looked at
     * whether the module is active, the module itself at its own setting.
     * With the setting off, the feed still rendered a button (class_exists
     * finds the file through the autoloader regardless) while the script
     * behind it was never loaded — a button that did nothing.
     */
    public static function is_enabled(): bool {
        return function_exists( 'sk_ext' )
            && sk_ext()->module->is_active( 'sk_zaps' )
            && sk_get_option( 'sk_zaps_enabled', 'sk_zaps', 'off' ) === 'on';
    }

    /**
     * Render the zap button HTML.
     *
     * The button starts hidden: zapping needs an extension on the viewer's
     * side, and only the browser can tell whether there is one. The script
     * reveals it when window.nostr exists.
     *
     * @param string $variant 'button' for store and product pages, 'feed'
     *                        for an icon among the post actions.
     */
    public static function render_button( array $data, int $post_id = 0, string $variant = 'button' ): void {
        if ( ! self::is_enabled() ) {
            return;
        }

        self::ensure_assets();

        $default_amount = (int) sk_get_option( 'sk_zaps_default_amount', 'sk_zaps', '21' );
        $zap_total      = $post_id ? (int) get_post_meta( $post_id, '_sk_zap_total_sats', true ) : 0;
        $classes        = 'sk-zap-btn' . ( 'feed' === $variant ? ' sk-zap-btn--feed' : '' );
        ?>
        <button type="button"
                class="<?php echo esc_attr( $classes ); ?>"
                hidden
                data-vendor-id="<?php echo esc_attr( $data['vendor_id'] ); ?>"
                data-lightning-address="<?php echo esc_attr( $data['lightning_address'] ); ?>"
                data-nostr-pubkey="<?php echo esc_attr( $data['nostr_pubkey'] ); ?>"
                data-store-name="<?php echo esc_attr( $data['store_name'] ); ?>"
                data-default-amount="<?php echo esc_attr( $default_amount ); ?>"
                <?php if ( $post_id ) : ?>data-post-id="<?php echo esc_attr( $post_id ); ?>"<?php endif; ?>
                title="Zap <?php echo esc_attr( $data['store_name'] ); ?>">
            <i class="fas fa-bolt"></i>
            <?php if ( 'feed' === $variant ) : ?>
                <span class="sk-zap-total"><?php echo $zap_total ? esc_html( number_format( $zap_total, 0, '', '.' ) ) : ''; ?></span>
            <?php else : ?>
                <?php if ( $zap_total ) : ?><span class="sk-zap-total"><?php echo esc_html( number_format( $zap_total, 0, '', '.' ) ); ?></span><?php else : ?>Zap<?php endif; ?>
            <?php endif; ?>
        </button>
        <?php
    }

    /**
     * Script and stylesheet, once, from wherever a button is rendered.
     *
     * Registered at render time rather than on a page-type guess: the guess
     * loaded the assets on every page of one site and on no feed page of the
     * other. Scripts go to the footer and late styles are printed there too,
     * so rendering inside the content is early enough.
     */
    public static function ensure_assets(): void {
        if ( wp_script_is( 'sk-zaps', 'enqueued' ) ) {
            return;
        }

        wp_enqueue_style(
            'sk-zaps',
            SK_ZAPS_URL . '/assets/css/sk-zaps.css',
            [],
            SK_ZAPS_VERSION
        );

        wp_enqueue_script(
            'sk-zaps',
            SK_ZAPS_URL . '/assets/js/sk-zaps.js',
            [ 'jquery' ],
            SK_ZAPS_VERSION,
            true
        );

        $relays_option = get_option( 'nostr_login_relays', "wss://purplepag.es\nwss://relay.nostr.band" );
        $relays        = array_filter( array_map( 'trim', explode( "\n", $relays_option ) ) );

        wp_localize_script( 'sk-zaps', 'skZaps', [
            'ajaxurl'       => admin_url( 'admin-ajax.php' ),
            'defaultAmount' => (int) sk_get_option( 'sk_zaps_default_amount', 'sk_zaps', '21' ),
            // Who is looking: their own buttons get a hint instead of a modal.
            'currentUserId' => get_current_user_id(),
            'currentPubkey' => is_user_logged_in() ? strtolower( (string) get_user_meta( get_current_user_id(), 'nostr_public_key', true ) ) : '',
            'i18nSelfZap'   => __( 'Du kannst dich nicht selbst zappen.', 'sk-core' ),
            'relays'        => array_values( $relays ),
            // QR codes are rendered on our own server, never by a third party.
            'qrUrl'         => rest_url( 'sk/v1/zaps/qr' ),
        ] );
    }
}
