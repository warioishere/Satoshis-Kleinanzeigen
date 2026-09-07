<?php

namespace SK\Core\Vendor;

defined( 'ABSPATH' ) || exit;

/**
 * No listing without a shop name and profile picture.
 *
 * Until the mandatory-contact requirement was removed, it enforced this as a
 * side effect: whoever hadn't filled those in couldn't publish, because the
 * shop-data form required a name and picture at the same time. Once that
 * requirement was dropped, this indirect effect was lost — a freshly created
 * account could list under "satoshi-cU8uP" and without a picture.
 *
 * Neither is a formality: the shop name is what buyers recognize a vendor by
 * and what trust in the Telegram channel hinges on, and an account with no
 * picture and no name is indistinguishable from a throwaway account to a
 * viewer.
 *
 * Deliberately uses the same seams as SuspensionGuard, just with a different
 * question — and deliberately only blocks publishing: the dashboard and
 * drafts stay reachable, otherwise no one could get back to where they fill
 * in what's missing.
 */
class ProfileGuard {

    public function __construct() {
        add_action( 'sk_new_product_added', [ $this, 'force_draft' ], 6, 2 );
        add_action( 'sk_product_updated',   [ $this, 'force_draft' ], 6, 2 );
        add_action( 'sk_bulk_product_status_change', [ $this, 'force_bulk_draft' ], 6, 2 );

        /*
         * Priority 20, not 6: Products::new_product_status hooks at 10 and
         * turns anything that isn't already "publish" into the status
         * configured in the settings — a "draft" set earlier would be
         * overwritten again. The old contact-lock ran at 20 for the same
         * reason.
         */
        add_filter( 'sk_get_default_product_status', [ $this, 'filter_default_status' ], 20, 2 );
        add_filter( 'sk_post_status', [ $this, 'filter_post_statuses' ], 98, 2 );

        /*
         * The notice from add_notice() only reaches someone whose listing was
         * actively pulled back. In the more common case, filter_default_status()
         * already applies: the listing is created as a draft straight away,
         * force_draft() never runs, and the vendor sees a draft with no
         * explanation at all. Hence an additional notice tied to the state
         * rather than the event — it stays on the edit page until the profile
         * is complete.
         */
        add_action( 'sk_product_content_inside_area_before', [ $this, 'show_listing_notice' ] );
    }

    /**
     * Why this listing isn't going live.
     */
    public function show_listing_notice(): void {
        $post = get_post();

        if ( ! $post instanceof \WP_Post || 'product' !== $post->post_type ) {
            return;
        }

        if ( 'publish' === $post->post_status ) {
            return;
        }

        $vendor_id = (int) $post->post_author ?: get_current_user_id();

        if ( $vendor_id !== get_current_user_id() || ! $this->guarded( $vendor_id ) ) {
            return;
        }

        $fehlt = self::missing( $vendor_id );

        if ( empty( $fehlt ) ) {
            return;
        }

        printf(
            '<div class="sk-alert sk-alert-warning">%s</div>',
            wp_kses_post( sprintf(
                /* translators: 1: list of what's missing, 2: URL of the shop data page. */
                __( 'Dieses Inserat bleibt ein Entwurf, weil dir noch %1$s fehlt. Trag das in deinem <a href="%2$s">Shop-Profil</a> nach — danach kannst du es veröffentlichen.', 'sk-core' ),
                implode( __( ' und ', 'sk-core' ), $fehlt ),
                esc_url( site_url( '/dashboard/settings/store/' ) )
            ) )
        );
    }

    /**
     * What this vendor is still missing.
     *
     * @return string[] Empty if everything is present.
     */
    public static function missing( int $vendor_id ): array {
        if ( $vendor_id <= 0 || ! function_exists( 'sk_get_store_info' ) ) {
            return [];
        }

        $info = sk_get_store_info( $vendor_id );
        $info = is_array( $info ) ? $info : [];

        $fehlt = [];

        if ( ! self::has_shop_name( $info ) ) {
            $fehlt[] = __( 'ein Shopname', 'sk-core' );
        }

        if ( ! self::has_picture( $info ) ) {
            $fehlt[] = __( 'ein Profilbild', 'sk-core' );
        }

        return $fehlt;
    }

    public static function is_complete( int $vendor_id ): bool {
        return empty( self::missing( $vendor_id ) );
    }

    /**
     * A shop name that deserves the name.
     *
     * The auto-assigned one doesn't count: every freshly created account
     * carries it, so it distinguishes no one from anyone. On live it appears
     * in two forms — sequentially numbered ("satoshi-104", the older ones)
     * and with five random characters ("satoshi-ngbru"). The pattern covers
     * both.
     */
    public static function has_shop_name( array $info ): bool {
        $name = trim( (string) ( $info['store_name'] ?? '' ) );

        if ( $name === '' ) {
            return false;
        }

        return ! preg_match( '/^satoshi-[a-z0-9]{1,8}$/i', $name );
    }

    public static function has_picture( array $info ): bool {
        foreach ( [ 'gravatar', 'icon' ] as $key ) {
            $wert = $info[ $key ] ?? '';
            $wert = is_array( $wert ) ? reset( $wert ) : $wert;

            if ( (string) $wert !== '' && (string) $wert !== '0' ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Set a single listing back to draft.
     *
     * @param int   $product_id
     * @param array $data
     */
    public function force_draft( $product_id, $data = [] ): void {
        $product_id = (int) $product_id;

        if ( $product_id <= 0 ) {
            return;
        }

        $author = (int) get_post_field( 'post_author', $product_id );

        if ( ! $author || ! $this->guarded( $author ) ) {
            return;
        }

        if ( ! in_array( get_post_status( $product_id ), [ 'publish', 'pending', 'future', 'private' ], true ) ) {
            return;
        }

        wp_update_post( [ 'ID' => $product_id, 'post_status' => 'draft' ] );

        $this->add_notice( $author );
    }

    /**
     * The same for the bulk action in the dashboard.
     *
     * @param string $status
     * @param array  $product_ids
     */
    public function force_bulk_draft( $status, $product_ids ): void {
        if ( ! in_array( $status, [ 'publish', 'pending', 'future' ], true ) ) {
            return;
        }

        $vendor_id = get_current_user_id();

        if ( ! $vendor_id || ! $this->guarded( $vendor_id ) ) {
            return;
        }

        foreach ( (array) $product_ids as $product_id ) {
            $product_id = (int) $product_id;

            if ( $product_id <= 0 || (int) get_post_field( 'post_author', $product_id ) !== $vendor_id ) {
                continue;
            }

            if ( in_array( get_post_status( $product_id ), [ 'publish', 'pending', 'future', 'private' ], true ) ) {
                wp_update_post( [ 'ID' => $product_id, 'post_status' => 'draft' ] );
            }
        }

        $this->add_notice( $vendor_id );
    }

    /**
     * New listings start as drafts as long as the profile is incomplete.
     */
    public function filter_default_status( $status, $seller_id = 0 ) {
        $seller_id = (int) ( $seller_id ?: get_current_user_id() );

        return $this->guarded( $seller_id ) ? 'draft' : $status;
    }

    /**
     * "Publish" disappears from the selection.
     */
    public function filter_post_statuses( $statuses, $product_id = 0 ) {
        $statuses = (array) $statuses;

        if ( ! $this->guarded( get_current_user_id() ) ) {
            return $statuses;
        }

        unset( $statuses['publish'], $statuses['pending'], $statuses['future'] );

        if ( ! isset( $statuses['draft'] ) && function_exists( 'sk_get_post_status' ) ) {
            $statuses['draft'] = sk_get_post_status( 'draft' );
        }

        return $statuses;
    }

    /** Does the lock apply to this user? */
    private function guarded( int $vendor_id ): bool {
        if ( $vendor_id <= 0 ) {
            return false;
        }

        if ( ! function_exists( 'sk_is_user_seller' ) || ! sk_is_user_seller( $vendor_id ) ) {
            return false;
        }

        return ! self::is_complete( $vendor_id );
    }

    private function add_notice( int $vendor_id ): void {
        if ( ! function_exists( 'sk_add_notice' ) ) {
            return;
        }

        static $gemeldet = false;

        if ( $gemeldet ) {
            return;
        }

        $gemeldet = true;

        $fehlt = self::missing( $vendor_id );

        sk_add_notice(
            wp_kses_post( sprintf(
                /* translators: 1: list of what's missing, 2: URL of the shop data page. */
                __( 'Veröffentlichung blockiert: Dir fehlt noch %1$s. Trag das in deinem <a href="%2$s">Shop-Profil</a> nach, dann kannst du dein Inserat veröffentlichen.', 'sk-core' ),
                implode( __( ' und ', 'sk-core' ), $fehlt ),
                esc_url( site_url( '/dashboard/settings/store/' ) )
            ) ),
            'error'
        );
    }
}
