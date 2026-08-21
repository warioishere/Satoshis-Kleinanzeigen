<?php

namespace SK\Core\Dashboard\Modules;

use SK\Core\Dashboard\DashboardModule;

/**
 * Gesuche (search requests) CPT and dashboard management.
 * Ported from plugin: sk-gesuche
 */
class Gesuche extends DashboardModule {

    public function config(): ?array {
        return [
            'slug'          => 'gesuche',
            'title'         => __( 'Gesuche', 'sk-core' ),
            'icon'          => '<i class="fas fa-search"></i>',
            'pos'           => 55,
            'permission'    => 'sk_view_overview_menu',
            'template'      => 'dashboard/gesuche/dashboard-gesuche',
            // Handlers run here, before the template loads; the template only
            // renders what this returns.
            'template_args' => [ $this, 'dashboard_view_data' ],
        ];
    }

    protected function register_extras(): void {
        add_action( 'init',                [ $this, 'register_cpt' ] );
        add_action( 'wp_enqueue_scripts',  [ $this, 'enqueue' ] );
        add_action( 'template_redirect',   [ $this, 'redirect_shortcode_page' ] );
        add_filter( 'template_include',    [ $this, 'override_templates' ] );
        add_filter( 'theme_page_templates', [ $this, 'register_page_template' ] );

        // Admin columns
        add_filter( 'manage_gesuch_posts_columns',       [ $this, 'admin_columns' ] );
        add_action( 'manage_gesuch_posts_custom_column', [ $this, 'admin_column_content' ], 10, 2 );
    }

    public function register_cpt(): void {
        register_post_type( 'gesuch', [
            'labels'          => [
                'name'               => 'Gesuche',
                'singular_name'      => 'Gesuch',
                'add_new_item'       => 'Neues Gesuch erstellen',
                'edit_item'          => 'Gesuch bearbeiten',
                'view_item'          => 'Gesuch ansehen',
                'search_items'       => 'Gesuche durchsuchen',
                'not_found'          => 'Keine Gesuche gefunden',
                'not_found_in_trash' => 'Keine Gesuche im Papierkorb',
                'all_items'          => 'Alle Gesuche',
            ],
            'public'          => true,
            'has_archive'     => false,
            'rewrite'         => [ 'slug' => 'gesuch' ],
            'supports'        => [ 'title', 'editor', 'author' ],
            'show_in_rest'    => false,
            'show_ui'         => true,
            'show_in_menu'    => true,
            'menu_icon'       => 'dashicons-search',
            'capability_type' => 'post',
            'map_meta_cap'    => true,
        ] );
    }

    public function enqueue(): void {
        // CSS merged into sk-theme.css (CSS consolidation)
    }

    /**
     * Load single-gesuch template from sk-core instead of theme.
     */
    public function override_templates( $template ) {
        if ( is_singular( 'gesuch' ) ) {
            $plugin_template = SK_CORE_DIR . '/templates/gesuche/single-gesuch.php';
            if ( file_exists( $plugin_template ) ) {
                return $plugin_template;
            }
        }

        // Page template: "Alle Gesuche".
        if ( is_page() ) {
            $page_template = get_page_template_slug();
            if ( $page_template === 'template-alle-gesuche.php' ) {
                $plugin_template = SK_CORE_DIR . '/templates/gesuche/template-alle-gesuche.php';
                if ( file_exists( $plugin_template ) ) {
                    return $plugin_template;
                }
            }
        }

        return $template;
    }

    /**
     * Register "Alle Gesuche" page template so it appears in the page editor dropdown.
     */
    public function register_page_template( $templates ) {
        $templates['template-alle-gesuche.php'] = __( 'Alle Gesuche', 'sk-core' );
        return $templates;
    }

    public function redirect_shortcode_page(): void {
        if ( ! is_singular() ) {
            return;
        }
        $post = get_post();
        if ( ! $post instanceof \WP_Post ) {
            return;
        }
        if ( function_exists( 'has_shortcode' ) && has_shortcode( $post->post_content, 'gesuche_dashboard' ) ) {
            wp_safe_redirect( sk_get_navigation_url( 'gesuche' ) );
            exit;
        }
    }

    public function admin_columns( array $columns ): array {
        $new = [];
        foreach ( $columns as $key => $label ) {
            $new[ $key ] = $label;
            if ( $key === 'title' ) {
                $new['author'] = 'Benutzer';
            }
        }
        return $new;
    }

    public function admin_column_content( string $column, int $post_id ): void {
        if ( $column === 'author' ) {
            $user = get_user_by( 'id', get_post_field( 'post_author', $post_id ) );
            echo $user ? esc_html( $user->user_login ) : '—';
        }
    }

    /**
     * Run the POST handlers and collect everything the dashboard template needs.
     *
     * Called by DashboardRegistry::dispatch_template() before the template is
     * included, so the template itself contains no write logic.
     *
     * @param array $query_vars Dashboard query vars (unused, part of the callback signature).
     *
     * @return array{logged_in:bool,notices:array<int,array{type:string,text:string}>,editing:bool,edit_post:?\WP_Post,gesuche:?\WP_Query}
     */
    public function dashboard_view_data( $query_vars = [] ): array {
        $data = [
            'logged_in' => is_user_logged_in(),
            'notices'   => [],
            'editing'   => false,
            'edit_post' => null,
            'gesuche'   => null,
        ];

        if ( ! $data['logged_in'] ) {
            return $data;
        }

        $user_id = get_current_user_id();

        $data['notices'] = array_merge(
            self::handle_delete( $user_id ),
            self::handle_create( $user_id ),
            self::handle_edit( $user_id )
        );

        // Edit state is resolved after the handlers, so a gesuch deleted in this
        // same request cannot be put back into the form.
        if ( isset( $_GET['edit_gesuch'] ) && is_numeric( $_GET['edit_gesuch'] ) ) {
            $edit_id   = (int) $_GET['edit_gesuch'];
            $edit_post = get_post( $edit_id );
            if ( $edit_post && (int) $edit_post->post_author === $user_id && $edit_post->post_type === 'gesuch' ) {
                $data['editing']   = true;
                $data['edit_post'] = $edit_post;
            }
        }

        $data['gesuche'] = new \WP_Query( [
            'post_type'      => 'gesuch',
            'post_status'    => [ 'publish', 'draft' ],
            'author'         => $user_id,
            'posts_per_page' => 20,
        ] );

        return $data;
    }

    /**
     * Delete one own gesuch. POST only, because a link that deletes can be
     * fired by browser prefetch.
     *
     * @param int $user_id
     *
     * @return array<int,array{type:string,text:string}>
     */
    private static function handle_delete( int $user_id ): array {
        if ( ! isset( $_POST['delete_gesuch'] ) || ! is_numeric( $_POST['delete_gesuch'] ) ) {
            return [];
        }

        $delete_id = (int) $_POST['delete_gesuch'];
        $post      = get_post( $delete_id );
        $nonce_ok  = isset( $_POST['_dg_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_dg_nonce'] ) ), 'dg_del_' . $delete_id );

        if ( ! $nonce_ok || ! $post || (int) $post->post_author !== $user_id || $post->post_type !== 'gesuch' ) {
            return [];
        }

        wp_delete_post( $delete_id, true );

        return [ [ 'type' => 'success', 'text' => 'Gesuch gelöscht.' ] ];
    }

    /**
     * Publish a new gesuch.
     *
     * @param int $user_id
     *
     * @return array<int,array{type:string,text:string}>
     */
    private static function handle_create( int $user_id ): array {
        if ( ! isset( $_POST['dg_action'] ) || $_POST['dg_action'] !== 'create' ) {
            return [];
        }
        if ( ! isset( $_POST['_dg_nonce'] )
            || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_dg_nonce'] ) ), 'dg_create' ) ) {
            return [];
        }

        // Slot erst nach der Nonce-Pruefung ziehen, damit verworfene
        // Requests das Kontingent nicht aufbrauchen.
        if ( ! sk_rate_limit( 'gesuch-create:' . $user_id, 5, HOUR_IN_SECONDS ) ) {
            return [ [ 'type' => 'warning', 'text' => 'Du kannst höchstens 5 Gesuche pro Stunde veröffentlichen. Bitte versuche es später erneut.' ] ];
        }

        $post_id = wp_insert_post( [
            'post_title'   => sanitize_text_field( $_POST['gesuch_title'] ?? '' ),
            'post_content' => sanitize_textarea_field( $_POST['gesuch_content'] ?? '' ),
            'post_type'    => 'gesuch',
            'post_status'  => 'publish',
            'post_author'  => $user_id,
        ] );

        if ( ! $post_id || is_wp_error( $post_id ) ) {
            return [];
        }

        update_post_meta( $post_id, '_vendor_id', $user_id );

        return [ [ 'type' => 'success', 'text' => 'Gesuch veröffentlicht.' ] ];
    }

    /**
     * Update one own gesuch.
     *
     * The post_type check is not cosmetic: without it a vendor could overwrite
     * their own products through this form.
     *
     * @param int $user_id
     *
     * @return array<int,array{type:string,text:string}>
     */
    private static function handle_edit( int $user_id ): array {
        // Both keys checked before is_numeric(), otherwise PHP warns about the
        // undefined array key.
        if ( ! isset( $_POST['dg_action'], $_POST['gesuch_id'] )
            || $_POST['dg_action'] !== 'edit'
            || ! is_numeric( $_POST['gesuch_id'] ) ) {
            return [];
        }

        $gesuch_id = (int) $_POST['gesuch_id'];
        $post      = get_post( $gesuch_id );
        $nonce_ok  = isset( $_POST['_dg_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_dg_nonce'] ) ), 'dg_edit_' . $gesuch_id );

        if ( ! $nonce_ok || ! $post || (int) $post->post_author !== $user_id || $post->post_type !== 'gesuch' ) {
            return [];
        }

        wp_update_post( [
            'ID'           => $gesuch_id,
            'post_title'   => sanitize_text_field( $_POST['gesuch_title'] ?? '' ),
            'post_content' => sanitize_textarea_field( $_POST['gesuch_content'] ?? '' ),
        ] );

        return [ [ 'type' => 'success', 'text' => 'Gesuch aktualisiert.' ] ];
    }
}
