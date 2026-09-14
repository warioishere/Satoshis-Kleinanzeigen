<?php

namespace SK\Modules\ShopImport;

defined( 'ABSPATH' ) || exit;

/**
 * Variants of a listing — editable from a larger package upward.
 *
 * Deliberately not WooCommerce variations: their selection lives in the
 * cart form, which is disabled in catalog mode. What the buyer needs is
 * the information "available in three variants, starting at 169 francs" —
 * they can't buy through the cart anyway, they contact the seller.
 *
 * The data record is the same one the import writes, including the key
 * and its own sats amount per variant.
 */
final class Variants {

    /**
     * The shop packages as a ladder, smallest first.
     *
     * A rung is a pack group, not a listing count. The limits belong to
     * pricing and get moved around; a feature must not change hands because
     * a number was raised. The group also survives renaming the package and
     * is shared by all terms of it, so the three-month and yearly siblings
     * sit on the same rung without being listed.
     *
     * A package outside this list — the free one — unlocks nothing.
     */
    const LADDER = [ 'krabbe', 'delphin', 'hai', 'wal' ];

    public function __construct() {
        add_action( 'sk_product_edit_after_pricing_fields', [ $this, 'render_field' ], 10, 2 );
        add_action( 'sk_process_product_meta', [ $this, 'save' ], 20 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ], 20 );
    }

    /**
     * The group a package belongs to, e.g. "krabbe".
     */
    private static function group_of( int $pack_id ): string {
        if ( $pack_id <= 0 ) {
            return '';
        }

        return class_exists( \SK\Modules\Subscription\Durations::class )
            ? \SK\Modules\Subscription\Durations::group( $pack_id )
            : (string) get_post_meta( $pack_id, \SK\Modules\Subscription\Durations::META_GROUP, true );
    }

    /**
     * Does this package sit at $group or higher on the ladder?
     *
     * A package off the ladder answers no, so a new one unlocks nothing
     * until it is listed — the safe direction.
     */
    public static function from_group( int $pack_id, string $group ): bool {
        $needed = array_search( $group, self::LADDER, true );
        $rung   = array_search( self::group_of( $pack_id ), self::LADDER, true );

        return false !== $needed && false !== $rung && $rung >= $needed;
    }

    /**
     * Packages that allow variants.
     *
     * @return int[]
     */
    public static function allowed_packs(): array {
        global $wpdb;

        $ids   = $wpdb->get_col( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_pack_validity'" );
        $packs = [];

        foreach ( $ids as $id ) {
            if ( self::pack_allows( (int) $id ) ) {
                $packs[] = (int) $id;
            }
        }

        return $packs;
    }

    /**
     * Does this package allow variants? For the package card in the subscription area.
     */
    public static function pack_allows( int $pack_id ): bool {
        return self::from_group( $pack_id, 'krabbe' );
    }

    /**
     * Does the catalog import belong to this package?
     */
    public static function import_pack_allows( int $pack_id ): bool {
        return self::from_group( $pack_id, 'delphin' );
    }

    /**
     * Does this vendor's package include the catalog import?
     */
    public static function import_allowed( int $vendor_id = 0 ): bool {
        $vendor_id = $vendor_id ?: get_current_user_id();

        if ( ! $vendor_id ) {
            return false;
        }

        return self::import_pack_allows( (int) get_user_meta( $vendor_id, 'product_package_id', true ) );
    }

    /**
     * Does bulk editing belong to this package?
     */
    public static function bulk_pack_allows( int $pack_id ): bool {
        return self::from_group( $pack_id, 'hai' );
    }

    /**
     * Does this vendor's package include bulk editing?
     */
    public static function bulk_allowed( int $vendor_id = 0 ): bool {
        $vendor_id = $vendor_id ?: get_current_user_id();

        if ( ! $vendor_id ) {
            return false;
        }

        return self::bulk_pack_allows( (int) get_user_meta( $vendor_id, 'product_package_id', true ) );
    }

    /**
     * Does the revenue report belong to this package?
     */
    public static function revenue_pack_allows( int $pack_id ): bool {
        return self::from_group( $pack_id, 'hai' );
    }

    /**
     * Does it belong to this vendor's package?
     */
    public static function revenue_allowed( int $vendor_id = 0 ): bool {
        $vendor_id = $vendor_id ?: get_current_user_id();

        if ( ! $vendor_id ) {
            return false;
        }

        return self::revenue_pack_allows( (int) get_user_meta( $vendor_id, 'product_package_id', true ) );
    }

    public static function is_allowed( int $vendor_id = 0 ): bool {
        $vendor_id = $vendor_id ?: get_current_user_id();
        if ( ! $vendor_id ) {
            return false;
        }

        return self::pack_allows( (int) get_user_meta( $vendor_id, 'product_package_id', true ) );
    }

    /**
     * Name of the cheapest package that allows variants — for the notice.
     */
    public static function cheapest_allowed_pack(): ?array {
        $best = null;

        foreach ( self::allowed_packs() as $id ) {
            $post = get_post( $id );
            if ( ! $post || $post->post_status !== 'publish' ) {
                continue;
            }
            $price = (int) get_post_meta( $id, '_price', true );
            if ( $best === null || $price < $best['price'] ) {
                $best = [ 'id' => $id, 'name' => $post->post_title, 'price' => $price ];
            }
        }

        return $best;
    }

    /**
     * @return array<int,array{key:string,name:string,price:?float,currency:string,sats:?int}>
     */
    public static function get( int $post_id ): array {
        $variants = get_post_meta( $post_id, Importer::META_VARIANTS, true );

        return is_array( $variants ) ? $variants : [];
    }

    public function enqueue(): void {
        if ( ! function_exists( 'sk_is_seller_dashboard' ) || ! sk_is_seller_dashboard() ) {
            return;
        }

        wp_enqueue_script(
            'sk-variants',
            SK_SHOP_IMPORT_URL . '/assets/js/sk-variants.js',
            [],
            SK_SHOP_IMPORT_VERSION,
            true
        );
    }

    public function render_field( $post = null, $post_id = 0 ): void {
        $post_id  = (int) ( $post_id ?: ( $post->ID ?? 0 ) );
        $allowed  = self::is_allowed();
        $variants = $post_id ? self::get( $post_id ) : [];
        $pack     = self::cheapest_allowed_pack();

        // Unit of the listing — for imported items this is the currency
        // from the file, otherwise sats.
        $currency = PriceUnit::current( $post_id );

        include SK_SHOP_IMPORT_PATH . '/templates/variants-field.php';
    }

    /**
     * Save variants.
     */
    public function save( $post_id ): void {
        $post_id = (int) $post_id;
        if ( ! $post_id ) {
            return;
        }

        // Without a matching package, nothing is written — and any existing
        // data stays untouched instead of silently disappearing on save.
        if ( ! self::is_allowed() ) {
            return;
        }

        if ( ! isset( $_POST['sk_variant_name'] ) || ! is_array( $_POST['sk_variant_name'] ) ) {
            return;
        }

        $names  = array_map( 'sanitize_text_field', wp_unslash( $_POST['sk_variant_name'] ) );
        $prices = isset( $_POST['sk_variant_price'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['sk_variant_price'] ) ) : [];

        // Same unit as the listing price — two units in one listing wouldn't
        // be comparable, and the "from" price would be nonsensical.
        $currency = PriceUnit::posted( $post_id );

        $existing = [];
        foreach ( self::get( $post_id ) as $variant ) {
            $existing[ $variant['name'] ] = $variant['key'] ?? '';
        }

        $clean = [];
        foreach ( $names as $index => $name ) {
            $name = trim( $name );
            if ( $name === '' ) {
                continue;
            }

            $amount = Importer::parse_price( (string) ( $prices[ $index ] ?? '' ) );
            $fiat   = null;
            $sats   = null;

            if ( $amount !== null && $amount > 0 ) {
                if ( $currency === 'SATS' ) {
                    $sats = (int) round( $amount );
                } else {
                    $fiat      = $amount;
                    $converted = Rate::to_sats( $fiat, $currency );
                    $sats      = is_wp_error( $converted ) ? null : (int) $converted;
                }
            }

            $clean[] = [
                // Keep the existing key so later references — e.g. an instant
                // purchase via sk_payments — don't point into thin air.
                'key'      => $existing[ $name ] ?? substr( md5( $name ), 0, 12 ),
                'name'     => $name,
                'price'    => $fiat,
                'currency' => $currency,
                'sats'     => $sats,
            ];
        }

        if ( empty( $clean ) ) {
            delete_post_meta( $post_id, Importer::META_VARIANTS );
            delete_post_meta( $post_id, Importer::META_FROM );
            return;
        }

        update_post_meta( $post_id, Importer::META_VARIANTS, $clean );
        update_post_meta( $post_id, Importer::META_FROM, count( $clean ) > 1 ? 1 : 0 );

        // The listing price is the cheapest one — hence "from".
        $lowest = null;
        foreach ( $clean as $variant ) {
            if ( $variant['sats'] === null || $variant['sats'] <= 0 ) {
                continue;
            }
            if ( $lowest === null || $variant['sats'] < $lowest['sats'] ) {
                $lowest = $variant;
            }
        }

        if ( $lowest === null ) {
            return;
        }

        // For fiat, store the same amount as the import: the parenthetical
        // note on the product page and the daily rate update both depend on
        // it. Without it, the sats price of manually maintained listings
        // would stand still while imported ones keep moving with the rate.
        if ( $lowest['price'] !== null ) {
            update_post_meta( $post_id, Importer::META_FIAT, $lowest['price'] );
            update_post_meta( $post_id, Importer::META_CURRENCY, $currency );
        }

        $product = wc_get_product( $post_id );
        if ( $product ) {
            $product->set_regular_price( (string) $lowest['sats'] );
            $product->set_price( (string) $lowest['sats'] );
            $product->save();
        }
    }
}
