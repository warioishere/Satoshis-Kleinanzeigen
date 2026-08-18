<?php

namespace SK\Modules\Payments\REST;

use SK\Modules\Payments\StoreSettings;

defined( 'ABSPATH' ) || exit;

/**
 * LNURL-Pay Endpoint — gives every vendor a Lightning Address.
 *
 * Handles:
 *   GET /.well-known/lnurlp/{store_slug}          → LNURL-Pay metadata (LUD-06/LUD-16)
 *   GET /.well-known/lnurlp/{store_slug}?amount=X  → Invoice creation via LNDHub/NWC
 *
 * Enables NIP-57 zaps and direct Lightning payments to vendor wallets
 * using their configured LNDHub or NWC connection.
 */
class LnurlPayEndpoint {

    public function __construct() {
        add_action( 'init', [ $this, 'add_rewrite_rules' ] );
        add_action( 'template_redirect', [ $this, 'handle_request' ] );
        add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
        add_filter( 'redirect_canonical', [ $this, 'prevent_slash_redirect' ], 10, 2 );
    }

    /**
     * Prevent trailing-slash redirect for LNURL-Pay endpoint.
     * Wallets don't follow 301 redirects reliably.
     */
    public function prevent_slash_redirect( $redirect_url, $requested_url ) {
        if ( get_query_var( 'sk_lnurlp_user' ) ) {
            return false;
        }
        return $redirect_url;
    }

    public function add_rewrite_rules() {
        // Stable endpoint using vendor ID (won't break if store name changes).
        add_rewrite_rule(
            '^\.well-known/lnurlp/v/(\d+)/?$',
            'index.php?sk_lnurlp_user=$matches[1]',
            'top'
        );
        // Human-readable alias using store slug (for display/sharing).
        add_rewrite_rule(
            '^\.well-known/lnurlp/([^/]+)/?$',
            'index.php?sk_lnurlp_user=$matches[1]',
            'top'
        );
    }

    public function add_query_vars( $vars ) {
        $vars[] = 'sk_lnurlp_user';
        return $vars;
    }

    public function handle_request() {
        $store_slug = get_query_var( 'sk_lnurlp_user' );
        if ( empty( $store_slug ) ) {
            return;
        }

        // Find vendor by store slug.
        $vendor_id = $this->get_vendor_id_by_slug( $store_slug );
        if ( ! $vendor_id ) {
            $this->send_json_error( 'User not found.' );
        }

        // Only NWC and LNDHub can mint an invoice here. A vendor who merely
        // stored a Lightning address has their own LNURL server and must not be
        // advertised through ours — the callback could never deliver.
        if ( ! StoreSettings::has_nwc( $vendor_id ) && ! StoreSettings::has_lndhub( $vendor_id ) ) {
            $this->send_json_error( 'This user cannot receive Lightning payments.' );
        }

        // If amount is present → create invoice (callback request).
        if ( isset( $_GET['amount'] ) ) {
            $this->handle_callback( $vendor_id, $store_slug );
        } else {
            $this->handle_metadata( $vendor_id, $store_slug );
        }
    }

    /**
     * Step 1: Return LNURL-Pay metadata (LUD-06).
     */
    private function handle_metadata( int $vendor_id, string $store_slug ) {
        $nostr_pubkey = get_user_meta( $vendor_id, 'nostr_public_key', true );

        // commentAllowed is deliberately absent: comments were advertised but
        // never passed on to the invoice.
        $response = [
            'callback'    => home_url( '/.well-known/lnurlp/v/' . $vendor_id ),
            'maxSendable' => 100000000000, // 100M msats = 100k sats
            'minSendable' => 1000,         // 1 sat
            'metadata'    => self::build_metadata( $vendor_id, $store_slug ),
            'tag'         => 'payRequest',
        ];

        // NIP-57: advertise Nostr support if vendor has pubkey.
        if ( ! empty( $nostr_pubkey ) ) {
            $response['allowsNostr'] = true;
            $response['nostrPubkey'] = $nostr_pubkey;
        }

        $this->send_json( $response );
    }

    /**
     * Step 2: Create invoice and return it (LNURL-Pay callback).
     */
    private function handle_callback( int $vendor_id, string $store_slug ) {
        $amount_msats = absint( $_GET['amount'] );
        $amount_sats  = (int) floor( $amount_msats / 1000 );

        if ( $amount_sats < 1 ) {
            $this->send_json_error( 'Amount too low.' );
        }

        if ( $amount_sats > 100000 ) {
            $this->send_json_error( 'Amount too high.' );
        }

        // Every call here reaches into the vendor's wallet to mint an invoice.
        // The endpoint has to stay open — LNURL wallets are anonymous — so it
        // is bounded instead, per caller and per vendor.
        if ( ! $this->rate_allows( $vendor_id ) ) {
            $this->send_json_error( 'Too many requests, please try again shortly.' );
        }

        // LUD-06: the invoice must commit to exactly the metadata advertised in
        // step 1, via description_hash. The old code built this string and then
        // used a free-text memo instead, which strict wallets reject.
        $metadata          = self::build_metadata( $vendor_id, $store_slug );
        $description_hash  = hash( 'sha256', $metadata );

        // Handle NIP-57 zap request if present.
        $nostr_zap_request = isset( $_GET['nostr'] ) ? sanitize_text_field( wp_unslash( $_GET['nostr'] ) ) : '';

        // Create invoice via vendor's payment method (NWC → LNDHub → Lightning Address).
        $invoice      = null;
        $payment_hash = '';
        $verifiable   = false;

        $nwc_client = StoreSettings::get_nwc_client( $vendor_id );
        if ( $nwc_client ) {
            $result = $nwc_client->make_invoice( $amount_sats, '', $description_hash );
            if ( ! is_wp_error( $result ) && ! empty( $result['pr'] ) ) {
                $invoice      = $result['pr'];
                $payment_hash = $result['payment_hash'] ?? '';
                $verifiable   = true;
            }
        }

        if ( ! $invoice ) {
            $lndhub_client = StoreSettings::get_lndhub_client( $vendor_id );
            if ( $lndhub_client ) {
                // LNDHub cannot set a description_hash, so the metadata itself
                // goes in as the memo — the closest a wallet can still verify.
                $result = $lndhub_client->make_invoice( $amount_sats, $metadata );
                if ( ! is_wp_error( $result ) && ! empty( $result['pr'] ) ) {
                    $invoice      = $result['pr'];
                    $payment_hash = $result['payment_hash'] ?? '';
                    $verifiable   = true;
                }
            }
        }

        if ( ! $invoice ) {
            $this->send_json_error( 'Could not create invoice.' );
        }

        $response = [
            'pr'     => $invoice,
            'routes' => [],
        ];

        // Include payment_hash + vendor_id so clients can poll for settlement.
        if ( $verifiable && $payment_hash ) {
            $response['payment_hash'] = $payment_hash;
            $response['verify']       = home_url( '/wp-admin/admin-ajax.php?action=sk_zap_check_payment' );

            // Store zap request for Kind 9735 receipt publishing on settlement.
            if ( ! empty( $nostr_zap_request ) ) {
                set_transient( 'sk_zap_req_' . $payment_hash, $nostr_zap_request, 600 );
            }
        }

        $this->send_json( $response );
    }

    /**
     * The LNURL metadata. Step 1 advertises it, step 2 commits the invoice to
     * it — they have to be byte-identical, so both call this.
     */
    private static function build_metadata( int $vendor_id, string $store_slug ): string {
        $store_info = function_exists( 'sk_get_store_info' ) ? sk_get_store_info( $vendor_id ) : [];
        $store_name = ! empty( $store_info['store_name'] ) ? $store_info['store_name'] : $store_slug;
        $domain     = wp_parse_url( home_url(), PHP_URL_HOST );

        return wp_json_encode( [
            [ 'text/identifier', $store_slug . '@' . $domain ],
            [ 'text/plain', 'Zap to ' . $store_name ],
        ] );
    }

    /**
     * Bounded invoice creation: 10 per caller per minute, 60 per vendor.
     */
    private function rate_allows( int $vendor_id ): bool {
        $ip     = function_exists( 'sk_get_client_ip' ) ? sk_get_client_ip() : '';
        $ip_key = 'sk_lnurlp_ip_' . md5( $ip !== '' ? $ip : 'unknown' );
        $by_ip  = (int) get_transient( $ip_key );

        if ( $by_ip >= 10 ) {
            return false;
        }

        $vendor_key = 'sk_lnurlp_v_' . $vendor_id;
        $by_vendor  = (int) get_transient( $vendor_key );

        if ( $by_vendor >= 60 ) {
            return false;
        }

        set_transient( $ip_key, $by_ip + 1, MINUTE_IN_SECONDS );
        set_transient( $vendor_key, $by_vendor + 1, MINUTE_IN_SECONDS );

        return true;
    }

    /**
     * Find vendor ID by numeric ID or store slug.
     */
    private function get_vendor_id_by_slug( string $identifier ): int {
        // Numeric → direct user ID lookup.
        if ( ctype_digit( $identifier ) ) {
            $user = get_user_by( 'ID', (int) $identifier );
            return $user ? (int) $user->ID : 0;
        }

        // String → lookup by user_nicename (store slug).
        $user = get_user_by( 'slug', sanitize_title( $identifier ) );
        return $user ? (int) $user->ID : 0;
    }

    private function send_json( array $data ) {
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Access-Control-Allow-Origin: *' );
        echo wp_json_encode( $data );
        exit;
    }

    private function send_json_error( string $reason ) {
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Access-Control-Allow-Origin: *' );
        echo wp_json_encode( [ 'status' => 'ERROR', 'reason' => $reason ] );
        exit;
    }
}
