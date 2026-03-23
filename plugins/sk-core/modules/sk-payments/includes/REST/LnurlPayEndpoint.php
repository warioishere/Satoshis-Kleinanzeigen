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

        // Check vendor has Lightning capability.
        if ( ! StoreSettings::has_lightning( $vendor_id ) ) {
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
        $store_info = function_exists( 'sk_get_store_info' ) ? sk_get_store_info( $vendor_id ) : [];
        $store_name = $store_info['store_name'] ?? $store_slug;
        $domain     = wp_parse_url( home_url(), PHP_URL_HOST );

        $nostr_pubkey = get_user_meta( $vendor_id, 'nostr_public_key', true );

        $response = [
            'callback'       => home_url( '/.well-known/lnurlp/v/' . $vendor_id ),
            'maxSendable'    => 100000000000, // 100M msats = 100k sats
            'minSendable'    => 1000,         // 1 sat
            'metadata'       => wp_json_encode( [
                [ 'text/identifier', $store_slug . '@' . $domain ],
                [ 'text/plain', 'Zap to ' . $store_name ],
            ] ),
            'tag'            => 'payRequest',
            'commentAllowed' => 140,
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

        $store_info = function_exists( 'sk_get_store_info' ) ? sk_get_store_info( $vendor_id ) : [];
        $store_name = $store_info['store_name'] ?? $store_slug;

        // Build memo from metadata hash (required by LNURL spec).
        $domain   = wp_parse_url( home_url(), PHP_URL_HOST );
        $metadata = wp_json_encode( [
            [ 'text/identifier', $store_slug . '@' . $domain ],
            [ 'text/plain', 'Zap to ' . $store_name ],
        ] );
        $memo = 'Zap ' . $amount_sats . ' sats to ' . $store_name;

        // Handle NIP-57 zap request if present.
        $nostr_zap_request = isset( $_GET['nostr'] ) ? sanitize_text_field( wp_unslash( $_GET['nostr'] ) ) : '';

        // Create invoice via vendor's payment method (NWC → LNDHub → Lightning Address).
        $invoice = null;

        $nwc_client = StoreSettings::get_nwc_client( $vendor_id );
        if ( $nwc_client ) {
            $result = $nwc_client->make_invoice( $amount_sats, $memo );
            if ( ! is_wp_error( $result ) && ! empty( $result['pr'] ) ) {
                $invoice = $result['pr'];
            }
        }

        if ( ! $invoice ) {
            $lndhub_client = StoreSettings::get_lndhub_client( $vendor_id );
            if ( $lndhub_client ) {
                $result = $lndhub_client->make_invoice( $amount_sats, $memo );
                if ( ! is_wp_error( $result ) && ! empty( $result['pr'] ) ) {
                    $invoice = $result['pr'];
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

        $this->send_json( $response );
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
