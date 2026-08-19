<?php

namespace SK\Core\Dashboard\Modules;

use SK\Core\Dashboard\DashboardModule;

/**
 * Merkliste (wishlist/bookmarks) for vendor dashboard.
 * Ported from plugin: sk-merkliste
 */
class Merkliste extends DashboardModule {

    public function config(): ?array {
        return [
            'slug'       => 'merkliste',
            'title'      => __( 'Merkliste', 'sk-core' ),
            'icon'       => '<i class="fas fa-thumbtack"></i>',
            'pos'        => 56,
            'permission' => 'sk_view_overview_menu',
            'template'   => 'dashboard/merkliste/dashboard-merkliste',
        ];
    }

    protected function register_extras(): void {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );

        add_action( 'wp_ajax_dm_add_to_merkliste',    [ $this, 'ajax_add' ] );
        add_action( 'wp_ajax_dm_remove_from_merkliste', [ $this, 'ajax_remove' ] );
        add_action( 'wp_ajax_dm_toggle_merkliste',    [ $this, 'ajax_toggle' ] );
        add_action( 'wp_ajax_dm_get_product_id_from_url',       [ $this, 'ajax_get_product_id' ] );
        add_action( 'wp_ajax_nopriv_dm_get_product_id_from_url', [ $this, 'ajax_get_product_id' ] );

        add_action( 'woocommerce_after_shop_loop_item',   [ $this, 'output_pin_icon' ], 98 );
        add_action( 'woocommerce_single_product_summary', [ $this, 'output_single_button' ], 26 );
    }

    public function enqueue(): void {
        $css_path   = SK_CORE_DIR . '/assets/css/sk-merkliste.css';
        $js_path    = SK_CORE_DIR . '/assets/js/sk-merkliste.js';
        $css_ver    = file_exists( $css_path ) ? (string) filemtime( $css_path ) : SK_CORE_VERSION;
        $js_ver     = file_exists( $js_path )  ? (string) filemtime( $js_path )  : SK_CORE_VERSION;

        if ( ! wp_style_is( 'fontawesome-free', 'enqueued' ) ) {
            wp_enqueue_style( 'fontawesome-free', SK_CORE_ASSETS . '/vendors/font-awesome-6/css/all.min.css', [], '6.5.0' );
        }

        // CSS merged into sk-theme.css (CSS consolidation)
        wp_enqueue_script( 'sk-merkliste-js', SK_CORE_ASSETS . '/js/sk-merkliste.js', [ 'jquery' ], $js_ver, true );

        wp_localize_script( 'sk-merkliste-js', 'merklisteAjax', [
            'ajaxurl'      => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( 'merkliste_nonce' ),
            'addedText'    => __( 'Zur Merkliste hinzugefügt', 'sk-core' ),
            'removedText'  => __( 'Von Merkliste entfernt', 'sk-core' ),
            'errorText'    => __( 'Fehler beim Speichern', 'sk-core' ),
            'loginRequired'=> __( 'Du musst eingeloggt sein, um die Merkliste zu nutzen.', 'sk-core' ),
            'loginUrl'     => wp_login_url( get_permalink() ),
            'isLoggedIn'   => is_user_logged_in(),
        ] );
    }

    /* ---- AJAX ---- */

    public function ajax_add(): void {
        check_ajax_referer( 'merkliste_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => __( 'Bitte einloggen', 'sk-core' ) ] );
        }
        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        if ( ! $product_id ) {
            wp_send_json_error( [ 'message' => __( 'Ungültige Produkt-ID', 'sk-core' ) ] );
        }
        if ( $this->add( $product_id ) ) {
            $this->purge_merkliste_cache();
            wp_send_json_success( [ 'message' => __( 'Zur Merkliste hinzugefügt', 'sk-core' ), 'count' => $this->count() ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Fehler beim Hinzufügen', 'sk-core' ) ] );
        }
    }

    public function ajax_remove(): void {
        check_ajax_referer( 'merkliste_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => __( 'Bitte einloggen', 'sk-core' ) ] );
        }
        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        if ( ! $product_id ) {
            wp_send_json_error( [ 'message' => __( 'Ungültige Produkt-ID', 'sk-core' ) ] );
        }
        if ( $this->remove( $product_id ) ) {
            $this->purge_merkliste_cache();
            wp_send_json_success( [ 'message' => __( 'Von Merkliste entfernt', 'sk-core' ), 'count' => $this->count() ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Fehler beim Entfernen', 'sk-core' ) ] );
        }
    }

    public function ajax_toggle(): void {
        check_ajax_referer( 'merkliste_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => __( 'Bitte einloggen', 'sk-core' ) ] );
        }
        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        if ( ! $product_id ) {
            wp_send_json_error( [ 'message' => __( 'Ungültige Produkt-ID', 'sk-core' ) ] );
        }
        $is_in_list = $this->is_in_list( $product_id );
        $result     = $is_in_list ? $this->remove( $product_id ) : $this->add( $product_id );
        $action     = $is_in_list ? 'removed' : 'added';
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

    public function ajax_get_product_id(): void {
        $product_url = isset( $_POST['product_url'] ) ? esc_url_raw( $_POST['product_url'] ) : '';
        if ( empty( $product_url ) ) {
            wp_send_json_error( [ 'message' => 'No URL provided' ] );
        }
        $url_parts = parse_url( $product_url );
        $path      = isset( $url_parts['path'] ) ? trim( $url_parts['path'], '/' ) : '';
        $slug      = basename( $path );
        if ( empty( $slug ) ) {
            wp_send_json_error( [ 'message' => 'Could not extract slug' ] );
        }
        $product = get_page_by_path( $slug, OBJECT, 'product' );
        if ( $product ) {
            wp_send_json_success( [ 'product_id' => $product->ID ] );
        } else {
            wp_send_json_error( [ 'message' => 'Product not found' ] );
        }
    }

    /* ---- Front-end output ---- */

    public function output_pin_icon(): void {
        if ( is_product() ) {
            return;
        }
        global $product;
        if ( ! $product ) {
            return;
        }
        $product_id   = $product->get_id();
        $is_logged_in = is_user_logged_in();
        $is_in_list   = $is_logged_in ? $this->is_in_list( $product_id ) : false;
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
        $product_id   = $product->get_id();
        $is_logged_in = is_user_logged_in();
        $is_in_list   = $is_logged_in ? $this->is_in_list( $product_id ) : false;
        $button_text  = $is_in_list ? __( 'Von Merkliste entfernen', 'sk-core' ) : __( 'Zur Merkliste hinzufügen', 'sk-core' );
        $button_class = 'dm-merkliste-btn' . ( $is_in_list ? ' dm-in-list' : '' ) . ( ! $is_logged_in ? ' dm-merkliste-btn-disabled' : '' );

        echo '<div class="dm-merkliste-button-wrapper">';
        echo '<button class="' . esc_attr( $button_class ) . '" data-product-id="' . esc_attr( $product_id ) . '">';
        echo '<i class="fas fa-thumbtack"></i> ' . esc_html( $button_text );
        echo '</button></div>';
    }

    /* ---- Cache helpers ---- */

    private function purge_merkliste_cache(): void {
        // Bust the Redis dashboard page cache (Performance.php uses sk_dcv_{user_hash} as version key)
        $user_hash = '';
        foreach ( $_COOKIE as $k => $v ) {
            if ( str_starts_with( $k, 'wordpress_logged_in_' ) ) {
                $user_hash = md5( $v );
                break;
            }
        }
        if ( '' !== $user_hash ) {
            wp_cache_set( 'sk_dcv_' . $user_hash, time(), 'sk_page_cache', 3600 );
        }

        // WP Fastest Cache file cache
        $url = sk_get_navigation_url( 'merkliste' );
        if ( function_exists( 'wpfc_clear_cache_by_url' ) ) {
            wpfc_clear_cache_by_url( $url );
        }
        global $WpFastestCache;
        if ( isset( $WpFastestCache ) && method_exists( $WpFastestCache, 'deleteSpecificCache' ) ) {
            $WpFastestCache->deleteSpecificCache( [ 'prefix' => 'startwith', 'content' => 'dashboard' ] );
        }
    }

    /* ---- DB helpers ---- */

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
        if ( $this->is_in_list( $product_id, $user_id ) ) {
            return true;
        }
        $table  = $wpdb->prefix . 'sk_merkliste';
        $result = $wpdb->insert( $table, [ 'user_id' => $user_id, 'product_id' => $product_id ], [ '%d', '%d' ] );
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

