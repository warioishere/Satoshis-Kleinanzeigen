<?php
/**
 * Kleines Modal nach dem Löschen eines Inserats.
 * Optik bewusst wie das Logout-Modal.
 */

defined( 'ABSPATH' ) || exit;

use SK\Modules\Donations\Donations;

$presets = [ 2100, 5000, 21000 ];
?>
<div id="sk-donate-modal" class="sk-donate-modal">
    <div class="sk-donate-modal-backdrop"></div>
    <div class="sk-donate-modal-box">
        <div class="sk-donate-modal-icon"><i class="fas fa-heart"></i></div>
        <h3><?php esc_html_e( 'Erfolgreich verkauft?', 'sk-core' ); ?></h3>
        <p>
            <?php esc_html_e( 'Dann hat die Plattform ihren Zweck erfüllt. Sie läuft ehrenamtlich und ohne Verkaufsgebühren — eine Spende hält sie am Leben.', 'sk-core' ); ?>
        </p>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( Donations::ACTION, 'sk_donation_nonce' ); ?>
            <input type="hidden" name="action" value="<?php echo esc_attr( Donations::ACTION ); ?>">

            <div class="sk-donate-modal-amounts">
                <?php foreach ( $presets as $sats ) : ?>
                    <button type="submit" name="sk_donation_sats" value="<?php echo (int) $sats; ?>" class="sk-donate-modal-amount">
                        <?php echo esc_html( number_format_i18n( $sats ) ); ?>
                        <small><?php esc_html_e( 'Sats', 'sk-core' ); ?></small>
                    </button>
                <?php endforeach; ?>
            </div>
        </form>

        <button type="button" class="sk-donate-modal-close"><?php esc_html_e( 'Jetzt nicht', 'sk-core' ); ?></button>
    </div>
</div>
