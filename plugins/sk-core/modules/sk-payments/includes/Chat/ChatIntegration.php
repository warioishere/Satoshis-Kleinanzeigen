<?php

namespace SK\Modules\Payments\Chat;

use SK\Modules\Payments\StoreSettings;
use SK\Modules\Payments\LNURL\ExchangeRate;

defined( 'ABSPATH' ) || exit;

class ChatIntegration {

    public function __construct() {
        // Button wird von ProductPage gerendert — hier nur Chat-spezifische AJAX + Assets.
        add_action( 'wp_ajax_sk_create_purchase_request', [ $this, 'ajax_create_purchase_request' ] );
        add_action( 'wp_ajax_sk_create_lightning_invoice', [ $this, 'ajax_create_lightning_invoice' ] );
        add_action( 'wp_ajax_sk_confirm_payment', [ $this, 'ajax_confirm_payment' ] );
        add_action( 'wp_ajax_sk_report_problem', [ $this, 'ajax_report_problem' ] );
        add_action( 'wp_ajax_sk_confirm_delivery', [ $this, 'ajax_confirm_delivery' ] );
        add_action( 'wp_ajax_sk_payment_confirmed_message', [ $this, 'ajax_payment_confirmed_message' ] );

        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function enqueue_assets() {
        if ( ! is_user_logged_in() ) {
            return;
        }

        wp_enqueue_style(
            'sk-lightning-css',
            SK_PAYMENTS_ASSETS . '/css/sk-lightning.css',
            [],
            SK_PAYMENTS_VERSION
        );

        wp_enqueue_script(
            'sk-lightning-pay',
            SK_PAYMENTS_ASSETS . '/js/sk-lightning-pay.js',
            [ 'jquery' ],
            SK_PAYMENTS_VERSION,
            true
        );

        wp_localize_script( 'sk-lightning-pay', 'skLightning', [
            'ajaxurl'   => admin_url( 'admin-ajax.php' ),
            'resturl'   => rest_url( 'sk/v1/lightning/' ),
            'nonce'     => wp_create_nonce( 'sk_lightning_nonce' ),
            'restNonce' => wp_create_nonce( 'wp_rest' ),
            'userId'    => get_current_user_id(),
        ] );
    }

    public function ajax_create_purchase_request() {
        check_ajax_referer( 'sk_lightning_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'Nicht eingeloggt.' ] );
        }

        $vendor_id     = absint( $_POST['vendor_id'] ?? 0 );
        $product_id    = absint( $_POST['product_id'] ?? 0 );
        $product_title = sanitize_text_field( wp_unslash( $_POST['product_title'] ?? '' ) );
        $price_fiat    = floatval( $_POST['price_fiat'] ?? 0 );
        $currency      = sanitize_text_field( wp_unslash( $_POST['currency'] ?? 'EUR' ) );
        $price_sats    = absint( $_POST['price_sats'] ?? 0 );
        $buyer_id      = get_current_user_id();

        if ( ! in_array( strtoupper( $currency ), [ 'EUR', 'CHF' ], true ) ) {
            $currency = 'EUR';
        }
        $currency = strtoupper( $currency );

        if ( ! $vendor_id || ! $product_id ) {
            wp_send_json_error( [ 'message' => 'Fehlende Parameter.' ] );
        }

        if ( $buyer_id === $vendor_id ) {
            wp_send_json_error( [ 'message' => 'Du kannst nicht bei dir selbst kaufen.' ] );
        }

        if ( $price_fiat > 0 ) {
            $fresh_sats = ExchangeRate::fiat_to_sats( $price_fiat, $currency );
            if ( ! is_wp_error( $fresh_sats ) ) {
                $price_sats = $fresh_sats;
            }
        }

        $message_data = wp_json_encode( [
            'type'          => 'purchase_request',
            'product_id'    => $product_id,
            'product_title' => $product_title,
            'price_fiat'    => $price_fiat,
            'currency'      => $currency,
            'price_sats'    => $price_sats,
        ] );

        $message_text = "[lightning_purchase_request]{$message_data}[/lightning_purchase_request]";

        $chat_id = $this->find_or_create_chat( $buyer_id, $vendor_id, $product_id, $product_title );

        if ( is_wp_error( $chat_id ) ) {
            wp_send_json_error( [ 'message' => $chat_id->get_error_message() ] );
        }

        $this->add_chat_message( $chat_id, $buyer_id, $message_text );

        $dashboard_url = sk_get_navigation_url( 'vendor-chat' );
        $chat_url = add_query_arg( 'chat_id', $chat_id, $dashboard_url );

        wp_send_json_success( [
            'message'  => 'Kaufanfrage gesendet.',
            'chat_id'  => $chat_id,
            'chat_url' => $chat_url,
        ] );
    }

    public function ajax_create_lightning_invoice() {
        check_ajax_referer( 'sk_lightning_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'Nicht eingeloggt.' ] );
        }

        $chat_id     = absint( $_POST['chat_id'] ?? 0 );
        $amount_sats = absint( $_POST['amount_sats'] ?? 0 );
        $product_id  = absint( $_POST['product_id'] ?? 0 );
        $vendor_id   = get_current_user_id();

        if ( ! $chat_id || ! $amount_sats ) {
            wp_send_json_error( [ 'message' => 'Fehlende Parameter.' ] );
        }

        if ( ! $this->is_chat_participant( $chat_id, $vendor_id ) ) {
            wp_send_json_error( [ 'message' => 'Keine Berechtigung für diesen Chat.' ] );
        }

        $buyer_id = $this->get_other_participant( $chat_id, $vendor_id );

        if ( ! $buyer_id ) {
            wp_send_json_error( [ 'message' => 'Kein Käufer im Chat gefunden.' ] );
        }

        $request = new \WP_REST_Request( 'POST', '/sk/v1/lightning/invoice' );
        $request->set_param( 'vendor_id', $vendor_id );
        $request->set_param( 'buyer_id', $buyer_id );
        $request->set_param( 'amount_sats', $amount_sats );
        $request->set_param( 'product_id', $product_id );
        $request->set_param( 'chat_id', $chat_id );

        $controller = new \SK\Modules\Payments\REST\LightningController();
        $response = $controller->create_invoice( $request );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [ 'message' => $response->get_error_message() ] );
        }

        $data = $response->get_data();

        $message_data = wp_json_encode( [
            'type'            => 'lightning_invoice',
            'payment_request' => $data['payment_request'],
            'payment_hash'    => $data['payment_hash'],
            'amount_sats'     => $amount_sats,
            'qr_data_uri'     => $data['qr_data_uri'],
            'deeplink'        => $data['deeplink'],
            'expires_at'      => $data['expires_at'],
        ] );

        $message_text = "[lightning_invoice]{$message_data}[/lightning_invoice]";
        $this->add_chat_message( $chat_id, $vendor_id, $message_text );

        global $wpdb;
        $messages = get_post_meta( $chat_id, '_dvc_messages', true );
        $msg_idx  = is_array( $messages ) ? count( $messages ) - 1 : 0;
        $wpdb->update(
            $wpdb->prefix . 'sk_lightning_payments',
            [ 'chat_message_idx' => $msg_idx ],
            [ 'payment_hash' => $data['payment_hash'] ],
            [ '%d' ],
            [ '%s' ]
        );

        wp_send_json_success( $data );
    }

    public function ajax_confirm_payment() {
        check_ajax_referer( 'sk_lightning_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'Nicht eingeloggt.' ] );
        }

        $payment_hash = sanitize_text_field( wp_unslash( $_POST['payment_hash'] ?? '' ) );
        $chat_id      = absint( $_POST['chat_id'] ?? 0 );

        if ( empty( $payment_hash ) ) {
            wp_send_json_error( [ 'message' => 'Payment-Hash fehlt.' ] );
        }

        $request = new \WP_REST_Request( 'POST', '/sk/v1/lightning/confirm' );
        $request->set_param( 'payment_hash', $payment_hash );

        $controller = new \SK\Modules\Payments\REST\LightningController();
        $response = $controller->confirm_payment( $request );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [ 'message' => $response->get_error_message() ] );
        }

        $this->add_payment_confirmed_message( $payment_hash, $chat_id );

        wp_send_json_success( [ 'status' => 'confirmed' ] );
    }

    public function ajax_payment_confirmed_message() {
        check_ajax_referer( 'sk_lightning_nonce', 'nonce' );

        $payment_hash = sanitize_text_field( wp_unslash( $_POST['payment_hash'] ?? '' ) );
        $chat_id      = absint( $_POST['chat_id'] ?? 0 );

        if ( empty( $payment_hash ) || ! $chat_id ) {
            wp_send_json_error( [ 'message' => 'Fehlende Parameter.' ] );
        }

        $this->add_payment_confirmed_message( $payment_hash, $chat_id );
        wp_send_json_success();
    }

    private function add_payment_confirmed_message( string $payment_hash, int $chat_id ) {
        if ( ! $chat_id ) {
            return;
        }

        $messages = get_post_meta( $chat_id, '_dvc_messages', true );
        $messages = is_array( $messages ) ? $messages : [];
        foreach ( $messages as $msg ) {
            if ( strpos( $msg['message'] ?? '', $payment_hash ) !== false
                 && strpos( $msg['message'] ?? '', 'lightning_payment_confirmed' ) !== false ) {
                return;
            }
        }

        global $wpdb;
        $payment = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT amount_sats, vendor_id FROM {$wpdb->prefix}sk_lightning_payments WHERE payment_hash = %s",
                $payment_hash
            )
        );

        $message_data = wp_json_encode( [
            'type'         => 'payment_confirmed',
            'payment_hash' => $payment_hash,
            'amount_sats'  => $payment ? $payment->amount_sats : 0,
        ] );

        $message_text = "[lightning_payment_confirmed]{$message_data}[/lightning_payment_confirmed]";

        $sender = $payment ? (int) $payment->vendor_id : get_current_user_id();
        $this->add_chat_message( $chat_id, $sender, $message_text );
    }

    public function ajax_confirm_delivery() {
        check_ajax_referer( 'sk_lightning_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'Nicht eingeloggt.' ] );
        }

        $payment_hash = sanitize_text_field( wp_unslash( $_POST['payment_hash'] ?? '' ) );

        if ( empty( $payment_hash ) ) {
            wp_send_json_error( [ 'message' => 'Payment-Hash fehlt.' ] );
        }

        $request = new \WP_REST_Request( 'POST', '/sk/v1/lightning/confirm-delivery' );
        $request->set_param( 'payment_hash', $payment_hash );

        $controller = new \SK\Modules\Payments\REST\LightningController();
        $response = $controller->confirm_delivery( $request );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [ 'message' => $response->get_error_message() ] );
        }

        wp_send_json_success( $response->get_data() );
    }

    public function ajax_report_problem() {
        check_ajax_referer( 'sk_lightning_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'Nicht eingeloggt.' ] );
        }

        $payment_hash = sanitize_text_field( wp_unslash( $_POST['payment_hash'] ?? '' ) );
        $reason       = sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) );

        if ( empty( $payment_hash ) ) {
            wp_send_json_error( [ 'message' => 'Payment-Hash fehlt.' ] );
        }

        global $wpdb;
        $table   = $wpdb->prefix . 'sk_lightning_payments';
        $payment = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE payment_hash = %s", $payment_hash )
        );

        if ( ! $payment ) {
            wp_send_json_error( [ 'message' => 'Zahlung nicht gefunden.' ] );
        }

        if ( (int) $payment->buyer_id !== get_current_user_id() ) {
            wp_send_json_error( [ 'message' => 'Nur der Käufer kann ein Problem melden.' ] );
        }

        if ( $payment->status !== 'confirmed' ) {
            wp_send_json_error( [ 'message' => 'Zahlung ist nicht im Status "bestätigt".' ] );
        }

        $confirmed = strtotime( $payment->confirmed_at );
        if ( time() - $confirmed > 7 * DAY_IN_SECONDS ) {
            wp_send_json_error( [ 'message' => 'Die 7-Tage-Frist für Problemmeldungen ist abgelaufen.' ] );
        }

        $existing_meta = json_decode( $payment->metadata ?? '{}', true ) ?: [];
        $existing_meta['dispute_reason']  = $reason;
        $existing_meta['dispute_at']      = current_time( 'mysql' );
        $existing_meta['dispute_user_id'] = get_current_user_id();

        $wpdb->update(
            $table,
            [
                'status'   => 'disputed',
                'metadata' => wp_json_encode( $existing_meta ),
            ],
            [ 'payment_hash' => $payment_hash ],
            [ '%s', '%s' ],
            [ '%s' ]
        );

        wp_send_json_success( [ 'message' => 'Problem wurde gemeldet. Ein Admin wird es prüfen.' ] );
    }

    public static function find_or_create_chat_static( int $user_id, int $vendor_id, int $product_id, string $product_title ) {
        return self::do_find_or_create_chat( $user_id, $vendor_id, $product_id, $product_title );
    }

    public static function add_chat_message_static( int $chat_id, int $user_id, string $message ) {
        $messages   = get_post_meta( $chat_id, '_dvc_messages', true );
        $messages   = is_array( $messages ) ? $messages : [];
        $messages[] = [
            'user_id'   => $user_id,
            'message'   => $message,
            'timestamp' => current_time( 'timestamp' ),
        ];
        update_post_meta( $chat_id, '_dvc_messages', $messages );
        update_post_meta( $chat_id, '_dvc_last_message_time', current_time( 'timestamp' ) );
    }

    private function find_or_create_chat( int $user_id, int $vendor_id, int $product_id, string $product_title ) {
        return self::do_find_or_create_chat( $user_id, $vendor_id, $product_id, $product_title );
    }

    private static function do_find_or_create_chat( int $user_id, int $vendor_id, int $product_id, string $product_title ) {
        $args = [
            'post_type'      => 'vendor_chat',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'   => '_dvc_product_id',
                    'value' => $product_id,
                ],
                [
                    'relation' => 'OR',
                    [
                        'relation' => 'AND',
                        [ 'key' => '_dvc_participant_1', 'value' => $user_id ],
                        [ 'key' => '_dvc_participant_2', 'value' => $vendor_id ],
                    ],
                    [
                        'relation' => 'AND',
                        [ 'key' => '_dvc_participant_1', 'value' => $vendor_id ],
                        [ 'key' => '_dvc_participant_2', 'value' => $user_id ],
                    ],
                ],
            ],
        ];

        $query = new \WP_Query( $args );

        if ( $query->have_posts() ) {
            return $query->posts[0]->ID;
        }

        $chat_id = wp_insert_post( [
            'post_type'   => 'vendor_chat',
            'post_status' => 'publish',
            'post_title'  => 'Chat über: ' . $product_title,
            'post_author' => $user_id,
        ] );

        if ( is_wp_error( $chat_id ) ) {
            return $chat_id;
        }

        update_post_meta( $chat_id, '_dvc_participant_1', $user_id );
        update_post_meta( $chat_id, '_dvc_participant_2', $vendor_id );
        update_post_meta( $chat_id, '_dvc_product_id', $product_id );
        update_post_meta( $chat_id, '_dvc_messages', [] );
        update_post_meta( $chat_id, '_dvc_archived_by', [] );

        return $chat_id;
    }

    private function add_chat_message( int $chat_id, int $user_id, string $message ) {
        $messages   = get_post_meta( $chat_id, '_dvc_messages', true );
        $messages   = is_array( $messages ) ? $messages : [];
        $messages[] = [
            'user_id'   => $user_id,
            'message'   => $message,
            'timestamp' => current_time( 'timestamp' ),
        ];
        update_post_meta( $chat_id, '_dvc_messages', $messages );
        update_post_meta( $chat_id, '_dvc_last_message_time', current_time( 'timestamp' ) );
    }

    private function is_chat_participant( int $chat_id, int $user_id ): bool {
        $p1 = (int) get_post_meta( $chat_id, '_dvc_participant_1', true );
        $p2 = (int) get_post_meta( $chat_id, '_dvc_participant_2', true );
        return $user_id === $p1 || $user_id === $p2;
    }

    private function get_other_participant( int $chat_id, int $user_id ): int {
        $p1 = (int) get_post_meta( $chat_id, '_dvc_participant_1', true );
        $p2 = (int) get_post_meta( $chat_id, '_dvc_participant_2', true );
        return $user_id === $p1 ? $p2 : $p1;
    }
}
