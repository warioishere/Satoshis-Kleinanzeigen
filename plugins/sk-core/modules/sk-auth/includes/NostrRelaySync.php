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

    const CRON_HOOK         = 'sk_nostr_relay_sync';
    const LAST_SYNC_KEY     = 'sk_nostr_relay_last_sync';
    const RELAY_FAIL_TTL    = 3600;  // Skip a failing relay for 1h after a bad run.
    const RELAY_TIMEOUT_SEC = 5;     // Socket/read timeout per relay (was 10).

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

        // Fetch events from each relay. Skip relays that failed recently so a
        // single bad relay can't drag the whole cron run past the 40s mark
        // (relay.nostr.band is flaky for us — this keeps it from blocking the
        // others for the next hour after a failure).
        foreach ( $relays as $relay_url ) {
            $fail_key = self::relay_fail_key( $relay_url );
            if ( get_transient( $fail_key ) ) {
                continue;
            }

            try {
                $events = self::fetch_events( $relay_url, $pubkeys, $since );
                foreach ( $events as $event ) {
                    $author = $event['pubkey'] ?? '';
                    $user_id = $pubkey_to_user[ $author ] ?? 0;
                    if ( ! $user_id ) {
                        continue;
                    }

                    $kind = (int) ( $event['kind'] ?? 0 );

                    // Kind 1 notes are intentionally NOT handled here: Nostr → SK is
                    // a one-way street. Only SK → Nostr (via feed post publish).
                    if ( 0 === $kind ) {
                        self::handle_profile_update( $user_id, $event );
                    } elseif ( 9735 === $kind ) {
                        self::handle_zap_receipt( $user_id, $event );
                    }
                }
            } catch ( \Throwable $e ) {
                error_log( '[NostrRelaySync] Error from ' . $relay_url . ': ' . $e->getMessage() );
                set_transient( $fail_key, 1, self::RELAY_FAIL_TTL );
            }
        }

        // NIP-05 verdicts age out after a week and a profile that never
        // changes never comes through the loop above, so a few due ones are
        // renewed here each run — each is one request to an outside host.
        $budget = 10;
        foreach ( $users as $u ) {
            if ( $budget <= 0 ) {
                break;
            }
            $uid = (int) $u['user_id'];
            if ( \SK\Core\Nostr\Nip05::needs_check( $uid ) ) {
                \SK\Core\Nostr\Nip05::check( $uid );
                $budget--;
            }
        }

        update_option( self::LAST_SYNC_KEY, time() );
    }

    private static function relay_fail_key( string $relay_url ): string {
        return 'sk_nostr_relay_fail_' . md5( $relay_url );
    }

    /**
     * Fetch Kind 0 profile + Kind 9735 zap-receipt events from a relay.
     * Kind 1 notes are intentionally not queried — we only sync profile
     * updates and zap receipts, no timeline content.
     */
    private static function fetch_events( string $relay_url, array $pubkeys, int $since ): array {
        // One REQ with both filters; every event verified (a profile update
        // rewrites display names and avatars, so a forged one must not count).
        $result = \SK\Core\Nostr\Relays::fetch(
            $relay_url,
            [
                [ 'authors' => $pubkeys, 'kinds' => [ 0 ], 'since' => $since ],
                [ 'kinds' => [ 9735 ], '#p' => $pubkeys, 'since' => $since ],
            ],
            [ 'timeout' => self::RELAY_TIMEOUT_SEC ]
        );

        if ( ! $result['eose'] ) {
            // The caller marks a relay that never finished as failing for a while.
            throw new \RuntimeException( 'no EOSE within ' . self::RELAY_TIMEOUT_SEC . 's' );
        }

        $events = [];

        foreach ( $result['events'] as $event ) {
            $dedup_key = 'sk_nsync_' . substr( (string) ( $event['id'] ?? '' ), 0, 16 );

            if ( ! get_transient( $dedup_key ) ) {
                set_transient( $dedup_key, 1, DAY_IN_SECONDS );
                $events[] = $event;
            }
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

        $created = (int) ( $event['created_at'] ?? 0 );

        /*
         * The newer profile wins, the rule every Nostr client follows.
         *
         * Both sides write now: a shop change goes out to the relays, and what
         * the vendor changes in a Nostr client comes in here. Without this
         * check our own publication would come back minutes later and undo
         * itself, and an old event from a slow relay could revive a value the
         * vendor had already replaced.
         */
        if ( ! NostrIdentity::profile_is_newer( $user_id, $created ) ) {
            return;
        }

        // The first profile after linking an account only fills gaps; from the
        // second one on, the profile is the source. See profile_seen().
        $replace = NostrIdentity::profile_seen( $user_id );

        $updated = false;

        // Sync name → shop name and display name.
        if ( ! empty( $profile['name'] ) ) {
            $name       = sanitize_text_field( $profile['name'] );
            $current    = get_userdata( $user_id );
            $store_name = function_exists( 'sk_get_store_info' ) ? ( sk_get_store_info( $user_id )['store_name'] ?? '' ) : '';

            // A name the vendor never chose: the shop slug we handed out.
            $placeholder = $store_name === '' || preg_match( '/^(satoshi-|nostr-|LN-)/', $store_name );

            if ( $store_name !== $name && ( $replace || $placeholder ) ) {
                sk_set_store_name( $user_id, $name );
                $updated = true;
            }

            if ( $current && $current->display_name !== $name
                && ( $replace || preg_match( '/^(satoshi-|nostr-|LN-)/', $current->display_name ) ) ) {
                wp_update_user( [ 'ID' => $user_id, 'display_name' => $name ] );
                $updated = true;
            }
        }

        // Sync avatar.
        if ( ! empty( $profile['picture'] ) ) {
            $avatar  = esc_url_raw( $profile['picture'] );
            $current = (string) get_user_meta( $user_id, 'nostr_avatar', true );

            if ( $avatar !== $current && ( $replace || $current === '' ) ) {
                update_user_meta( $user_id, 'nostr_avatar', $avatar );
                $updated = true;
            }
        }

        // Sync lud16 → lightning_address. This event is the newer one, so it
        // replaces what stands here, address changes included. Whether the
        // address can also carry a sale is decided and recorded in
        // Wallet\Settings::adopt_discovered_address().
        if ( ! empty( $profile['lud16'] ) ) {
            if ( \SK\Core\Wallet\Settings::adopt_discovered_address( $user_id, sanitize_text_field( $profile['lud16'] ), $replace ) ) {
                $updated = true;
            }
        }

        /*
         * Sync banner → store banner, but only while the vendor has none of
         * their own: what they picked in the shop settings stays.
         *
         * The address used to be written straight into the field, where every
         * reader expects the id of an uploaded image — so those shops showed no
         * banner at all. The image is taken into the media library instead, and
         * a leftover address counts as "none" so it repairs itself.
         */
        if ( ! empty( $profile['banner'] ) ) {
            $store_info = function_exists( 'sk_get_store_info' ) ? sk_get_store_info( $user_id ) : [];

            if ( is_array( $store_info ) && absint( $store_info['banner'] ?? 0 ) === 0 ) {
                $attachment_id = self::sideload_banner( $user_id, esc_url_raw( $profile['banner'] ) );

                if ( $attachment_id > 0 ) {
                    $store_info['banner'] = $attachment_id;
                    update_user_meta( $user_id, 'sk_profile_settings', $store_info );
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

        // Sync NIP-05, then ask its domain whether the name really maps to
        // the vendor's key. The verdict is stored; the trust page only reads it.
        if ( ! empty( $profile['nip05'] ) ) {
            $nip05   = sanitize_text_field( $profile['nip05'] );
            $changed = $nip05 !== get_user_meta( $user_id, 'nip05', true );

            if ( $changed ) {
                update_user_meta( $user_id, 'nip05', $nip05 );
                $updated = true;
            }

            \SK\Core\Nostr\Nip05::check( $user_id, $changed );
        }

        // Sync website.
        if ( ! empty( $profile['website'] ) ) {
            $website = esc_url_raw( $profile['website'] );
            $user    = get_userdata( $user_id );

            if ( $user && $user->user_url !== $website && ( $replace || $user->user_url === '' ) ) {
                wp_update_user( [ 'ID' => $user_id, 'user_url' => $website ] );
                $updated = true;
            }
        }

        // Seen and applied: an older event may not undo any of it, and neither
        // may this one a second time.
        NostrIdentity::remember_profile_time( $user_id, $created );

        if ( $updated ) {
            error_log( sprintf( '[NostrRelaySync] Profile updated for user %d from Kind 0 event', $user_id ) );
        }
    }

    /**
     * Take a banner image from a Nostr profile into the media library.
     *
     * @return int Attachment id, or 0 when nothing was taken over.
     */
    private static function sideload_banner( int $user_id, string $url ): int {
        if ( ! preg_match( '#^https://#i', $url ) ) {
            return 0;
        }

        // The same image is not fetched again on every run, and a failed
        // attempt is not retried every hour either.
        if ( (string) get_user_meta( $user_id, 'sk_nostr_banner_src', true ) === $url ) {
            return 0;
        }

        update_user_meta( $user_id, 'sk_nostr_banner_src', $url );

        $name = basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );

        // media_handle_sideload goes by the file name, so one without a usable
        // extension would be refused anyway.
        if ( ! preg_match( '/\.(jpe?g|png|gif|webp)$/i', $name ) ) {
            return 0;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $tmp = download_url( $url, 20 );

        if ( is_wp_error( $tmp ) ) {
            return 0;
        }

        $attachment_id = media_handle_sideload(
            [ 'name' => $name, 'tmp_name' => $tmp ],
            0,
            null,
            [ 'post_author' => $user_id ]
        );

        if ( is_wp_error( $attachment_id ) ) {
            if ( file_exists( $tmp ) ) {
                wp_delete_file( $tmp );
            }

            return 0;
        }

        return (int) $attachment_id;
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
        $post_id = 0;

        if ( ! empty( $zapped_event_id ) ) {
            global $wpdb;
            $post_id = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sk_nostr_event_id' AND meta_value = %s LIMIT 1",
                $zapped_event_id
            ) );
        }

        /*
         * Counting happens in one place for every path (see ZapStats::count_zap).
         * This one used to add up on its own, without a lock: the same receipt
         * coming in from a second relay counted twice, and the vendor's own
         * total was never touched at all.
         */
        if ( class_exists( 'SK\Modules\Zaps\ZapStats' ) ) {
            \SK\Modules\Zaps\ZapStats::count_zap( $user_id, (string) ( $event['id'] ?? '' ), $amount_sats, $post_id );
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
