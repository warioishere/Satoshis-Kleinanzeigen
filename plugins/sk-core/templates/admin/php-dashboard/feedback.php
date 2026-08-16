<?php
/**
 * Feedback tab template — entries list + settings.
 *
 * @var string $sub         Active sub tab (entries|settings)
 * @var array  $opts        Feedback options
 * @var string $base_url    admin.php?page=sk&tab=feedback
 * @var array  $entries     WP_Post[] for the entries list
 * @var int    $total_items
 * @var int    $total_pages
 * @var int    $paged
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use SK\Core\Dashboard\Modules\Feedback;

$opts_key = Feedback::OPTS_KEY;
?>

<div class="sk-feedback-wrap">

    <?php if ( isset( $_GET['saved'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Einstellungen gespeichert.', 'sk-core' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['deleted'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Eintrag gelöscht.', 'sk-core' ); ?></p></div>
    <?php endif; ?>

    <h2 class="nav-tab-wrapper" style="margin-bottom: 16px;">
        <a href="<?php echo esc_url( $base_url . '&sub=entries' ); ?>"
           class="nav-tab <?php echo $sub === 'entries' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e( 'Eingänge', 'sk-core' ); ?>
            <?php if ( $sub === 'entries' && $total_items ) : ?>
                <span class="count">(<?php echo esc_html( $total_items ); ?>)</span>
            <?php endif; ?>
        </a>
        <a href="<?php echo esc_url( $base_url . '&sub=settings' ); ?>"
           class="nav-tab <?php echo $sub === 'settings' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e( 'Einstellungen', 'sk-core' ); ?>
        </a>
    </h2>

    <?php if ( $sub === 'settings' ) : ?>

        <form method="post">
            <?php wp_nonce_field( 'sk_feedback_action', 'sk_feedback_nonce' ); ?>
            <input type="hidden" name="feedback_action" value="save_settings">

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="wpsf_title"><?php esc_html_e( 'Titel', 'sk-core' ); ?></label></th>
                    <td>
                        <input id="wpsf_title" type="text" class="regular-text"
                               name="<?php echo esc_attr( $opts_key ); ?>[title]"
                               value="<?php echo esc_attr( $opts['title'] ); ?>">
                        <p class="description"><?php esc_html_e( 'Überschrift über dem Feedback-Formular.', 'sk-core' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wpsf_description"><?php esc_html_e( 'Beschreibung', 'sk-core' ); ?></label></th>
                    <td>
                        <textarea id="wpsf_description" rows="5" class="large-text"
                                  name="<?php echo esc_attr( $opts_key ); ?>[description]"><?php echo esc_textarea( $opts['description'] ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'Kurzer Einleitungstext, einfaches HTML erlaubt.', 'sk-core' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Nur eingeloggte Nutzer', 'sk-core' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" value="1"
                                   name="<?php echo esc_attr( $opts_key ); ?>[require_login]"
                                <?php checked( 1, (int) $opts['require_login'] ); ?>>
                            <?php esc_html_e( 'Feedback nur für angemeldete Benutzer erlauben', 'sk-core' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wpsf_rate_limit"><?php esc_html_e( 'Rate Limit', 'sk-core' ); ?></label></th>
                    <td>
                        <input id="wpsf_rate_limit" type="number" class="small-text" min="0"
                               name="<?php echo esc_attr( $opts_key ); ?>[rate_limit]"
                               value="<?php echo esc_attr( (int) $opts['rate_limit'] ); ?>">
                        <?php esc_html_e( 'Sekunden', 'sk-core' ); ?>
                        <p class="description"><?php esc_html_e( 'Mindestabstand zwischen zwei Einsendungen pro IP. 0 = kein Limit.', 'sk-core' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Shortcode', 'sk-core' ); ?></th>
                    <td>
                        <code>[feedback_box]</code>
                        <p class="description"><?php esc_html_e( 'Auf einer Seite einfügen um das Formular anzuzeigen.', 'sk-core' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Benachrichtigung', 'sk-core' ); ?></th>
                    <td>
                        <code><?php echo esc_html( get_option( 'admin_email' ) ); ?></code>
                        <p class="description"><?php esc_html_e( 'Jede neue Einsendung geht per E-Mail an diese Adresse (Admin-E-Mail unter Einstellungen → Allgemein).', 'sk-core' ); ?></p>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>

    <?php else : ?>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" style="width: 14%;"><?php esc_html_e( 'Datum', 'sk-core' ); ?></th>
                    <th scope="col" style="width: 18%;"><?php esc_html_e( 'Benutzer', 'sk-core' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Nachricht', 'sk-core' ); ?></th>
                    <th scope="col" style="width: 12%;"><?php esc_html_e( 'IP', 'sk-core' ); ?></th>
                    <th scope="col" style="width: 10%;"><?php esc_html_e( 'Aktion', 'sk-core' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $entries ) ) : ?>
                    <tr><td colspan="5"><?php esc_html_e( 'Noch kein Feedback vorhanden.', 'sk-core' ); ?></td></tr>
                <?php else : ?>
                    <?php foreach ( $entries as $entry ) :
                        $uid      = (int) get_post_meta( $entry->ID, '_wpsf_user_id', true );
                        $ip       = get_post_meta( $entry->ID, '_wpsf_ip', true );
                        $userdata = $uid ? get_userdata( $uid ) : null;
                        ?>
                        <tr>
                            <td><?php echo esc_html( get_the_date( 'd.m.Y H:i', $entry ) ); ?></td>
                            <td>
                                <?php if ( $userdata ) : ?>
                                    <a href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . $uid ) ); ?>">
                                        <?php echo esc_html( $userdata->user_login ); ?>
                                    </a>
                                <?php else : ?>
                                    <em><?php esc_html_e( 'Gast', 'sk-core' ); ?></em>
                                <?php endif; ?>
                            </td>
                            <td><?php echo wp_kses_post( wpautop( $entry->post_content ) ); ?></td>
                            <td><?php echo $ip ? esc_html( $ip ) : '—'; ?></td>
                            <td>
                                <form method="post" onsubmit="return confirm('<?php echo esc_js( __( 'Diesen Eintrag endgültig löschen?', 'sk-core' ) ); ?>');">
                                    <?php wp_nonce_field( 'sk_feedback_action', 'sk_feedback_nonce' ); ?>
                                    <input type="hidden" name="feedback_action" value="delete_entry">
                                    <input type="hidden" name="entry_id" value="<?php echo esc_attr( $entry->ID ); ?>">
                                    <button type="submit" class="button button-small"><?php esc_html_e( 'Löschen', 'sk-core' ); ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ( $total_pages > 1 ) : ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links( [
                        'base'      => add_query_arg( 'paged', '%#%', $base_url . '&sub=entries' ),
                        'format'    => '',
                        'current'   => $paged,
                        'total'     => $total_pages,
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                    ] );
                    ?>
                </div>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>
