<?php
/**
 * Last look before the payment page.
 *
 * The chosen package and term only exist in the card until the buy button
 * is pressed, so the box is filled from that card in the browser. Same
 * classes as the shop info modal, so it is the same box to the eye.
 */

defined( 'ABSPATH' ) || exit;
?>
<div id="sk-pack-confirm" class="sk-pack-info">
    <div class="sk-pack-info__backdrop"></div>
    <div class="sk-pack-info__box" role="dialog" aria-modal="true" aria-labelledby="sk-pack-confirm-title">
        <div class="sk-pack-info__icon"><i class="fas fa-box-open"></i></div>
        <h3 id="sk-pack-confirm-title" data-role="title"><?php esc_html_e( 'Dein Paket', 'sk-core' ); ?></h3>

        <p class="sk-pack-confirm__summary">
            <strong data-role="price"></strong>
            <span data-role="term"></span>
        </p>

        <ul class="sk-pack-info__list" data-role="facts"></ul>

        <p class="sk-pack-info__note">
            <?php esc_html_e( 'Weiter geht es zur Zahlung. Das Paket startet, sobald die Zahlung bestätigt ist.', 'sk-core' ); ?>
        </p>

        <div class="sk-pack-confirm__actions">
            <?php // buy_product_pack: from here the usual purchase runs, sk-buynow.js picks it up. ?>
            <a href="#" class="sk-pack-confirm__go buy_product_pack" data-role="go"><?php esc_html_e( 'Zur Zahlung', 'sk-core' ); ?></a>
            <button type="button" class="sk-pack-info__close"><?php esc_html_e( 'Abbrechen', 'sk-core' ); ?></button>
        </div>
    </div>
</div>
