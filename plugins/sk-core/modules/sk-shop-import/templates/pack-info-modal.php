<?php
/**
 * Erklärt, was ein Shop ab diesem Paket machen kann.
 * Optik bewusst wie das Spenden-Modal nach dem Löschen eines Inserats.
 */

defined( 'ABSPATH' ) || exit;
?>
<div id="sk-pack-info" class="sk-pack-info">
    <div class="sk-pack-info__backdrop"></div>
    <div class="sk-pack-info__box" role="dialog" aria-modal="true" aria-labelledby="sk-pack-info-title">
        <div class="sk-pack-info__icon"><i class="fas fa-store"></i></div>
        <h3 id="sk-pack-info-title"><?php esc_html_e( 'Für Shops', 'sk-core' ); ?></h3>
        <p>
            <?php esc_html_e( 'Betreibst du einen eigenen Shop, musst du deine Artikel hier nicht von Hand nacherfassen — und du kannst sie verkaufen, ohne dass eine Zahlung über uns läuft.', 'sk-core' ); ?>
        </p>

        <ul class="sk-pack-info__list">
            <li>
                <i class="fas fa-file-import"></i>
                <span>
                    <strong><?php esc_html_e( 'WooCommerce Produkt Importe', 'sk-core' ); ?></strong>
                    <?php esc_html_e( 'Du exportierst deinen Katalog als CSV-Datei und lädst sie im Dashboard hoch. Bilder und Kategorien kommen mit, bis zu fünf Bilder je Artikel. Vor dem Import wählst du aus, was übernommen wird.', 'sk-core' ); ?>
                </span>
            </li>
            <li>
                <i class="fas fa-layer-group"></i>
                <span>
                    <strong><?php esc_html_e( 'Variable Produkte', 'sk-core' ); ?></strong>
                    <?php esc_html_e( 'Grössen oder Ausstattungen werden zu Ausführungen eines Inserats zusammengefasst — statt einer eigenen Anzeige je Variante. Sie bleiben hier bearbeitbar, und der Käufer wählt beim Kauf aus.', 'sk-core' ); ?>
                </span>
            </li>
            <li>
                <i class="fas fa-bolt"></i>
                <span>
                    <strong><?php esc_html_e( 'Adaptive Preise in Sats', 'sk-core' ); ?></strong>
                    <?php esc_html_e( 'Du zeichnest in Euro oder Franken aus, angezeigt wird in Sats. Der Fiat-Betrag bleibt die Wahrheit, der Sats-Preis wird täglich am Kurs nachgeführt — auch je Ausführung.', 'sk-core' ); ?>
                </span>
            </li>
            <li>
                <i class="fas fa-clipboard-list"></i>
                <span>
                    <strong><?php esc_html_e( 'Verkaufsübersicht', 'sk-core' ); ?></strong>
                    <?php esc_html_e( 'Jede Bestellung steht unter Käufe/Verkäufe — mit gewählter Ausführung, Lieferangabe des Käufers und Zahlungsstand. Dafür musst du nicht in den Chat wechseln.', 'sk-core' ); ?>
                </span>
            </li>
            <li>
                <i class="fab fa-bitcoin"></i>
                <span>
                    <strong><?php esc_html_e( 'Direkte Onchain- & Offchain-Zahlungen', 'sk-core' ); ?></strong>
                    <?php esc_html_e( 'Der Käufer zahlt per Lightning oder Onchain direkt an deine Wallet. Kein Treuhänder, keine Zwischenstation — hinterlege dazu in den Einstellungen eine Lightning-Adresse, NWC oder einen xpub.', 'sk-core' ); ?>
                </span>
            </li>
        </ul>

        <p class="sk-pack-info__note">
            <?php esc_html_e( 'Import und Ausführungen sind ab diesem Paket freigeschaltet.', 'sk-core' ); ?>
        </p>

        <button type="button" class="sk-pack-info__close"><?php esc_html_e( 'Schliessen', 'sk-core' ); ?></button>
    </div>
</div>
