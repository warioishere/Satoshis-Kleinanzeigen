<?php

namespace SK\Core\Dashboard\Modules;

use SK\Core\Dashboard\DashboardModule;
use SK\Core\Dashboard\PageCache;

/**
 * Merkliste (wishlist/bookmarks) for vendor dashboard.
 * Ported from plugin: sk-merkliste
 */
class Merkliste extends DashboardModule {

    /**
     * Upper bound per user. A watchlist this long is no longer a watchlist —
     * the cap is there so a script cannot grow the table without limit.
     */
    private const MAX_ITEMS = 500;

    /**
     * Writes allowed per user and minute.
     */
    private const RATE_LIMIT = 30;

    /**
     * Product IDs on the current user's list, loaded once per request.
     *
     * @var int[]|null
     */
    private ?array $ids_cache = null;

    public function config(): ?array {
        return [
            'slug'          => 'merkliste',
            'title'         => __( 'Merkliste', 'sk-core' ),
            'icon'          => '<i class="fas fa-thumbtack"></i>',
            'pos'           => 56,
            'permission'    => 'sk_view_overview_menu',
            'template'      => 'dashboard/merkliste/dashboard-merkliste',
            // Handler runs here, before the template loads; the template only
            // renders what this returns.
            'template_args' => [ $this, 'dashboard_view_data' ],
        ];
    }

    protected function register_extras(): void {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );

        add_action( 'wp_ajax_dm_remove_from_merkliste', [ $this, 'ajax_remove' ] );
        add_action( 'wp_ajax_dm_toggle_merkliste',      [ $this, 'ajax_toggle' ] );

        add_action( 'woocommerce_after_shop_loop_item',   [ $this, 'output_pin_icon' ], 98 );
        add_action( 'woocommerce_single_product_summary', [ $this, 'output_single_button' ], 26 );

        // The slider markup carries no product ID, so the pin icons injected into
        // it used to resolve one from the permalink over ajax — which meant they
        // never knew whether the product was already on the list.
        add_filter( 'wcps_layout_element_thumbnail_class', [ $this, 'wcps_thumb_class' ], 10, 2 );

        add_action( 'deleted_user',       [ $this, 'delete_user_rows' ] );
        add_action( 'before_delete_post', [ $this, 'delete_product_rows' ] );

        add_filter( 'wp_privacy_personal_data_exporters', [ $this, 'register_exporter' ] );
        add_filter( 'wp_privacy_personal_data_erasers',   [ $this, 'register_eraser' ] );
    }

    public function enqueue(): void {
        $js_path = SK_CORE_DIR . '/assets/js/sk-merkliste.js';
        $js_ver  = file_exists( $js_path ) ? (string) filemtime( $js_path ) : SK_CORE_VERSION;

        if ( ! wp_style_is( 'fontawesome-free', 'enqueued' ) ) {
            wp_enqueue_style( 'fontawesome-free', SK_CORE_ASSETS . '/vendors/font-awesome-6/css/all.min.css', [], '6.5.0' );
        }

        // CSS merged into sk-theme.css (CSS consolidation). The script is only
        // registered here — a page without a single merkliste control does not
        // need it, and building its data costs a query.
        wp_register_script( 'sk-merkliste-js', SK_CORE_ASSETS . '/js/sk-merkliste.js', [ 'jquery' ], $js_ver, true );

        // isset, not truthiness: the rewrite sets merkliste="" for the bare
        // /dashboard/merkliste/ URL, which get_query_var() cannot tell from absent.
        global $wp;
        if ( isset( $wp->query_vars['merkliste'] ) ) {
            $this->enqueue_assets();
        }
    }

    /**
     * Load the script for the page being rendered. Called by every place that
     * emits a control, so it runs at most once and only when there is one.
     */
    private function enqueue_assets(): void {
        if ( wp_script_is( 'sk-merkliste-js', 'enqueued' ) ) {
            return;
        }

        wp_enqueue_script( 'sk-merkliste-js' );

        wp_localize_script( 'sk-merkliste-js', 'merklisteAjax', [
            'ajaxurl'      => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( 'merkliste_nonce' ),
            'items'        => $this->ids(),
            'errorText'    => __( 'Fehler beim Speichern', 'sk-core' ),
            'loginRequired'=> __( 'Du musst eingeloggt sein, um die Merkliste zu nutzen.', 'sk-core' ),
            'isLoggedIn'   => is_user_logged_in(),
            'addTitle'     => __( 'Zur Merkliste hinzufügen', 'sk-core' ),
            'removeTitle'  => __( 'Von Merkliste entfernen', 'sk-core' ),
            'confirmText'  => __( 'Wirklich von der Merkliste entfernen?', 'sk-core' ),
        ] );
    }

    /* ---- Dashboard page ---- */

    /**
     * Run the POST handler and collect everything the dashboard template needs.
     *
     * Called by DashboardRegistry::dispatch_template() before the template is
     * included, so the template itself contains no write logic.
     *
     * @param array $query_vars Dashboard query vars (unused, part of the callback signature).
     *
     * @return array{logged_in:bool,notices:array<int,array{type:string,text:string}>,products:\WC_Product[]}
     */
    public function dashboard_view_data( $query_vars = [] ): array {
        $data = [
            'logged_in' => is_user_logged_in(),
            'notices'   => [],
            'products'  => [],
        ];

        if ( ! $data['logged_in'] ) {
            return $data;
        }

        $user_id = get_current_user_id();

        $data['notices']  = $this->handle_remove_post( $user_id );
        $data['products'] = $this->resolve_products( $user_id );

        return $data;
    }

    /**
     * Remove one product from the own list. POST only, because a link that
     * deletes can be fired by browser prefetch.
     *
     * No ownership check is needed on the product: remove() scopes the DELETE
     * to the calling user, so a foreign product ID simply matches no row.
     *
     * @param int $user_id
     *
     * @return array<int,array{type:string,text:string}>
     */
    private function handle_remove_post( int $user_id ): array {
        if ( ! isset( $_POST['delete_product'] ) || ! is_numeric( $_POST['delete_product'] ) ) {
            return [];
        }

        $delete_id = (int) $_POST['delete_product'];
        $nonce_ok  = isset( $_POST['_dm_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_dm_nonce'] ) ), 'dm_del_' . $delete_id );

        if ( ! $nonce_ok ) {
            return [];
        }

        $this->remove( $delete_id, $user_id );

        return [ [ 'type' => 'success', 'text' => 'Produkt von Merkliste entfernt.' ] ];
    }

    /**
     * Rows resolved to products.
     *
     * Rows can point at products that are gone or no longer published, and
     * those must not count towards a non-empty list either.
     *
     * @param int $user_id
     *
     * @return \WC_Product[]
     */
    private function resolve_products( int $user_id ): array {
        $products = [];
        foreach ( $this->get_products( $user_id ) as $item ) {
            $product = wc_get_product( $item->product_id );
            if ( $product && 'publish' === $product->get_status() ) {
                $products[] = $product;
            }
        }
        return $products;
    }

    /* ---- AJAX ---- */

    /**
     * Shared entry check for the three write endpoints.
     *
     * Sends the error response and exits when the request may not proceed.
     *
     * @return int Validated product ID.
     */
    private function guard_write_request(): int {
        check_ajax_referer( 'merkliste_nonce', 'nonce' );

        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            wp_send_json_error( [ 'message' => __( 'Bitte einloggen', 'sk-core' ) ] );
        }

        if ( ! sk_rate_limit( 'merkliste:' . $user_id, self::RATE_LIMIT ) ) {
            wp_send_json_error( [ 'message' => __( 'Zu viele Anfragen. Bitte kurz warten.', 'sk-core' ) ] );
        }

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        if ( ! $product_id || ! $this->is_listable_product( $product_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Ungültige Produkt-ID', 'sk-core' ) ] );
        }

        return $product_id;
    }

    public function ajax_remove(): void {
        $product_id = $this->guard_write_request();

        if ( $this->remove( $product_id ) ) {
            $this->purge_merkliste_cache();
            wp_send_json_success( [ 'message' => __( 'Von Merkliste entfernt', 'sk-core' ), 'count' => $this->count() ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Fehler beim Entfernen', 'sk-core' ) ] );
        }
    }

    public function ajax_toggle(): void {
        $product_id = $this->guard_write_request();

        $is_in_list = $this->is_in_list( $product_id );

        if ( ! $is_in_list && $this->count() >= self::MAX_ITEMS ) {
            wp_send_json_error( [ 'message' => __( 'Deine Merkliste ist voll.', 'sk-core' ) ] );
        }

        $result = $is_in_list ? $this->remove( $product_id ) : $this->add( $product_id );
        $action = $is_in_list ? 'removed' : 'added';

        if ( $result ) {
            $this->purge_merkliste_cache();
            wp_send_json_success( [
                'action'     => $action,
                'message'    => $action === 'added' ? __( 'Zur Merkliste hinzugefügt', 'sk-core' ) : __( 'Von Merkliste entfernt', 'sk-core' ),
                'count'      => $this->count(),
                'is_in_list' => ! $is_in_list,
            ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Fehler beim Speichern', 'sk-core' ) ] );
        }
    }

    /* ---- Front-end output ---- */

    public function output_pin_icon(): void {
        global $product;
        if ( ! $product ) {
            return;
        }
        $this->enqueue_assets();

        $product_id   = $product->get_id();
        $is_logged_in = is_user_logged_in();
        $is_in_list   = $is_logged_in && in_array( $product_id, $this->ids(), true );
        $link_class   = 'dm-pin-icon' . ( $is_in_list ? ' active' : '' ) . ( ! $is_logged_in ? ' dm-pin-icon-disabled' : '' );
        $title        = $is_in_list ? __( 'Von Merkliste entfernen', 'sk-core' ) : __( 'Zur Merkliste hinzufügen', 'sk-core' );

        echo '<div class="dm-pin-icon-wrapper">';
        echo '<a href="#" class="' . esc_attr( $link_class ) . '" data-product-id="' . esc_attr( $product_id ) . '" title="' . esc_attr( $title ) . '">';
        echo '<i class="fas fa-thumbtack" aria-hidden="true"></i>';
        echo '</a></div>';
    }

    public function output_single_button(): void {
        global $product;
        if ( ! $product ) {
            return;
        }
        $this->enqueue_assets();

        $product_id   = $product->get_id();
        $is_logged_in = is_user_logged_in();
        $is_in_list   = $is_logged_in && in_array( $product_id, $this->ids(), true );
        $button_text  = $is_in_list ? __( 'Von Merkliste entfernen', 'sk-core' ) : __( 'Zur Merkliste hinzufügen', 'sk-core' );
        $button_class = 'dm-merkliste-btn' . ( $is_in_list ? ' dm-in-list' : '' ) . ( ! $is_logged_in ? ' dm-merkliste-btn-disabled' : '' );

        echo '<div class="dm-merkliste-button-wrapper">';
        echo '<button class="' . esc_attr( $button_class ) . '" data-product-id="' . esc_attr( $product_id ) . '">';
        echo '<i class="fas fa-thumbtack"></i> ' . esc_html( $button_text );
        echo '</button></div>';
    }

    /**
     * Carry the product ID into the slider thumbnail markup so the injected pin
     * icon can address the product without a lookup.
     *
     * @param string $class
     * @param array  $args
     * @return string
     */
    public function wcps_thumb_class( $class, $args ) {
        $product_id = isset( $args['product_id'] ) ? absint( $args['product_id'] ) : 0;

        if ( ! $product_id ) {
            return $class;
        }

        $this->enqueue_assets();

        return $class . ' sk-merk-pid-' . $product_id;
    }

    /* ---- Cleanup ---- */

    public function delete_user_rows( int $user_id ): void {
        global $wpdb;
        if ( ! $user_id ) {
            return;
        }
        $wpdb->delete( $wpdb->prefix . 'sk_merkliste', [ 'user_id' => $user_id ], [ '%d' ] );
    }

    public function delete_product_rows( int $post_id ): void {
        if ( 'product' !== get_post_type( $post_id ) ) {
            return;
        }
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'sk_merkliste', [ 'product_id' => $post_id ], [ '%d' ] );
    }

    /* ---- Privacy ---- */

    public function register_exporter( array $exporters ): array {
        $exporters['sk-merkliste'] = [
            'exporter_friendly_name' => __( 'Merkliste', 'sk-core' ),
            'callback'               => [ $this, 'export_personal_data' ],
        ];
        return $exporters;
    }

    public function register_eraser( array $erasers ): array {
        $erasers['sk-merkliste'] = [
            'eraser_friendly_name' => __( 'Merkliste', 'sk-core' ),
            'callback'             => [ $this, 'erase_personal_data' ],
        ];
        return $erasers;
    }

    /**
     * @param string $email
     * @param int    $page
     * @return array
     */
    public function export_personal_data( $email, $page = 1 ): array {
        $user = get_user_by( 'email', $email );
        if ( ! $user ) {
            return [ 'data' => [], 'done' => true ];
        }

        $data = [];
        foreach ( $this->get_products( $user->ID ) as $row ) {
            $product = wc_get_product( $row->product_id );

            $data[] = [
                'group_id'    => 'sk-merkliste',
                'group_label' => __( 'Merkliste', 'sk-core' ),
                'item_id'     => 'merkliste-' . $row->product_id,
                'data'        => [
                    [
                        'name'  => __( 'Produkt', 'sk-core' ),
                        'value' => $product ? $product->get_name() : (string) $row->product_id,
                    ],
                    [
                        'name'  => __( 'Hinzugefügt am', 'sk-core' ),
                        'value' => $row->added_date,
                    ],
                ],
            ];
        }

        return [ 'data' => $data, 'done' => true ];
    }

    /**
     * @param string $email
     * @param int    $page
     * @return array
     */
    public function erase_personal_data( $email, $page = 1 ): array {
        $user = get_user_by( 'email', $email );
        if ( ! $user ) {
            return [ 'items_removed' => false, 'items_retained' => false, 'messages' => [], 'done' => true ];
        }

        global $wpdb;
        $removed = $wpdb->delete( $wpdb->prefix . 'sk_merkliste', [ 'user_id' => $user->ID ], [ '%d' ] );

        return [
            'items_removed'  => (bool) $removed,
            'items_retained' => false,
            'messages'       => [],
            'done'           => true,
        ];
    }

    /* ---- Cache helpers ---- */

    private function purge_merkliste_cache(): void {
        $this->ids_cache = null;

        // Bust the Redis dashboard page cache (PageCache holds the key rules).
        $user_hash = PageCache::user_hash();
        if ( '' !== $user_hash ) {
            wp_cache_set( 'sk_dcv_' . $user_hash, time(), PageCache::GROUP, HOUR_IN_SECONDS );
        }

        // WP Fastest Cache file cache. Neither entry point exists in the free
        // version, so the URL is only resolved once one of them does.
        if ( function_exists( 'wpfc_clear_cache_by_url' ) ) {
            wpfc_clear_cache_by_url( sk_get_navigation_url( 'merkliste' ) );
        }
        global $WpFastestCache;
        if ( isset( $WpFastestCache ) && method_exists( $WpFastestCache, 'deleteSpecificCache' ) ) {
            $WpFastestCache->deleteSpecificCache( [ 'prefix' => 'startwith', 'content' => 'dashboard' ] );
        }
    }

    /* ---- DB helpers ---- */

    /**
     * May this ID go onto a list at all? Anything that is not a published
     * product would either be invisible in the dashboard or expose a listing
     * its owner has not published.
     */
    private function is_listable_product( int $product_id ): bool {
        $post = get_post( $product_id );

        return $post instanceof \WP_Post
            && 'product' === $post->post_type
            && 'publish' === $post->post_status;
    }

    /**
     * Product IDs on the current user's list — one query per request, shared by
     * every pin icon on the page.
     *
     * @return int[]
     */
    private function ids(): array {
        if ( null !== $this->ids_cache ) {
            return $this->ids_cache;
        }

        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return $this->ids_cache = [];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'sk_merkliste';

        return $this->ids_cache = array_map(
            'intval',
            (array) $wpdb->get_col( $wpdb->prepare( "SELECT product_id FROM {$table} WHERE user_id = %d", $user_id ) )
        );
    }

    private function is_in_list( int $product_id, int $user_id = 0 ): bool {
        global $wpdb;
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }
        if ( ! $user_id ) {
            return false;
        }
        $table = $wpdb->prefix . 'sk_merkliste';
        $count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND product_id = %d", $user_id, $product_id ) );
        return $count > 0;
    }

    private function add( int $product_id, int $user_id = 0 ): bool {
        global $wpdb;
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }
        if ( ! $user_id || ! $product_id ) {
            return false;
        }
        // INSERT IGNORE, not insert(): two parallel adds used to collide on the
        // unique key and report a failure for a row that is on the list.
        $table  = $wpdb->prefix . 'sk_merkliste';
        $result = $wpdb->query(
            $wpdb->prepare( "INSERT IGNORE INTO {$table} (user_id, product_id) VALUES (%d, %d)", $user_id, $product_id )
        );
        return $result !== false;
    }

    public function remove( int $product_id, int $user_id = 0 ): bool {
        global $wpdb;
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }
        if ( ! $user_id || ! $product_id ) {
            return false;
        }
        $table  = $wpdb->prefix . 'sk_merkliste';
        $result = $wpdb->delete( $table, [ 'user_id' => $user_id, 'product_id' => $product_id ], [ '%d', '%d' ] );
        return $result !== false;
    }

    private function count( int $user_id = 0 ): int {
        global $wpdb;
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }
        if ( ! $user_id ) {
            return 0;
        }
        $table = $wpdb->prefix . 'sk_merkliste';
        return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE user_id = %d", $user_id ) );
    }

    /**
     * Return all merkliste rows for a user (used by template shim).
     *
     * @param int $user_id
     * @return object[]
     */
    public function get_products( int $user_id ): array {
        global $wpdb;
        if ( ! $user_id ) {
            return [];
        }
        $table = $wpdb->prefix . 'sk_merkliste';
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d ORDER BY added_date DESC", $user_id ) ) ?: [];
    }
}
