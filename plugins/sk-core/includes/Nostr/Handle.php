<?php

namespace SK\Core\Nostr;

defined( 'ABSPATH' ) || exit;

/**
 * The name in front of the @ in a vendor's NIP-05 address.
 *
 * It follows the shop name: rename the shop and the address reads the same
 * way again. What it does not do is hand the old address to somebody else —
 * every name a vendor has ever used stays theirs and keeps resolving, so a
 * contact saved a year ago still verifies and nobody inherits a name whose
 * reputation they did not earn.
 *
 * The shop link is deliberately not part of this: it used to decide the
 * address, which left vendors with "satoshi-247@..." although they had given
 * their shop a name.
 */
final class Handle {

    /** Every name this vendor has held, newest first. */
    const META = 'sk_nip05_names';

    /**
     * Names nobody may take, because the directory answers them itself or
     * they would read as somebody else.
     */
    const RESERVED = [ '_', 'admin', 'administrator', 'root', 'support', 'sk', 'satoshiskleinanzeigen' ];

    /**
     * This vendor's current name, assigning one on first use.
     */
    public static function get( int $user_id ): string {
        $names = self::history( $user_id );

        if ( isset( $names[0] ) && '' !== $names[0] ) {
            return $names[0];
        }

        return self::adopt( $user_id, (string) ( self::store_name( $user_id ) ) );
    }

    /**
     * Take on the name that goes with this shop name.
     *
     * Called wherever a shop name is written. Keeps the previous one on the
     * list so the old address does not stop working, and leaves everything
     * alone when the wanted name belongs to someone else.
     */
    public static function adopt( int $user_id, string $shop_name ): string {
        $names   = self::history( $user_id );
        $current = (string) ( $names[0] ?? '' );
        $wanted  = self::clean( $shop_name );

        if ( '' === $wanted || ! self::free( $wanted, $user_id ) ) {
            // Nothing usable in the shop name — fall back once, then keep it.
            $wanted = '' !== $current ? $current : self::fallback( $user_id );
        }

        if ( '' === $wanted || $wanted === $current ) {
            return $current;
        }

        array_unshift( $names, $wanted );
        update_user_meta( $user_id, self::META, array_values( array_unique( $names ) ) );

        return $wanted;
    }

    /** The full address, or '' when no name could be found. */
    public static function address( int $user_id ): string {
        $name = self::get( $user_id );

        return '' === $name ? '' : $name . '@' . wp_parse_url( home_url(), PHP_URL_HOST );
    }

    /**
     * Who owns this name? Falls back to the shop link and the shop name so
     * addresses published before this existed keep resolving.
     */
    public static function owner( string $name ): ?\WP_User {
        $name = strtolower( trim( $name ) );

        if ( '' === $name ) {
            return null;
        }

        $found = get_users( [
            'meta_key'     => self::META,  // phpcs:ignore WordPress.DB.SlowDBQuery
            'meta_value'   => '"' . $name . '"', // phpcs:ignore WordPress.DB.SlowDBQuery
            'meta_compare' => 'LIKE',
            'number'       => 5,
        ] );

        // LIKE on the serialized array can only narrow the field — the match
        // itself is made on the unserialized list.
        foreach ( $found as $candidate ) {
            if ( in_array( $name, self::history( (int) $candidate->ID ), true ) ) {
                return $candidate;
            }
        }

        $user = get_user_by( 'slug', $name );

        if ( $user ) {
            return $user;
        }

        foreach ( get_users( [ 'role__in' => [ 'seller', 'administrator' ], 'number' => 500 ] ) as $candidate ) {
            if ( strtolower( self::store_name( (int) $candidate->ID ) ) === $name ) {
                return $candidate;
            }
        }

        return null;
    }

    /** Every name this vendor has held, newest first. */
    public static function history( int $user_id ): array {
        $names = get_user_meta( $user_id, self::META, true );

        return is_array( $names ) ? array_values( array_filter( $names ) ) : [];
    }

    /** Shop link, then the id — used when the shop name yields nothing. */
    private static function fallback( int $user_id ): string {
        $user = get_userdata( $user_id );
        $slug = self::clean( $user ? $user->user_nicename : '' );

        if ( '' !== $slug && self::free( $slug, $user_id ) ) {
            return $slug;
        }

        return 'sk-' . $user_id;
    }

    private static function store_name( int $user_id ): string {
        return function_exists( 'sk_get_store_info' )
            ? (string) ( sk_get_store_info( $user_id )['store_name'] ?? '' )
            : '';
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
