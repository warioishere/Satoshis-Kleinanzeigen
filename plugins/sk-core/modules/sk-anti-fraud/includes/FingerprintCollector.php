<?php

namespace SK\Modules\AntiFraud;

defined( 'ABSPATH' ) || exit;

class FingerprintCollector {

    public function __construct() {
        add_action( 'wp_ajax_sk_collect_fingerprint', [ $this, 'ajax_collect' ] );
        add_action( 'wp_ajax_nopriv_sk_collect_fingerprint', [ $this, 'ajax_collect' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function enqueue_assets() {
        if ( ! is_user_logged_in() ) {
            return;
        }

        wp_enqueue_script(
            'sk-fingerprint',
            SK_ANTIFRAUD_URL . '/assets/js/sk-fingerprint.js',
            [],
            SK_ANTIFRAUD_VERSION,
            true
        );

        wp_localize_script( 'sk-fingerprint', 'skFP', [
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'sk_fp' ),
        ] );
    }

    public function ajax_collect() {
        check_ajax_referer( 'sk_fp', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error();
        }

        $user_id = get_current_user_id();

        $data = [
            'user_id'          => $user_id,
            'fingerprint_hash' => sanitize_text_field( $_POST['fingerprint_hash'] ?? '' ),
            'canvas_hash'      => sanitize_text_field( $_POST['canvas_hash'] ?? '' ),
            'webgl_hash'       => sanitize_text_field( $_POST['webgl_hash'] ?? '' ),
            'audio_hash'       => sanitize_text_field( $_POST['audio_hash'] ?? '' ),
            'fonts_hash'       => sanitize_text_field( $_POST['fonts_hash'] ?? '' ),
            'ip_hash'          => hash( 'sha256', self::get_client_ip() ),
            'geo_city'         => self::get_user_city( $user_id ),
            'timezone'         => sanitize_text_field( $_POST['timezone'] ?? '' ),
            'screen'           => sanitize_text_field( $_POST['screen'] ?? '' ),
            'platform'         => sanitize_text_field( $_POST['platform'] ?? '' ),
            'created_at'       => current_time( 'mysql' ),
        ];

        if ( empty( $data['fingerprint_hash'] ) ) {
            wp_send_json_error();
        }

        // Store fingerprint.
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'sk_fingerprints', $data );

        // Check against banned signals.
        $score   = $this->calculate_score( $data );
        $signals = $this->get_matched_signals( $data );

        if ( $score > 0 ) {
            update_user_meta( $user_id, 'sk_scam_score', $score );
            update_user_meta( $user_id, 'sk_scam_signals', wp_json_encode( $signals ) );
        }

        $flag_score        = (int) sk_get_option( 'sk_antifraud_flag_score', 'sk_antifraud', '50' );
        $autosuspend_score = (int) sk_get_option( 'sk_antifraud_autosuspend_score', 'sk_antifraud', '70' );

        if ( $score >= $autosuspend_score ) {
            self::suspend_user( $user_id, 'fingerprint_score_' . $score );
        } elseif ( $score >= $flag_score ) {
            self::notify_admin( $user_id, $score, $signals );
        }

        wp_send_json_success( [ 'score' => $score ] );
    }

    private function calculate_score( array $data ): int {
        global $wpdb;
        $table  = $wpdb->prefix . 'sk_banned_signals';
        $score  = 0;

        // Fingerprint match.
        if ( ! empty( $data['fingerprint_hash'] ) ) {
            $match = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE signal_type = 'fingerprint' AND signal_value = %s",
                $data['fingerprint_hash']
            ) );
            if ( $match ) {
                $score += 60;
            }
        }

        // Canvas match (partial fingerprint).
        if ( ! empty( $data['canvas_hash'] ) && $score < 60 ) {
            $match = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE signal_type = 'canvas' AND signal_value = %s",
                $data['canvas_hash']
            ) );
            if ( $match ) {
                $score += 40;
            }
        }

        // Audio match.
        if ( ! empty( $data['audio_hash'] ) ) {
            $match = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE signal_type = 'audio' AND signal_value = %s",
                $data['audio_hash']
            ) );
            if ( $match ) {
                $score += 35;
            }
        }

        // IP match.
        if ( ! empty( $data['ip_hash'] ) ) {
            $match = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE signal_type = 'ip' AND signal_value = %s",
                $data['ip_hash']
            ) );
            if ( $match ) {
                $score += 30;
            }
        }

        // Geo match.
        if ( ! empty( $data['geo_city'] ) ) {
            $match = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE signal_type = 'geo' AND signal_value = %s",
                $data['geo_city']
            ) );
            if ( $match ) {
                $score += 20;
            }
        }

        // Timing: banned user in last 7 days.
        $recent_ban = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE banned_at > %s LIMIT 1",
            gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) )
        ) );
        if ( $recent_ban && $score > 0 ) {
            $score += 20;
        }

        return $score;
    }

    private function get_matched_signals( array $data ): array {
        global $wpdb;
        $table   = $wpdb->prefix . 'sk_banned_signals';
        $matches = [];

        $checks = [
            'fingerprint' => $data['fingerprint_hash'],
            'canvas'      => $data['canvas_hash'],
            'audio'       => $data['audio_hash'],
            'ip'          => $data['ip_hash'],
            'geo'         => $data['geo_city'],
        ];

        foreach ( $checks as $type => $value ) {
            if ( empty( $value ) ) {
                continue;
            }
            $row = $wpdb->get_row( $wpdb->prepare(
                "SELECT banned_user_id FROM {$table} WHERE signal_type = %s AND signal_value = %s LIMIT 1",
                $type,
                $value
            ) );
            if ( $row ) {
                $banned_user = get_userdata( $row->banned_user_id );
                $matches[]   = [
                    'type'        => $type,
                    'banned_user' => $banned_user ? $banned_user->user_login : $row->banned_user_id,
                ];
            }
        }

        return $matches;
    }

    /**
     * Ban a user: store all their signals for future matching.
     */
    public static function ban_user( int $user_id ) {
        global $wpdb;

        update_user_meta( $user_id, 'sk_banned', 1 );

        $now = current_time( 'mysql' );
        $table_fp  = $wpdb->prefix . 'sk_fingerprints';
        $table_ban = $wpdb->prefix . 'sk_banned_signals';

        // Collect all fingerprints for this user.
        $fingerprints = $wpdb->get_results( $wpdb->prepare(
            "SELECT fingerprint_hash, canvas_hash, webgl_hash, audio_hash, fonts_hash, ip_hash, geo_city FROM {$table_fp} WHERE user_id = %d",
            $user_id
        ) );

        foreach ( $fingerprints as $fp ) {
            $signals = [
                'fingerprint' => $fp->fingerprint_hash,
                'canvas'      => $fp->canvas_hash,
                'webgl'       => $fp->webgl_hash,
                'audio'       => $fp->audio_hash,
                'fonts'       => $fp->fonts_hash,
                'ip'          => $fp->ip_hash,
                'geo'         => $fp->geo_city,
            ];

            foreach ( $signals as $type => $value ) {
                if ( empty( $value ) ) {
                    continue;
                }
                // Avoid duplicates.
                $exists = $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table_ban} WHERE signal_type = %s AND signal_value = %s AND banned_user_id = %d",
                    $type, $value, $user_id
                ) );
                if ( ! $exists ) {
                    $wpdb->insert( $table_ban, [
                        'banned_user_id' => $user_id,
                        'signal_type'    => $type,
                        'signal_value'   => $value,
                        'banned_at'      => $now,
                    ] );
                }
            }
        }

        // Suspend the user.
        self::suspend_user( $user_id, 'admin_ban' );
    }

    /**
     * Suspend a user: draft all products, close store.
     */
    public static function suspend_user( int $user_id, string $reason = '' ) {
        update_user_meta( $user_id, 'sk_auto_suspended', 1 );
        update_user_meta( $user_id, 'sk_auto_suspended_reason', $reason );

        // Draft all products.
        $products = get_posts( [
            'author'         => $user_id,
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ] );

        foreach ( $products as $pid ) {
            wp_update_post( [ 'ID' => $pid, 'post_status' => 'draft' ] );
        }

        // Close store.
        $store_info = function_exists( 'sk_get_store_info' ) ? sk_get_store_info( $user_id ) : [];
        if ( is_array( $store_info ) ) {
            $store_info['store_open_close'] = 'close';
            update_user_meta( $user_id, 'skdar_profile_settings', $store_info );
        }
    }

    private static function notify_admin( int $user_id, int $score, array $signals ) {
        $user    = get_userdata( $user_id );
        $subject = sprintf( '[SK Anti-Fraud] Verdächtiger Account: %s (Score %d)', $user->user_login, $score );

        $body = "Ein neuer Account hat Übereinstimmungen mit gebannten Usern:\n\n";
        $body .= "User: {$user->user_login} (ID {$user_id})\n";
        $body .= "Score: {$score}\n\n";

        foreach ( $signals as $s ) {
            $body .= "- {$s['type']}: matcht gebannten User {$s['banned_user']}\n";
        }

        $body .= "\n" . admin_url( "user-edit.php?user_id={$user_id}" );

        wp_mail( get_option( 'admin_email' ), $subject, $body );
    }

    /**
     * Client IP for the fraud fingerprint.
     *
     * Resolved centrally by sk_get_client_ip(), which ignores spoofable proxy
     * headers unless the request really came through a proxy — a forgeable IP
     * would make the whole fingerprint worthless.
     */
    private static function get_client_ip(): string {
        $ip = function_exists( 'sk_get_client_ip' ) ? sk_get_client_ip() : '';

        return $ip !== '' ? $ip : 'unknown';
    }

    private static function get_user_city( int $user_id ): string {
        $address = get_user_meta( $user_id, 'sk_geo_address', true );
        if ( ! empty( $address ) ) {
            // Extract city from address like "Dortmund, Nordrhein-Westfalen, Deutschland"
            $parts = array_map( 'trim', explode( ',', $address ) );
            return $parts[0] ?? '';
        }
        return '';
    }
}
