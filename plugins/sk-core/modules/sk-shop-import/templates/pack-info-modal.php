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
                    <strong><?php esc_html_e( 'Woo & Shopify Produkt Import', 'sk-core' ); ?></strong>
                    <?php esc_html_e( 'Läuft dein Shop auf Shopify, holen wir den Katalog direkt — du gibst nur die Adresse an. Bei WooCommerce exportierst du eine CSV-Datei und lädst sie hoch. Bilder und Kategorien kommen mit, bis zu fünf Bilder je Artikel. Vor dem Import wählst du aus, was übernommen wird.', 'sk-core' ); ?>
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
                <i class="fas fa-chart-bar"></i>
                <span>
                    <strong><?php esc_html_e( 'Umsatz & CSV-Export', 'sk-core' ); ?> <span class="sk-pack-info__from"><?php esc_html_e( 'ab Hai', 'sk-core' ); ?></span></strong>
                    <?php esc_html_e( 'Monatssummen in Sats und Euro, gerechnet mit dem Kurs, der im Moment der Zahlung galt — nicht mit dem heutigen. Genau dieser Wert zählt für die Steuer, und er lässt sich später nicht mehr rekonstruieren. Als CSV-Datei mit Datum, Artikel, Ausführung, Kurs und Betrag herunterladbar, fertig für die Buchhaltung.', 'sk-core' ); ?>
                </span>
            </li>
            <li>
                <i class="fas fa-envelope"></i>
                <span>
                    <strong><?php esc_html_e( 'Bestellungen per E-Mail', 'sk-core' ); ?></strong>
                    <?php esc_html_e( 'Du bekommst jede Bestellung sofort ins Postfach — mit Ausführung, Betrag und Lieferanschrift, alles zum Packen. Sobald die Zahlung bestätigt ist, folgt eine zweite Mail; erst dann versendest du. Dein Käufer erhält eine Bestellbestätigung. Ohne Paket siehst du Bestellungen nur im Dashboard.', 'sk-core' ); ?>
                </span>
            </li>
            <li>
                <i class="fas fa-truck"></i>
                <span>
                    <strong><?php esc_html_e( 'Sendungsverfolgung', 'sk-core' ); ?></strong>
                    <?php esc_html_e( 'Du trägst Versender und Sendungsnummer bei der Bestellung ein — neun Anbieter von der Schweizerischen Post bis FedEx stehen zur Auswahl, für alle anderen gibst du den Link direkt an. Der Käufer bekommt daraufhin eine Mail mit Verfolgungslink, und beide Seiten sehen den Stand in der Übersicht. Das spart dir die Rückfragen im Chat.', 'sk-core' ); ?>
                </span>
            </li>
            <li>
                <i class="fab fa-bitcoin"></i>
                <span>
                    <strong><?php esc_html_e( 'Direkt an deine Wallet', 'sk-core' ); ?></strong>
                    <?php esc_html_e( 'Der Käufer zahlt per Lightning oder Onchain direkt an dich. Kein Treuhänder, keine Zwischenstation, keine Verkaufsgebühr — hinterlege dazu eine Lightning-Adresse, NWC oder einen xpub. Das gilt für alle Anbieter, auch ohne Paket.', 'sk-core' ); ?>
                </span>
            </li>
        </ul>

        <p class="sk-pack-info__note">
            <?php esc_html_e( 'Import und Ausführungen sind ab diesem Paket freigeschaltet.', 'sk-core' ); ?>
        </p>

        <button type="button" class="sk-pack-info__close"><?php esc_html_e( 'Schliessen', 'sk-core' ); ?></button>
    </div>
</div>
