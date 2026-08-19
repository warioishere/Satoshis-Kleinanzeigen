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
            'slug'       => 'gesuche',
            'title'      => __( 'Gesuche', 'sk-core' ),
            'icon'       => '<i class="fas fa-search"></i>',
            'pos'        => 55,
            'permission' => 'sk_view_overview_menu',
            'template'   => 'dashboard/gesuche/dashboard-gesuche',
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
            'show_in_rest'    => true,
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
     * Render the gesuche dashboard content (called from template).
     */
    public static function render_dashboard(): string {
        if ( ! is_user_logged_in() ) {
            return '<p>Bitte <a href="/mein-konto/">einloggen</a>, um Gesuche zu verwalten.</p>';
        }
        $user_id = get_current_user_id();

        // Löschen
        if ( isset( $_GET['delete_gesuch'] ) && is_numeric( $_GET['delete_gesuch'] ) ) {
            $delete_id = (int) $_GET['delete_gesuch'];
            $post      = get_post( $delete_id );
            $nonce_ok  = isset( $_GET['_dg_nonce'] ) && wp_verify_nonce( sanitize_text_field( $_GET['_dg_nonce'] ), 'dg_del_' . $delete_id );
            if ( $nonce_ok && $post && (int) $post->post_author === $user_id && $post->post_type === 'gesuch' ) {
                wp_delete_post( $delete_id, true );
                echo '<div class="sk-alert sk-alert-success">Gesuch gelöscht.</div>';
            }
        }

        // Neu anlegen
        if ( isset( $_POST['dg_action'] ) && $_POST['dg_action'] === 'create' ) {
            $title   = sanitize_text_field( $_POST['gesuch_title'] ?? '' );
            $content = sanitize_textarea_field( $_POST['gesuch_content'] ?? '' );
            $post_id = wp_insert_post( [
                'post_title'   => $title,
                'post_content' => $content,
                'post_type'    => 'gesuch',
                'post_status'  => 'publish',
                'post_author'  => $user_id,
            ] );
            if ( $post_id && ! is_wp_error( $post_id ) ) {
                update_post_meta( $post_id, '_vendor_id', $user_id );
                echo '<div class="sk-alert sk-alert-success">Gesuch veröffentlicht.</div>';
            }
        }

        // Bearbeiten
        if ( isset( $_POST['dg_action'] ) && $_POST['dg_action'] === 'edit' && is_numeric( $_POST['gesuch_id'] ) ) {
            $gesuch_id = (int) $_POST['gesuch_id'];
            $post      = get_post( $gesuch_id );
            if ( $post && (int) $post->post_author === $user_id ) {
                wp_update_post( [
                    'ID'           => $gesuch_id,
                    'post_title'   => sanitize_text_field( $_POST['gesuch_title'] ?? '' ),
                    'post_content' => sanitize_textarea_field( $_POST['gesuch_content'] ?? '' ),
                ] );
                echo '<div class="sk-alert sk-alert-success">Gesuch aktualisiert.</div>';
            }
        }

        $editing   = false;
        $edit_post = null;
        if ( isset( $_GET['edit_gesuch'] ) && is_numeric( $_GET['edit_gesuch'] ) ) {
            $edit_id   = (int) $_GET['edit_gesuch'];
            $edit_post = get_post( $edit_id );
            if ( $edit_post && (int) $edit_post->post_author === $user_id && $edit_post->post_type === 'gesuch' ) {
                $editing = true;
            }
        }

        ob_start();
        ?>
        <div class="sk-review-page-header">
            <h2><i class="fas fa-search"></i> Gesuche</h2>
        </div>

        <div class="gesuche-dashboard-wrapper">
          <div class="gesuche-dashboard-inner">
            <h3 class="sk-gesuche-section-title"><?php echo $editing ? 'Gesuch bearbeiten' : 'Gesuch erstellen'; ?></h3>
            <form method="post" class="gesuch-form">
                <div class="sk-form-group">
                    <label for="gesuch_title">Titel</label>
                    <input type="text" id="gesuch_title" name="gesuch_title" class="sk-form-control"
                           value="<?php echo $editing ? esc_attr( $edit_post->post_title ) : ''; ?>" required>
                </div>
                <div class="sk-form-group">
                    <label for="gesuch_content">Beschreibung</label>
                    <textarea id="gesuch_content" name="gesuch_content" rows="6" class="sk-form-control" required><?php
                        echo $editing ? esc_textarea( $edit_post->post_content ) : ''; ?></textarea>
                </div>
                <?php if ( $editing ) : ?>
                    <input type="hidden" name="dg_action" value="edit">
                    <input type="hidden" name="gesuch_id" value="<?php echo esc_attr( $edit_post->ID ); ?>">
                <?php else : ?>
                    <input type="hidden" name="dg_action" value="create">
                <?php endif; ?>
                <input type="submit" class="sk-btn sk-btn-btc"
                       value="<?php echo $editing ? 'Gesuch speichern' : 'Gesuch veröffentlichen'; ?>">
            </form>

            <h3 class="sk-gesuche-section-title">Meine Gesuche</h3>
            <?php
            $query = new \WP_Query( [
                'post_type'      => 'gesuch',
                'post_status'    => [ 'publish', 'draft' ],
                'author'         => $user_id,
                'posts_per_page' => 20,
            ] );
            if ( $query->have_posts() ) : ?>
                <ul class="gesuch-list">
                    <?php while ( $query->have_posts() ) : $query->the_post();
                        $pid      = get_the_ID();
                        $del_url  = add_query_arg( [ 'delete_gesuch' => $pid, '_dg_nonce' => wp_create_nonce( 'dg_del_' . $pid ) ], remove_query_arg( [ 'edit_gesuch' ] ) );
                        $edit_url = add_query_arg( [ 'edit_gesuch' => $pid ], remove_query_arg( [ 'delete_gesuch', '_dg_nonce' ] ) );
                    ?>
                        <li class="gesuch-item">
                            <div class="gesuch-item-head">
                                <strong class="gesuch-title"><?php the_title(); ?></strong>
                                <span class="gesuch-status"><?php echo esc_html( get_post_status_object( get_post_status() )->label ); ?></span>
                            </div>
                            <div class="gesuch-excerpt"><?php echo wp_trim_words( get_the_content(), 28 ); ?></div>
                            <div class="gesuch-actions">
                                <a class="btn btn-sm btn-secondary" href="<?php echo esc_url( $edit_url ); ?>">Bearbeiten</a>
                                <a class="btn btn-sm btn-danger" href="<?php echo esc_url( $del_url ); ?>" onclick="return confirm('Wirklich löschen?');">Löschen</a>
                                <a class="btn btn-sm btn-outline" href="<?php the_permalink(); ?>" target="_blank" rel="noopener">Ansehen</a>
                            </div>
                        </li>
                    <?php endwhile; wp_reset_postdata(); ?>
                </ul>
            <?php else : ?>
                <p>Du hast noch keine Gesuche erstellt.</p>
            <?php endif; ?>
          </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

// =============================================================================
