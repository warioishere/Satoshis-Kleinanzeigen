<?php
/**
 * Mail an den Käufer: Bestellbestätigung.
 *
 * @var array $data
 */

defined( 'ABSPATH' ) || exit;
?>
<div style="font-family:-apple-system,Segoe UI,Roboto,sans-serif;font-size:15px;color:#1a2332;line-height:1.6;">
    <p style="margin:0 0 16px;">
        <?php
        printf(
            /* translators: %s: shop name */
            esc_html__( 'Danke für deine Bestellung bei %s. Die Zahlung ist bestätigt.', 'sk-core' ),
            '<strong>' . esc_html( $data['shop'] ) . '</strong>'
        );
        ?>
    </p>

    <table cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:0 0 18px;">
        <tr>
            <td style="padding:4px 16px 4px 0;color:#5a6a7e;"><?php esc_html_e( 'Artikel', 'sk-core' ); ?></td>
            <td style="padding:4px 0;font-weight:600;">
                <?php if ( $data['produkt'] !== '' ) : ?>
                    <a href="<?php echo esc_url( $data['produkt'] ); ?>" style="color:#1a2332;"><?php echo esc_html( $data['titel'] ); ?></a>
                <?php else : ?>
                    <?php echo esc_html( $data['titel'] ); ?>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <td style="padding:4px 16px 4px 0;color:#5a6a7e;"><?php esc_html_e( 'Bezahlt', 'sk-core' ); ?></td>
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
    </table>

    <p style="margin:0 0 18px;color:#5a6a7e;font-size:13px;">
        <?php esc_html_e( 'Der Anbieter versendet die Ware. Rückfragen gehen direkt an ihn — Satoshis Kleinanzeigen tritt nicht als Zwischenhändler auf und hält kein Geld.', 'sk-core' ); ?>
    </p>

    <?php if ( $data['chat'] !== '' ) : ?>
        <p style="margin:0;">
            <a href="<?php echo esc_url( $data['chat'] ); ?>" style="color:#f7931a;"><?php esc_html_e( 'Chat mit dem Anbieter öffnen', 'sk-core' ); ?></a>
        </p>
    <?php endif; ?>
</div>
