<?php
/**
 * Gesuche dashboard.
 *
 * Variables come from Gesuche::dashboard_view_data(), registered as
 * 'template_args' in the module config and run before this file is included.
 *
 * @var bool                                            $logged_in
 * @var array<int,array{type:string,text:string}>       $notices
 * @var bool                                            $editing
 * @var WP_Post|null                                    $edit_post
 * @var WP_Query|null                                   $gesuche
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

do_action( 'sk_dashboard_wrap_start' );
?>

<div class="sk-dashboard-wrap">
    <?php
        /**
         * Hook: sk_dashboard_content_before
         *
         */
        do_action( 'sk_dashboard_content_before' );
    ?>

    <div class="sk-dashboard-content sk-dashboard-content--gesuche">
        <?php
            /**
             * Hook: sk_dashboard_content_inside_before
             *
             */
            do_action( 'sk_dashboard_content_inside_before' );
        ?>

        <?php if ( ! $logged_in ) : ?>
            <p>Bitte <a href="/mein-konto/">einloggen</a>, um Gesuche zu verwalten.</p>
        <?php else : ?>

        <div class="sk-review-page-header">
            <h2><i class="fas fa-search"></i> Gesuche</h2>
        </div>

        <div class="gesuche-dashboard-wrapper">
          <div class="gesuche-dashboard-inner">
            <?php foreach ( $notices as $notice ) : ?>
                <div class="sk-alert sk-alert-<?php echo esc_attr( $notice['type'] ); ?>"><?php echo esc_html( $notice['text'] ); ?></div>
            <?php endforeach; ?>

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
                    <?php wp_nonce_field( 'dg_edit_' . $edit_post->ID, '_dg_nonce' ); ?>
                <?php else : ?>
                    <input type="hidden" name="dg_action" value="create">
                    <?php wp_nonce_field( 'dg_create', '_dg_nonce' ); ?>
                <?php endif; ?>
                <input type="submit" class="sk-btn sk-btn-btc"
                       value="<?php echo $editing ? 'Gesuch speichern' : 'Gesuch veröffentlichen'; ?>">
            </form>

            <h3 class="sk-gesuche-section-title">Meine Gesuche</h3>
            <?php if ( $gesuche && $gesuche->have_posts() ) : ?>
                <ul class="gesuch-list">
                    <?php while ( $gesuche->have_posts() ) : $gesuche->the_post();
                        $pid        = get_the_ID();
                        $del_action = remove_query_arg( [ 'edit_gesuch', 'delete_gesuch', '_dg_nonce' ] );
                        $edit_url   = add_query_arg( [ 'edit_gesuch' => $pid ], remove_query_arg( [ 'delete_gesuch', '_dg_nonce' ] ) );
                    ?>
                        <li class="gesuch-item">
                            <div class="gesuch-item-head">
                                <strong class="gesuch-title"><?php echo esc_html( get_the_title() ); ?></strong>
                                <span class="gesuch-status"><?php echo esc_html( get_post_status_object( get_post_status() )->label ); ?></span>
                            </div>
                            <div class="gesuch-excerpt"><?php echo wp_trim_words( get_the_content(), 28 ); ?></div>
                            <div class="gesuch-actions">
                                <a class="btn btn-sm btn-secondary" href="<?php echo esc_url( $edit_url ); ?>">Bearbeiten</a>
                                <form method="post" action="<?php echo esc_url( $del_action ); ?>" class="gesuch-delete-form" onsubmit="return confirm('Wirklich löschen?');">
                                    <?php wp_nonce_field( 'dg_del_' . $pid, '_dg_nonce' ); ?>
                                    <input type="hidden" name="delete_gesuch" value="<?php echo esc_attr( $pid ); ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Löschen</button>
                                </form>
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

        <?php endif; ?>

        <?php
            /**
             * Hook: sk_dashboard_content_inside_after
             *
             */
            do_action( 'sk_dashboard_content_inside_after' );
        ?>
    </div>

    <?php
        /**
         * Hook: sk_dashboard_content_after
         *
         */
        do_action( 'sk_dashboard_content_after' );
    ?>
</div>

<?php do_action( 'sk_dashboard_wrap_end' );
