<?php
/**
 * Verifizierung im Verkäufer-Dashboard.
 *
 * Variablen kommen aus VerifiedLinksPage::view_data(), registriert als
 * 'template_args'; diese Datei rendert nur.
 *
 * @var string  $url
 * @var mixed   $message
 * @var array[] $links
 * @var string  $target
 * @var string  $snippet
 * @var string  $token
 * @var bool    $verified
 * @var int     $max_links
 */

defined( 'ABSPATH' ) || exit;

use SK\Core\Dashboard\Modules\VerifiedLinksPage;
use SK\Core\Verification\VerifiedLinks;

do_action( 'sk_dashboard_wrap_start' );
?>

<div class="sk-dashboard-wrap">
    <?php do_action( 'sk_dashboard_content_before' ); ?>

    <div class="sk-dashboard-content">
        <?php do_action( 'sk_dashboard_content_inside_before' ); ?>

        <div class="sk-review-page-header">
            <h2><i class="fas fa-circle-check"></i> <?php esc_html_e( 'Verifizierung', 'sk-core' ); ?></h2>
        </div>

        <?php if ( $message ) : ?>
            <div class="sk-alert <?php echo $verified ? 'sk-alert-success' : 'sk-alert-danger'; ?>"><?php echo esc_html( $message ); ?></div>
        <?php endif; ?>

        <div class="sk-section-heading">
            <h3><i class="fas fa-shield-halved"></i> <?php esc_html_e( 'Wofür das gut ist', 'sk-core' ); ?></h3>
        </div>
        <div class="sk-section-content">
            <p><?php esc_html_e( 'Zeig, dass eine Seite im Netz wirklich dir gehört: trag sie unten ein und setze dort einen Verweis zurück auf dein Profil. Ist beides da, bekommst du das Abzeichen — und andere sehen, dass du hinter dieser Adresse stehst.', 'sk-core' ); ?></p>

            <?php if ( $verified ) : ?>
                <p>
                    <span class="sk-verify-badge"><i class="fas fa-circle-check"></i></span>
                    <strong><?php esc_html_e( 'Dein Abzeichen ist aktiv.', 'sk-core' ); ?></strong>
                </p>
            <?php endif; ?>
        </div>

        <div class="sk-section-heading">
            <h3><i class="fas fa-list-ol"></i> <?php esc_html_e( 'So geht es', 'sk-core' ); ?></h3>
        </div>
        <div class="sk-section-content">
            <div class="sk-form-group sk-clearfix">
                <label class="sk-w3 sk-control-label" for="sk_verify_snippet"><?php esc_html_e( 'Auf einer Website', 'sk-core' ); ?></label>
                <div class="sk-w9">
                    <input class="sk-form-control" type="text" id="sk_verify_snippet" value="<?php echo esc_attr( $snippet ); ?>" readonly onclick="this.select();">
                    <p class="sk-settings-hint"><?php esc_html_e( 'Diese Zeile in den <head> deiner Seite setzen — sie ist unsichtbar.', 'sk-core' ); ?></p>
                </div>
            </div>

            <div class="sk-form-group sk-clearfix">
                <label class="sk-w3 sk-control-label" for="sk_verify_token"><?php esc_html_e( 'Auf GitHub und ähnlichem', 'sk-core' ); ?></label>
                <div class="sk-w9">
                    <input class="sk-form-control" type="text" id="sk_verify_token" value="<?php echo esc_attr( $token ); ?>" readonly onclick="this.select();">
                    <p class="sk-settings-hint"><?php esc_html_e( 'Dort überlebt der Verweis das Rendern oft nicht. Schreib stattdessen diesen Beleg irgendwo auf die Seite.', 'sk-core' ); ?></p>
                </div>
            </div>

            <div class="sk-form-group sk-clearfix">
                <label class="sk-w3 sk-control-label"><?php esc_html_e( 'Ziel des Verweises', 'sk-core' ); ?></label>
                <div class="sk-w9">
                    <p><a href="<?php echo esc_url( $target ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $target ); ?></a></p>
                </div>
            </div>
        </div>

        <div class="sk-section-heading">
            <h3><i class="fas fa-link"></i> <?php esc_html_e( 'Deine Adressen', 'sk-core' ); ?></h3>
        </div>
        <div class="sk-section-content">
            <?php if ( empty( $links ) ) : ?>
                <p><?php esc_html_e( 'Noch keine Adresse eingetragen.', 'sk-core' ); ?></p>
            <?php else : ?>
                <table class="sk-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Adresse', 'sk-core' ); ?></th>
                            <th><?php esc_html_e( 'Zustand', 'sk-core' ); ?></th>
                            <th><?php esc_html_e( 'zuletzt geprüft', 'sk-core' ); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $links as $link ) : ?>
                        <tr>
                            <td>
                                <a href="<?php echo esc_url( $link['url'] ); ?>" target="_blank" rel="noopener nofollow"><?php echo esc_html( $link['host'] ); ?></a>
                            </td>
                            <td>
                                <?php if ( $link['status'] === VerifiedLinks::OK ) : ?>
                                    <span class="sk-verify-badge"><i class="fas fa-circle-check"></i></span> <?php esc_html_e( 'bestätigt', 'sk-core' ); ?>
                                <?php elseif ( $link['status'] === VerifiedLinks::UNREACHABLE ) : ?>
                                    <?php esc_html_e( 'nicht erreichbar', 'sk-core' ); ?>
                                <?php else : ?>
                                    <?php esc_html_e( 'kein Verweis gefunden', 'sk-core' ); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo $link['checked'] ? esc_html( wp_date( 'd.m.Y H:i', (int) $link['checked'] ) ) : '—'; ?>
                            </td>
                            <td>
                                <form method="post" action="<?php echo esc_url( $url ); ?>">
                                    <?php wp_nonce_field( VerifiedLinksPage::NONCE, 'sk_verify_nonce' ); ?>
                                    <input type="hidden" name="sk_verify_url" value="<?php echo esc_attr( $link['url'] ); ?>">
                                    <button type="submit" name="sk_verify_action" value="check" class="sk-btn sk-btn-theme"><?php esc_html_e( 'Erneut prüfen', 'sk-core' ); ?></button>
                                    <button type="submit" name="sk_verify_action" value="remove" class="sk-btn sk-btn-default"><?php esc_html_e( 'Entfernen', 'sk-core' ); ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php if ( count( $links ) < $max_links ) : ?>
                <form method="post" action="<?php echo esc_url( $url ); ?>">
                    <?php wp_nonce_field( VerifiedLinksPage::NONCE, 'sk_verify_nonce' ); ?>
                    <div class="sk-form-group sk-clearfix">
                        <label class="sk-w3 sk-control-label" for="sk_verify_url"><?php esc_html_e( 'Adresse hinzufügen', 'sk-core' ); ?></label>
                        <div class="sk-w9">
                            <input class="sk-form-control" type="url" name="sk_verify_url" id="sk_verify_url" placeholder="https://meine-seite.de" required>
                        </div>
                    </div>
                    <div class="sk-form-group">
                        <button type="submit" name="sk_verify_action" value="add" class="sk-btn sk-btn-theme"><?php esc_html_e( 'Hinzufügen und prüfen', 'sk-core' ); ?></button>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <?php do_action( 'sk_dashboard_content_inside_after' ); ?>
    </div>

    <?php do_action( 'sk_dashboard_content_after' ); ?>
</div>

<?php do_action( 'sk_dashboard_wrap_end' ); ?>
