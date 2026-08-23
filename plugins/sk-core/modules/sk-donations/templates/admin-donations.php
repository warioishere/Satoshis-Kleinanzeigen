<?php
/**
 * SK → Spenden.
 *
 * @var int    $goal
 * @var int    $month
 * @var int    $total
 * @var int    $coverage
 * @var bool   $dashboard
 * @var bool   $sold_modal
 * @var string $p_modal
 * @var string $p_bar
 * @var int    $month_wc
 * @var int    $month_bp
 * @var int    $since
 * @var bool   $btcpay_ok
 * @var string $exclude
 * @var array  $sources
 * @var string $notice
 * @var array  $orders
 * @var array  $history
 */

defined( 'ABSPATH' ) || exit;

$base_url = add_query_arg( [ 'page' => 'sk', 'tab' => 'donations' ], admin_url( 'admin.php' ) );
$max      = max( 1, max( $history ) );
?>

<?php if ( $notice === 'saved' ) : ?>
    <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Gespeichert.', 'sk-core' ); ?></p></div>
<?php elseif ( $notice === 'refreshed' ) : ?>
    <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Zahlen vom BTCPay-Server neu geladen.', 'sk-core' ); ?></p></div>
<?php endif; ?>

<h2 style="margin-top:0;"><?php esc_html_e( 'Spenden', 'sk-core' ); ?></h2>

<div style="display:flex;gap:16px;flex-wrap:wrap;margin:16px 0;">
    <?php
    $cards = [
        [ __( 'Diesen Monat', 'sk-core' ), number_format_i18n( $month ) . ' Sats' ],
        [ __( 'Monatsbedarf', 'sk-core' ), number_format_i18n( $goal ) . ' Sats' ],
        [ __( 'Gedeckt', 'sk-core' ), $coverage . ' %' ],
        [ __( 'Seit Stichtag', 'sk-core' ), number_format_i18n( $total ) . ' Sats' ],
    ];
    foreach ( $cards as $card ) :
        ?>
        <div style="background:#fff;border:1px solid #c3c4c7;padding:12px 16px;min-width:150px;">
            <div style="font-size:12px;color:#646970;"><?php echo esc_html( $card[0] ); ?></div>
            <div style="font-size:22px;font-weight:600;"><?php echo esc_html( $card[1] ); ?></div>
        </div>
    <?php endforeach; ?>
</div>

<div style="background:#fff;border:1px solid #c3c4c7;padding:14px;max-width:760px;margin-bottom:16px;">
    <h3 style="margin-top:0;"><?php esc_html_e( 'Woher der Monatsstand kommt', 'sk-core' ); ?></h3>
    <table class="widefat striped" style="max-width:420px;">
        <tbody>
            <tr>
                <td><?php esc_html_e( 'WooCommerce (Balken, Modal)', 'sk-core' ); ?></td>
                <td style="text-align:right;"><strong><?php echo esc_html( number_format_i18n( $month_wc ) ); ?></strong> Sats</td>
            </tr>
            <tr>
                <td>
                    <?php esc_html_e( 'BTCPay-Crowdfund', 'sk-core' ); ?>
                    <?php if ( ! $btcpay_ok ) : ?>
                        <span style="color:#d63638;">— <?php esc_html_e( 'nicht konfiguriert', 'sk-core' ); ?></span>
                    <?php endif; ?>
                </td>
                <td style="text-align:right;"><strong><?php echo esc_html( number_format_i18n( $month_bp ) ); ?></strong> Sats</td>
            </tr>
        </tbody>
    </table>
    <p style="color:#646970;font-size:12px;">
        <?php esc_html_e( 'Crowdfund-Zahlungen laufen auf dem BTCPay-Server und berühren WooCommerce nicht; sie werden über die API dazugeholt. Es gibt keine feste Liste von Crowdfunds — jede bezahlte Rechnung ohne WooCommerce-Bestellnummer zählt, gelöschte Apps stören nicht, neue erscheinen von selbst. Rechnungen in Euro oder Franken bleiben unberücksichtigt, weil ein geschätzter Kurs in einer Zahlenanzeige nichts verloren hat.', 'sk-core' ); ?>
    </p>

    <form method="post" action="<?php echo esc_url( $base_url ); ?>" style="margin-bottom:12px;">
        <?php wp_nonce_field( 'sk_donations_action', 'sk_donations_nonce' ); ?>
        <input type="hidden" name="sk_donations_action" value="refresh">
        <button type="submit" class="button"><?php esc_html_e( 'Zahlen neu laden', 'sk-core' ); ?></button>
        <span style="color:#646970;font-size:12px;margin-left:6px;">
            <?php esc_html_e( 'Die Abfrage ist 15 Minuten zwischengespeichert.', 'sk-core' ); ?>
        </span>
    </form>

    <?php if ( ! empty( $sources ) ) : ?>
        <h4 style="margin-bottom:6px;"><?php esc_html_e( 'Was der Server seit dem Stichtag meldet', 'sk-core' ); ?></h4>
        <table class="widefat striped" style="max-width:560px;">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Beschreibung', 'sk-core' ); ?></th>
                    <th style="width:60px;text-align:right;"><?php esc_html_e( 'Zahl.', 'sk-core' ); ?></th>
                    <th style="width:110px;text-align:right;"><?php esc_html_e( 'Sats', 'sk-core' ); ?></th>
                    <th style="width:90px;"><?php esc_html_e( 'gezählt', 'sk-core' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $sources as $desc => $row ) : ?>
                <tr>
                    <td><?php echo esc_html( $desc ); ?></td>
                    <td style="text-align:right;"><?php echo (int) $row['n']; ?></td>
                    <td style="text-align:right;"><?php echo esc_html( number_format_i18n( (int) $row['sats'] ) ); ?></td>
                    <td>
                        <?php if ( $row['gezaehlt'] ) : ?>
                            <span style="color:#008a20;"><?php esc_html_e( 'ja', 'sk-core' ); ?></span>
                        <?php else : ?>
                            <span style="color:#646970;"><?php esc_html_e( 'nein', 'sk-core' ); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div style="background:#fff;border:1px solid #c3c4c7;padding:14px;max-width:760px;margin-bottom:20px;">
    <h3 style="margin-top:0;"><?php esc_html_e( 'Einstellungen', 'sk-core' ); ?></h3>
    <form method="post" action="<?php echo esc_url( $base_url ); ?>">
        <?php wp_nonce_field( 'sk_donations_action', 'sk_donations_nonce' ); ?>
        <input type="hidden" name="sk_donations_action" value="save">
        <p>
            <label>
                <strong><?php esc_html_e( 'Monatsbedarf in Sats', 'sk-core' ); ?></strong><br>
                <input type="number" name="sk_donations_goal" min="0" step="1000" value="<?php echo esc_attr( (string) $goal ); ?>">
            </label>
            <span style="color:#646970;font-size:12px;margin-left:8px;">
                <?php esc_html_e( 'Wird im Balken als Ziel genannt. Die Voreinstellung stammt aus der Spendenseite: 210.000 Sats für drei Monate.', 'sk-core' ); ?>
            </span>
        </p>
        <p style="margin-bottom:4px;"><strong><?php esc_html_e( 'Platzierungen', 'sk-core' ); ?></strong></p>
        <p style="margin-top:0;">
            <label style="display:block;margin-bottom:6px;">
                <input type="checkbox" name="sk_donations_sold_modal" value="1" <?php checked( $sold_modal ); ?>>
                <?php esc_html_e( 'Modal nach dem Löschen eines Inserats', 'sk-core' ); ?>
                <span style="color:#646970;font-size:12px;">— <?php esc_html_e( 'der wahrscheinlichste Moment eines erfolgreichen Verkaufs', 'sk-core' ); ?></span>
            </label>
            <label style="display:block;">
                <input type="checkbox" name="sk_donations_dashboard" value="1" <?php checked( $dashboard ); ?>>
                <?php esc_html_e( 'Kostenbalken am Ende des Verkäufer-Dashboards', 'sk-core' ); ?>
            </label>
        </p>
        <p style="color:#646970;font-size:12px;margin-top:0;">
            <?php esc_html_e( 'Einzeln abschaltbar, damit sich nacheinander messen lässt, welche Platzierung etwas bringt.', 'sk-core' ); ?>
        </p>

        <p style="margin-bottom:4px;"><strong><?php esc_html_e( 'Betragsvorschläge', 'sk-core' ); ?></strong></p>
        <p style="margin-top:0;">
            <label style="display:block;margin-bottom:8px;">
                <?php esc_html_e( 'Im Modal', 'sk-core' ); ?><br>
                <input type="text" name="sk_donations_presets_modal" value="<?php echo esc_attr( $p_modal ); ?>" class="regular-text" placeholder="2100, 5000, 21000">
            </label>
            <label style="display:block;">
                <?php esc_html_e( 'Im Kostenbalken', 'sk-core' ); ?><br>
                <input type="text" name="sk_donations_presets_bar" value="<?php echo esc_attr( $p_bar ); ?>" class="regular-text" placeholder="5000, 21000, 100000">
            </label>
        </p>
        <p style="color:#646970;font-size:12px;margin-top:0;">
            <?php esc_html_e( 'Beträge in Sats, durch Komma getrennt, höchstens vier. Leer lassen setzt die Voreinstellung zurück. Der Kostenbalken hat zusätzlich ein freies Betragsfeld, das Modal bewusst nicht.', 'sk-core' ); ?>
        </p>
        <p style="color:#646970;font-size:12px;margin-top:0;">
            <?php esc_html_e( 'An anderen Stellen lässt er sich mit dem Shortcode einsetzen:', 'sk-core' ); ?>
            <code>[sk_donation_bar]</code> <?php esc_html_e( 'oder kompakt', 'sk-core' ); ?> <code>[sk_donation_bar compact="yes"]</code>
        </p>
        <p style="margin-bottom:4px;"><strong><?php esc_html_e( 'Nicht als Spende zählen', 'sk-core' ); ?></strong></p>
        <p style="margin-top:0;">
            <input type="text" name="sk_donations_exclude" value="<?php echo esc_attr( $exclude ); ?>" class="large-text" placeholder="Kontaktzugriff, Pay-Wall">
        </p>
        <p style="color:#646970;font-size:12px;margin-top:0;">
            <?php esc_html_e( 'Textbausteine, durch Komma getrennt. Eine Rechnung, deren Beschreibung einen davon enthält, wird übersprungen — gedacht für die Kontaktdaten-Feewall, die Kontaktzugriffe verkauft und keine Spende ist.', 'sk-core' ); ?>
        </p>

        <p style="margin-bottom:4px;"><strong><?php esc_html_e( 'Gezählt wird ab', 'sk-core' ); ?></strong></p>
        <p style="margin-top:0;">
            <input type="date" name="sk_donations_since" value="<?php echo esc_attr( gmdate( 'Y-m-d', $since ) ); ?>">
        </p>
        <p style="color:#646970;font-size:12px;margin-top:0;">
            <?php esc_html_e( 'Alles davor bleibt draußen. Die Crowdfunds der Aufbauphase 2025 haben rund 4,1 Mio Sats eingebracht — mitgezählt stünde der Balken dauerhaft auf 100 Prozent.', 'sk-core' ); ?>
        </p>

        <button type="submit" class="button button-primary"><?php esc_html_e( 'Speichern', 'sk-core' ); ?></button>
    </form>
</div>

<h3><?php esc_html_e( 'Verlauf (12 Monate)', 'sk-core' ); ?></h3>
<table class="wp-list-table widefat striped" style="max-width:520px;margin-bottom:20px;">
    <tbody>
    <?php foreach ( $history as $ym => $sats ) : ?>
        <tr>
            <td style="width:90px;"><?php echo esc_html( $ym ); ?></td>
            <td style="width:120px;text-align:right;"><?php echo esc_html( number_format_i18n( $sats ) ); ?> Sats</td>
            <td>
                <div style="background:#f0f0f1;height:14px;">
                    <div style="background:#db6218;height:14px;width:<?php echo (int) round( $sats / $max * 100 ); ?>%;"></div>
                </div>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<h3><?php esc_html_e( 'Letzte Spenden', 'sk-core' ); ?></h3>
<?php if ( empty( $orders ) ) : ?>
    <p><?php esc_html_e( 'Noch keine Spende über diesen Weg eingegangen.', 'sk-core' ); ?></p>
<?php else : ?>
    <table class="wp-list-table widefat fixed striped" style="max-width:760px;">
        <thead>
            <tr>
                <th style="width:90px;"><?php esc_html_e( 'Bestellung', 'sk-core' ); ?></th>
                <th style="width:150px;"><?php esc_html_e( 'Datum', 'sk-core' ); ?></th>
                <th style="width:120px;"><?php esc_html_e( 'Betrag', 'sk-core' ); ?></th>
                <th><?php esc_html_e( 'Status', 'sk-core' ); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $orders as $order ) : ?>
            <tr>
                <td><a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">#<?php echo (int) $order->get_id(); ?></a></td>
                <td><?php echo esc_html( $order->get_date_created() ? $order->get_date_created()->date_i18n( 'd.m.Y H:i' ) : '' ); ?></td>
                <td><?php echo esc_html( number_format_i18n( (int) $order->get_total() ) ); ?> Sats</td>
                <td><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
