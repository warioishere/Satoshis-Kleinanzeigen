<?php
/**
 * Erklärt den Katalogimport auf der Abo-Seite.
 * Optik bewusst wie das Spenden-Modal nach dem Löschen eines Inserats.
 */

defined( 'ABSPATH' ) || exit;
?>
<div id="sk-pack-info" class="sk-pack-info">
    <div class="sk-pack-info__backdrop"></div>
    <div class="sk-pack-info__box" role="dialog" aria-modal="true" aria-labelledby="sk-pack-info-title">
        <div class="sk-pack-info__icon"><i class="fas fa-file-csv"></i></div>
        <h3 id="sk-pack-info-title"><?php esc_html_e( 'Shop-Katalog importieren', 'sk-core' ); ?></h3>
        <p>
            <?php esc_html_e( 'Betreibst du einen eigenen WooCommerce-Shop, musst du deine Artikel hier nicht von Hand nacherfassen. Du exportierst sie in deinem Shop als CSV-Datei und lädst sie im Dashboard hoch — daraus werden Inserate.', 'sk-core' ); ?>
        </p>

        <ul class="sk-pack-info__list">
            <li>
                <i class="fas fa-layer-group"></i>
                <span><?php esc_html_e( 'Variable Produkte kommen als Ausführungen herein — ein Inserat statt einer Zeile je Variante — und bleiben hier bearbeitbar.', 'sk-core' ); ?></span>
            </li>
            <li>
                <i class="fas fa-bolt"></i>
                <span><?php esc_html_e( 'Preise in Euro oder Franken werden in Sats umgerechnet und täglich am Kurs nachgeführt.', 'sk-core' ); ?></span>
            </li>
            <li>
                <i class="fas fa-images"></i>
                <span><?php esc_html_e( 'Bilder und Kategorien kommen mit, bis zu fünf Bilder je Artikel.', 'sk-core' ); ?></span>
            </li>
            <li>
                <i class="fas fa-hand-pointer"></i>
                <span><?php esc_html_e( 'Du wählst vor dem Import aus, welche Artikel übernommen werden.', 'sk-core' ); ?></span>
            </li>
        </ul>

        <p class="sk-pack-info__note">
            <?php esc_html_e( 'Der Import mit Ausführungen ist ab diesem Paket freigeschaltet.', 'sk-core' ); ?>
        </p>

        <button type="button" class="sk-pack-info__close"><?php esc_html_e( 'Schliessen', 'sk-core' ); ?></button>
    </div>
</div>
