<?php

namespace SK\Core\Nostr;

defined( 'ABSPATH' ) || exit;

/**
 * The name in front of the @ in a vendor's NIP-05 address.
 *
 * It used to be the WordPress slug, which had two faults: someone who set a
 * shop name still ended up as "satoshi-247@…", and changing the shop link
 * silently changed their Nostr identity — every verification anybody had
 * already done broke.
 *
 * So the name is chosen once from the shop name and then kept. A NIP-05
 * address is an identifier: it may be ugly, but it must not move.
 */
final class Handle {

    /** User meta holding the chosen name. */
    const META = 'sk_nip05_name';

    /**
     * Names nobody may take, because the directory answers them itself or
     * they would read as somebody else.
     */
    const RESERVED = [ '_', 'admin', 'administrator', 'root', 'support', 'sk', 'satoshiskleinanzeigen' ];

    /**
     * This vendor's name, assigning one on first use.
     *
     * Stored on the way out so it stays put afterwards, whatever happens to
     * the shop name or the shop link.
     */
    public static function get( int $user_id ): string {
        $stored = (string) get_user_meta( $user_id, self::META, true );

        if ( '' !== $stored ) {
            return $stored;
        }

        $name = self::pick( $user_id );

        if ( '' !== $name ) {
            update_user_meta( $user_id, self::META, $name );
        }

        return $name;
    }

    /** The full address, or '' when no name could be found. */
    public static function address( int $user_id ): string {
        $name = self::get( $user_id );

        return '' === $name ? '' : $name . '@' . wp_parse_url( home_url(), PHP_URL_HOST );
    }

    /**
     * Who owns this name? Falls back to the shop link and the shop name so
     * addresses that were published before this existed keep resolving.
     */
    public static function owner( string $name ): ?\WP_User {
        $name = strtolower( trim( $name ) );

        if ( '' === $name ) {
            return null;
        }

        $found = get_users( [
            'meta_key'   => self::META, // phpcs:ignore WordPress.DB.SlowDBQuery
            'meta_value' => $name,      // phpcs:ignore WordPress.DB.SlowDBQuery
            'number'     => 1,
        ] );

        if ( $found ) {
            return $found[0];
        }

        $user = get_user_by( 'slug', $name );

        if ( $user ) {
            return $user;
        }

        foreach ( get_users( [ 'role__in' => [ 'seller', 'administrator' ], 'number' => 500 ] ) as $candidate ) {
            $shop = function_exists( 'sk_get_store_info' )
                ? (string) ( sk_get_store_info( $candidate->ID )['store_name'] ?? '' )
                : '';

            if ( '' !== $shop && strtolower( $shop ) === $name ) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Pick a free name: shop name first, then the shop link, then the id.
     *
     * A number is the last resort rather than the default — it says nothing
     * about the vendor, and that was the whole complaint.
     */
    private static function pick( int $user_id ): string {
        $info = function_exists( 'sk_get_store_info' ) ? sk_get_store_info( $user_id ) : [];
        $user = get_userdata( $user_id );

        $wishes = [
            (string) ( $info['store_name'] ?? '' ),
            $user ? $user->user_nicename : '',
            'sk-' . $user_id,
        ];

        foreach ( $wishes as $wish ) {
            $name = self::clean( $wish );

            if ( '' !== $name && self::free( $name, $user_id ) ) {
                return $name;
            }
        }

        // Every wish taken: hang the id on the best one we had.
        $base = self::clean( (string) ( $info['store_name'] ?? '' ) ) ?: 'sk';

        return $base . '-' . $user_id;
    }

    /** Down to what a NIP-05 name may contain: a-z, 0-9, -_.+ */
    private static function clean( string $value ): string {
        $value = strtolower( remove_accents( trim( $value ) ) );
        $value = preg_replace( '/[^a-z0-9_.+-]+/', '-', $value );
        $value = trim( (string) $value, '-._+' );

        return (string) substr( (string) $value, 0, 40 );
    }

    private static function free( string $name, int $user_id ): bool {
        if ( in_array( $name, self::RESERVED, true ) ) {
            return false;
        }

        $owner = self::owner( $name );

        return ! $owner || (int) $owner->ID === $user_id;
    }
}
