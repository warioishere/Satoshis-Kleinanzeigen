<?php

namespace SK\Modules\Zaps;

defined( 'ABSPATH' ) || exit;

/**
 * Renders Zap buttons on store and product pages.
 * Enqueues the JS that handles NIP-57 zapping via Nostr extension.
 */
class ZapButton {

    /** Wallet lookups a single IP may trigger per minute on the public verify endpoint. */
    const MAX_LOOKUPS_PER_MINUTE = 60;

    public function __construct() {
        // Store page — next to follow button in tab bar.
        if ( sk_get_option( 'sk_zaps_on_store', 'sk_zaps', 'on' ) === 'on' ) {
            add_action( 'sk_after_store_tabs', [ $this, 'render_store_button' ], 98, 1 );
        }

        // Product page.
        if ( sk_get_option( 'sk_zaps_on_product', 'sk_zaps', 'on' ) === 'on' ) {
            add_action( 'woocommerce_single_product_summary', [ $this, 'render_product_button' ], 35 );
        }

        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_sk_zap_check_payment', [ __CLASS__, 'ajax_check_payment' ] );
        add_action( 'wp_ajax_nopriv_sk_zap_check_payment', [ __CLASS__, 'ajax_check_payment' ] );
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
    public static function get_vendor_zap_data( int $vendor_id ): ?array {
        $settings = get_user_meta( $vendor_id, 'sk_profile_settings', true );
        if ( ! is_array( $settings ) ) {
            return null;
        }

        $lightning_address = $settings['lightning_address'] ?? '';
        $nostr_pubkey     = get_user_meta( $vendor_id, 'nostr_public_key', true );

        // Fallback: generate Lightning Address from our LNURL-Pay endpoint
        // if vendor has LNDHub/NWC but no explicit Lightning Address.
        if ( empty( $lightning_address ) && class_exists( 'SK\Modules\Payments\StoreSettings' ) ) {
            if ( \SK\Modules\Payments\StoreSettings::has_lightning( $vendor_id ) ) {
                $user = get_user_by( 'ID', $vendor_id );
                $domain = wp_parse_url( home_url(), PHP_URL_HOST );
                $lightning_address = 'v/' . $vendor_id . '@' . $domain;
            }
        }

        // If still no address but has Nostr pubkey, try fetching lud16 from relay (cached 24h).
        if ( empty( $lightning_address ) && ! empty( $nostr_pubkey ) ) {
            $lightning_address = self::get_cached_lud16( $vendor_id, $nostr_pubkey );
            if ( ! empty( $lightning_address ) ) {
                // Persist it so we don't need relay fetch next time.
                if ( ! is_array( $settings ) ) {
                    $settings = [];
                }
                $settings['lightning_address'] = $lightning_address;
                update_user_meta( $vendor_id, 'sk_profile_settings', $settings );
            }
        }

        // No way to receive payment → no zap button.
        if ( empty( $lightning_address ) ) {
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
        $privkey = null;
        if ( defined( 'NAP_NOSTR_PRIVKEY' ) ) {
            $privkey = NAP_NOSTR_PRIVKEY;
        } elseif ( function_exists( 'nap_resolve_private_key' ) ) {
            $privkey = nap_resolve_private_key();
        }

        if ( ! $privkey ) {
            return;
        }

        try {
            $event = new \swentel\nostr\Event\Event();
            $event->setKind( 9735 );
            $event->setContent( '' );
            foreach ( $tags as $tag ) {
                $event->addTag( $tag );
            }

            $signer = new \swentel\nostr\Sign\Sign();
            $signer->signEvent( $event, $privkey );

            $relays = \SK\Modules\Auth\NostrIdentity::get_relays();
            foreach ( $relays as $relay_url ) {
                try {
                    $msg   = new \swentel\nostr\Message\EventMessage( $event );
                    $relay = new \swentel\nostr\Relay\Relay( $relay_url );
                    if ( method_exists( $relay, 'setTimeout' ) ) {
                        $relay->setTimeout( 3 );
                    }
                    $relay->setMessage( $msg );
                    $relay->send();
                } catch ( \Throwable $e ) {}
            }
        } catch ( \Throwable $e ) {
            error_log( '[SK Zaps] Failed to publish zap receipt: ' . $e->getMessage() );
        }
    }

    /**
     * Fetch lud16 from Nostr profile via relay HTTP API, cached 24h.
     */
    private static function get_cached_lud16( int $vendor_id, string $nostr_pubkey ): string {
        $cache_key = 'sk_lud16_' . $vendor_id;
        $cached    = get_transient( $cache_key );

        if ( false !== $cached ) {
            return $cached; // may be empty string (= checked, no lud16)
        }

        $lud16 = '';

        // Try Primal Cache API (REST, no WebSocket needed).
        $response = wp_remote_post( 'https://cache.primal.net/api', [
            'timeout' => 5,
            'body'    => wp_json_encode( [ 'user_profile', [ 'pubkey' => $nostr_pubkey ] ] ),
            'headers' => [ 'Content-Type' => 'application/json' ],
        ] );

        if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
            $body   = wp_remote_retrieve_body( $response );
            $events = json_decode( $body, true );

            if ( is_array( $events ) ) {
                foreach ( $events as $event ) {
                    if ( isset( $event['kind'] ) && 0 === (int) $event['kind'] && ! empty( $event['content'] ) ) {
                        $profile = json_decode( $event['content'], true );
                        if ( ! empty( $profile['lud16'] ) ) {
                            $lud16 = sanitize_text_field( $profile['lud16'] );
                        }
                        break;
                    }
                }
            }
        }

        // Cache result for 24 hours (even empty = "no lud16 found").
        set_transient( $cache_key, $lud16, DAY_IN_SECONDS );

        return $lud16;
    }

    /**
     * Render the zap button HTML.
     */
    public static function render_button( array $data, int $post_id = 0 ): void {
        $default_amount = (int) sk_get_option( 'sk_zaps_default_amount', 'sk_zaps', '21' );
        $zap_total      = $post_id ? (int) get_post_meta( $post_id, '_sk_zap_total_sats', true ) : 0;
        ?>
        <button type="button"
                class="sk-zap-btn"
                data-vendor-id="<?php echo esc_attr( $data['vendor_id'] ); ?>"
                data-lightning-address="<?php echo esc_attr( $data['lightning_address'] ); ?>"
                data-nostr-pubkey="<?php echo esc_attr( $data['nostr_pubkey'] ); ?>"
                data-store-name="<?php echo esc_attr( $data['store_name'] ); ?>"
                data-default-amount="<?php echo esc_attr( $default_amount ); ?>"
                <?php if ( $post_id ) : ?>data-post-id="<?php echo esc_attr( $post_id ); ?>"<?php endif; ?>
                title="Zap <?php echo esc_attr( $data['store_name'] ); ?>">
            &#9889; <?php if ( $zap_total ) : ?><span class="sk-zap-total"><?php echo esc_html( number_format( $zap_total, 0, '', '.' ) ); ?></span><?php else : ?>Zap<?php endif; ?>
        </button>
        <?php
    }

    /**
     * Enqueue zap JS + CSS on relevant pages.
     */
    public function enqueue_assets(): void {
        // Load on product pages, store pages, and feed/community pages.
        $is_feed = is_singular() && get_post_type() === 'page' && has_shortcode( get_post()->post_content ?? '', 'sk_feed' );
        if ( ! is_product() && ! function_exists( 'sk_is_store_page' ) && ! $is_feed ) {
            return;
        }

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
            'defaultAmount' => (int) sk_get_option( 'sk_zaps_default_amount', 'sk_zaps', '21' ),
            'relays'        => array_values( $relays ),
            // QR codes are rendered on our own server, never by a third party.
            'qrUrl'         => rest_url( 'sk/v1/lightning/qr' ),
        ] );

        // Inline CSS.
        wp_add_inline_style( 'sk-theme', '
            .sk-zap-btn {
                background: none;
                border: 1px solid rgba(247,147,26,0.3);
                color: #f7931a;
                padding: 6px 14px;
                border-radius: 6px;
                cursor: pointer;
                font-size: 14px;
                font-weight: 600;
                transition: all 0.2s;
            }
            .sk-zap-btn:hover {
                background: rgba(247,147,26,0.1);
                border-color: #f7931a;
            }
            .sk-zap-modal {
                position: fixed; inset: 0; background: rgba(0,0,0,0.7);
                z-index: 99999; display: flex; align-items: center; justify-content: center;
            }
            .sk-zap-modal-inner {
                background: #1a2332; border: 1px solid rgba(255,255,255,0.1);
                border-radius: 12px; padding: 24px; max-width: 360px; width: 90%;
            }
            .sk-zap-amounts { display: flex; gap: 6px; margin: 12px 0; flex-wrap: wrap; }
            .sk-zap-amount-btn {
                background: rgba(247,147,26,0.1); border: 1px solid rgba(247,147,26,0.3);
                color: #f7931a; padding: 8px 14px; border-radius: 6px; cursor: pointer;
                font-size: 14px; font-weight: 600;
            }
            .sk-zap-amount-btn:hover, .sk-zap-amount-btn.active {
                background: #f7931a; color: #fff;
            }
            .sk-zap-custom { width: 100%; margin: 8px 0; }
            .sk-zap-custom input {
                width: 100%; background: #0f1923; border: 1px solid rgba(255,255,255,0.08);
                color: #e8ecf0; padding: 8px 12px; border-radius: 6px; font-size: 14px;
            }
            .sk-zap-send {
                width: 100%; background: #f7931a; color: #fff; border: none;
                padding: 10px; border-radius: 6px; font-size: 15px; font-weight: 600;
                cursor: pointer; margin-top: 8px;
            }
            .sk-zap-send:hover { background: #e8850f; }
            .sk-zap-send:disabled { opacity: 0.7; cursor: wait; }
            .sk-zap-close {
                width: 100%; background: none; border: 1px solid rgba(255,255,255,0.1);
                color: #5a6a7e; padding: 8px; border-radius: 6px; cursor: pointer;
                font-size: 13px; margin-top: 6px;
            }
            .sk-zap-status { text-align: center; padding: 8px; font-size: 13px; color: #5a6a7e; }
        ' );
    }
}
