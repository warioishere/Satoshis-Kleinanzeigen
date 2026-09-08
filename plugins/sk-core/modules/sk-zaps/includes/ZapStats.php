<?php

namespace SK\Modules\Zaps;

defined( 'ABSPATH' ) || exit;

/**
 * How many sats a vendor has received in zaps — on this site and on Nostr
 * at large, since both end up as zap receipts (kind 9735) under the
 * vendor's key.
 *
 * The number is fetched in the background and cached on the user; a page
 * render only reads the cache and, when it has gone stale, asks for a
 * refresh. Nothing here talks to the network while a page is being built.
 */
class ZapStats {

    const SATS_META   = 'sk_zap_received_sats';
    const COUNT_META  = 'sk_zap_received_count';
    const TIME_META   = 'sk_zap_received_time';
    const SOURCE_META = 'sk_zap_received_source';

    /** How long a fetched total is trusted before it is refreshed. */
    const MAX_AGE = 6 * HOUR_IN_SECONDS;

    /** Cron hook that does the fetching. */
    const CRON_HOOK = 'sk_zaps_refresh_received';

    /** Receipts read per relay when Primal has nothing. */
    const RECEIPTS_LIMIT = 1000;

    public static function init(): void {
        add_action( self::CRON_HOOK, [ __CLASS__, 'refresh' ], 10, 2 );

        // The sats line is one chip in the vendor's trust strip (store
        // banner below the rating, vendor box on the product page).
        \SK\Core\Trust\TrustSignals::register( 'zaps', [ __CLASS__, 'chip' ], 20 );

        // A zap paid to an outside Lightning address: the browser hands in
        // the receipt it saw on the relays. Zappers need no account.
        add_action( 'wp_ajax_sk_zap_receipt', [ __CLASS__, 'ajax_receipt' ] );
        add_action( 'wp_ajax_nopriv_sk_zap_receipt', [ __CLASS__, 'ajax_receipt' ] );
    }

    /**
     * Count a zap the moment it is known to be paid — once per payment hash
     * or receipt id, whichever proves it.
     *
     * The total shown must move with the zap, not with the next background
     * fetch hours later. The cached number is bumped and its timestamp set
     * to now; the next fetch, when it is due, replaces it with the
     * network's count, which by then includes this zap.
     *
     * @param string $key Payment hash or receipt id, 64 hex.
     * @return bool True when counted now, false when already counted or unusable.
     */
    public static function add_received( int $vendor_id, string $key, int $sats ): bool {
        $key = strtolower( $key );

        if ( $vendor_id <= 0 || $sats <= 0 || ! preg_match( '/^[0-9a-f]{64}$/', $key ) ) {
            return false;
        }

        // Unique meta as the lock: a second call for the same key adds nothing.
        if ( ! add_user_meta( $vendor_id, '_sk_zap_seen_' . $key, $sats, true ) ) {
            return false;
        }

        update_user_meta( $vendor_id, self::SATS_META, (int) get_user_meta( $vendor_id, self::SATS_META, true ) + $sats );
        update_user_meta( $vendor_id, self::COUNT_META, (int) get_user_meta( $vendor_id, self::COUNT_META, true ) + 1 );
        update_user_meta( $vendor_id, self::TIME_META, time() );

        return true;
    }

    /**
     * AJAX: a zap receipt (kind 9735) the browser saw arrive for a vendor
     * with an outside Lightning address.
     *
     * Nothing in the receipt is taken on trust: the signature must be valid,
     * the signer must be the zapper key the vendor's Lightning address
     * publishes, the receipt must name the vendor, and each receipt counts
     * once. What survives that is a paid zap.
     */
    public static function ajax_receipt(): void {
        $vendor_id = absint( $_POST['vendor_id'] ?? 0 );
        $post_id   = absint( $_POST['post_id'] ?? 0 );
        $receipt   = json_decode( (string) wp_unslash( $_POST['receipt'] ?? '' ), true );

        $ip = function_exists( 'sk_get_client_ip' ) ? sk_get_client_ip() : '';

        if ( function_exists( 'sk_rate_limit' ) && ! sk_rate_limit( 'zap-receipt:' . md5( $ip ?: 'unknown' ), 20 ) ) {
            wp_send_json_error( [ 'message' => __( 'Zu viele Anfragen.', 'sk-core' ) ] );
        }

        $not_a_receipt = __( 'Keine Zap-Quittung.', 'sk-core' );

        if ( ! $vendor_id || ! is_array( $receipt ) || 9735 !== (int) ( $receipt['kind'] ?? 0 ) ) {
            wp_send_json_error( [ 'message' => $not_a_receipt ] );
        }

        foreach ( [ 'id', 'pubkey', 'sig', 'content' ] as $field ) {
            if ( ! isset( $receipt[ $field ] ) || ! is_string( $receipt[ $field ] ) ) {
                wp_send_json_error( [ 'message' => $not_a_receipt ] );
            }
        }

        if ( ! isset( $receipt['created_at'] ) || ! is_int( $receipt['created_at'] ) ) {
            wp_send_json_error( [ 'message' => $not_a_receipt ] );
        }

        $receipt['kind'] = 9735;
        $receipt['tags'] = isset( $receipt['tags'] ) && is_array( $receipt['tags'] ) ? $receipt['tags'] : [];

        if ( ! \SK\Core\Nostr\Events::verify( $receipt, 9735 ) ) {
            wp_send_json_error( [ 'message' => __( 'Signatur ungültig.', 'sk-core' ) ] );
        }

        $vendor_pub = ZapButton::vendor_pubkey( $vendor_id );
        $named      = false;

        foreach ( $receipt['tags'] as $tag ) {
            if ( is_array( $tag ) && 'p' === ( $tag[0] ?? '' ) && strtolower( (string) ( $tag[1] ?? '' ) ) === $vendor_pub ) {
                $named = true;
            }
        }

        if ( '' === $vendor_pub || ! $named ) {
            wp_send_json_error( [ 'message' => __( 'Quittung gehört nicht zu diesem Anbieter.', 'sk-core' ) ] );
        }

        $zapper = self::zapper_pubkey_for( $vendor_id );

        if ( '' === $zapper || strtolower( $receipt['pubkey'] ) !== $zapper ) {
            wp_send_json_error( [ 'message' => __( 'Quittung stammt nicht vom Zahlungsdienst des Anbieters.', 'sk-core' ) ] );
        }

        $sats = (int) floor( self::receipt_msats( $receipt ) / 1000 );

        if ( $sats <= 0 ) {
            wp_send_json_error( [ 'message' => __( 'Betrag unlesbar.', 'sk-core' ) ] );
        }

        $counted = self::add_received( $vendor_id, $receipt['id'], $sats );

        // The post it was zapped on, when the vendor wrote it.
        $post_total = null;

        if ( $post_id && class_exists( 'SK\Modules\Feed\PostType' ) ) {
            $post = get_post( $post_id );

            if ( $post && \SK\Modules\Feed\PostType::POST_TYPE === $post->post_type && (int) $post->post_author === $vendor_id ) {
                if ( $counted && add_post_meta( $post_id, '_sk_zap_hash_' . strtolower( $receipt['id'] ), $sats, true ) ) {
                    update_post_meta( $post_id, '_sk_zap_total_sats', (int) get_post_meta( $post_id, '_sk_zap_total_sats', true ) + $sats );
                }

                $post_total = (int) get_post_meta( $post_id, '_sk_zap_total_sats', true );
            }
        }

        wp_send_json_success( [
            'counted'      => $counted,
            'vendor_total' => (int) get_user_meta( $vendor_id, self::SATS_META, true ),
            'post_total'   => $post_total,
        ] );
    }

    /**
     * The key that signs zap receipts for the vendor's Lightning address
     * (the "nostrPubkey" of its LNURL-pay metadata), cached for a day.
     */
    private static function zapper_pubkey_for( int $vendor_id ): string {
        $cache_key = 'sk_zap_zapper_' . $vendor_id;
        $cached    = get_transient( $cache_key );

        if ( is_string( $cached ) ) {
            return $cached;
        }

        $zapper = '';
        $data   = ZapButton::get_vendor_zap_data( $vendor_id );
        $addr   = (string) ( $data['lightning_address'] ?? '' );

        if ( preg_match( '/^([^@\s]+)@([^@\s]+)$/', $addr, $m ) ) {
            $response = wp_remote_get( 'https://' . $m[2] . '/.well-known/lnurlp/' . rawurlencode( $m[1] ), [ 'timeout' => 5 ] );

            if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
                $meta = json_decode( wp_remote_retrieve_body( $response ), true );

                if ( is_array( $meta ) && ! empty( $meta['allowsNostr'] ) && preg_match( '/^[0-9a-f]{64}$/i', (string) ( $meta['nostrPubkey'] ?? '' ) ) ) {
                    $zapper = strtolower( $meta['nostrPubkey'] );
                }
            }
        }

        set_transient( $cache_key, $zapper, DAY_IN_SECONDS );

        return $zapper;
    }

    /**
     * Sats received so far, from the cache; 0 until the first fetch is in.
     * A stale or missing value queues a refresh.
     */
    public static function received_sats( int $vendor_id ): int {
        if ( $vendor_id <= 0 ) {
            return 0;
        }

        $fetched = (int) get_user_meta( $vendor_id, self::TIME_META, true );

        if ( $fetched < time() - self::MAX_AGE ) {
            self::queue_refresh( $vendor_id );
        }

        return (int) get_user_meta( $vendor_id, self::COUNT_META, true ) > 0
            ? (int) get_user_meta( $vendor_id, self::SATS_META, true )
            : 0;
    }

    /**
     * The trust-strip chip: a list item under the rating in the store
     * banner, an inline badge in the vendor box on the product page.
     * Nothing at all while the vendor has received no zaps.
     */
    public static function chip( int $vendor_id, string $context ): string {
        // The feed card has the post's own zap button with its total.
        if ( \SK\Core\Trust\TrustSignals::CONTEXT_FEED === $context ) {
            return '';
        }

        $sats = ZapButton::is_enabled() ? self::received_sats( $vendor_id ) : 0;

        if ( $sats <= 0 ) {
            return '';
        }

        ZapButton::ensure_assets();

        $title = esc_attr__( 'Erhaltene Zaps', 'sk-core' );
        $text  = esc_html( self::format_sats( $sats ) );

        if ( \SK\Core\Trust\TrustSignals::CONTEXT_STORE === $context ) {
            return '<li class="sk-store-zaps" title="' . $title . '"><i class="fas fa-bolt"></i> ' . $text . '</li>';
        }

        return '<span class="sk-vendor-zaps" title="' . $title . '"><i class="fas fa-bolt"></i> ' . $text . '</span>';
    }

    public static function format_sats( int $sats ): string {
        return number_format( $sats, 0, '', '.' ) . ' Sats';
    }

    /**
     * Ask for a refresh, at most once an hour per vendor, off the page.
     */
    private static function queue_refresh( int $vendor_id ): void {
        $pubkey = ZapButton::vendor_pubkey( $vendor_id );

        if ( ! preg_match( '/^[0-9a-f]{64}$/', $pubkey ) ) {
            return;
        }

        $marker = 'sk_zap_stats_asked_' . $vendor_id;

        if ( false !== get_transient( $marker ) ) {
            return;
        }

        set_transient( $marker, 1, HOUR_IN_SECONDS );

        wp_schedule_single_event( time() + 30, self::CRON_HOOK, [ $vendor_id, $pubkey ] );
    }

    /**
     * Fetch the total and store it. Runs on cron, never during a render.
     *
     * Primal's cache answers with the account's zap statistics across the
     * relays it indexes — that covers zaps the vendor got anywhere on Nostr.
     * When it has nothing, the receipts on our own relays are summed.
     */
    public static function refresh( $vendor_id, $pubkey ): void {
        $vendor_id = (int) $vendor_id;
        $pubkey    = strtolower( (string) $pubkey );

        if ( $vendor_id <= 0 || ! preg_match( '/^[0-9a-f]{64}$/', $pubkey ) ) {
            return;
        }

        $stats  = self::from_primal( $pubkey );
        $source = 'primal';

        if ( null === $stats ) {
            $stats  = self::from_relays( $pubkey );
            $source = 'relays';
        }

        // A failed fetch keeps the old number and is tried again next hour.
        if ( null === $stats ) {
            return;
        }

        /*
         * Our relays see a part of what Primal sees, never more. A relay
         * count must not replace a Primal count, and it is trusted for an
         * hour only, so the next try at Primal comes soon.
         */
        if ( 'relays' === $source ) {
            if ( 'primal' === get_user_meta( $vendor_id, self::SOURCE_META, true )
                && (int) get_user_meta( $vendor_id, self::SATS_META, true ) >= (int) $stats['sats'] ) {
                update_user_meta( $vendor_id, self::TIME_META, time() - self::MAX_AGE + HOUR_IN_SECONDS );

                return;
            }

            update_user_meta( $vendor_id, self::TIME_META, time() - self::MAX_AGE + HOUR_IN_SECONDS );
        } else {
            update_user_meta( $vendor_id, self::TIME_META, time() );
        }

        update_user_meta( $vendor_id, self::SATS_META, (int) $stats['sats'] );
        update_user_meta( $vendor_id, self::COUNT_META, (int) $stats['count'] );
        update_user_meta( $vendor_id, self::SOURCE_META, $source );
    }

    /**
     * Primal's cache answers with a 502 more often than not on a bad
     * minute (measured: four of six in a row). Asked up to this many
     * times, a second apart, before the relays stand in.
     */
    const PRIMAL_ATTEMPTS = 5;

    /**
     * @return array{sats: int, count: int}|null
     */
    private static function from_primal( string $pubkey ): ?array {
        $events = null;

        for ( $attempt = 1; $attempt <= self::PRIMAL_ATTEMPTS; $attempt++ ) {
            $response = wp_remote_post( 'https://cache.primal.net/api', [
                'timeout' => 8,
                'body'    => wp_json_encode( [ 'user_profile', [ 'pubkey' => $pubkey ] ] ),
                'headers' => [ 'Content-Type' => 'application/json' ],
            ] );

            if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
                $events = json_decode( wp_remote_retrieve_body( $response ), true );

                if ( is_array( $events ) ) {
                    break;
                }
            }

            if ( $attempt < self::PRIMAL_ATTEMPTS ) {
                sleep( 1 );
            }
        }

        if ( ! is_array( $events ) ) {
            return null;
        }

        // Kind 10000105: Primal's user statistics, content is JSON.
        foreach ( $events as $event ) {
            if ( 10000105 !== (int) ( $event['kind'] ?? 0 ) ) {
                continue;
            }

            $stats = json_decode( (string) ( $event['content'] ?? '' ), true );

            if ( ! is_array( $stats ) || ! isset( $stats['total_satszapped'] ) ) {
                continue;
            }

            return [
                'sats'  => max( 0, (int) $stats['total_satszapped'] ),
                'count' => max( 0, (int) ( $stats['total_zap_count'] ?? 0 ) ),
            ];
        }

        return null;
    }

    /**
     * Sum of the zap receipts on our own relays.
     *
     * The amount comes from the zap request inside the receipt (msats);
     * where that is missing, from the invoice's amount.
     *
     * @return array{sats: int, count: int}|null
     */
    private static function from_relays( string $pubkey ): ?array {
        // Every relay, merged by id; only receipts with a valid signature.
        $result = \SK\Core\Nostr\Relays::query(
            [ [ 'kinds' => [ 9735 ], '#p' => [ $pubkey ], 'limit' => self::RECEIPTS_LIMIT ] ],
            null,
            [ 'timeout' => 8, 'max' => self::RECEIPTS_LIMIT ]
        );

        if ( 0 === $result['answered'] ) {
            return null;
        }

        $msats = 0;

        foreach ( $result['events'] as $event ) {
            if ( 9735 === (int) ( $event['kind'] ?? 0 ) ) {
                $msats += self::receipt_msats( $event );
            }
        }

        return [ 'sats' => (int) floor( $msats / 1000 ), 'count' => count( $result['events'] ) ];
    }

    /**
     * Amount of one receipt in msats, 0 when it cannot be read.
     */
    private static function receipt_msats( array $event ): int {
        $tags = [];

        foreach ( (array) ( $event['tags'] ?? [] ) as $tag ) {
            if ( is_array( $tag ) && isset( $tag[0], $tag[1] ) && is_string( $tag[1] ) ) {
                $tags[ $tag[0] ] = $tag[1];
            }
        }

        // The zap request the receipt answers, with its amount in msats.
        if ( ! empty( $tags['description'] ) ) {
            $request = json_decode( $tags['description'], true );

            foreach ( (array) ( $request['tags'] ?? [] ) as $tag ) {
                if ( is_array( $tag ) && 'amount' === ( $tag[0] ?? '' ) && ctype_digit( (string) ( $tag[1] ?? '' ) ) ) {
                    return (int) $tag[1];
                }
            }
        }

        // Otherwise the invoice: lnbc<amount><unit>1...
        if ( ! empty( $tags['bolt11'] ) && preg_match( '/^ln(?:bc|tb|bcrt)(\d+)([munp]?)1/i', $tags['bolt11'], $m ) ) {
            $factor = [ '' => 100000000000, 'm' => 100000000, 'u' => 100000, 'n' => 100, 'p' => 0.1 ];

            return (int) floor( (int) $m[1] * $factor[ strtolower( $m[2] ) ] );
        }

        return 0;
    }
}
