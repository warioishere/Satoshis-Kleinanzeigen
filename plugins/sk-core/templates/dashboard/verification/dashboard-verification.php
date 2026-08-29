<?php
/**
 * Verifizierung im Verkäufer-Dashboard.
 *
 * Aufbau wie die Shopdaten unter Einstellungen: dieselbe Huelle
 * (sk-settings-content, sk-settings-area, entry-title im sk-dashboard-header),
 * sk-settings-form als Rahmen, sk-settings-section je Abschnitt und Felder
 * ueber sk_form_input(). An sk-settings-form haengt die dunkle
 * Feldgestaltung — ein sk-form-control ausserhalb dieses Rahmens bliebe weiss.
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

    <div class="sk-dashboard-content sk-settings-content">
        <?php do_action( 'sk_dashboard_content_inside_before' ); ?>

        <article class="sk-settings-area">

        <header class="sk-dashboard-header">
            <div class="sk-store-settign-header-wrap">
                <h1 class="entry-title"><?php esc_html_e( 'Verifizierung', 'sk-core' ); ?></h1>
            </div>
        </header>

        <?php if ( $message ) : ?>
            <div class="sk-alert <?php echo $verified ? 'sk-alert-success' : 'sk-alert-danger'; ?>"><?php echo esc_html( $message ); ?></div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url( $url ); ?>" class="sk-settings-form">
            <?php wp_nonce_field( VerifiedLinksPage::NONCE, 'sk_verify_nonce' ); ?>

            <div class="sk-settings-section">
                <div class="sk-settings-section-title">
                    <?php esc_html_e( 'Dein Abzeichen', 'sk-core' ); ?>
                </div>

                <div class="sk-settings-field">
                    <div class="sk-settings-input">
                        <p><?php esc_html_e( 'Zeig, dass eine Seite im Netz wirklich dir gehört: trag sie unten ein und setze dort einen Verweis zurück auf dein Profil. Ist beides da, bekommst du das Abzeichen — und andere sehen, dass du hinter dieser Adresse stehst.', 'sk-core' ); ?></p>
                        <?php if ( $verified ) : ?>
                            <p><span class="sk-verify-badge"><i class="fas fa-circle-check"></i></span> <strong><?php esc_html_e( 'Dein Abzeichen ist aktiv.', 'sk-core' ); ?></strong></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="sk-settings-section">
                <div class="sk-settings-section-title">
                    <?php esc_html_e( 'So geht es', 'sk-core' ); ?>
                </div>

                <div class="sk-settings-field">
                    <label class="sk-settings-label" for="sk_verify_snippet"><?php esc_html_e( 'Auf einer Website', 'sk-core' ); ?></label>
                    <div class="sk-settings-input">
                        <input type="text" id="sk_verify_snippet" class="sk-form-control" value="<?php echo esc_attr( $snippet ); ?>" readonly onclick="this.select();">
                        <p class="sk-settings-hint"><?php esc_html_e( 'Diese Zeile in den <head> deiner Seite setzen — sie ist unsichtbar. Anklicken markiert sie.', 'sk-core' ); ?></p>
                    </div>
                </div>

                <div class="sk-settings-field">
                    <label class="sk-settings-label" for="sk_verify_token"><?php esc_html_e( 'Auf GitHub und ähnlichem', 'sk-core' ); ?></label>
                    <div class="sk-settings-input">
                        <input type="text" id="sk_verify_token" class="sk-form-control" value="<?php echo esc_attr( $token ); ?>" readonly onclick="this.select();">
                        <p class="sk-settings-hint"><?php esc_html_e( 'Dort überlebt der Verweis das Rendern oft nicht. Schreib stattdessen diesen Beleg irgendwo auf die Seite.', 'sk-core' ); ?></p>
                    </div>
                </div>

                <div class="sk-settings-field">
                    <label class="sk-settings-label"><?php esc_html_e( 'Ziel des Verweises', 'sk-core' ); ?></label>
                    <div class="sk-settings-input">
                        <p><a href="<?php echo esc_url( $target ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $target ); ?></a></p>
                    </div>
                </div>
            </div>

            <div class="sk-settings-section">
                <div class="sk-settings-section-title">
                    <?php esc_html_e( 'Deine Adressen', 'sk-core' ); ?>
                </div>

                <?php if ( ! empty( $links ) ) : ?>
                    <div class="sk-settings-field">
                        <div class="sk-settings-input">
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
                                        <td><a href="<?php echo esc_url( $link['url'] ); ?>" target="_blank" rel="noopener nofollow"><?php echo esc_html( $link['host'] ); ?></a></td>
                                        <td>
                                            <?php if ( $link['status'] === VerifiedLinks::OK ) : ?>
                                                <span class="sk-verify-badge"><i class="fas fa-circle-check"></i></span> <?php esc_html_e( 'bestätigt', 'sk-core' ); ?>
                                            <?php elseif ( $link['status'] === VerifiedLinks::UNREACHABLE ) : ?>
                                                <?php esc_html_e( 'nicht erreichbar', 'sk-core' ); ?>
                                            <?php else : ?>
                                                <?php esc_html_e( 'kein Verweis gefunden', 'sk-core' ); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $link['checked'] ? esc_html( wp_date( 'd.m.Y H:i', (int) $link['checked'] ) ) : '—'; ?></td>
                                        <td>
                                            <button type="submit" name="sk_verify_action" value="check" class="sk-btn sk-btn-theme" formnovalidate
                                                onclick="document.getElementById('sk_verify_url').value=<?php echo esc_attr( wp_json_encode( $link['url'] ) ); ?>;"><?php esc_html_e( 'Prüfen', 'sk-core' ); ?></button>
                                            <button type="submit" name="sk_verify_action" value="remove" class="sk-btn sk-btn-default" formnovalidate
                                                onclick="document.getElementById('sk_verify_url').value=<?php echo esc_attr( wp_json_encode( $link['url'] ) ); ?>;"><?php esc_html_e( 'Entfernen', 'sk-core' ); ?></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <?php
                sk_form_input( [
                    'type'        => 'url',
                    'name'        => 'sk_verify_url',
                    'id'          => 'sk_verify_url',
                    'value'       => '',
                    'label'       => __( 'Adresse', 'sk-core' ),
                    'placeholder' => 'https://meine-seite.de',
                    'hint'        => count( $links ) >= $max_links
                        ? sprintf(
                            /* translators: %d: Hoechstzahl der Adressen. */
                            __( 'Du hast die Höchstzahl von %d Adressen erreicht. Entferne zuerst eine.', 'sk-core' ),
                            $max_links
                        )
                        : __( 'Trag die Adresse ein und setze dort den Verweis — wir prüfen sofort.', 'sk-core' ),
                ] );
                ?>

                <?php if ( count( $links ) < $max_links ) : ?>
                    <div class="sk-settings-field">
                        <div class="sk-settings-input">
                            <button type="submit" name="sk_verify_action" value="add" class="sk-btn sk-btn-theme"><?php esc_html_e( 'Hinzufügen und prüfen', 'sk-core' ); ?></button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </form>

        </article>

        <?php do_action( 'sk_dashboard_content_inside_after' ); ?>
    </div>

    <?php do_action( 'sk_dashboard_content_after' ); ?>
</div>

<?php do_action( 'sk_dashboard_wrap_end' ); ?>
