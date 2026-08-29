<?php

namespace SK\Modules\Payments\REST;

use SK\Modules\Payments\LNURL\Resolver;
use SK\Modules\Payments\LNURL\Bolt11Parser;
use SK\Modules\Payments\LNURL\ExchangeRate;
use SK\Modules\Payments\StoreSettings;
use SK\Modules\Payments\QrImage;
use SK\Modules\Payments\ClientIp;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

class LightningController extends WP_REST_Controller {

    protected $namespace = 'sk/v1';
    protected $rest_base = 'lightning';

    /** Max proofs returned by the public proof endpoint. */
    const PROOF_LIMIT = 100;

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

        register_rest_route( $this->namespace, '/' . $this->rest_base . '/check-onchain', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'check_onchain' ],
                'permission_callback' => [ $this, 'check_logged_in' ],
                'args'                => [
                    'payment_hash' => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
                ],
            ],
        ] );

        register_rest_route( $this->namespace, '/' . $this->rest_base . '/qr', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_qr' ],
                'permission_callback' => '__return_true',
                'args'                => [
                    'data' => [ 'required' => true, 'type' => 'string' ],
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
     * Is the current user one of the two parties of this payment?
     *
     * Payment status is not public: without this check anyone could poll a
     * payment_hash and learn whether and when a stranger's purchase settled.
     */
    private static function user_is_party( object $payment ): bool {
        $user_id = get_current_user_id();

        if ( ! $user_id ) {
            return false;
        }

        return $user_id === (int) $payment->vendor_id || $user_id === (int) $payment->buyer_id;
    }

    /** Upper bound for a single invoice. */
    const MAX_INVOICE_SATS = 21000000;

    /**
     * Each invoice costs the vendor's wallet a round trip, so cap how often one
     * user can ask for one.
     */
    private static function invoice_rate_allows( int $user_id ): bool {
        return sk_rate_limit( 'invoice:' . $user_id, 15 );
    }

    public function create_invoice( WP_REST_Request $request ) {
        global $wpdb;

        $vendor_id   = $request->get_param( 'vendor_id' );
        $amount_sats = $request->get_param( 'amount_sats' );
        $product_id  = $request->get_param( 'product_id' );
        $chat_id     = $request->get_param( 'chat_id' );
        $current_user = get_current_user_id();

        // buyer_id can only be set internally (from ChatIntegration AJAX).
        // External REST calls always use the current user.
        $buyer_id = $request->get_param( 'buyer_id' );
        if ( $buyer_id && (int) $buyer_id !== $current_user ) {
            // Only allow if current user is the vendor (vendor creates invoice for a buyer).
            if ( (int) $vendor_id !== $current_user ) {
                return new WP_Error( 'forbidden', 'buyer_id kann nur vom Vendor gesetzt werden.', [ 'status' => 403 ] );
            }
        }
        $buyer_id = $buyer_id ?: $current_user;

        if ( $amount_sats < 1 ) {
            return new WP_Error( 'invalid_amount', 'Betrag muss mindestens 1 Sat sein.', [ 'status' => 400 ] );
        }

        // Sanity ceiling. Nothing on this marketplace costs a fifth of a
        // bitcoin, and an unbounded amount is only useful for making a wallet
        // choke on the request.
        if ( $amount_sats > self::MAX_INVOICE_SATS ) {
            return new WP_Error( 'invalid_amount', 'Betrag zu hoch.', [ 'status' => 400 ] );
        }

        // The recipient has to be an actual seller, otherwise this is a way to
        // mint invoices against any account that ever configured a wallet.
        if ( ! $vendor_id || ! function_exists( 'sk_is_user_seller' ) || ! sk_is_user_seller( $vendor_id ) ) {
            return new WP_Error( 'invalid_vendor', 'Kein gültiger Anbieter.', [ 'status' => 400 ] );
        }

        // A payment may only reference a listing that belongs to this vendor.
        // The reputation module trusts product_id for its 24h rule, so pointing
        // at somebody else's old listing must not be possible.
        if ( $product_id && (int) get_post_field( 'post_author', $product_id ) !== (int) $vendor_id ) {
            return new WP_Error( 'invalid_product', 'Produkt gehört nicht zu diesem Anbieter.', [ 'status' => 400 ] );
        }

        if ( ! self::invoice_rate_allows( $current_user ) ) {
            return new WP_Error( 'rate_limited', 'Zu viele Anfragen, bitte kurz warten.', [ 'status' => 429 ] );
        }

        $bolt11      = '';
        $verify_url  = '';
        $payment_hash = '';
        $via_nwc     = false;

        $nwc_client = StoreSettings::get_nwc_client( $vendor_id );

        if ( $nwc_client ) {
            $product_title = $product_id ? get_the_title( $product_id ) : '';
            $description   = $product_title ? "Zahlung für: {$product_title}" : 'Lightning-Zahlung';

            $nwc_result = $nwc_client->make_invoice( $amount_sats, $description );
            if ( is_wp_error( $nwc_result ) ) {
                $nwc_client = null;
            } else {
                $bolt11       = $nwc_result['pr'];
                $payment_hash = $nwc_result['payment_hash'];
                $via_nwc      = true;

                if ( empty( $payment_hash ) ) {
                    $payment_hash = Bolt11Parser::get_payment_hash( $bolt11 );
                    if ( is_wp_error( $payment_hash ) ) {
                        return new WP_Error( 'hash_failed', $payment_hash->get_error_message(), [ 'status' => 500 ] );
                    }
                }
            }
        }

        if ( ! $via_nwc ) {
            $lndhub_client = StoreSettings::get_lndhub_client( $vendor_id );

            if ( $lndhub_client ) {
                $product_title = $product_id ? get_the_title( $product_id ) : '';
                $description   = $product_title ? "Zahlung für: {$product_title}" : 'Lightning-Zahlung';

                $lndhub_result = $lndhub_client->make_invoice( $amount_sats, $description );
                if ( ! is_wp_error( $lndhub_result ) ) {
                    $bolt11       = $lndhub_result['pr'];
                    $payment_hash = $lndhub_result['payment_hash'];
                    $via_nwc      = false;

                    if ( empty( $payment_hash ) ) {
                        $payment_hash = Bolt11Parser::get_payment_hash( $bolt11 );
                        if ( is_wp_error( $payment_hash ) ) {
                            return new WP_Error( 'hash_failed', $payment_hash->get_error_message(), [ 'status' => 500 ] );
                        }
                    }
                }
            }
        }

        if ( empty( $bolt11 ) ) {
            $address = StoreSettings::get_lightning_address( $vendor_id );
            if ( empty( $address ) ) {
                return new WP_Error( 'no_lightning', 'Anbieter hat weder NWC noch Lightning-Adresse.', [ 'status' => 404 ] );
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

        // The invoice must ask for exactly the amount we requested. A hostile or
        // broken LNURL backend must not be able to hand the buyer a different
        // amount than the UI shows.
        $invoice_msats = Bolt11Parser::get_amount_msats( $bolt11 );

        if ( is_wp_error( $invoice_msats ) ) {
            return new WP_Error(
                'amount_unverifiable',
                'Invoice-Betrag konnte nicht geprüft werden: ' . $invoice_msats->get_error_message(),
                [ 'status' => 502 ]
            );
        }

        if ( $invoice_msats !== $amount_sats * 1000 ) {
            return new WP_Error(
                'amount_mismatch',
                sprintf(
                    'Invoice lautet über %d Sats statt der angeforderten %d Sats.',
                    (int) round( $invoice_msats / 1000 ),
                    $amount_sats
                ),
                [ 'status' => 502 ]
            );
        }

        $rate = ExchangeRate::get_btc_eur_rate();
        $exchange_rate = is_wp_error( $rate ) ? null : $rate;

        $qr_data_uri = QrImage::bolt11( $bolt11 );

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
            'buyer_ip_hash'   => ClientIp::hash(),
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

        if ( (int) $payment->vendor_id !== $user_id ) {
            return new WP_Error( 'forbidden', 'Nur der Anbieter kann die Zahlung bestätigen.', [ 'status' => 403 ] );
        }

        $now    = current_time( 'mysql' );
        $rep_at = wp_date( 'Y-m-d H:i:s', time() + 7 * DAY_IN_SECONDS );

        // Marked as vendor-asserted: nothing was checked here, so the
        // reputation module must not treat it like a settled invoice.
        $updated = $wpdb->query( $wpdb->prepare(
            "UPDATE {$table} SET status = 'confirmed', confirmed_at = %s, reputation_at = %s,
             confirmed_via = 'vendor'
             WHERE payment_hash = %s AND status = 'pending'",
            $now,
            $rep_at,
            $payment_hash
        ) );

        if ( ! $updated ) {
            return new WP_Error( 'already_processed', 'Zahlung wurde bereits verarbeitet.', [ 'status' => 400 ] );
        }

        do_action( 'sk_payment_confirmed', (string) $payment_hash, 'vendor' );

        return new WP_REST_Response( [ 'status' => 'confirmed', 'confirmed_by' => 'vendor' ], 200 );
    }

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

        if ( ! self::user_is_party( $payment ) ) {
            return new WP_Error( 'forbidden', 'Keine Berechtigung für diese Zahlung.', [ 'status' => 403 ] );
        }

        if ( $payment->status === 'confirmed' ) {
            return new WP_REST_Response( [
                'status'       => 'confirmed',
                'settled'      => true,
                'confirmed_by' => 'previous',
            ], 200 );
        }

        if ( $payment->status !== 'pending' ) {
            return new WP_REST_Response( [
                'status'  => $payment->status,
                'settled' => false,
            ], 200 );
        }

        $nwc_client = StoreSettings::get_nwc_client( (int) $payment->vendor_id );
        if ( $nwc_client ) {
            $nwc_result = $nwc_client->lookup_invoice( $payment_hash );
            if ( ! is_wp_error( $nwc_result ) && ! empty( $nwc_result['settled'] ) ) {
                return $this->settle_payment( $payment, $nwc_result['preimage'] ?? null, 'nwc' );
            }
            if ( ! is_wp_error( $nwc_result ) ) {
                return new WP_REST_Response( [
                    'status'     => 'pending',
                    'settled'    => false,
                    'has_verify' => true,
                    'via'        => 'nwc',
                ], 200 );
            }
        }

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

        if ( empty( $payment->verify_url ) ) {
            return new WP_REST_Response( [
                'status'     => 'pending',
                'settled'    => false,
                'has_verify' => false,
            ], 200 );
        }

        // verify_url comes from the vendor's LNURL server, so it is treated as
        // an untrusted URL: wp_safe_remote_get blocks loopback/private targets.
        $response = wp_safe_remote_get( $payment->verify_url, [
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

        $settled = false;
        $preimage = null;

        if ( ! empty( $body['settled'] ) ) {
            $settled = true;
            $preimage = $body['preimage'] ?? null;
        } elseif ( ! empty( $body['status'] ) && $body['status'] === 'complete' ) {
            $settled = true;
            $preimage = $body['preimage'] ?? null;
        } elseif ( isset( $body['paid'] ) && $body['paid'] === true ) {
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

    private function settle_payment( object $payment, ?string $preimage, string $via ): WP_REST_Response {
        global $wpdb;
        $table = $wpdb->prefix . 'sk_lightning_payments';

        $now    = current_time( 'mysql' );
        $rep_at = wp_date( 'Y-m-d H:i:s', time() + 7 * DAY_IN_SECONDS );

        $update_data = [
            'status'        => 'confirmed',
            'confirmed_at'  => $now,
            'reputation_at' => $rep_at,
            'confirmed_via' => $via,
        ];

        if ( $preimage && preg_match( '/^[0-9a-f]{64}$/i', $preimage ) ) {
            $computed = hash( 'sha256', hex2bin( strtolower( $preimage ) ) );
            if ( $computed === strtolower( $payment->payment_hash ) ) {
                $update_data['preimage']          = strtolower( $preimage );
                $update_data['preimage_verified']  = 1;
            }
        }

        $updated = $wpdb->update(
            $table,
            $update_data,
            [ 'payment_hash' => $payment->payment_hash, 'status' => 'pending' ]
        );

        // If 0 rows affected, another process already settled this payment.
        if ( ! $updated ) {
            return new WP_REST_Response( [
                'status'       => 'confirmed',
                'settled'      => true,
                'confirmed_by' => 'previous',
            ], 200 );
        }

        // Genau hier, nicht frueher: das UPDATE oben greift nur einmal, weil es
        // auf status='pending' bedingt ist. Damit kann die Benachrichtigung
        // nicht doppelt rausgehen, auch wenn zwei Pruefungen gleichzeitig laufen.
        do_action( 'sk_payment_confirmed', (string) $payment->payment_hash, $via );

        return new WP_REST_Response( [
            'status'            => 'confirmed',
            'settled'           => true,
            'confirmed_by'      => $via,
            'preimage_verified' => ! empty( $update_data['preimage_verified'] ),
        ], 200 );
    }

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

        if ( (int) $payment->buyer_id !== $user_id ) {
            return new WP_Error( 'forbidden', 'Nur der Käufer kann den Erhalt bestätigen.', [ 'status' => 403 ] );
        }

        if ( $payment->status !== 'confirmed' ) {
            return new WP_Error( 'invalid_status', 'Zahlung muss zuerst vom Anbieter bestätigt sein.', [ 'status' => 400 ] );
        }

        $now   = current_time( 'mysql' );

        // Use reputation module if available.
        $valid = false;
        $flags = [];
        if ( class_exists( 'SK\Modules\Reputation\Calculator' ) ) {
            $valid = \SK\Modules\Reputation\Calculator::is_reputation_valid( $payment );
            $flags = \SK\Modules\Reputation\Calculator::check_sybil( $payment );
        }

        $wpdb->update(
            $table,
            [
                'status'           => 'delivered',
                'reputation_at'    => $now,
                'reputation_valid' => $valid ? 1 : 0,
                'reputation_flags' => ! empty( $flags ) ? wp_json_encode( $flags ) : null,
                'reputation_state' => $valid ? 'credited' : 'rejected',
            ],
            [ 'payment_hash' => $payment_hash, 'status' => 'confirmed' ]
        );

        if ( $valid && class_exists( 'SK\Modules\Reputation\Calculator' ) ) {
            \SK\Modules\Reputation\Calculator::recalculate_vendor( (int) $payment->vendor_id );
        }

        // Re-fetch payment for commission hook (status is now 'delivered').
        $updated_payment = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE payment_hash = %s", $payment_hash )
        );
        if ( $updated_payment ) {
            do_action( 'sk_payment_delivered', $updated_payment );
        }

        return new WP_REST_Response( [
            'status'           => 'delivered',
            'reputation_valid' => $valid,
            'reputation_flags' => $flags,
        ], 200 );
    }

    /**
     * GET /sk/v1/lightning/qr?data=<bolt11|bitcoin: URI>
     *
     * For flows where the invoice only exists in the browser (zaps). Rendering
     * happens here so no third party learns the invoices or gets to choose the
     * image the payer scans. Only payment payloads are accepted.
     */
    public function get_qr( WP_REST_Request $request ) {
        $data = trim( (string) $request->get_param( 'data' ) );

        if ( strlen( $data ) > 1000 ) {
            return new WP_Error( 'qr_too_long', 'Payload zu lang.', [ 'status' => 400 ] );
        }

        $is_bolt11 = (bool) preg_match( '/^ln[a-z0-9]{20,}$/i', $data );
        $is_bip21  = (bool) preg_match( '/^bitcoin:[a-zA-Z0-9]{20,90}(?:\?[A-Za-z0-9=&.\-_%]*)?$/', $data );

        if ( ! $is_bolt11 && ! $is_bip21 ) {
            return new WP_Error( 'qr_invalid', 'Nur bolt11-Invoices und bitcoin:-URIs werden gerendert.', [ 'status' => 400 ] );
        }

        $uri = $is_bolt11 ? QrImage::bolt11( $data ) : QrImage::data_uri( $data );

        if ( $uri === '' ) {
            return new WP_Error( 'qr_failed', 'QR-Code konnte nicht erzeugt werden.', [ 'status' => 500 ] );
        }

        return new WP_REST_Response( [ 'qr' => $uri ], 200 );
    }

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

    public function verify_preimage( WP_REST_Request $request ) {
        global $wpdb;

        $payment_hash = $request->get_param( 'payment_hash' );
        $preimage     = $request->get_param( 'preimage' );
        $user_id      = get_current_user_id();
        $table        = $wpdb->prefix . 'sk_lightning_payments';

        if ( ! preg_match( '/^[0-9a-f]{64}$/i', $preimage ) ) {
            return new WP_Error( 'invalid_preimage', 'Preimage muss 64 Hex-Zeichen sein.', [ 'status' => 400 ] );
        }

        $computed_hash = hash( 'sha256', hex2bin( $preimage ) );

        if ( $computed_hash !== strtolower( $payment_hash ) ) {
            return new WP_Error( 'preimage_mismatch', 'SHA256(preimage) stimmt nicht mit dem Payment-Hash überein.', [ 'status' => 400 ] );
        }

        $payment = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE payment_hash = %s", strtolower( $payment_hash ) )
        );

        if ( ! $payment ) {
            return new WP_Error( 'not_found', 'Zahlung nicht gefunden.', [ 'status' => 404 ] );
        }

        if ( (int) $payment->buyer_id !== $user_id ) {
            return new WP_Error( 'forbidden', 'Nur der Käufer kann das Preimage einreichen.', [ 'status' => 403 ] );
        }

        /*
         * Das Preimage ist der staerkste Nachweis, den es hier gibt: es
         * entsteht nur, wenn die Zahlung wirklich durchging, und anders als
         * bei NWC, LNDHub oder LUD-21 antwortet dabei kein Server, den der
         * Verkaeufer benannt hat.
         *
         * Frueher wurde es nur in die Zeile geschrieben — die Zahlung blieb
         * "pending", sk_payment_confirmed feuerte nie, und es entstand weder
         * Reputation noch eine Provisionsforderung. Der Beweis lag also da und
         * bewirkte nichts. Jetzt laeuft er durch denselben settle_payment()
         * wie die uebrigen Wege, samt dessen Schutz gegen Doppelbestaetigung.
         */
        if ( (string) $payment->status === 'pending' ) {
            return $this->settle_payment( $payment, $preimage, 'preimage' );
        }

        // Schon bestaetigt: dann fehlt nur noch der Nachweis in der Zeile.
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
            'status'        => 'confirmed',
            'settled'       => true,
            'payment_hash'  => $payment_hash,
            'sha256_check'  => true,
        ], 200 );
    }

    public function get_proof( WP_REST_Request $request ) {
        global $wpdb;

        $vendor_id = $request->get_param( 'vendor_id' );
        $table     = $wpdb->prefix . 'sk_lightning_payments';
        $rep_table = $wpdb->prefix . 'sk_reputation_scores';

        $rep = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$rep_table} WHERE vendor_id = %d", $vendor_id )
        );

        $total = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE vendor_id = %d AND reputation_valid = 1",
            $vendor_id
        ) );

        // This endpoint is public by design (anyone may verify a vendor's
        // reputation), so it stays bounded and omits the raw bolt11: the
        // invoice carries the memo and is not needed to check the preimage.
        $payments = $wpdb->get_results( $wpdb->prepare(
            "SELECT payment_hash, amount_sats, preimage, preimage_verified,
                    created_at, confirmed_at, product_id
             FROM {$table}
             WHERE vendor_id = %d AND reputation_valid = 1
             ORDER BY confirmed_at DESC
             LIMIT %d",
            $vendor_id,
            self::PROOF_LIMIT
        ) );

        $proofs = [];
        foreach ( $payments as $p ) {
            $proof = [
                'payment_hash'      => $p->payment_hash,
                'amount_sats'       => (int) $p->amount_sats,
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
            'proofs_total'       => $total,
            'proofs_shown'       => count( $proofs ),
            'proofs'             => $proofs,
            'verification_howto' => [
                'description' => 'Jeder Eintrag mit preimage kann verifiziert werden: SHA256(hex2bin(preimage)) muss den payment_hash ergeben.',
                'command'     => 'echo -n "<preimage_hex>" | xxd -r -p | sha256sum',
                'online_tool' => 'https://emn178.github.io/online-tools/sha256.html (Input type: Hex)',
            ],
        ], 200 );
    }

    /**
     * GET /sk/v1/lightning/check-onchain
     *
     * Polls blockchain APIs to check if an onchain payment was received.
     * Auto-confirms when 1+ confirmations detected.
     */
    public function check_onchain( WP_REST_Request $request ) {
        global $wpdb;

        $payment_hash = $request->get_param( 'payment_hash' );
        $table        = $wpdb->prefix . 'sk_lightning_payments';

        $payment = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE payment_hash = %s", $payment_hash )
        );

        if ( ! $payment ) {
            return new WP_Error( 'not_found', 'Zahlung nicht gefunden.', [ 'status' => 404 ] );
        }

        if ( ! self::user_is_party( $payment ) ) {
            return new WP_Error( 'forbidden', 'Keine Berechtigung für diese Zahlung.', [ 'status' => 403 ] );
        }

        if ( $payment->context !== 'onchain' ) {
            return new WP_Error( 'not_onchain', 'Keine Onchain-Zahlung.', [ 'status' => 400 ] );
        }

        if ( $payment->status === 'confirmed' || $payment->status === 'delivered' ) {
            return new WP_REST_Response( [
                'status'        => $payment->status,
                'confirmed'     => true,
                'confirmations' => 1,
            ], 200 );
        }

        if ( $payment->status !== 'pending' ) {
            return new WP_REST_Response( [
                'status'    => $payment->status,
                'confirmed' => false,
            ], 200 );
        }

        // verify_url contains the BTC address for onchain payments.
        $address = $payment->verify_url;
        if ( empty( $address ) ) {
            return new WP_REST_Response( [
                'status'    => 'pending',
                'confirmed' => false,
                'error'     => 'Keine Adresse zum Prüfen.',
            ], 200 );
        }

        // Transactions already booked against another payment to this address.
        // Vendors without an xpub reuse one static address, so without this a
        // single transaction would settle every open payment sitting on it.
        $spent_txids = $wpdb->get_col( $wpdb->prepare(
            "SELECT preimage FROM {$table}
             WHERE context = 'onchain' AND verify_url = %s
             AND preimage IS NOT NULL AND preimage != '' AND id != %d",
            $address,
            (int) $payment->id
        ) );

        $check = \SK\Modules\Payments\Onchain\BlockchainChecker::check_payment(
            $address,
            (int) $payment->amount_sats,
            $payment->created_at,
            $spent_txids
        );

        if ( $check['confirmed'] && $check['confirmations'] >= 1 && ! empty( $check['txid'] ) ) {
            $now    = current_time( 'mysql' );
            $rep_at = wp_date( 'Y-m-d H:i:s', time() + 7 * DAY_IN_SECONDS );

            // The txid is claimed in the same statement that settles the row, so
            // two concurrent checks cannot both book the same transaction.
            $claimed = $wpdb->query( $wpdb->prepare(
                "UPDATE {$table} SET status = 'confirmed', confirmed_at = %s, reputation_at = %s,
                 preimage = %s, preimage_verified = 1, confirmed_via = 'onchain'
                 WHERE payment_hash = %s AND status = 'pending'
                 AND NOT EXISTS (
                     SELECT 1 FROM (SELECT id FROM {$table} WHERE context = 'onchain' AND preimage = %s) AS t
                 )",
                $now,
                $rep_at,
                $check['txid'],
                $payment_hash,
                $check['txid']
            ) );

            if ( ! $claimed ) {
                return new WP_REST_Response( [
                    'status'    => $payment->status,
                    'confirmed' => false,
                    'error'     => 'Diese Transaktion ist bereits einer anderen Zahlung zugeordnet.',
                ], 200 );
            }

            do_action( 'sk_payment_confirmed', (string) $payment_hash, 'onchain' );

            return new WP_REST_Response( [
                'status'        => 'confirmed',
                'confirmed'     => true,
                'txid'          => $check['txid'],
                'amount_sats'   => $check['amount_sats'],
                'confirmations' => $check['confirmations'],
            ], 200 );
        }

        // Unconfirmed tx found in mempool.
        if ( ! empty( $check['txid'] ) && ! $check['confirmed'] ) {
            return new WP_REST_Response( [
                'status'        => 'pending',
                'confirmed'     => false,
                'in_mempool'    => true,
                'txid'          => $check['txid'],
                'amount_sats'   => $check['amount_sats'],
                'confirmations' => 0,
            ], 200 );
        }

        return new WP_REST_Response( [
            'status'    => 'pending',
            'confirmed' => false,
        ], 200 );
    }

}
