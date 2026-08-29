<?php
/**
 * Verifizierung im Verkäufer-Dashboard.
 *
 * Huelle wie die uebrigen Menueeintraege (Merkliste, Gesuche): eigener
 * Modifikator auf sk-dashboard-content und sk-review-page-header als Kopf.
 *
 * Die Felder stehen in einem sk-settings-form, weil die dunkle
 * Feldgestaltung daran haengt — ein sk-form-control ausserhalb bliebe weiss.
 * Abschnittsbloecke gibt es bewusst keine: sk-settings-section-title bringt
 * Trennlinie und Groesse mit, die auf einer Menueseite fremd wirken.
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

    <div class="sk-dashboard-content sk-dashboard-content--verification">
        <?php do_action( 'sk_dashboard_content_inside_before' ); ?>

        <div class="sk-review-page-header">
            <h2><i class="fas fa-circle-check"></i> <?php esc_html_e( 'Verifizierung', 'sk-core' ); ?></h2>
        </div>

        <?php if ( $message ) : ?>
            <div class="sk-alert <?php echo $verified ? 'sk-alert-success' : 'sk-alert-danger'; ?>"><?php echo esc_html( $message ); ?></div>
        <?php endif; ?>

        <p><?php esc_html_e( 'Zeig, dass eine Seite im Netz wirklich dir gehört: trag sie unten ein und setze dort einen Verweis zurück auf dein Profil. Ist beides da, bekommst du das Abzeichen — und andere sehen, dass du hinter dieser Adresse stehst.', 'sk-core' ); ?></p>

        <?php if ( $verified ) : ?>
            <p><span class="sk-verify-badge"><i class="fas fa-circle-check"></i></span> <strong><?php esc_html_e( 'Dein Abzeichen ist aktiv.', 'sk-core' ); ?></strong></p>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url( $url ); ?>" class="sk-settings-form">
            <?php wp_nonce_field( VerifiedLinksPage::NONCE, 'sk_verify_nonce' ); ?>

            <?php
            sk_form_input( [
                'type'     => 'text',
                'name'     => 'sk_verify_snippet_display',
                'id'       => 'sk_verify_snippet',
                'value'    => $snippet,
                'label'    => __( 'Auf einer Website', 'sk-core' ),
                'readonly' => true,
                'hint'     => __( 'Diese Zeile in den &lt;head&gt; deiner Seite setzen — sie ist unsichtbar.', 'sk-core' ),
            ] );

            sk_form_input( [
                'type'     => 'text',
                'name'     => 'sk_verify_token_display',
                'id'       => 'sk_verify_token',
                'value'    => $token,
                'label'    => __( 'Auf GitHub und ähnlichem', 'sk-core' ),
                'readonly' => true,
                'hint'     => __( 'Dort überlebt der Verweis das Rendern oft nicht. Schreib stattdessen diesen Beleg irgendwo auf die Seite.', 'sk-core' ),
            ] );

            sk_form_input( [
                'type'        => 'url',
                'name'        => 'sk_verify_url',
                'id'          => 'sk_verify_url',
                'value'       => '',
                'label'       => __( 'Adresse', 'sk-core' ),
                'placeholder' => 'https://meine-seite.de',
                'hint'        => sprintf(
                    /* translators: %s: Adresse der eigenen Profilseite. */
                    __( 'Der Verweis muss auf %s zeigen.', 'sk-core' ),
                    '<a href="' . esc_url( $target ) . '" target="_blank" rel="noopener">' . esc_html( $target ) . '</a>'
                ),
            ] );
            ?>

            <?php if ( count( $links ) < $max_links ) : ?>
                <div class="sk-settings-field">
                    <div class="sk-settings-input">
                        <button type="submit" name="sk_verify_action" value="add" class="sk-btn sk-btn-theme"><?php esc_html_e( 'Hinzufügen und prüfen', 'sk-core' ); ?></button>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ( ! empty( $links ) ) : ?>
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
            <?php endif; ?>
        </form>

        <?php do_action( 'sk_dashboard_content_inside_after' ); ?>
    </div>

    <?php do_action( 'sk_dashboard_content_after' ); ?>
</div>

<?php do_action( 'sk_dashboard_wrap_end' ); ?>
