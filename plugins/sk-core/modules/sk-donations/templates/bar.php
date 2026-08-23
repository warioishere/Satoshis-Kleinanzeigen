<?php
/**
 * Spendenbalken.
 *
 * @var int   $goal
 * @var int   $received
 * @var int   $coverage
 * @var int   $missing
 * @var bool  $error
 * @var array $presets
 * @var bool  $compact
 * @var string $heading
 */

defined( 'ABSPATH' ) || exit;

use SK\Modules\Donations\Donations;
?>
<section class="sk-donate<?php echo $compact ? ' sk-donate--compact' : ''; ?>">
    <h3 class="sk-donate__heading">
        <?php echo esc_html( $heading !== '' ? $heading : __( 'Was diese Plattform kostet', 'sk-core' ) ); ?>
    </h3>

    <?php if ( ! $compact ) : ?>
        <p class="sk-donate__intro">
            <?php esc_html_e( 'Satoshis Kleinanzeigen wird ehrenamtlich betrieben — keine Investoren, keine Verkaufsgebühren, kein KYC. Server und Wartung kosten trotzdem.', 'sk-core' ); ?>
        </p>
    <?php endif; ?>

    <?php if ( $error ) : ?>
        <p class="sk-donate__error"><?php esc_html_e( 'Die Zahlung konnte nicht gestartet werden. Bitte versuche es noch einmal.', 'sk-core' ); ?></p>
    <?php endif; ?>

    <p class="sk-donate__numbers">
        <?php
        printf(
            /* translators: 1: monthly goal, 2: received so far */
            esc_html__( 'Dieser Monat: %1$s Sats — gedeckt sind %2$s Sats.', 'sk-core' ),
            '<strong>' . esc_html( number_format_i18n( $goal ) ) . '</strong>',
            '<strong>' . esc_html( number_format_i18n( $received ) ) . '</strong>'
        );
        ?>
    </p>

    <div class="sk-donate__track" role="img"
         aria-label="<?php echo esc_attr( sprintf( __( 'Kostendeckung %d Prozent', 'sk-core' ), $coverage ) ); ?>">
        <div class="sk-donate__fill" style="width: <?php echo (int) $coverage; ?>%;"></div>
        <span class="sk-donate__pct"><?php echo (int) $coverage; ?>&nbsp;%</span>
    </div>

    <?php if ( $missing > 0 ) : ?>
        <p class="sk-donate__missing">
            <?php
            printf(
                /* translators: %s: missing sats */
                esc_html__( 'Es fehlen noch %s Sats.', 'sk-core' ),
                esc_html( number_format_i18n( $missing ) )
            );
            ?>
        </p>
    <?php else : ?>
        <p class="sk-donate__missing sk-donate__missing--done">
            <?php esc_html_e( 'Dieser Monat ist gedeckt. Danke.', 'sk-core' ); ?>
        </p>
    <?php endif; ?>

    <form class="sk-donate__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( Donations::ACTION, 'sk_donation_nonce' ); ?>
        <input type="hidden" name="action" value="<?php echo esc_attr( Donations::ACTION ); ?>">

        <div class="sk-donate__amounts">
            <?php foreach ( $presets as $i => $sats ) : ?>
                <label class="sk-donate__amount">
                    <input type="radio" name="sk_donation_sats" value="<?php echo (int) $sats; ?>" <?php checked( $i, 1 ); ?>>
                    <span><?php echo esc_html( number_format_i18n( $sats ) ); ?></span>
                </label>
            <?php endforeach; ?>
            <label class="sk-donate__amount sk-donate__amount--free">
                <input type="number" name="sk_donation_custom" min="1000" step="1000"
                       placeholder="<?php esc_attr_e( 'Betrag', 'sk-core' ); ?>"
                       aria-label="<?php esc_attr_e( 'Eigener Betrag in Sats', 'sk-core' ); ?>">
            </label>
        </div>

        <button type="submit" class="sk-donate__submit">
            <?php esc_html_e( 'Mit Bitcoin unterstützen', 'sk-core' ); ?>
        </button>
    </form>

    <p class="sk-donate__hint">
        <?php esc_html_e( 'Lightning oder Onchain über unseren eigenen BTCPay-Server. Kein Konto nötig.', 'sk-core' ); ?>
    </p>
</section>
