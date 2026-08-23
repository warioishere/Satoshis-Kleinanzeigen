<?php
/**
 * SK → Sponsoren.
 *
 * @var \WP_Post[] $sponsors
 * @var array      $clicks
 * @var int        $total_clicks
 * @var int        $days
 * @var string     $from
 * @var string     $to
 * @var int        $legacy_pending
 * @var string     $notice
 */

defined( 'ABSPATH' ) || exit;

use SK\Modules\Sponsors\Backlink;
use SK\Modules\Sponsors\TopUp;
use SK\Modules\Sponsors\Billing;
use SK\Modules\Sponsors\PostType;

$base_url = add_query_arg( [ 'page' => 'sk', 'tab' => 'sponsors' ], admin_url( 'admin.php' ) );
?>

<?php if ( strpos( $notice, 'imported-' ) === 0 ) : ?>
    <?php list( , $created, $skipped, $missing ) = array_pad( explode( '-', $notice ), 4, 0 ); ?>
    <div class="notice notice-success is-dismissible">
        <p>
            <?php
            printf(
                /* translators: 1: created, 2: skipped, 3: without target URL */
                esc_html__( 'Import abgeschlossen: %1$d übernommen, %2$d bereits vorhanden, %3$d ohne Ziel-URL übersprungen.', 'sk-core' ),
                (int) $created,
                (int) $skipped,
                (int) $missing
            );
            ?>
        </p>
    </div>
<?php endif; ?>

<?php if ( strpos( $notice, 'backlinks-' ) === 0 ) : ?>
    <?php list( , $checked, $ok ) = array_pad( explode( '-', $notice ), 3, 0 ); ?>
    <div class="notice notice-info is-dismissible">
        <p><?php printf( esc_html__( '%1$d Ziele geprüft, %2$d verlinken zurück.', 'sk-core' ), (int) $checked, (int) $ok ); ?></p>
    </div>
<?php elseif ( $notice === 'billing-on' ) : ?>
    <div class="notice notice-warning is-dismissible"><p><?php esc_html_e( 'Monatliche Abbuchung eingeschaltet. Sponsoren mit Monatsrate verlieren ihren Platz, sobald das Guthaben nicht mehr reicht.', 'sk-core' ); ?></p></div>
<?php elseif ( $notice === 'billing-off' ) : ?>
    <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Monatliche Abbuchung abgeschaltet.', 'sk-core' ); ?></p></div>
<?php endif; ?>

<?php if ( $new_invoice ) : ?>
    <div class="notice notice-success">
        <p>
            <strong><?php esc_html_e( 'Rechnung erstellt.', 'sk-core' ); ?></strong>
            <?php
            printf(
                /* translators: 1: sats, 2: sponsor name */
                esc_html__( '%1$s Sats für %2$s. Diesen Zahllink an den Sponsor schicken:', 'sk-core' ),
                esc_html( number_format_i18n( (int) $new_invoice->get_meta( TopUp::ORDER_SATS ) ) ),
                esc_html( get_the_title( (int) $new_invoice->get_meta( TopUp::ORDER_SPONSOR ) ) )
            );
            ?>
        </p>
        <p>
            <input type="text" readonly class="large-text code"
                   onclick="this.select()"
                   value="<?php echo esc_attr( $new_invoice->get_checkout_payment_url() ); ?>">
        </p>
    </div>
<?php elseif ( $notice === 'invoice-error' ) : ?>
    <div class="notice notice-error"><p><?php esc_html_e( 'Rechnung konnte nicht erstellt werden.', 'sk-core' ); ?></p></div>
<?php endif; ?>

<div class="sk-php-dashboard-header" style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:16px;">
    <div>
        <h2 style="margin:0;"><?php esc_html_e( 'Sponsoren', 'sk-core' ); ?></h2>
        <p style="margin:4px 0 0;color:#646970;">
            <?php
            printf(
                /* translators: 1: number of clicks, 2: number of days */
                esc_html__( '%1$s Klicks in den letzten %2$d Tagen.', 'sk-core' ),
                '<strong>' . esc_html( number_format_i18n( $total_clicks ) ) . '</strong>',
                (int) $days
            );
            ?>
            &nbsp;·&nbsp;
            <?php
            printf(
                /* translators: 1: sats per month, 2: paying sponsors, 3: total sponsors */
                esc_html__( '%1$s Sats/Monat von %2$d der %3$d Plätze.', 'sk-core' ),
                '<strong>' . esc_html( number_format_i18n( $monthly_income ) ) . '</strong>',
                (int) $paying,
                count( $sponsors )
            );
            ?>
        </p>
    </div>

    <div style="display:flex;gap:8px;align-items:center;">
        <?php foreach ( [ 7, 30, 90, 365 ] as $option ) : ?>
            <a class="button<?php echo $days === $option ? ' button-primary' : ''; ?>"
               href="<?php echo esc_url( add_query_arg( 'days', $option, $base_url ) ); ?>">
                <?php printf( esc_html__( '%d Tage', 'sk-core' ), (int) $option ); ?>
            </a>
        <?php endforeach; ?>

        <form method="post" action="<?php echo esc_url( $base_url ); ?>" style="display:inline;">
            <?php wp_nonce_field( 'sk_sponsors_action', 'sk_sponsors_nonce' ); ?>
            <input type="hidden" name="sk_sponsors_action" value="check_backlinks">
            <button type="submit" class="button"><?php esc_html_e( 'Rücklinks prüfen', 'sk-core' ); ?></button>
        </form>

        <a class="button button-primary"
           href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . PostType::POST_TYPE ) ); ?>">
            <?php esc_html_e( 'Sponsor anlegen', 'sk-core' ); ?>
        </a>
    </div>
</div>

<div style="background:#fff;border:1px solid #c3c4c7;padding:10px 14px;margin-bottom:16px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
    <strong><?php esc_html_e( 'Monatliche Abbuchung:', 'sk-core' ); ?></strong>
    <span><?php echo $billing_enabled ? esc_html__( 'eingeschaltet', 'sk-core' ) : esc_html__( 'abgeschaltet', 'sk-core' ); ?></span>
    <form method="post" action="<?php echo esc_url( $base_url ); ?>" style="display:inline;">
        <?php wp_nonce_field( 'sk_sponsors_action', 'sk_sponsors_nonce' ); ?>
        <input type="hidden" name="sk_sponsors_action" value="toggle_billing">
        <button type="submit" class="button"><?php echo $billing_enabled ? esc_html__( 'Abschalten', 'sk-core' ) : esc_html__( 'Einschalten', 'sk-core' ); ?></button>
    </form>
    <span style="color:#646970;font-size:12px;">
        <?php esc_html_e( 'Solange sie aus ist, wird nichts verbraucht und niemand verliert seinen Platz wegen leeren Guthabens.', 'sk-core' ); ?>
    </span>
</div>

<?php if ( $legacy_pending > 0 ) : ?>
    <div class="notice notice-info" style="padding:12px;">
        <p style="margin-top:0;">
            <?php
            printf(
                /* translators: %d: number of legacy posts */
                esc_html__( 'Es liegen noch %d Sponsoren als Beiträge in der Kategorie „sponsoren". Der Import übernimmt Titel, Text, Logo, Ziel-URL und Reihenfolge; die Beiträge selbst bleiben unverändert.', 'sk-core' ),
                (int) $legacy_pending
            );
            ?>
        </p>
        <form method="post" action="<?php echo esc_url( $base_url ); ?>">
            <?php wp_nonce_field( 'sk_sponsors_action', 'sk_sponsors_nonce' ); ?>
            <input type="hidden" name="sk_sponsors_action" value="import_legacy">
            <button type="submit" class="button"><?php esc_html_e( 'Bestandssponsoren importieren', 'sk-core' ); ?></button>
        </form>
    </div>
<?php endif; ?>

<?php if ( empty( $sponsors ) ) : ?>
    <p><?php esc_html_e( 'Noch keine Sponsoren angelegt.', 'sk-core' ); ?></p>
<?php else : ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width:56px;"></th>
                <th><?php esc_html_e( 'Sponsor', 'sk-core' ); ?></th>
                <th style="width:90px;"><?php esc_html_e( 'Stufe', 'sk-core' ); ?></th>
                <th style="width:120px;"><?php esc_html_e( 'Monatsrate', 'sk-core' ); ?></th>
                <th style="width:130px;"><?php esc_html_e( 'Guthaben', 'sk-core' ); ?></th>
                <th style="width:110px;"><?php esc_html_e( 'Rücklink', 'sk-core' ); ?></th>
                <th><?php esc_html_e( 'Ziel', 'sk-core' ); ?></th>
                <th style="width:110px;"><?php esc_html_e( 'Klicks', 'sk-core' ); ?></th>
                <th style="width:110px;"><?php esc_html_e( 'Besucher', 'sk-core' ); ?></th>
                <th style="width:120px;"><?php esc_html_e( 'Status', 'sk-core' ); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $sponsors as $sponsor ) : ?>
            <?php
            $stat    = $clicks[ $sponsor->ID ] ?? [ 'clicks' => 0, 'unique' => 0 ];
            $url     = (string) get_post_meta( $sponsor->ID, PostType::META_URL, true );
            $is_top  = PostType::get_tier( $sponsor->ID ) === PostType::TIER_TOP;
            $running = $sponsor->post_status === 'publish' && PostType::is_running( (int) $sponsor->ID );
            $expires = (string) get_post_meta( $sponsor->ID, PostType::META_EXPIRES, true );
            ?>
            <tr>
                <td>
                    <?php if ( has_post_thumbnail( $sponsor->ID ) ) : ?>
                        <?php echo get_the_post_thumbnail( $sponsor->ID, [ 40, 40 ], [ 'style' => 'width:40px;height:40px;object-fit:contain;' ] ); ?>
                    <?php endif; ?>
                </td>
                <td>
                    <strong>
                        <a href="<?php echo esc_url( get_edit_post_link( $sponsor->ID ) ); ?>">
                            <?php echo esc_html( $sponsor->post_title ); ?>
                        </a>
                    </strong>
                    <div style="color:#646970;font-size:12px;">
                        <?php echo esc_html( home_url( '/go/' . $sponsor->post_name . '/' ) ); ?>
                    </div>
                </td>
                <td><?php echo $is_top ? '<span class="dashicons dashicons-star-filled" style="color:#db6218;"></span> ' . esc_html( _x( 'Top', 'Sponsorenstufe', 'sk-core' ) ) : esc_html( _x( 'Standard', 'Sponsorenstufe', 'sk-core' ) ); ?></td>
                <?php
                $rate    = (int) get_post_meta( $sponsor->ID, PostType::META_MONTHLY, true );
                $balance = (int) get_post_meta( $sponsor->ID, PostType::META_BALANCE, true );
                $left    = PostType::months_left( (int) $sponsor->ID );
                $bl      = Backlink::status( (int) $sponsor->ID );
                ?>
                <td>
                    <?php if ( $rate > 0 ) : ?>
                        <strong><?php echo esc_html( number_format_i18n( $rate ) ); ?></strong> sats
                    <?php else : ?>
                        <span style="color:#d63638;"><?php esc_html_e( 'zahlt nichts', 'sk-core' ); ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php echo esc_html( number_format_i18n( $balance ) ); ?> sats
                    <?php if ( $left !== null ) : ?>
                        <div style="color:#646970;font-size:12px;">
                            <?php printf( esc_html__( 'noch %d Monate', 'sk-core' ), (int) $left ); ?>
                        </div>
                    <?php endif; ?>
                </td>
                <td>
                    <?php
                    if ( $bl === 1 ) {
                        echo '<span style="color:#008a20;">' . esc_html__( 'ja', 'sk-core' ) . '</span>';
                    } elseif ( $bl === 0 ) {
                        echo '<span style="color:#d63638;">' . esc_html__( 'nein', 'sk-core' ) . '</span>';
                    } elseif ( $bl === -1 ) {
                        echo '<span style="color:#646970;">' . esc_html__( 'nicht prüfbar', 'sk-core' ) . '</span>';
                    } else {
                        echo '<span style="color:#646970;">' . esc_html__( 'ungeprüft', 'sk-core' ) . '</span>';
                    }
                    ?>
                </td>
                <td>
                    <?php if ( $url ) : ?>
                        <a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener nofollow">
                            <?php echo esc_html( wp_parse_url( $url, PHP_URL_HOST ) ); ?>
                        </a>
                    <?php else : ?>
                        <span style="color:#d63638;"><?php esc_html_e( 'fehlt', 'sk-core' ); ?></span>
                    <?php endif; ?>
                </td>
                <td><strong><?php echo esc_html( number_format_i18n( $stat['clicks'] ) ); ?></strong></td>
                <td><?php echo esc_html( number_format_i18n( $stat['unique'] ) ); ?></td>
                <td>
                    <?php if ( $running ) : ?>
                        <span style="color:#008a20;">&#9679; <?php esc_html_e( 'sichtbar', 'sk-core' ); ?></span>
                        <?php if ( $expires !== '' ) : ?>
                            <div style="color:#646970;font-size:12px;"><?php printf( esc_html__( 'bis %s', 'sk-core' ), esc_html( $expires ) ); ?></div>
                        <?php endif; ?>
                    <?php else : ?>
                        <span style="color:#d63638;">&#9679; <?php esc_html_e( 'inaktiv', 'sk-core' ); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div style="background:#fff;border:1px solid #c3c4c7;padding:14px;margin-top:20px;max-width:760px;">
        <h3 style="margin-top:0;"><?php esc_html_e( 'Guthaben aufladen', 'sk-core' ); ?></h3>
        <p style="color:#646970;margin-top:0;">
            <?php esc_html_e( 'Erzeugt eine BTCPay-Rechnung. Den Zahllink an den Sponsor schicken — sobald bezahlt ist, wird das Guthaben automatisch gutgeschrieben. Ein Benutzerkonto braucht der Sponsor dafür nicht.', 'sk-core' ); ?>
        </p>
        <form method="post" action="<?php echo esc_url( $base_url ); ?>" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
            <?php wp_nonce_field( 'sk_sponsors_action', 'sk_sponsors_nonce' ); ?>
            <input type="hidden" name="sk_sponsors_action" value="create_invoice">
            <label>
                <span style="display:block;font-weight:600;"><?php esc_html_e( 'Sponsor', 'sk-core' ); ?></span>
                <select name="sk_sponsor_id" required>
                    <?php foreach ( $sponsors as $s ) : ?>
                        <option value="<?php echo (int) $s->ID; ?>"><?php echo esc_html( $s->post_title ); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span style="display:block;font-weight:600;"><?php esc_html_e( 'Betrag (Sats)', 'sk-core' ); ?></span>
                <input type="number" name="sk_topup_sats" min="1" step="1000" value="75000" required>
            </label>
            <label>
                <span style="display:block;font-weight:600;"><?php esc_html_e( 'E-Mail (optional)', 'sk-core' ); ?></span>
                <input type="email" name="sk_topup_email" placeholder="rechnung@shop.example">
            </label>
            <button type="submit" class="button button-primary"><?php esc_html_e( 'Rechnung erstellen', 'sk-core' ); ?></button>
        </form>
    </div>

    <p style="color:#646970;margin-top:12px;">
        <?php esc_html_e( '„Klicks" zählt jeden Aufruf, „Besucher" zählt jede Person nur einmal pro Tag. Bots, Vorab-Ladevorgänge und Link-Vorschauen werden nicht gezählt.', 'sk-core' ); ?>
        <?php esc_html_e( 'Für ein Angebot an einen Sponsor ist die Besucherzahl die belastbarere Größe.', 'sk-core' ); ?>
    </p>
<?php endif; ?>
