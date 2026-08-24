<?php
/**
 * Mail an den Anbieter: Zahlung eingegangen.
 *
 * @var array $data
 */

defined( 'ABSPATH' ) || exit;
?>
<div style="font-family:-apple-system,Segoe UI,Roboto,sans-serif;font-size:15px;color:#1a2332;line-height:1.6;">
    <p style="margin:0 0 16px;"><?php esc_html_e( 'Es ist eine Zahlung eingegangen.', 'sk-core' ); ?></p>

    <table cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:0 0 18px;">
        <tr>
            <td style="padding:4px 16px 4px 0;color:#5a6a7e;"><?php esc_html_e( 'Artikel', 'sk-core' ); ?></td>
            <td style="padding:4px 0;font-weight:600;"><?php echo esc_html( $data['titel'] ); ?></td>
        </tr>
        <tr>
            <td style="padding:4px 16px 4px 0;color:#5a6a7e;"><?php esc_html_e( 'Betrag', 'sk-core' ); ?></td>
            <td style="padding:4px 0;font-weight:600;">
                <?php echo esc_html( number_format_i18n( $data['sats'] ) ); ?> Sats<?php
                if ( $data['fiat'] !== '' ) {
                    echo ' <span style="color:#5a6a7e;font-weight:400;">(' . esc_html( $data['fiat'] ) . ')</span>';
                }
                ?>
            </td>
        </tr>
        <tr>
            <td style="padding:4px 16px 4px 0;color:#5a6a7e;"><?php esc_html_e( 'Zahlweg', 'sk-core' ); ?></td>
            <td style="padding:4px 0;"><?php echo esc_html( $data['weg'] ); ?></td>
        </tr>
        <tr>
            <td style="padding:4px 16px 4px 0;color:#5a6a7e;"><?php esc_html_e( 'Käufer', 'sk-core' ); ?></td>
            <td style="padding:4px 0;"><?php echo esc_html( $data['kaeufer'] ); ?></td>
        </tr>
    </table>

    <?php if ( ! empty( $data['shop'] ) && $data['lieferung'] !== '' ) : ?>
        <div style="margin:0 0 18px;padding:12px 14px;background:#f6f7f9;border-left:3px solid #f7931a;">
            <div style="color:#5a6a7e;font-size:13px;margin-bottom:4px;"><?php esc_html_e( 'Lieferangabe des Käufers', 'sk-core' ); ?></div>
            <div style="white-space:pre-line;"><?php echo esc_html( $data['lieferung'] ); ?></div>
        </div>
    <?php elseif ( empty( $data['lieferung'] ) ) : ?>
        <p style="margin:0 0 18px;color:#5a6a7e;font-size:13px;">
            <?php esc_html_e( 'Die Lieferangabe des Käufers steht im Chat zur Bestellung.', 'sk-core' ); ?>
        </p>
    <?php endif; ?>

    <p style="margin:0 0 8px;">
        <a href="<?php echo esc_url( $data['verkaeufe'] ); ?>" style="color:#f7931a;"><?php esc_html_e( 'Zur Verkaufsübersicht', 'sk-core' ); ?></a>
        <?php if ( $data['chat'] !== '' ) : ?>
            &nbsp;·&nbsp;
            <a href="<?php echo esc_url( $data['chat'] ); ?>" style="color:#f7931a;"><?php esc_html_e( 'Chat mit dem Käufer', 'sk-core' ); ?></a>
        <?php endif; ?>
    </p>
</div>
