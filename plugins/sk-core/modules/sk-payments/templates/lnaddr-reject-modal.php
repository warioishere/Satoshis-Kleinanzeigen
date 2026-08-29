<?php
/**
 * Erklärt, warum eine Lightning-Adresse nicht angenommen wurde.
 *
 * Optik und Verhalten wie das Paket-Infofenster im Shop-Import: dieselben
 * sk-pack-info-Klassen, damit es sich nicht wie ein Fremdkörper anfühlt und
 * kein zweites Modal-Design entsteht.
 *
 * Geöffnet wird es von sk-payments-lnaddr.js, sobald eine Prüfung oder ein
 * Speicherversuch die Adresse abweist.
 */

defined( 'ABSPATH' ) || exit;
?>
<div id="skp-lnaddr-reject" class="sk-pack-info">
    <div class="sk-pack-info__backdrop"></div>
    <div class="sk-pack-info__box" role="dialog" aria-modal="true" aria-labelledby="skp-lnaddr-reject-title">
        <div class="sk-pack-info__icon"><i class="fas fa-bolt"></i></div>
        <h3 id="skp-lnaddr-reject-title"><?php esc_html_e( 'Diese Wallet können wir nicht abrechnen', 'sk-core' ); ?></h3>

        <p id="skp-lnaddr-reject-reason">
            <?php esc_html_e( 'Deine Wallet kann uns nicht bestätigen, ob eine Rechnung bezahlt wurde. Damit lässt sich ein Verkauf nicht abrechnen — deshalb nehmen wir die Adresse nicht an.', 'sk-core' ); ?>
        </p>

        <ul class="sk-pack-info__list">
            <li>
                <i class="fas fa-circle-check"></i>
                <span>
                    <strong><?php esc_html_e( 'Diese gehen', 'sk-core' ); ?></strong>
                    <?php esc_html_e( 'Alby, Blink, Coinos, Stacker.News und ein eigener BTCPay-Server. Dort haben wir nachgemessen, dass sich eine Zahlung bestätigen lässt.', 'sk-core' ); ?>
                </span>
            </li>
            <li>
                <i class="fas fa-circle-xmark"></i>
                <span>
                    <strong><?php esc_html_e( 'Diese gehen nicht', 'sk-core' ); ?></strong>
                    <?php esc_html_e( 'Wallet of Satoshi, ZBD, Fountain und LNbits. Sie geben keine Auskunft darüber, ob eine Rechnung beglichen wurde.', 'sk-core' ); ?>
                </span>
            </li>
            <li>
                <i class="fas fa-plug"></i>
                <span>
                    <strong><?php esc_html_e( 'Oder ganz ohne Adresse', 'sk-core' ); ?></strong>
                    <?php esc_html_e( 'Verbinde stattdessen NWC weiter oben. Damit fragen wir deine Wallet direkt, ob gezahlt wurde — unabhängig davon, was deine Lightning-Adresse kann.', 'sk-core' ); ?>
                </span>
            </li>
        </ul>

        <p class="sk-pack-info__note">
            <?php esc_html_e( 'Bis dahin bleibt deine bisherige Adresse gespeichert. Verkaufen kannst du weiter — nur diese Adresse übernehmen wir nicht.', 'sk-core' ); ?>
        </p>

        <button type="button" class="sk-pack-info__close"><?php esc_html_e( 'Verstanden', 'sk-core' ); ?></button>
    </div>
</div>
