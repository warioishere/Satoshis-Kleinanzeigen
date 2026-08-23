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
<?php endif; ?>

<h2 style="margin-top:0;"><?php esc_html_e( 'Spenden', 'sk-core' ); ?></h2>

<div style="display:flex;gap:16px;flex-wrap:wrap;margin:16px 0;">
    <?php
    $cards = [
        [ __( 'Diesen Monat', 'sk-core' ), number_format_i18n( $month ) . ' Sats' ],
        [ __( 'Monatsbedarf', 'sk-core' ), number_format_i18n( $goal ) . ' Sats' ],
        [ __( 'Gedeckt', 'sk-core' ), $coverage . ' %' ],
        [ __( 'Insgesamt', 'sk-core' ), number_format_i18n( $total ) . ' Sats' ],
    ];
    foreach ( $cards as $card ) :
        ?>
        <div style="background:#fff;border:1px solid #c3c4c7;padding:12px 16px;min-width:150px;">
            <div style="font-size:12px;color:#646970;"><?php echo esc_html( $card[0] ); ?></div>
            <div style="font-size:22px;font-weight:600;"><?php echo esc_html( $card[1] ); ?></div>
        </div>
    <?php endforeach; ?>
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
        <p style="color:#646970;font-size:12px;margin-top:0;">
            <?php esc_html_e( 'An anderen Stellen lässt er sich mit dem Shortcode einsetzen:', 'sk-core' ); ?>
            <code>[sk_donation_bar]</code> <?php esc_html_e( 'oder kompakt', 'sk-core' ); ?> <code>[sk_donation_bar compact="yes"]</code>
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
