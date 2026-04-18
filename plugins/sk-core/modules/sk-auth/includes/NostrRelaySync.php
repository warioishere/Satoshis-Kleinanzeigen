<?php

namespace SK\Modules\Auth;

use Phrity\Net\Uri;

defined( 'ABSPATH' ) || exit;

/**
 * Nostr Relay Sync — Polls relays for profile updates and incoming events.
 *
 * Cron-based: runs every 5 minutes, connects to relays, fetches new events
 * for all users with Nostr identities, and syncs changes back to WordPress.
 *
 * Handles:
 * - Kind 0: Profile changes (name, avatar, lud16) → update WP/store profile
 * - Kind 9735: Incoming Zap Receipts → track in zap system
 */
class NostrRelaySync {

    const CRON_HOOK     = 'sk_nostr_relay_sync';
    const LAST_SYNC_KEY = 'sk_nostr_relay_last_sync';

    public static function init() {
        add_action( self::CRON_HOOK, [ __CLASS__, 'run' ] );
        add_filter( 'cron_schedules', [ __CLASS__, 'add_interval' ] );

        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time(), 'sk_5min', self::CRON_HOOK );
        }
    }

    public static function add_interval( $schedules ) {
        $schedules['sk_5min'] = [
            'interval' => 300,
            'display'  => __( 'Every 5 Minutes', 'sk-core' ),
        ];
        return $schedules;
    }

    /**
     * Main sync run — fetch events from relays for all Nostr users.
     */
    public static function run() {
        $users = self::get_nostr_users();
        if ( empty( $users ) ) {
            return;
        }

        $pubkeys = array_column( $users, 'pubkey' );
        $since   = (int) get_option( self::LAST_SYNC_KEY, time() - 600 );

        $relays = NostrIdentity::get_relays();
        if ( empty( $relays ) ) {
            return;
        }

        // Build user lookup map.
        $pubkey_to_user = [];
        foreach ( $users as $u ) {
            $pubkey_to_user[ $u['pubkey'] ] = $u['user_id'];
        }

        // Fetch events from each relay.
        foreach ( $relays as $relay_url ) {
            try {
                $events = self::fetch_events( $relay_url, $pubkeys, $since );
                foreach ( $events as $event ) {
                    $author = $event['pubkey'] ?? '';
                    $user_id = $pubkey_to_user[ $author ] ?? 0;
                    if ( ! $user_id ) {
                        continue;
                    }

                    $kind = (int) ( $event['kind'] ?? 0 );

                    // Kind 1 notes sind absichtlich NICHT hier: Nostr → SK ist
                    // Einbahnstraße. Nur SK → Nostr (via Feed-Post-Publish).
                    if ( 0 === $kind ) {
                        self::handle_profile_update( $user_id, $event );
                    } elseif ( 9735 === $kind ) {
                        self::handle_zap_receipt( $user_id, $event );
                    }
                }
            } catch ( \Throwable $e ) {
                error_log( '[NostrRelaySync] Error from ' . $relay_url . ': ' . $e->getMessage() );
            }
        }

        update_option( self::LAST_SYNC_KEY, time() );
    }

    /**
     * Fetch Kind 0 profile + Kind 9735 zap-receipt events from a relay.
     * Kind 1 notes werden absichtlich nicht abgefragt — wir syncen nur
     * Profil-Updates und Zap-Empfänge, keine Timeline-Inhalte.
     */
    private static function fetch_events( string $relay_url, array $pubkeys, int $since ): array {
        $context = stream_context_create( [
            'ssl' => [ 'verify_peer' => true, 'verify_peer_name' => true ],
        ] );

        $client = new \WebSocket\Client( $relay_url, [ 'context' => $context, 'timeout' => 10 ] );
        $sub_id = bin2hex( random_bytes( 8 ) );

        $events = [];

        try {
            $client->text( json_encode( [
                'REQ',
                $sub_id,
                [
                    'authors' => $pubkeys,
                    'kinds'   => [ 0 ],
                    'since'   => $since,
                ],
            ] ) );

            // Also subscribe for zap receipts targeting our users.
            $sub_id2 = bin2hex( random_bytes( 8 ) );
            $client->text( json_encode( [
                'REQ',
                $sub_id2,
                [
                    'kinds' => [ 9735 ],
                    '#p'    => $pubkeys,
                    'since' => $since,
                ],
            ] ) );

            $start = time();
            $eose_count = 0;

            while ( time() - $start < 10 ) {
                $msg = $client->receive();
                if ( ! $msg ) {
                    break;
                }

                $content = $msg->getContent();
                $data    = json_decode( $content, true );
                if ( ! is_array( $data ) ) {
                    continue;
                }

                if ( 'EVENT' === ( $data[0] ?? '' ) && isset( $data[2] ) ) {
                    $event = $data[2];
                    $eid   = $event['id'] ?? '';
                    // Dedup.
                    $dedup_key = 'sk_nsync_' . substr( $eid, 0, 16 );
                    if ( ! get_transient( $dedup_key ) ) {
                        set_transient( $dedup_key, 1, DAY_IN_SECONDS );
                        $events[] = $event;
                    }
                }

                if ( 'EOSE' === ( $data[0] ?? '' ) ) {
                    $eose_count++;
                    if ( $eose_count >= 2 ) {
                        break; // Both subscriptions done.
                    }
                }
            }

            // Clean up subscriptions.
            $client->text( json_encode( [ 'CLOSE', $sub_id ] ) );
            $client->text( json_encode( [ 'CLOSE', $sub_id2 ] ) );
            $client->disconnect();
        } catch ( \Throwable $e ) {
            try { $client->disconnect(); } catch ( \Throwable $_ ) {}
            throw $e;
        }

        return $events;
    }

    /**
     * Handle Kind 0 profile update from Nostr.
     */
    private static function handle_profile_update( int $user_id, array $event ) {
        $profile = json_decode( $event['content'] ?? '{}', true );
        if ( ! is_array( $profile ) || empty( $profile ) ) {
            return;
        }

        $updated = false;

        // Sync name → display_name (only if not a placeholder).
        if ( ! empty( $profile['name'] ) ) {
            $current = get_userdata( $user_id );
            if ( $current && ( strpos( $current->display_name, 'satoshi-' ) === 0 || strpos( $current->display_name, 'nostr-' ) === 0 || strpos( $current->display_name, 'LN-' ) === 0 ) ) {
                wp_update_user( [ 'ID' => $user_id, 'display_name' => sanitize_text_field( $profile['name'] ) ] );
                $updated = true;
            }
        }

        // Sync avatar.
        if ( ! empty( $profile['picture'] ) ) {
            $avatar = esc_url_raw( $profile['picture'] );
            if ( $avatar !== get_user_meta( $user_id, 'nostr_avatar', true ) ) {
                update_user_meta( $user_id, 'nostr_avatar', $avatar );
                $updated = true;
            }
        }

        // Sync lud16 → lightning_address (only if user hasn't set one manually).
        if ( ! empty( $profile['lud16'] ) ) {
            $settings = get_user_meta( $user_id, 'sk_profile_settings', true );
            if ( ! is_array( $settings ) ) {
                $settings = [];
            }
            $domain = wp_parse_url( home_url(), PHP_URL_HOST );
            $our_lud16 = 'v/' . $user_id . '@' . $domain;

            // Only update if currently empty or set to our generated address.
            if ( empty( $settings['lightning_address'] ) || $settings['lightning_address'] === $our_lud16 ) {
                $new_lud16 = sanitize_text_field( $profile['lud16'] );
                if ( $new_lud16 !== ( $settings['lightning_address'] ?? '' ) ) {
                    $settings['lightning_address'] = $new_lud16;
                    update_user_meta( $user_id, 'sk_profile_settings', $settings );
                    $updated = true;
                }
            }
        }

        // Sync banner → store banner.
        if ( ! empty( $profile['banner'] ) ) {
            $banner_url = esc_url_raw( $profile['banner'] );
            $store_info = function_exists( 'sk_get_store_info' ) ? sk_get_store_info( $user_id ) : [];
            if ( is_array( $store_info ) ) {
                $current_banner = $store_info['banner'] ?? '';
                if ( $banner_url !== $current_banner ) {
                    $store_info['banner'] = $banner_url;
                    update_user_meta( $user_id, 'skdar_profile_settings', $store_info );
                    $updated = true;
                }
            }
        }

        // Sync about → store description + user bio.
        if ( ! empty( $profile['about'] ) ) {
            $about = sanitize_textarea_field( $profile['about'] );

            // User bio (WordPress description).
            if ( $about !== get_user_meta( $user_id, 'description', true ) ) {
                update_user_meta( $user_id, 'description', $about );
                $updated = true;
            }
        }

        // Sync name → store_name (if store name is still default/empty).
        if ( ! empty( $profile['name'] ) ) {
            $store_info = function_exists( 'sk_get_store_info' ) ? sk_get_store_info( $user_id ) : [];
            if ( is_array( $store_info ) ) {
                $store_name = $store_info['store_name'] ?? '';
                $user = get_userdata( $user_id );
                // Only update if store name is empty or matches the generated username.
                if ( empty( $store_name ) || ( $user && $store_name === $user->user_login ) ) {
                    $store_info['store_name'] = sanitize_text_field( $profile['name'] );
                    update_user_meta( $user_id, 'skdar_profile_settings', $store_info );
                    $updated = true;
                }
            }
        }

        // Sync NIP-05.
        if ( ! empty( $profile['nip05'] ) ) {
            $nip05 = sanitize_text_field( $profile['nip05'] );
            if ( $nip05 !== get_user_meta( $user_id, 'nip05', true ) ) {
                update_user_meta( $user_id, 'nip05', $nip05 );
                $updated = true;
            }
        }

        // Sync website.
        if ( ! empty( $profile['website'] ) ) {
            $website = esc_url_raw( $profile['website'] );
            $user = get_userdata( $user_id );
            if ( $user && $user->user_url !== $website ) {
                wp_update_user( [ 'ID' => $user_id, 'user_url' => $website ] );
                $updated = true;
            }
        }

        if ( $updated ) {
            error_log( sprintf( '[NostrRelaySync] Profile updated for user %d from Kind 0 event', $user_id ) );
        }
    }

    /**
     * Handle Kind 9735 Zap Receipt.
     */
    private static function handle_zap_receipt( int $user_id, array $event ) {
        $amount_sats   = 0;
        $zapper_pubkey = '';
        $zapped_event_id = '';

        // Extract zap request from description tag.
        foreach ( $event['tags'] ?? [] as $tag ) {
            if ( 'description' === ( $tag[0] ?? '' ) && ! empty( $tag[1] ) ) {
                $zap_request = json_decode( $tag[1], true );
                if ( is_array( $zap_request ) ) {
                    $zapper_pubkey = $zap_request['pubkey'] ?? '';
                    foreach ( $zap_request['tags'] ?? [] as $ztag ) {
                        if ( 'amount' === ( $ztag[0] ?? '' ) ) {
                            $amount_sats = (int) floor( (int) $ztag[1] / 1000 );
                        }
                        // 'e' tag references the zapped event.
                        if ( 'e' === ( $ztag[0] ?? '' ) && ! empty( $ztag[1] ) ) {
                            $zapped_event_id = $ztag[1];
                        }
                    }
                }
            }
            // Also check top-level 'e' tag.
            if ( 'e' === ( $tag[0] ?? '' ) && ! empty( $tag[1] ) && empty( $zapped_event_id ) ) {
                $zapped_event_id = $tag[1];
            }
        }

        if ( $amount_sats <= 0 ) {
            return;
        }

        // Find the SK feed post linked to this Nostr event.
        if ( ! empty( $zapped_event_id ) ) {
            global $wpdb;
            $post_id = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sk_nostr_event_id' AND meta_value = %s LIMIT 1",
                $zapped_event_id
            ) );

            if ( $post_id ) {
                $current = (int) get_post_meta( $post_id, '_sk_zap_total_sats', true );
                update_post_meta( $post_id, '_sk_zap_total_sats', $current + $amount_sats );
            }
        }

        do_action( 'sk_nostr_zap_received', $user_id, $amount_sats, $zapper_pubkey, $event );
        error_log( sprintf(
            '[NostrRelaySync] Zap receipt: %d sats to user %d from %s%s',
            $amount_sats, $user_id, substr( $zapper_pubkey, 0, 12 ),
            $zapped_event_id ? ' on event ' . substr( $zapped_event_id, 0, 12 ) : ''
        ) );
    }

    /**
     * Get all users with Nostr identities.
     */
    private static function get_nostr_users(): array {
        global $wpdb;
        $results = $wpdb->get_results(
            "SELECT user_id, meta_value AS pubkey FROM {$wpdb->usermeta} WHERE meta_key = 'nostr_public_key' AND meta_value != ''",
            ARRAY_A
        );
        return $results ?: [];
    }
}
