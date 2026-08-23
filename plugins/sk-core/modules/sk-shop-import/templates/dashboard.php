<?php
/**
 * Verkäufer-Dashboard: Shop-Import.
 *
 * @var string $step
 * @var string $url
 * @var mixed  $message
 * @var array|null $csv
 * @var array  $mapping
 * @var array  $items
 * @var int    $item_count
 * @var array  $csv_cats
 * @var array|null $quota
 * @var array|null $quota_block
 * @var array  $packs
 * @var bool   $stay_online
 * @var array  $saved_map
 * @var int    $default_cat
 * @var array  $categories
 * @var mixed  $rate
 * @var array|null $result
 */

defined( 'ABSPATH' ) || exit;

use SK\Modules\ShopImport\Csv;
use SK\Modules\ShopImport\DashboardPage;
use SK\Modules\ShopImport\Display;
?>
<div class="sk-shop-import">

    <?php if ( $message ) : ?>
        <div class="sk-import-note sk-import-note--error"><?php echo esc_html( $message ); ?></div>
    <?php endif; ?>

    <?php if ( $step === 'fertig' && $result ) : ?>

        <h2><?php esc_html_e( 'Import abgeschlossen', 'sk-core' ); ?></h2>
        <ul class="sk-import-result">
            <li><?php printf( esc_html__( '%d Inserate neu angelegt', 'sk-core' ), (int) $result['created'] ); ?></li>
            <li><?php printf( esc_html__( '%d aktualisiert', 'sk-core' ), (int) $result['updated'] ); ?></li>
            <li><?php printf( esc_html__( '%d übersprungen', 'sk-core' ), (int) $result['skipped'] ); ?></li>
            <li><?php printf( esc_html__( '%d Bilder geladen', 'sk-core' ), (int) $result['images'] ); ?></li>
        </ul>
        <?php if ( ! empty( $result['errors'] ) ) : ?>
            <div class="sk-import-note sk-import-note--error">
                <strong><?php esc_html_e( 'Hinweise:', 'sk-core' ); ?></strong>
                <ul>
                    <?php foreach ( array_slice( $result['errors'], 0, 10 ) as $error ) : ?>
                        <li><?php echo esc_html( $error ); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <p><a class="sk-btn sk-btn-theme" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Neuen Import starten', 'sk-core' ); ?></a></p>

    <?php elseif ( $step === 'kontingent' && $quota_block ) : ?>

        <div class="sk-import-upgrade">
            <h2><?php esc_html_e( 'Dein Paket reicht nicht ganz', 'sk-core' ); ?></h2>
            <p>
                <?php
                printf(
                    /* translators: 1: needed, 2: remaining */
                    esc_html__( 'Deine Datei enthält %1$d Inserate, dein Paket erlaubt aktuell noch %2$d.', 'sk-core' ),
                    (int) $quota_block['needed'],
                    (int) $quota_block['remaining']
                );
                ?>
            </p>

            <?php if ( $stay_online ) : ?>
                <p class="sk-import-upgrade__hint">
                    <?php esc_html_e( 'Eine einmalige Zahlung genügt: Läuft das Paket später aus, bleiben deine Inserate online — du kannst dann nur keine neuen mehr anlegen.', 'sk-core' ); ?>
                </p>
            <?php endif; ?>

            <?php if ( ! empty( $packs ) ) : ?>
                <ul class="sk-import-packs">
                    <?php foreach ( $packs as $pack ) : ?>
                        <li>
                            <strong><?php echo esc_html( $pack['name'] ); ?></strong>
                            — <?php printf( esc_html__( '%d Inserate', 'sk-core' ), (int) $pack['products'] ); ?>,
                            <?php echo esc_html( number_format_i18n( $pack['price'] ) ); ?> Sats
                            <?php if ( $pack['days'] > 0 ) : ?>
                                <?php printf( esc_html__( 'für %d Tage', 'sk-core' ), (int) $pack['days'] ); ?>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p>
                    <a class="sk-btn sk-btn-theme" href="<?php echo esc_url( site_url( '/dashboard/subscription/' ) ); ?>">
                        <?php esc_html_e( 'Paket wählen', 'sk-core' ); ?>
                    </a>
                    <a class="sk-btn" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Zurück', 'sk-core' ); ?></a>
                </p>
            <?php else : ?>
                <p><?php esc_html_e( 'Für diese Menge gibt es derzeit kein passendes Paket. Melde dich bitte bei uns.', 'sk-core' ); ?></p>
            <?php endif; ?>
        </div>

    <?php elseif ( $step === 'zuordnen' && $csv ) : ?>

        <h2><?php esc_html_e( 'Spalten zuordnen', 'sk-core' ); ?></h2>
        <p>
            <?php
            printf(
                /* translators: 1: rows in file, 2: importable listings */
                esc_html__( 'Die Datei enthält %1$d Zeilen, daraus werden %2$d Inserate. Varianten desselben Artikels werden zusammengefasst.', 'sk-core' ),
                (int) $csv['count'],
                (int) $item_count
            );
            ?>
        </p>

        <?php if ( $quota && $quota['remaining'] !== null ) : ?>
            <div class="sk-import-note<?php echo $quota['ok'] ? '' : ' sk-import-note--warn'; ?>">
                <?php
                printf(
                    esc_html__( 'Dein Paket erlaubt noch %d Inserate.', 'sk-core' ),
                    (int) $quota['remaining']
                );
                ?>
                <?php if ( ! $quota['ok'] ) : ?>
                    <strong><?php printf( esc_html__( 'Es fehlen %d.', 'sk-core' ), (int) $quota['missing'] ); ?></strong>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url( $url ); ?>">
            <?php wp_nonce_field( DashboardPage::NONCE, 'sk_shop_import_nonce' ); ?>
            <input type="hidden" name="sk_step" value="run">

            <table class="sk-import-map">
                <tbody>
                <?php foreach ( Csv::FIELDS as $field => $label ) : ?>
                    <tr>
                        <th><?php echo esc_html( $label ); ?></th>
                        <td>
                            <select name="map_<?php echo esc_attr( $field ); ?>">
                                <option value="-1"><?php esc_html_e( '— nicht übernehmen —', 'sk-core' ); ?></option>
                                <?php foreach ( $csv['headers'] as $i => $header ) : ?>
                                    <option value="<?php echo (int) $i; ?>" <?php selected( (int) ( $mapping[ $field ] ?? -1 ), (int) $i ); ?>>
                                        <?php echo esc_html( $header ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ( ! empty( $csv_cats ) ) : ?>
                <h3><?php esc_html_e( 'Kategorien zuordnen', 'sk-core' ); ?></h3>
                <table class="sk-import-map">
                    <tbody>
                    <?php foreach ( $csv_cats as $name ) : ?>
                        <?php $selected = (int) ( $saved_map[ mb_strtolower( $name, 'UTF-8' ) ] ?? 0 ); ?>
                        <tr>
                            <th><?php echo esc_html( $name ); ?></th>
                            <td>
                                <select name="cat_map[<?php echo esc_attr( $name ); ?>]">
                                    <option value="0"><?php esc_html_e( '— Standardkategorie —', 'sk-core' ); ?></option>
                                    <?php foreach ( $categories as $term ) : ?>
                                        <option value="<?php echo (int) $term->term_id; ?>" <?php selected( $selected, (int) $term->term_id ); ?>>
                                            <?php echo esc_html( $term->name ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr>
                        <th><?php esc_html_e( 'Standardkategorie', 'sk-core' ); ?></th>
                        <td>
                            <select name="sk_default_cat">
                                <option value="0"><?php esc_html_e( '— keine —', 'sk-core' ); ?></option>
                                <?php foreach ( $categories as $term ) : ?>
                                    <option value="<?php echo (int) $term->term_id; ?>" <?php selected( $default_cat, (int) $term->term_id ); ?>>
                                        <?php echo esc_html( $term->name ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    </tbody>
                </table>
            <?php endif; ?>

            <h3><?php esc_html_e( 'Einstellungen', 'sk-core' ); ?></h3>
            <table class="sk-import-map">
                <tbody>
                <tr>
                    <th><?php esc_html_e( 'Währung der Preise', 'sk-core' ); ?></th>
                    <td>
                        <select name="sk_currency">
                            <option value="EUR">EUR</option>
                            <option value="CHF">CHF</option>
                        </select>
                        <?php if ( ! is_wp_error( $rate ) ) : ?>
                            <span class="sk-import-hint">
                                <?php printf( esc_html__( 'Kurs: 1 BTC = %s', 'sk-core' ), esc_html( Display::format_fiat( (float) $rate, 'EUR' ) ) ); ?>
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Bilder höchstens', 'sk-core' ); ?></th>
                    <td><input type="number" name="sk_image_cap" value="60" min="0" step="10"> <span class="sk-import-hint"><?php esc_html_e( 'je Durchlauf', 'sk-core' ); ?></span></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Anlegen als', 'sk-core' ); ?></th>
                    <td>
                        <select name="sk_status">
                            <option value="publish"><?php esc_html_e( 'veröffentlicht', 'sk-core' ); ?></option>
                            <option value="draft"><?php esc_html_e( 'Entwurf', 'sk-core' ); ?></option>
                        </select>
                        <span class="sk-import-hint"><?php esc_html_e( 'Im Shop private Artikel werden in jedem Fall Entwurf.', 'sk-core' ); ?></span>
                    </td>
                </tr>
                </tbody>
            </table>

            <h3><?php esc_html_e( 'Vorschau', 'sk-core' ); ?></h3>
            <div class="sk-import-preview">
                <table>
                    <thead><tr><?php foreach ( array_slice( $csv['headers'], 0, 8 ) as $h ) : ?><th><?php echo esc_html( $h ); ?></th><?php endforeach; ?></tr></thead>
                    <tbody>
                    <?php foreach ( $csv['rows'] as $row ) : ?>
                        <tr><?php foreach ( array_slice( $row, 0, 8 ) as $cell ) : ?><td><?php echo esc_html( mb_substr( (string) $cell, 0, 40 ) ); ?></td><?php endforeach; ?></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <p><button type="submit" class="sk-btn sk-btn-theme"><?php esc_html_e( 'Import starten', 'sk-core' ); ?></button></p>
        </form>

    <?php else : ?>

        <h2><?php esc_html_e( 'Shop-Import', 'sk-core' ); ?></h2>
        <p><?php esc_html_e( 'Exportiere in deinem eigenen Shop unter Produkte → Exportieren eine CSV-Datei und lade sie hier hoch. Varianten desselben Artikels werden zu einem Inserat zusammengefasst, Preise werden in Sats umgerechnet und täglich am Kurs nachgeführt.', 'sk-core' ); ?></p>

        <form method="post" action="<?php echo esc_url( $url ); ?>" enctype="multipart/form-data">
            <?php wp_nonce_field( DashboardPage::NONCE, 'sk_shop_import_nonce' ); ?>
            <input type="hidden" name="sk_step" value="upload">
            <p><input type="file" name="sk_csv" accept=".csv,text/csv" required></p>
            <p><button type="submit" class="sk-btn sk-btn-theme"><?php esc_html_e( 'Datei hochladen', 'sk-core' ); ?></button></p>
        </form>

    <?php endif; ?>
</div>
