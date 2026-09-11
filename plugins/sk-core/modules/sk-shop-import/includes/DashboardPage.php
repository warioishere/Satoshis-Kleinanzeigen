<?php

namespace SK\Modules\ShopImport;

use SK\Core\Dashboard\DashboardModule;

defined( 'ABSPATH' ) || exit;

/**
 * Vendor dashboard: upload and import a catalog.
 *
 * Reached from the products page, where the import sits as a button next
 * to "create listing" — it has no entry of its own in the dashboard menu.
 */
class DashboardPage extends DashboardModule {

    const NONCE = 'sk_shop_import';

    /**
     * Most recently fetched Shopify shop.
     *
     * The origin label of imported listings and the currency suggestion
     * (based on the domain suffix) both hang off this.
     */
    const META_FETCH_URL = '_sk_import_shopify_url';

    /**
     * Always register, not just for dealers.
     *
     * The query variable and rewrite rule are global; if registration
     * depended on the current user, the URL would exist for no one and the
     * page would report "not found". Who is allowed to see it is governed
     * by the capability — the registry checks it both in the menu and on
     * the actual request.
     */
    public function config(): ?array {
        return [
            'slug'       => 'shop-import',
            'title'      => __( 'Shop-Import', 'sk-core' ),
            'icon'       => '<i class="fas fa-file-import"></i>',
            'pos'        => 31,
            // No menu entry: the page belongs to the products list and is
            // opened from the button next to "create listing" there.
            'in_menu'    => false,
            // The page is open to every vendor — anyone not yet enabled
            // finds the path to verification here. The import steps inside
            // it check Dealer::may_import().
            'permission' => 'sk_view_overview_menu',
            // Path instead of a callback, with data via template_args — the
            // same pattern as the watchlist and inquiries. This way the
            // template brings the dashboard shell with menu and containers.
            'template'      => 'dashboard/shop-import/dashboard-shop-import',
            'template_args' => [ $this, 'view_data' ],
        ];
    }

    protected function register_extras(): void {
        add_action( 'template_redirect', [ $this, 'handle_post' ] );
        add_action( 'wp_ajax_sk_shop_import_batch', [ $this, 'ajax_batch' ] );
    }

    private function url(): string {
        return function_exists( 'sk_get_navigation_url' )
            ? sk_get_navigation_url( 'shop-import' )
            : home_url( '/dashboard/shop-import/' );
    }

    /**
     * Accept upload and import.
     */
    /**
     * Process one batch. The browser keeps calling this until done.
     */
    public function ajax_batch(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        $vendor_id = get_current_user_id();
        if ( ! $vendor_id || ! Dealer::may_import( $vendor_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Keine Berechtigung.', 'sk-core' ) ] );
        }

        $step = Job::step( $vendor_id );

        if ( is_wp_error( $step ) ) {
            wp_send_json_error( [ 'message' => $step->get_error_message() ] );
        }

        $step['weiter'] = add_query_arg( 'schritt', 'fertig', $this->url() );

        wp_send_json_success( $step );
    }

    public function handle_post(): void {
        if ( strtoupper( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) !== 'POST' ) {
            return;
        }
        if ( ! isset( $_POST['sk_shop_import_nonce'] ) || ! wp_verify_nonce( $_POST['sk_shop_import_nonce'], self::NONCE ) ) {
            return;
        }

        $vendor_id = get_current_user_id();
        if ( ! $vendor_id || ! function_exists( 'sk_is_user_seller' ) || ! sk_is_user_seller( $vendor_id ) ) {
            return;
        }

        $step = sanitize_key( wp_unslash( $_POST['sk_step'] ?? '' ) );

        if ( ! Dealer::may_import( $vendor_id ) ) {
            return;
        }

        if ( $step === 'upload' ) {
            $path = Storage::accept( $_FILES['sk_csv'] ?? [], $vendor_id );

            if ( is_wp_error( $path ) ) {
                set_transient( 'sk_import_msg_' . $vendor_id, $path->get_error_message(), 120 );
                wp_safe_redirect( $this->url() );
                exit;
            }

            update_user_meta( $vendor_id, '_sk_import_file', $path );
            wp_safe_redirect( add_query_arg( 'schritt', 'zuordnen', $this->url() ) );
            exit;
        }

        if ( $step === 'holen' ) {
            /*
             * The dealer enters the URL themselves — they know where their
             * shop is, the operator doesn't need to register it beforehand.
             *
             * This makes it user input, unlike the rest of the module. The
             * fetch therefore goes through wp_safe_remote_get(), which
             * rejects internal address ranges, and the page is open only to
             * enabled dealers anyway (Dealer::may_import()).
             */
            $shop = isset( $_POST['sk_shop_url'] )
                ? esc_url_raw( trim( wp_unslash( $_POST['sk_shop_url'] ) ) )
                : '';

            if ( $shop === '' || ! in_array( wp_parse_url( $shop, PHP_URL_SCHEME ), [ 'http', 'https' ], true ) ) {
                set_transient( 'sk_import_msg_' . $vendor_id, __( 'Bitte gib die Adresse deines Shops an, zum Beispiel https://mein-shop.myshopify.com.', 'sk-core' ), 120 );
                wp_safe_redirect( $this->url() );
                exit;
            }

            /*
             * Fetching only happens from the domain the dealer has
             * confirmed. Otherwise confirmation would be a mere formality:
             * claim your own site once, then import arbitrary foreign
             * catalogs afterward.
             *
             * Anyone enabled manually has no confirmed host — the operator
             * has already looked at that case directly.
             */
            if ( \SK\Core\Verification\VerifiedLinks::is_verified( $vendor_id )
                && ! \SK\Core\Verification\VerifiedLinks::covers( $vendor_id, $shop ) ) {
                set_transient(
                    'sk_import_msg_' . $vendor_id,
                    sprintf(
                        /* translators: %s: confirmed hostname. */
                        __( 'Du kannst nur von einer Adresse holen, die du bestätigt hast. Bestätigt sind: %s.', 'sk-core' ),
                        implode( ', ', \SK\Core\Verification\VerifiedLinks::confirmed_hosts( $vendor_id ) )
                    ),
                    120
                );
                wp_safe_redirect( $this->url() );
                exit;
            }

            $products = Shopify::fetch( $shop );

            if ( is_wp_error( $products ) ) {
                set_transient( 'sk_import_msg_' . $vendor_id, $products->get_error_message(), 120 );
                wp_safe_redirect( $this->url() );
                exit;
            }

            $path = Storage::put_catalog( (string) wp_json_encode( [ 'products' => $products ] ), $vendor_id );

            if ( is_wp_error( $path ) ) {
                set_transient( 'sk_import_msg_' . $vendor_id, $path->get_error_message(), 120 );
                wp_safe_redirect( $this->url() );
                exit;
            }

            // Otherwise the previous source would linger as a dead file.
            $vorige = (string) get_user_meta( $vendor_id, '_sk_import_file', true );
            if ( $vorige !== '' && Storage::belongs_to( $vorige, $vendor_id ) ) {
                Storage::forget( $vorige );
            }

            // Next time, the URL will already be pre-filled in the field.
            update_user_meta( $vendor_id, self::META_FETCH_URL, $shop );

            update_user_meta( $vendor_id, '_sk_import_file', $path );
            wp_safe_redirect( add_query_arg( 'schritt', 'zuordnen', $this->url() ) );
            exit;
        }

        if ( $step === 'run' ) {
            $this->run_import( $vendor_id );
            exit;
        }
    }

    private function run_import( int $vendor_id ): void {
        $path = (string) get_user_meta( $vendor_id, '_sk_import_file', true );

        if ( $path === '' || ! Storage::belongs_to( $path, $vendor_id ) ) {
            set_transient( 'sk_import_msg_' . $vendor_id, __( 'Die hochgeladene Datei wurde nicht gefunden. Bitte erneut hochladen.', 'sk-core' ), 120 );
            wp_safe_redirect( $this->url() );
            exit;
        }

        $mapping = [];

        // A fetched catalog doesn't need a column mapping.
        if ( ! Source::is_json( $path ) ) {
            $csv = Csv::read( $path );
            if ( is_wp_error( $csv ) ) {
                set_transient( 'sk_import_msg_' . $vendor_id, $csv->get_error_message(), 120 );
                wp_safe_redirect( $this->url() );
                exit;
            }

            $mapping = Csv::guess_mapping( $csv['headers'] );
            foreach ( array_keys( Csv::FIELDS ) as $field ) {
                if ( isset( $_POST[ 'map_' . $field ] ) ) {
                    $mapping[ $field ] = (int) $_POST[ 'map_' . $field ];
                }
            }
        }

        // Save the category mapping so it's already there for the next import.
        $map = [];
        foreach ( (array) ( $_POST['cat_map'] ?? [] ) as $name => $term ) {
            $map[ sanitize_text_field( wp_unslash( $name ) ) ] = (int) $term;
        }
        Settings::save_category_map( $vendor_id, $map );
        Settings::save_default_category( $vendor_id, (int) ( $_POST['sk_default_cat'] ?? 0 ) );
        Settings::save_currency( $vendor_id, sanitize_text_field( wp_unslash( $_POST['sk_currency'] ?? '' ) ) );

        $items = Source::items( $path, $mapping );

        if ( is_wp_error( $items ) ) {
            set_transient( 'sk_import_msg_' . $vendor_id, $items->get_error_message(), 120 );
            wp_safe_redirect( $this->url() );
            exit;
        }

        // Only take the checked ones. With no selection in the form, everything applies.
        // The pack lock for variants is enforced by the importer itself.
        $chosen = array_filter( array_map( 'strval', (array) ( $_POST['sk_pick'] ?? [] ) ) );
        if ( ! empty( $chosen ) ) {
            $items = array_values(
                array_filter( $items, static fn( $item ) => in_array( (string) ( $item['key'] ?? '' ), $chosen, true ) )
            );
        }

        // The quota applies to dealers too — anyone wanting to list more
        // needs a bigger pack or has to select fewer items.
        $quota = Quota::check( $vendor_id, count( $items ) );
        if ( ! $quota['ok'] ) {
            set_transient( 'sk_import_quota_' . $vendor_id, $quota, 600 );
            wp_safe_redirect( add_query_arg( 'schritt', 'kontingent', $this->url() ) );
            exit;
        }

        // Don't import right away, create a job instead: the images alone
        // make even half a dozen items slower than PHP's time limit allows.
        Job::create(
            $vendor_id,
            $path,
            $mapping,
            $chosen,
            [
                'vendor_id'    => $vendor_id,
                'currency'     => sanitize_text_field( wp_unslash( $_POST['sk_currency'] ?? 'EUR' ) ),
                'default_cat'  => Settings::default_category( $vendor_id ),
                'image_cap'    => max( 0, (int) ( $_POST['sk_image_cap'] ?? Importer::DEFAULT_IMAGE_CAP ) ),
                'status'       => self::import_status(),
                // Origin of the listings: the shop they were fetched from.
                // For an uploaded file there is none — the file doesn't
                // say where it came from.
                'source'       => Source::is_json( $path )
                    ? (string) get_user_meta( $vendor_id, self::META_FETCH_URL, true )
                    : '',
                'category_map' => Settings::category_map( $vendor_id ),
            ],
            count( $items )
        );

        wp_safe_redirect( add_query_arg( 'schritt', 'laeuft', $this->url() ) );
        exit;
    }

    /**
     * Status of the imported listings.
     *
     * Only the two values the form offers — and if new listings on this
     * site need review, that applies here too. Otherwise the import would
     * be a way around review.
     */
    private static function import_status(): string {
        $wanted = sanitize_key( wp_unslash( $_POST['sk_status'] ?? 'publish' ) ); // phpcs:ignore WordPress.Security.NonceVerification

        if ( ! in_array( $wanted, [ 'publish', 'draft' ], true ) ) {
            $wanted = 'draft';
        }

        if ( $wanted === 'publish' && function_exists( 'sk_get_default_product_status' ) ) {
            $default = sk_get_default_product_status( get_current_user_id() );
            if ( $default !== 'publish' ) {
                return $default;
            }
        }

        return $wanted;
    }

    /**
     * Data for the template. Runs before inclusion; the template only renders.
     */
    public function view_data( $query_vars = [] ): array {
        $vendor_id = get_current_user_id();
        $step      = isset( $_GET['schritt'] ) ? sanitize_key( wp_unslash( $_GET['schritt'] ) ) : 'start';
        $message   = get_transient( 'sk_import_msg_' . $vendor_id );
        if ( $message ) {
            delete_transient( 'sk_import_msg_' . $vendor_id );
        }

        $csv     = null;
        $mapping = [];
        $path    = (string) get_user_meta( $vendor_id, '_sk_import_file', true );

        $items      = [];
        $csv_cats   = [];
        $quota      = null;
        $item_count = 0;
        $rows       = 0;
        // A fetched catalog brings its own structure; the mapping form
        // doesn't belong on the page then.
        $is_json    = false;

        if ( $step === 'zuordnen' && $path !== '' && Storage::belongs_to( $path, $vendor_id ) ) {
            $is_json = Source::is_json( $path );

            if ( ! $is_json ) {
                $csv = Csv::read( $path, 5 );
                if ( ! is_wp_error( $csv ) ) {
                    $mapping = Csv::guess_mapping( $csv['headers'] );
                } else {
                    $message = $csv->get_error_message();
                    $csv     = null;
                }
            }

            if ( $is_json || $csv ) {
                $built = Source::items( $path, $mapping );

                if ( is_wp_error( $built ) ) {
                    $message = $built->get_error_message();
                } else {
                    $items      = $built;
                    $item_count = count( $items );
                    $csv_cats   = Catalog::categories( $items );
                    $quota      = Quota::check( $vendor_id, $item_count );
                    $rows       = Source::count( $path );
                }
            }
        }

        $result = $step === 'fertig' ? get_transient( 'sk_import_result_' . $vendor_id ) : null;
        if ( $result ) {
            delete_transient( 'sk_import_result_' . $vendor_id );
        }

        $quota_block = $step === 'kontingent' ? get_transient( 'sk_import_quota_' . $vendor_id ) : null;
        if ( $quota_block ) {
            delete_transient( 'sk_import_quota_' . $vendor_id );
        }

        // Summary of what the import will do — this is the information a
        // person decides "yes, go" on. The column mapping only matters
        // when it was guessed wrong.
        $summary = [];
        if ( $items ) {
            $with_variants = 0;
            $drafts        = 0;
            $with_images   = 0;
            $without_price = 0;

            foreach ( $items as $item ) {
                if ( ! empty( $item['variants'] ) ) {
                    $with_variants++;
                }
                if ( ! empty( $item['draft'] ) ) {
                    $drafts++;
                }
                if ( trim( (string) ( $item['images'] ?? '' ) ) !== '' ) {
                    $with_images++;
                }
                if ( Importer::parse_price( (string) ( $item['price'] ?? '' ) ) === null ) {
                    $without_price++;
                }
            }

            $summary = [
                'rows'          => $rows,
                'items'         => $item_count,
                'variants'      => $with_variants,
                'drafts'        => $drafts,
                'images'        => $with_images,
                'without_price' => $without_price,
                'categories'    => count( $csv_cats ),
                'unmapped'      => count( array_filter( $mapping, static fn( $i ) => $i < 0 ) ),
            ];
        }

        // Items with variants can't be imported without a matching pack;
        // they get flagged instead of silently going missing.
        $variants_allowed = Variants::is_allowed( $vendor_id );
        $variants_pack    = $variants_allowed ? null : Variants::cheapest_allowed_pack();
        $blocked          = 0;

        if ( ! $variants_allowed ) {
            foreach ( $items as $item ) {
                if ( ! empty( $item['variants'] ) ) {
                    $blocked++;
                }
            }
        }

        $currency_guess   = Settings::currency( $vendor_id );
        $subscription_url = function_exists( 'sk_get_navigation_url' ) ? sk_get_navigation_url( 'subscription' ) : home_url( '/dashboard/subscription/' );

        $packs        = $quota_block ? Quota::packs_for( (int) $quota_block['needed'] ) : [];
        $stay_online  = Quota::listings_stay_online();
        $saved_map    = Settings::category_map( $vendor_id );
        $default_cat  = Settings::default_category( $vendor_id );

        // A job survives closing the browser window. If one is pending, the
        // page shows it instead of pretending nothing happened.
        $job = Job::get( $vendor_id );
        if ( $job && $step === 'start' ) {
            $step = 'laeuft';
        }

        $categories = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false ] );
        $rate       = Rate::btc_rate( 'EUR' );
        $url        = $this->url();
        // Only the most recently fetched one. The URL from the dealer
        // profile doesn't belong here — it describes the dealer's shop,
        // not the source of a Shopify fetch, and would show up as a
        // suggestion for a WooCommerce dealer that could never work.
        $shop_url   = (string) get_user_meta( $vendor_id, self::META_FETCH_URL, true );

        // Which hosts this dealer has confirmed — the fetch is restricted
        // to those.
        $verified_hosts = \SK\Core\Verification\VerifiedLinks::confirmed_hosts( $vendor_id );

        // Is he even allowed to import? If not, the page shows the path
        // there instead of a form that silently does nothing.
        $may_import   = Dealer::may_import( $vendor_id );
        $verify_url   = function_exists( 'sk_get_navigation_url' )
            ? sk_get_navigation_url( 'verification' )
            : home_url( '/dashboard/verification/' );

        return compact(
            'step',
            'url',
            'message',
            'csv',
            'is_json',
            'shop_url',
            'verified_hosts',
            'may_import',
            'verify_url',
            'mapping',
            'items',
            'item_count',
            'csv_cats',
            'quota',
            'quota_block',
            'packs',
            'stay_online',
            'saved_map',
            'default_cat',
            'categories',
            'rate',
            'result',
            'summary',
            'subscription_url',
            'job',
            'currency_guess',
            'variants_allowed',
            'variants_pack',
            'blocked'
        );
    }
}
