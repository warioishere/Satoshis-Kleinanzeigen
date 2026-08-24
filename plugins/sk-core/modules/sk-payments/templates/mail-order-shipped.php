<?php
/**
 * Mail an den Käufer: die Bestellung ist unterwegs.
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
            esc_html__( 'Deine Bestellung bei %s ist unterwegs.', 'sk-core' ),
            '<strong>' . esc_html( $data['shop'] ) . '</strong>'
        );
        ?>
    </p>

    <table cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:0 0 18px;">
        <tr>
            <td style="padding:4px 16px 4px 0;color:#5a6a7e;"><?php esc_html_e( 'Artikel', 'sk-core' ); ?></td>
            <td style="padding:4px 0;font-weight:600;"><?php echo esc_html( $data['titel'] ); ?></td>
        </tr>
        <tr>
            <td style="padding:4px 16px 4px 0;color:#5a6a7e;"><?php esc_html_e( 'Versender', 'sk-core' ); ?></td>
            <td style="padding:4px 0;"><?php echo esc_html( $data['versender'] ); ?></td>
        </tr>
        <?php if ( $data['sendungsnummer'] !== '' ) : ?>
            <tr>
                <td style="padding:4px 16px 4px 0;color:#5a6a7e;"><?php esc_html_e( 'Sendungsnummer', 'sk-core' ); ?></td>
                <td style="padding:4px 0;font-family:monospace;"><?php echo esc_html( $data['sendungsnummer'] ); ?></td>
            </tr>
        <?php endif; ?>
    </table>

    <?php if ( $data['sendungslink'] !== '' ) : ?>
        <p style="margin:0 0 18px;">
            <a href="<?php echo esc_url( $data['sendungslink'] ); ?>"
               style="display:inline-block;padding:10px 18px;background:#f7931a;color:#fff;text-decoration:none;border-radius:6px;font-weight:600;">
                <?php esc_html_e( 'Sendung verfolgen', 'sk-core' ); ?>
            </a>
        </p>
    <?php endif; ?>

    <p style="margin:0 0 18px;color:#5a6a7e;font-size:13px;">
        <?php esc_html_e( 'Ist die Ware angekommen, kannst du das im Dashboard bestätigen — das schreibt dem Anbieter seine Reputation gut.', 'sk-core' ); ?>
    </p>

    <?php if ( $data['chat'] !== '' ) : ?>
        <p style="margin:0;">
            <a href="<?php echo esc_url( $data['chat'] ); ?>" style="color:#f7931a;"><?php esc_html_e( 'Chat mit dem Anbieter öffnen', 'sk-core' ); ?></a>
        </p>
    <?php endif; ?>
</div>
