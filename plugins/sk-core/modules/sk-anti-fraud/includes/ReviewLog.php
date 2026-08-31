<?php

namespace SK\Modules\AntiFraud;

defined( 'ABSPATH' ) || exit;

/**
 * Record of listings that were held back for review.
 *
 * Until now this only existed as an e-mail to the admin. When the listing and
 * the account were deleted afterwards — which is what happens with scammers —
 * nothing was left to work with, and every incident started from scratch.
 *
 * Vendor name, shop and e-mail are therefore stored as copies, not as a
 * reference: the entry has to stay readable after the account is gone.
 */
final class ReviewLog {

    public static function table(): string {
        global $wpdb;

        return $wpdb->prefix . 'sk_review_log';
    }

    public static function create_table(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta( 'CREATE TABLE ' . self::table() . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL,
            product_title varchar(255) NOT NULL DEFAULT '',
            vendor_id bigint(20) unsigned NOT NULL,
            vendor_login varchar(100) NOT NULL DEFAULT '',
            vendor_store varchar(191) NOT NULL DEFAULT '',
            vendor_email varchar(191) NOT NULL DEFAULT '',
            vendor_registered datetime DEFAULT NULL,
            matched varchar(255) NOT NULL DEFAULT '',
            trigger_type varchar(32) NOT NULL DEFAULT 'keyword',
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_vendor (vendor_id),
            KEY idx_created (created_at)
        ) " . $wpdb->get_charset_collate() . ';' );
    }

    /**
     * Write an entry. Vendor details are copied, deliberately.
     */
    public static function record( int $product_id, string $matched, string $trigger = 'keyword' ): bool {
        global $wpdb;

        $post = get_post( $product_id );

        if ( ! $post ) {
            return false;
        }

        $vendor_id = (int) $post->post_author;
        $vendor    = get_userdata( $vendor_id );

        return (bool) $wpdb->insert( self::table(), [
            'product_id'        => $product_id,
            'product_title'     => mb_substr( (string) $post->post_title, 0, 255 ),
            'vendor_id'         => $vendor_id,
            'vendor_login'      => $vendor ? $vendor->user_login : '',
            'vendor_store'      => (string) get_user_meta( $vendor_id, 'sk_store_name', true ),
            'vendor_email'      => $vendor ? $vendor->user_email : '',
            'vendor_registered' => $vendor ? $vendor->user_registered : null,
            'matched'           => mb_substr( $matched, 0, 255 ),
            'trigger_type'      => $trigger,
            'created_at'        => current_time( 'mysql' ),
        ], [ '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ] );
    }

    /**
     * @return object[]
     */
    public static function all( int $limit = 100 ): array {
        global $wpdb;

        if ( ! self::table_exists() ) {
            return [];
        }

        return (array) $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' ORDER BY created_at DESC, id DESC LIMIT %d',
            $limit
        ) );
    }

    public static function count(): int {
        global $wpdb;

        if ( ! self::table_exists() ) {
            return 0;
        }

        return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() );
    }

    /**
     * Ein einzelner Eintrag, fuer Aktionen die von der Liste ausgehen.
     */
    public static function get( int $id ) {
        global $wpdb;

        if ( ! self::table_exists() ) {
            return null;
        }

        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE id = %d',
            $id
        ) );
    }

    public static function remove( int $id ): bool {
        global $wpdb;

        return (bool) $wpdb->delete( self::table(), [ 'id' => $id ], [ '%d' ] );
    }

    private static function table_exists(): bool {
        global $wpdb;

        return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', self::table() ) ) === self::table();
    }
}
