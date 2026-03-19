<?php

namespace SK_Lightning\REST;

use SK_Lightning\LNURL\Resolver;
use SK_Lightning\LNURL\Bolt11Parser;
use SK_Lightning\LNURL\ExchangeRate;
use SK_Lightning\StoreSettings;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

class LightningController extends WP_REST_Controller {

    protected $namespace = 'sk/v1';
    protected $rest_base = 'lightning';

    public function register_routes() {
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/invoice', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'create_invoice' ],
                'permission_callback' => [ $this, 'check_logged_in' ],
                'args'                => [
                    'vendor_id'   => [ 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ],
                    'amount_sats' => [ 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ],
                    'product_id'  => [ 'type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 0 ],
                    'chat_id'     => [ 'type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 0 ],
                ],
            ],
        ] );

        register_rest_route( $this->namespace, '/' . $this->rest_base . '/confirm', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'confirm_payment' ],
                'permission_callback' => [ $this, 'check_logged_in' ],
                'args'                => [
                    'payment_hash' => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
                ],
            ],
        ] );

        register_rest_route( $this->namespace, '/' . $this->rest_base . '/check-payment', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'check_payment' ],
                'permission_callback' => [ $this, 'check_logged_in' ],
                'args'                => [
                    'payment_hash' => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
                ],
            ],
        ] );

        register_rest_route( $this->namespace, '/' . $this->rest_base . '/confirm-delivery', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'confirm_delivery' ],
                'permission_callback' => [ $this, 'check_logged_in' ],
                'args'                => [
                    'payment_hash' => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
                ],
            ],
        ] );

        register_rest_route( $this->namespace, '/' . $this->rest_base . '/verify-preimage', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'verify_preimage' ],
                'permission_callback' => [ $this, 'check_logged_in' ],
                'args'                => [
                    'payment_hash' => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
                    'preimage'     => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
                ],
            ],
        ] );

        register_rest_route( $this->namespace, '/' . $this->rest_base . '/proof/(?P<vendor_id>\d+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_proof' ],
                'permission_callback' => '__return_true',
                'args'                => [
                    'vendor_id' => [ 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ],
                ],
            ],
        ] );

        register_rest_route( $this->namespace, '/' . $this->rest_base . '/rate', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_rate' ],
                'permission_callback' => '__return_true',
            ],
        ] );
    }

    public function check_logged_in() {
        return is_user_logged_in();
    }

    /**
     * POST /sk/v1/lightning/invoice
     */
    public function create_invoice( WP_REST_Request $request ) {
        global $wpdb;

        $vendor_id   = $request->get_param( 'vendor_id' );
        $amount_sats = $request->get_param( 'amount_sats' );
        $product_id  = $request->get_param( 'product_id' );
        $chat_id     = $request->get_param( 'chat_id' );
        $buyer_id    = $request->get_param( 'buyer_id' ) ?: get_current_user_id();

        if ( $amount_sats < 1 ) {
            return new WP_Error( 'invalid_amount', 'Betrag muss mindestens 1 Sat sein.', [ 'status' => 400 ] );
        }

        // Determine payment method: NWC (preferred) or LNURL (fallback).
        $bolt11      = '';
        $verify_url  = '';
        $payment_hash = '';
        $via_nwc     = false;

        $nwc_client = StoreSettings::get_nwc_client( $vendor_id );

        if ( $nwc_client ) {
            // ── NWC: Create invoice directly on vendor's wallet ──
            $product_title = $product_id ? get_the_title( $product_id ) : '';
            $description   = $product_title ? "Zahlung für: {$product_title}" : 'Lightning-Zahlung';

            $nwc_result = $nwc_client->make_invoice( $amount_sats, $description );
            if ( is_wp_error( $nwc_result ) ) {
                // NWC failed — fall through to LNURL.
                $nwc_client = null;
            } else {
                $bolt11       = $nwc_result['pr'];
                $payment_hash = $nwc_result['payment_hash'];
                $via_nwc      = true;

                // If NWC didn't return payment_hash, extract from bolt11.
                if ( empty( $payment_hash ) ) {
                    $payment_hash = Bolt11Parser::get_payment_hash( $bolt11 );
                    if ( is_wp_error( $payment_hash ) ) {
                        return new WP_Error( 'hash_failed', $payment_hash->get_error_message(), [ 'status' => 500 ] );
                    }
                }
            }
        }

        // ── LNDHub: Create invoice via REST API ──
        if ( ! $via_nwc ) {
            $lndhub_client = StoreSettings::get_lndhub_client( $vendor_id );

            if ( $lndhub_client ) {
                $product_title = $product_id ? get_the_title( $product_id ) : '';
                $description   = $product_title ? "Zahlung für: {$product_title}" : 'Lightning-Zahlung';

                $lndhub_result = $lndhub_client->make_invoice( $amount_sats, $description );
                if ( ! is_wp_error( $lndhub_result ) ) {
                    $bolt11       = $lndhub_result['pr'];
                    $payment_hash = $lndhub_result['payment_hash'];
                    $via_nwc      = false; // Track separately below.

                    if ( empty( $payment_hash ) ) {
                        $payment_hash = Bolt11Parser::get_payment_hash( $bolt11 );
                        if ( is_wp_error( $payment_hash ) ) {
                            return new WP_Error( 'hash_failed', $payment_hash->get_error_message(), [ 'status' => 500 ] );
                        }
                    }
                }
                // If LNDHub failed, fall through to LNURL.
            }
        }

        if ( empty( $bolt11 ) ) {
            // ── LNURL Fallback: Resolve + request invoice ──
            $address = StoreSettings::get_lightning_address( $vendor_id );
            if ( empty( $address ) ) {
                return new WP_Error( 'no_lightning', 'Verkäufer hat weder NWC noch Lightning-Adresse.', [ 'status' => 404 ] );
            }

            $lnurl_data = Resolver::resolve( $address );
            if ( is_wp_error( $lnurl_data ) ) {
                return new WP_Error( 'resolve_failed', $lnurl_data->get_error_message(), [ 'status' => 502 ] );
            }

            $amount_msats = $amount_sats * 1000;
            $min = $lnurl_data['minSendable'] ?? 1000;
            $max = $lnurl_data['maxSendable'] ?? 100000000000;

            if ( $amount_msats < $min || $amount_msats > $max ) {
                $min_sats = (int) ceil( $min / 1000 );
                $max_sats = (int) floor( $max / 1000 );
                return new WP_Error(
                    'amount_out_of_range',
                    "Betrag muss zwischen {$min_sats} und {$max_sats} Sats liegen.",
                    [ 'status' => 400 ]
                );
            }

            $invoice_data = Resolver::request_invoice( $lnurl_data['callback'], $amount_msats );
            if ( is_wp_error( $invoice_data ) ) {
                return new WP_Error( 'invoice_failed', $invoice_data->get_error_message(), [ 'status' => 502 ] );
            }

            $bolt11     = $invoice_data['pr'];
            $verify_url = ! empty( $invoice_data['verify'] ) ? esc_url_raw( $invoice_data['verify'] ) : '';

            $payment_hash = Bolt11Parser::get_payment_hash( $bolt11 );
            if ( is_wp_error( $payment_hash ) ) {
                return new WP_Error( 'hash_failed', $payment_hash->get_error_message(), [ 'status' => 500 ] );
            }

            if ( $verify_url && strpos( $verify_url, '{payment_hash}' ) !== false ) {
                $verify_url = str_replace( '{payment_hash}', $payment_hash, $verify_url );
            }
        }

        // Get exchange rate.
        $rate = ExchangeRate::get_btc_eur_rate();
        $exchange_rate = is_wp_error( $rate ) ? null : $rate;

        // Generate QR code as data URI.
        $qr_data_uri = self::generate_qr_data_uri( strtoupper( $bolt11 ) );

        // Store in database.
        $table = $wpdb->prefix . 'sk_lightning_payments';
        $wpdb->insert( $table, [
            'vendor_id'       => $vendor_id,
            'buyer_id'        => $buyer_id,
            'product_id'      => $product_id ?: null,
            'chat_id'         => $chat_id ?: null,
            'amount_sats'     => $amount_sats,
            'payment_hash'    => $payment_hash,
            'payment_request' => $bolt11,
            'status'          => 'pending',
            'context'         => $chat_id ? 'chat' : 'direct',
            'verify_url'      => $verify_url ?: null,
            'exchange_rate'   => $exchange_rate,
            'buyer_ip_hash'   => hash( 'sha256', self::get_client_ip() ),
            'created_at'      => current_time( 'mysql' ),
        ], [
            '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s',
        ] );

        return new WP_REST_Response( [
            'payment_request' => $bolt11,
            'payment_hash'    => $payment_hash,
            'qr_data_uri'     => $qr_data_uri,
            'deeplink'        => 'lightning:' . $bolt11,
            'amount_sats'     => $amount_sats,
            'has_verify'      => $via_nwc || ! empty( $verify_url ),
            'via_nwc'         => $via_nwc,
            'expires_at'      => gmdate( 'Y-m-d\TH:i:s\Z', time() + 600 ),
        ], 200 );
    }

    /**
     * POST /sk/v1/lightning/confirm
     *
     * Manual confirmation — only the vendor can confirm (fallback when no verify-URL).
     */
    public function confirm_payment( WP_REST_Request $request ) {
        global $wpdb;

        $payment_hash = $request->get_param( 'payment_hash' );
        $user_id      = get_current_user_id();
        $table        = $wpdb->prefix . 'sk_lightning_payments';

        $payment = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE payment_hash = %s", $payment_hash )
        );

        if ( ! $payment ) {
            return new WP_Error( 'not_found', 'Zahlung nicht gefunden.', [ 'status' => 404 ] );
        }

        // Only the vendor can manually confirm.
        if ( (int) $payment->vendor_id !== $user_id ) {
            return new WP_Error( 'forbidden', 'Nur der Verkäufer kann die Zahlung bestätigen.', [ 'status' => 403 ] );
        }

        // Atomic update: only update if still pending (prevents race condition).
        // reputation_at = 7 days from now as fallback if buyer doesn't confirm delivery.
        $now    = current_time( 'mysql' );
        $rep_at = wp_date( 'Y-m-d H:i:s', time() + 7 * DAY_IN_SECONDS );

        $updated = $wpdb->query( $wpdb->prepare(
            "UPDATE {$table} SET status = 'confirmed', confirmed_at = %s, reputation_at = %s
             WHERE payment_hash = %s AND status = 'pending'",
            $now,
            $rep_at,
            $payment_hash
        ) );

        if ( ! $updated ) {
            return new WP_Error( 'already_processed', 'Zahlung wurde bereits verarbeitet.', [ 'status' => 400 ] );
        }

        return new WP_REST_Response( [ 'status' => 'confirmed', 'confirmed_by' => 'vendor' ], 200 );
    }

    /**
     * GET /sk/v1/lightning/check-payment
     *
     * Polls the LUD-21 verify URL to check if payment was settled.
     * If settled, auto-confirms the payment. If no verify URL, returns current status.
     */
    public function check_payment( WP_REST_Request $request ) {
        global $wpdb;

        $payment_hash = $request->get_param( 'payment_hash' );
        $table        = $wpdb->prefix . 'sk_lightning_payments';

        $payment = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE payment_hash = %s", $payment_hash )
        );

        if ( ! $payment ) {
            return new WP_Error( 'not_found', 'Zahlung nicht gefunden.', [ 'status' => 404 ] );
        }

        // Already confirmed? Return immediately.
        if ( $payment->status === 'confirmed' ) {
            return new WP_REST_Response( [
                'status'       => 'confirmed',
                'settled'      => true,
                'confirmed_by' => 'previous',
            ], 200 );
        }

        // Not pending anymore? Return status.
        if ( $payment->status !== 'pending' ) {
            return new WP_REST_Response( [
                'status'  => $payment->status,
                'settled' => false,
            ], 200 );
        }

        // Try NWC lookup_invoice first (most reliable).
        $nwc_client = StoreSettings::get_nwc_client( (int) $payment->vendor_id );
        if ( $nwc_client ) {
            $nwc_result = $nwc_client->lookup_invoice( $payment_hash );
            if ( ! is_wp_error( $nwc_result ) && ! empty( $nwc_result['settled'] ) ) {
                return $this->settle_payment( $payment, $nwc_result['preimage'] ?? null, 'nwc' );
            }
            // NWC responded but not settled yet — return pending.
            if ( ! is_wp_error( $nwc_result ) ) {
                return new WP_REST_Response( [
                    'status'     => 'pending',
                    'settled'    => false,
                    'has_verify' => true,
                    'via'        => 'nwc',
                ], 200 );
            }
            // NWC error — fall through to LNDHub / LUD-21.
        }

        // Try LNDHub lookup_invoice.
        $lndhub_client = StoreSettings::get_lndhub_client( (int) $payment->vendor_id );
        if ( $lndhub_client ) {
            $lndhub_result = $lndhub_client->lookup_invoice( $payment_hash );
            if ( ! is_wp_error( $lndhub_result ) && ! empty( $lndhub_result['settled'] ) ) {
                return $this->settle_payment( $payment, $lndhub_result['preimage'] ?? null, 'lndhub' );
            }
            if ( ! is_wp_error( $lndhub_result ) ) {
                return new WP_REST_Response( [
                    'status'     => 'pending',
                    'settled'    => false,
                    'has_verify' => true,
                    'via'        => 'lndhub',
                ], 200 );
            }
        }

        // No verify URL, no NWC, no LNDHub? Return pending + flag for manual confirmation.
        if ( empty( $payment->verify_url ) ) {
            return new WP_REST_Response( [
                'status'     => 'pending',
                'settled'    => false,
                'has_verify' => false,
            ], 200 );
        }

        // Poll the LUD-21 verify URL.
        $response = wp_remote_get( $payment->verify_url, [
            'timeout' => 5,
            'headers' => [ 'Accept' => 'application/json' ],
        ] );

        if ( is_wp_error( $response ) ) {
            return new WP_REST_Response( [
                'status'     => 'pending',
                'settled'    => false,
                'has_verify' => true,
                'verify_error' => $response->get_error_message(),
            ], 200 );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        // LUD-21: { settled: true, preimage: "..." }
        $settled = false;
        $preimage = null;

        if ( ! empty( $body['settled'] ) ) {
            $settled = true;
            $preimage = $body['preimage'] ?? null;
        } elseif ( ! empty( $body['status'] ) && $body['status'] === 'complete' ) {
            // Some services use { status: "complete" }.
            $settled = true;
            $preimage = $body['preimage'] ?? null;
        } elseif ( isset( $body['paid'] ) && $body['paid'] === true ) {
            // Another common format.
            $settled = true;
            $preimage = $body['preimage'] ?? null;
        }

        if ( $settled ) {
            return $this->settle_payment( $payment, $preimage, 'lud21' );
        }

        return new WP_REST_Response( [
            'status'     => 'pending',
            'settled'    => false,
            'has_verify' => true,
        ], 200 );
    }

    /**
     * Helper: Mark a payment as confirmed/settled.
     */
    private function settle_payment( object $payment, ?string $preimage, string $via ): WP_REST_Response {
        global $wpdb;
        $table = $wpdb->prefix . 'sk_lightning_payments';

        $now    = current_time( 'mysql' );
        $rep_at = wp_date( 'Y-m-d H:i:s', time() + 7 * DAY_IN_SECONDS );

        $update_data = [
            'status'        => 'confirmed',
            'confirmed_at'  => $now,
            'reputation_at' => $rep_at,
        ];

        // Verify and store preimage if provided.
        if ( $preimage && preg_match( '/^[0-9a-f]{64}$/i', $preimage ) ) {
            $computed = hash( 'sha256', hex2bin( strtolower( $preimage ) ) );
            if ( $computed === strtolower( $payment->payment_hash ) ) {
                $update_data['preimage']          = strtolower( $preimage );
                $update_data['preimage_verified']  = 1;
            }
        }

        $wpdb->update(
            $table,
            $update_data,
            [ 'payment_hash' => $payment->payment_hash, 'status' => 'pending' ]
        );

        return new WP_REST_Response( [
            'status'            => 'confirmed',
            'settled'           => true,
            'confirmed_by'      => $via,
            'preimage_verified' => ! empty( $update_data['preimage_verified'] ),
        ], 200 );
    }

    /**
     * POST /sk/v1/lightning/confirm-delivery
     *
     * Buyer confirms product received → reputation is credited immediately.
     */
    public function confirm_delivery( WP_REST_Request $request ) {
        global $wpdb;

        $payment_hash = $request->get_param( 'payment_hash' );
        $user_id      = get_current_user_id();
        $table        = $wpdb->prefix . 'sk_lightning_payments';

        $payment = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE payment_hash = %s", $payment_hash )
        );

        if ( ! $payment ) {
            return new WP_Error( 'not_found', 'Zahlung nicht gefunden.', [ 'status' => 404 ] );
        }

        // Only the buyer can confirm delivery.
        if ( (int) $payment->buyer_id !== $user_id ) {
            return new WP_Error( 'forbidden', 'Nur der Käufer kann den Erhalt bestätigen.', [ 'status' => 403 ] );
        }

        if ( $payment->status !== 'confirmed' ) {
            return new WP_Error( 'invalid_status', 'Zahlung muss zuerst vom Verkäufer bestätigt sein.', [ 'status' => 400 ] );
        }

        // Set status to delivered + run reputation validation immediately.
        $now   = current_time( 'mysql' );
        $valid = \SK_Lightning\Reputation\Calculator::is_reputation_valid( $payment );
        $flags = \SK_Lightning\Reputation\Calculator::check_sybil( $payment );

        $wpdb->update(
            $table,
            [
                'status'           => 'delivered',
                'reputation_at'    => $now,
                'reputation_valid' => $valid ? 1 : 0,
                'reputation_flags' => ! empty( $flags ) ? wp_json_encode( $flags ) : null,
            ],
            [ 'payment_hash' => $payment_hash, 'status' => 'confirmed' ]
        );

        // Recalculate vendor reputation score.
        if ( $valid ) {
            \SK_Lightning\Reputation\Calculator::recalculate_vendor( (int) $payment->vendor_id );
        }

        return new WP_REST_Response( [
            'status'           => 'delivered',
            'reputation_valid' => $valid,
            'reputation_flags' => $flags,
        ], 200 );
    }

    /**
     * GET /sk/v1/lightning/rate
     */
    public function get_rate( WP_REST_Request $request ) {
        $currency = strtoupper( $request->get_param( 'currency' ) ?: 'EUR' );
        if ( ! in_array( $currency, [ 'EUR', 'CHF' ], true ) ) {
            $currency = 'EUR';
        }

        $rate = ExchangeRate::get_btc_rate( $currency );

        if ( is_wp_error( $rate ) ) {
            return new WP_Error( 'rate_error', $rate->get_error_message(), [ 'status' => 502 ] );
        }

        return new WP_REST_Response( [
            'currency' => $currency,
            'rate'     => $rate,
        ], 200 );
    }

    /**
     * POST /sk/v1/lightning/verify-preimage
     *
     * Buyer submits preimage → SHA256(preimage) must equal payment_hash.
     */
    public function verify_preimage( WP_REST_Request $request ) {
        global $wpdb;

        $payment_hash = $request->get_param( 'payment_hash' );
        $preimage     = $request->get_param( 'preimage' );
        $user_id      = get_current_user_id();
        $table        = $wpdb->prefix . 'sk_lightning_payments';

        // Validate hex format (64 chars).
        if ( ! preg_match( '/^[0-9a-f]{64}$/i', $preimage ) ) {
            return new WP_Error( 'invalid_preimage', 'Preimage muss 64 Hex-Zeichen sein.', [ 'status' => 400 ] );
        }

        // Cryptographic verification: SHA256(preimage) == payment_hash.
        $computed_hash = hash( 'sha256', hex2bin( $preimage ) );

        if ( $computed_hash !== strtolower( $payment_hash ) ) {
            return new WP_Error( 'preimage_mismatch', 'SHA256(preimage) stimmt nicht mit dem Payment-Hash überein.', [ 'status' => 400 ] );
        }

        // Load payment.
        $payment = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE payment_hash = %s", strtolower( $payment_hash ) )
        );

        if ( ! $payment ) {
            return new WP_Error( 'not_found', 'Zahlung nicht gefunden.', [ 'status' => 404 ] );
        }

        // Only buyer can submit preimage.
        if ( (int) $payment->buyer_id !== $user_id ) {
            return new WP_Error( 'forbidden', 'Nur der Käufer kann das Preimage einreichen.', [ 'status' => 403 ] );
        }

        // Store preimage and mark as verified.
        $wpdb->update(
            $table,
            [
                'preimage'          => strtolower( $preimage ),
                'preimage_verified' => 1,
            ],
            [ 'payment_hash' => strtolower( $payment_hash ) ],
            [ '%s', '%d' ],
            [ '%s' ]
        );

        return new WP_REST_Response( [
            'status'        => 'verified',
            'payment_hash'  => $payment_hash,
            'preimage'      => strtolower( $preimage ),
            'sha256_check'  => $computed_hash === strtolower( $payment_hash ),
        ], 200 );
    }

    /**
     * GET /sk/v1/lightning/proof/{vendor_id}
     *
     * Public endpoint: returns all reputation-valid payment hashes for a vendor.
     * Anyone can independently verify these proofs.
     */
    public function get_proof( WP_REST_Request $request ) {
        global $wpdb;

        $vendor_id = $request->get_param( 'vendor_id' );
        $table     = $wpdb->prefix . 'sk_lightning_payments';
        $rep_table = $wpdb->prefix . 'sk_reputation_scores';

        // Get vendor reputation summary.
        $rep = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$rep_table} WHERE vendor_id = %d", $vendor_id )
        );

        // Get all reputation-valid payments (public data only — no buyer IPs etc.).
        $payments = $wpdb->get_results( $wpdb->prepare(
            "SELECT payment_hash, amount_sats, payment_request, preimage, preimage_verified,
                    created_at, confirmed_at, product_id
             FROM {$table}
             WHERE vendor_id = %d AND reputation_valid = 1
             ORDER BY confirmed_at DESC",
            $vendor_id
        ) );

        $proofs = [];
        foreach ( $payments as $p ) {
            $proof = [
                'payment_hash'      => $p->payment_hash,
                'amount_sats'       => (int) $p->amount_sats,
                'bolt11'            => $p->payment_request,
                'product'           => $p->product_id ? get_the_title( $p->product_id ) : null,
                'created_at'        => $p->created_at,
                'confirmed_at'      => $p->confirmed_at,
                'preimage_verified' => (bool) $p->preimage_verified,
            ];

            if ( $p->preimage_verified && $p->preimage ) {
                $proof['preimage'] = $p->preimage;
            }

            $proofs[] = $proof;
        }

        $vendor      = get_userdata( $vendor_id );
        $vendor_name = $vendor ? $vendor->display_name : '';

        return new WP_REST_Response( [
            'vendor_id'          => $vendor_id,
            'vendor_name'        => $vendor_name,
            'valid_transactions' => $rep ? (int) $rep->valid_transactions : 0,
            'unique_buyers'      => $rep ? (int) $rep->unique_buyers : 0,
            'valid_volume_sats'  => $rep ? (int) $rep->valid_volume_sats : 0,
            'last_calculated_at' => $rep ? $rep->last_calculated_at : null,
            'proofs'             => $proofs,
            'verification_howto' => [
                'description' => 'Jeder Eintrag mit preimage kann verifiziert werden: SHA256(hex2bin(preimage)) muss den payment_hash ergeben.',
                'command'     => 'echo -n "<preimage_hex>" | xxd -r -p | sha256sum',
                'online_tool' => 'https://emn178.github.io/online-tools/sha256.html (Input type: Hex)',
            ],
        ], 200 );
    }

    /**
     * Generate a simple QR code as SVG data URI.
     */
    private static function generate_qr_data_uri( string $data ): string {
        // Use a minimal PHP QR code approach — generate via Google Charts API fallback
        // or inline SVG. For production, consider chillerlan/php-qrcode.
        // Here we use a simple data URI that frontends can use with a JS QR library.
        return 'qr:' . $data;
    }

    private static function get_client_ip(): string {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ( $headers as $header ) {
            if ( ! empty( $_SERVER[ $header ] ) ) {
                $ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
                // Handle comma-separated IPs (X-Forwarded-For).
                if ( strpos( $ip, ',' ) !== false ) {
                    $ip = trim( explode( ',', $ip )[0] );
                }
                return $ip;
            }
        }

        // Fallback: unique value per request so unknown IPs don't collide in Sybil checks.
        return 'unknown-' . wp_generate_password( 16, false );
    }
}
