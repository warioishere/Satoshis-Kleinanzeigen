<?php

namespace SK\Modules\ShopImport;

defined( 'ABSPATH' ) || exit;

/**
 * Who is allowed to upload a shop catalog.
 *
 * Deliberately an opt-in per vendor rather than an open feature for all
 * 480 accounts: a catalog import creates hundreds of listings, and who's
 * allowed to do that should be a deliberate decision.
 */
final class Dealer {

    const META_ENABLED  = '_sk_dealer_import';
    const META_LAST_RUN = '_sk_dealer_last_import';

    /**
     * Reviewed by the operator.
     *
     * Deliberately kept separate from the import opt-in: "may upload a
     * catalog" and "is reviewed" are two different statements. The second
     * one is what instant checkout via sk_payments is meant to hang off
     * later — there, the buyer pays directly into the vendor's wallet
     * without escrow, which is why unreviewed vendors have no business
     * there. A combined flag would have to be split apart again later.
     */
    const META_VERIFIED    = '_sk_vendor_verified';
    const META_VERIFIED_AT = '_sk_vendor_verified_at';
    const META_VERIFIED_BY = '_sk_vendor_verified_by';

    public static function is_enabled( int $user_id ): bool {
        return (int) get_user_meta( $user_id, self::META_ENABLED, true ) === 1;
    }

    public static function set_enabled( int $user_id, bool $on ): void {
        update_user_meta( $user_id, self::META_ENABLED, $on ? 1 : 0 );
    }

    /**
     * Set the import flag after a confirmed URL.
     *
     * Previously, the opt-in followed only from may_import(); the checkbox
     * in the admin then stayed unchecked even though the dealer was
     * allowed to import. The flag gets set, but never removed
     * automatically — an expired confirmation shouldn't revoke an opt-in
     * the operator may have granted themselves. Taking it away remains
     * their decision.
     */
    public static function enable_on_verification( int $user_id ): void {
        if ( $user_id > 0 && ! self::is_enabled( $user_id ) ) {
            self::set_enabled( $user_id, true );
        }
    }

    public static function is_verified( int $user_id ): bool {
        return (int) get_user_meta( $user_id, self::META_VERIFIED, true ) === 1;
    }

    public static function set_verified( int $user_id, bool $on, int $by = 0 ): void {
        update_user_meta( $user_id, self::META_VERIFIED, $on ? 1 : 0 );

        if ( $on ) {
            update_user_meta( $user_id, self::META_VERIFIED_AT, time() );
            update_user_meta( $user_id, self::META_VERIFIED_BY, $by ?: get_current_user_id() );
        }
    }

    /**
     * Is this vendor allowed to upload a catalog?
     *
     * Two paths get you there. One works without the operator's
     * involvement: whoever has confirmed a URL via back-link is allowed to
     * import — what they list is limited by their pack anyway. The other
     * remains the previous manual path; it's still needed because not
     * every site can be fetched from this server (see VerifiedLinks).
     *
     * Explicitly NOT the same as is_verified(): a confirmed domain proves
     * control over a domain, not that someone is a trustworthy dealer. The
     * "reviewed" checkbox is what instant checkout will hang off later,
     * where money moves without escrow — that stays a deliberate decision.
     */
    public static function may_import( int $user_id ): bool {
        if ( \SK\Core\Verification\VerifiedLinks::is_verified( $user_id ) ) {
            return true;
        }

        return self::is_enabled( $user_id ) && self::is_verified( $user_id );
    }

    /**
     * All enabled dealers.
     *
     * @return \WP_User[]
     */
    /**
     * All dealers — enabled ones and self-confirmed ones.
     *
     * Anyone who has confirmed their domain may import without the
     * operator checking a box. If they weren't in this list, the admin
     * would look as if nothing had happened — and no one would think to
     * review them either.
     *
     * @return \WP_User[]
     */
    public static function all(): array {
        $freigeschaltet = get_users(
            [
                'meta_key'   => self::META_ENABLED,
                'meta_value' => 1,
                'number'     => 200,
            ]
        );

        $bestaetigt = get_users(
            [
                'meta_key'     => \SK\Core\Verification\VerifiedLinks::META_UNTIL,
                'meta_value'   => time(),
                'meta_compare' => '>',
                'meta_type'    => 'NUMERIC',
                'number'       => 200,
            ]
        );

        $alle = [];

        foreach ( array_merge( $freigeschaltet, $bestaetigt ) as $user ) {
            $alle[ $user->ID ] = $user;
        }

        return array_values( $alle );
    }

}
