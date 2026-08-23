<?php
/**
 * Shop-Import im Verkäufer-Dashboard.
 *
 * Variablen kommen aus DashboardPage::view_data(), registriert als
 * 'template_args'; diese Datei rendert nur.
 *
 * @var string     $step
 * @var string     $url
 * @var mixed      $message
 * @var array|null $csv
 * @var array      $mapping
 * @var int        $item_count
 * @var array      $csv_cats
 * @var array|null $quota
 * @var array|null $quota_block
 * @var array      $packs
 * @var bool       $stay_online
 * @var array      $saved_map
 * @var int        $default_cat
 * @var array      $categories
 * @var mixed      $rate
 * @var array|null $result
 */

defined( 'ABSPATH' ) || exit;

use SK\Modules\ShopImport\Csv;
use SK\Modules\ShopImport\DashboardPage;
use SK\Modules\ShopImport\Display;

do_action( 'sk_dashboard_wrap_start' );
?>

<div class="sk-dashboard-wrap">
    <?php do_action( 'sk_dashboard_content_before' ); ?>

    <div class="sk-dashboard-content sk-dashboard-content--shop-import">
        <?php do_action( 'sk_dashboard_content_inside_before' ); ?>

        <div class="sk-review-page-header">
            <h2><i class="fas fa-file-import"></i> <?php esc_html_e( 'Shop-Import', 'sk-core' ); ?></h2>
        </div>

        <div class="sk-shop-import">

            <?php if ( $message ) : ?>
                <div class="sk-alert sk-alert-danger"><?php echo esc_html( $message ); ?></div>
            <?php endif; ?>

            <?php if ( $step === 'fertig' && $result ) : ?>

                <div class="sk-section-heading">
                    <h3><?php esc_html_e( 'Import abgeschlossen', 'sk-core' ); ?></h3>
                </div>
                <div class="sk-section-content">
                    <ul class="sk-import-result">
                        <li><?php printf( esc_html__( '%d Inserate neu angelegt', 'sk-core' ), (int) $result['created'] ); ?></li>
                        <li><?php printf( esc_html__( '%d aktualisiert', 'sk-core' ), (int) $result['updated'] ); ?></li>
                        <li><?php printf( esc_html__( '%d übersprungen', 'sk-core' ), (int) $result['skipped'] ); ?></li>
                        <li><?php printf( esc_html__( '%d Bilder geladen', 'sk-core' ), (int) $result['images'] ); ?></li>
                    </ul>

                    <?php if ( ! empty( $result['errors'] ) ) : ?>
                        <div class="sk-alert sk-alert-danger">
                            <strong><?php esc_html_e( 'Hinweise', 'sk-core' ); ?></strong>
                            <ul>
                                <?php foreach ( array_slice( $result['errors'], 0, 10 ) as $error ) : ?>
                                    <li><?php echo esc_html( $error ); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div class="sk-form-group">
                        <a class="sk-btn sk-btn-theme" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Neuen Import starten', 'sk-core' ); ?></a>
                    </div>
                </div>

            <?php elseif ( $step === 'kontingent' && $quota_block ) : ?>

                <div class="sk-section-heading">
                    <h3><?php esc_html_e( 'Dein Paket reicht nicht ganz', 'sk-core' ); ?></h3>
                </div>
                <div class="sk-section-content">
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
                        <p><strong><?php esc_html_e( 'Eine einmalige Zahlung genügt: Läuft das Paket später aus, bleiben deine Inserate online — du kannst dann nur keine neuen mehr anlegen.', 'sk-core' ); ?></strong></p>
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
                        <div class="sk-form-group">
                            <a class="sk-btn sk-btn-theme" href="<?php echo esc_url( sk_get_navigation_url( 'subscription' ) ); ?>">
                                <?php esc_html_e( 'Paket wählen', 'sk-core' ); ?>
                            </a>
                            <a class="sk-btn sk-btn-default" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Zurück', 'sk-core' ); ?></a>
                        </div>
                    <?php else : ?>
                        <p><?php esc_html_e( 'Für diese Menge gibt es derzeit kein passendes Paket. Melde dich bitte bei uns.', 'sk-core' ); ?></p>
                    <?php endif; ?>
                </div>

            <?php elseif ( $step === 'zuordnen' && $csv ) : ?>

                <form method="post" action="<?php echo esc_url( $url ); ?>">
                    <?php wp_nonce_field( DashboardPage::NONCE, 'sk_shop_import_nonce' ); ?>
                    <input type="hidden" name="sk_step" value="run">

                    <div class="sk-section-heading">
                        <h3><?php esc_html_e( 'Spalten zuordnen', 'sk-core' ); ?></h3>
                    </div>
                    <div class="sk-section-content">
                        <p>
                            <?php
                            printf(
                                /* translators: 1: rows, 2: listings */
                                esc_html__( 'Die Datei enthält %1$d Zeilen, daraus werden %2$d Inserate. Varianten desselben Artikels werden zusammengefasst.', 'sk-core' ),
                                (int) $csv['count'],
                                (int) $item_count
                            );
                            ?>
                        </p>

                        <?php if ( $quota && $quota['remaining'] !== null && ! $quota['ok'] ) : ?>
                            <div class="sk-alert sk-alert-danger">
                                <?php
                                printf(
                                    esc_html__( 'Dein Paket erlaubt noch %1$d Inserate — es fehlen %2$d.', 'sk-core' ),
                                    (int) $quota['remaining'],
                                    (int) $quota['missing']
                                );
                                ?>
                            </div>
                        <?php endif; ?>

                        <?php foreach ( Csv::FIELDS as $field => $label ) : ?>
                            <div class="sk-form-group sk-clearfix">
                                <label class="sk-w3 sk-control-label" for="map_<?php echo esc_attr( $field ); ?>"><?php echo esc_html( $label ); ?></label>
                                <div class="sk-w9">
                                    <select class="sk-form-control" name="map_<?php echo esc_attr( $field ); ?>" id="map_<?php echo esc_attr( $field ); ?>">
                                        <option value="-1"><?php esc_html_e( '— nicht übernehmen —', 'sk-core' ); ?></option>
                                        <?php foreach ( $csv['headers'] as $i => $header ) : ?>
                                            <option value="<?php echo (int) $i; ?>" <?php selected( (int) ( $mapping[ $field ] ?? -1 ), (int) $i ); ?>>
                                                <?php echo esc_html( $header ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ( ! empty( $csv_cats ) ) : ?>
                        <div class="sk-section-heading">
                            <h3><?php esc_html_e( 'Kategorien zuordnen', 'sk-core' ); ?></h3>
                        </div>
                        <div class="sk-section-content">
                            <?php foreach ( $csv_cats as $name ) : ?>
                                <?php $selected = (int) ( $saved_map[ mb_strtolower( $name, 'UTF-8' ) ] ?? 0 ); ?>
                                <div class="sk-form-group sk-clearfix">
                                    <label class="sk-w3 sk-control-label"><?php echo esc_html( $name ); ?></label>
                                    <div class="sk-w9">
                                        <select class="sk-form-control" name="cat_map[<?php echo esc_attr( $name ); ?>]">
                                            <option value="0"><?php esc_html_e( '— Standardkategorie —', 'sk-core' ); ?></option>
                                            <?php foreach ( $categories as $term ) : ?>
                                                <option value="<?php echo (int) $term->term_id; ?>" <?php selected( $selected, (int) $term->term_id ); ?>>
                                                    <?php echo esc_html( $term->name ); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <div class="sk-form-group sk-clearfix">
                                <label class="sk-w3 sk-control-label" for="sk_default_cat"><?php esc_html_e( 'Standardkategorie', 'sk-core' ); ?></label>
                                <div class="sk-w9">
                                    <select class="sk-form-control" name="sk_default_cat" id="sk_default_cat">
                                        <option value="0"><?php esc_html_e( '— keine —', 'sk-core' ); ?></option>
                                        <?php foreach ( $categories as $term ) : ?>
                                            <option value="<?php echo (int) $term->term_id; ?>" <?php selected( $default_cat, (int) $term->term_id ); ?>>
                                                <?php echo esc_html( $term->name ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="sk-section-heading">
                        <h3><?php esc_html_e( 'Einstellungen', 'sk-core' ); ?></h3>
                    </div>
                    <div class="sk-section-content">
                        <div class="sk-form-group sk-clearfix">
                            <label class="sk-w3 sk-control-label" for="sk_currency"><?php esc_html_e( 'Währung der Preise', 'sk-core' ); ?></label>
                            <div class="sk-w9">
                                <select class="sk-form-control" name="sk_currency" id="sk_currency">
                                    <option value="EUR">EUR</option>
                                    <option value="CHF">CHF</option>
                                </select>
                                <?php if ( ! is_wp_error( $rate ) ) : ?>
                                    <span class="sk-import-hint">
                                        <?php printf( esc_html__( 'Kurs: 1 BTC = %s', 'sk-core' ), esc_html( Display::format_fiat( (float) $rate, 'EUR' ) ) ); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="sk-form-group sk-clearfix">
                            <label class="sk-w3 sk-control-label" for="sk_image_cap"><?php esc_html_e( 'Bilder höchstens', 'sk-core' ); ?></label>
                            <div class="sk-w9">
                                <input class="sk-form-control" type="number" name="sk_image_cap" id="sk_image_cap" value="60" min="0" step="10">
                                <span class="sk-import-hint"><?php esc_html_e( 'je Durchlauf', 'sk-core' ); ?></span>
                            </div>
                        </div>

                        <div class="sk-form-group sk-clearfix">
                            <label class="sk-w3 sk-control-label" for="sk_status"><?php esc_html_e( 'Anlegen als', 'sk-core' ); ?></label>
                            <div class="sk-w9">
                                <select class="sk-form-control" name="sk_status" id="sk_status">
                                    <option value="publish"><?php esc_html_e( 'veröffentlicht', 'sk-core' ); ?></option>
                                    <option value="draft"><?php esc_html_e( 'Entwurf', 'sk-core' ); ?></option>
                                </select>
                                <span class="sk-import-hint"><?php esc_html_e( 'Im Shop private Artikel werden in jedem Fall Entwurf.', 'sk-core' ); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="sk-section-heading">
                        <h3><?php esc_html_e( 'Vorschau', 'sk-core' ); ?></h3>
                    </div>
                    <div class="sk-section-content">
                        <div class="sk-import-preview">
                            <table>
                                <thead>
                                    <tr><?php foreach ( array_slice( $csv['headers'], 0, 8 ) as $h ) : ?><th><?php echo esc_html( $h ); ?></th><?php endforeach; ?></tr>
                                </thead>
                                <tbody>
                                <?php foreach ( $csv['rows'] as $row ) : ?>
                                    <tr><?php foreach ( array_slice( $row, 0, 8 ) as $cell ) : ?><td><?php echo esc_html( mb_substr( (string) $cell, 0, 40 ) ); ?></td><?php endforeach; ?></tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="sk-form-group">
                            <button type="submit" class="sk-btn sk-btn-theme"><?php esc_html_e( 'Import starten', 'sk-core' ); ?></button>
                            <a class="sk-btn sk-btn-default" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Abbrechen', 'sk-core' ); ?></a>
                        </div>
                    </div>
                </form>

            <?php else : ?>

                <div class="sk-section-heading">
                    <h3><?php esc_html_e( 'Katalog hochladen', 'sk-core' ); ?></h3>
                </div>
                <div class="sk-section-content">
                    <p><?php esc_html_e( 'Exportiere in deinem eigenen Shop unter Produkte → Exportieren eine CSV-Datei und lade sie hier hoch. Varianten desselben Artikels werden zu einem Inserat zusammengefasst, Preise werden in Sats umgerechnet und täglich am Kurs nachgeführt.', 'sk-core' ); ?></p>

                    <form method="post" action="<?php echo esc_url( $url ); ?>" enctype="multipart/form-data">
                        <?php wp_nonce_field( DashboardPage::NONCE, 'sk_shop_import_nonce' ); ?>
                        <input type="hidden" name="sk_step" value="upload">

                        <div class="sk-form-group sk-clearfix">
                            <label class="sk-w3 sk-control-label" for="sk_csv"><?php esc_html_e( 'CSV-Datei', 'sk-core' ); ?></label>
                            <div class="sk-w9">
                                <input class="sk-form-control" type="file" name="sk_csv" id="sk_csv" accept=".csv,text/csv" required>
                            </div>
                        </div>

                        <div class="sk-form-group">
                            <button type="submit" class="sk-btn sk-btn-theme"><?php esc_html_e( 'Datei hochladen', 'sk-core' ); ?></button>
                        </div>
                    </form>
                </div>

            <?php endif; ?>
        </div>

        <?php do_action( 'sk_dashboard_content_inside_after' ); ?>
    </div>

    <?php do_action( 'sk_dashboard_content_after' ); ?>
</div>

<?php do_action( 'sk_dashboard_wrap_end' ); ?>
