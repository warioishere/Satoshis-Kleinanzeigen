<?php

namespace SK\Modules\Escrow;

defined( 'ABSPATH' ) || exit;

/**
 * The goodwill fund (§2, §7): a ledger over the platform's own money, not
 * a wallet. Every settled fee credits its share, every paid claim debits;
 * the balance is the sum. The sats themselves sit in the platform's
 * wallet with everything else.
 */
final class Pool {

    const DB_VERSION     = '1';
    const VERSION_OPTION = 'sk_escrow_pool_db_version';

    /** Option holding the published balance: [ 'sats' => int, 'at' => 'Y-m-d' ]. */
    const PUBLISHED_OPTION = 'sk_escrow_pool_published';

    const KIND_FEE_SHARE = 'fee_share';
    const KIND_CLAIM     = 'claim_payout';

    public static function table(): string {
        global $wpdb;

        return $wpdb->prefix . 'sk_escrow_pool';
    }

    public static function maybe_install(): void {
        if ( get_option( self::VERSION_OPTION ) === self::DB_VERSION ) {
            return;
        }

        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta(
            'CREATE TABLE ' . self::table() . " (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                ts BIGINT NOT NULL,
                kind VARCHAR(20) NOT NULL,
                sats BIGINT NOT NULL,
                escrow_hash CHAR(64) NOT NULL DEFAULT '',
                user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY  (id),
                KEY escrow_hash (escrow_hash)
            ) {$wpdb->get_charset_collate()};"
        );

        update_option( self::VERSION_OPTION, self::DB_VERSION );
    }

    /** Credit (positive) or debit (negative) the fund. Once per escrow and kind. */
    public static function add( string $kind, int $sats, string $escrow_hash, int $user_id = 0 ): bool {
        self::maybe_install();

        global $wpdb;

        if ( 0 === $sats ) {
            return false;
        }

        if ( '' !== $escrow_hash ) {
            $seen = $wpdb->get_var( $wpdb->prepare(
                'SELECT id FROM ' . self::table() . ' WHERE escrow_hash = %s AND kind = %s LIMIT 1',
                $escrow_hash,
                $kind
            ) );

            if ( $seen ) {
                return false;
            }
        }

        return (bool) $wpdb->insert( self::table(), [
            'ts'          => time(),
            'kind'        => $kind,
            'sats'        => $sats,
            'escrow_hash' => $escrow_hash,
            'user_id'     => $user_id,
        ], [ '%d', '%s', '%d', '%s', '%d' ] );
    }

    public static function balance(): int {
        self::maybe_install();

        global $wpdb;

        return (int) $wpdb->get_var( 'SELECT COALESCE( SUM( sats ), 0 ) FROM ' . self::table() );
    }

    /** Record the balance for the rulebook page (§2: published monthly). */
    public static function publish(): void {
        update_option( self::PUBLISHED_OPTION, [ 'sats' => self::balance(), 'at' => wp_date( 'Y-m-d' ) ] );
    }

    /** @return array{sats:int, at:string} */
    public static function published(): array {
        $v = get_option( self::PUBLISHED_OPTION, [] );

        return [ 'sats' => (int) ( $v['sats'] ?? 0 ), 'at' => (string) ( $v['at'] ?? '' ) ];
    }
}
