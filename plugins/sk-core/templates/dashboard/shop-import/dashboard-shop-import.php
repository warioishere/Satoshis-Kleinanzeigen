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
 * @var array      $summary
 * @var array|null $quota
 * @var array|null $quota_block
 * @var array      $packs
 * @var bool       $stay_online
 * @var array      $saved_map
 * @var int        $default_cat
 * @var array      $categories
 * @var mixed      $rate
 * @var array|null $result
 * @var string     $subscription_url
 * @var array      $items
 * @var array      $currency_guess
 * @var bool       $variants_allowed
 * @var array|null $variants_pack
 * @var int        $blocked
 */

defined( 'ABSPATH' ) || exit;

use SK\Modules\ShopImport\Csv;
use SK\Modules\ShopImport\DashboardPage;
use SK\Modules\ShopImport\Display;
use SK\Modules\ShopImport\Importer;

$current = 1;
if ( $step === 'zuordnen' || $step === 'kontingent' ) {
    $current = 2;
} elseif ( $step === 'fertig' || $step === 'laeuft' ) {
    $current = 3;
}

$steps = [
    1 => __( 'Datei wählen', 'sk-core' ),
    2 => __( 'Prüfen', 'sk-core' ),
    3 => __( 'Fertig', 'sk-core' ),
];

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

            <ol class="sk-import-steps">
                <?php foreach ( $steps as $number => $label ) : ?>
                    <li class="sk-import-step<?php echo $number === $current ? ' is-current' : ( $number < $current ? ' is-done' : '' ); ?>">
                        <span class="sk-import-step__num"><?php echo (int) $number; ?></span>
                        <span class="sk-import-step__label"><?php echo esc_html( $label ); ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>

            <?php if ( $message ) : ?>
                <div class="sk-alert sk-alert-danger"><?php echo esc_html( $message ); ?></div>
            <?php endif; ?>

            <?php if ( $step === 'fertig' && $result ) : ?>

                <div class="sk-section-heading"><h3><?php esc_html_e( 'Import abgeschlossen', 'sk-core' ); ?></h3></div>
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
                        <a class="sk-btn sk-btn-theme" href="<?php echo esc_url( sk_get_navigation_url( 'products' ) ); ?>"><?php esc_html_e( 'Inserate ansehen', 'sk-core' ); ?></a>
                        <a class="sk-btn sk-btn-default" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Neuen Import starten', 'sk-core' ); ?></a>
                    </div>
                </div>

            <?php elseif ( $step === 'laeuft' && $job ) : ?>

                <?php
                $job_done  = (int) ( $job['offset'] ?? 0 );
                $job_total = max( 1, (int) ( $job['total'] ?? 1 ) );
                ?>
                <div class="sk-section-heading"><h3><?php esc_html_e( 'Der Import läuft', 'sk-core' ); ?></h3></div>
                <div class="sk-section-content">
                    <p>
                        <?php esc_html_e( 'Die Artikel werden nacheinander angelegt und die Bilder dabei von deinem Shop geholt. Das dauert einen Moment — lass das Fenster offen.', 'sk-core' ); ?>
                    </p>

                    <div class="sk-import-bar" id="sk-import-bar"
                         data-done="<?php echo esc_attr( $job_done ); ?>"
                         data-total="<?php echo esc_attr( $job_total ); ?>"
                         data-nonce="<?php echo esc_attr( wp_create_nonce( DashboardPage::NONCE ) ); ?>"
                         data-ajaxurl="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
                         role="progressbar" aria-valuemin="0" aria-valuemax="100"
                         aria-valuenow="<?php echo esc_attr( (int) round( $job_done / $job_total * 100 ) ); ?>">
                        <div class="sk-import-bar__fill" id="sk-import-bar-fill"
                             style="width: <?php echo esc_attr( (int) round( $job_done / $job_total * 100 ) ); ?>%;"></div>
                    </div>

                    <p class="sk-import-bar__label">
                        <span id="sk-import-bar-text">
                            <?php
                            printf(
                                /* translators: 1: done, 2: total */
                                esc_html__( '%1$d von %2$d Inseraten angelegt', 'sk-core' ),
                                $job_done,
                                $job_total
                            );
                            ?>
                        </span>
                    </p>

                    <p id="sk-import-bar-error" class="sk-alert sk-alert-danger" style="display:none;"></p>

                    <div class="sk-form-group">
                        <button type="button" class="sk-btn sk-btn-theme" id="sk-import-bar-start">
                            <?php echo $job_done > 0
                                ? esc_html__( 'Import fortsetzen', 'sk-core' )
                                : esc_html__( 'Import starten', 'sk-core' ); ?>
                        </button>
                    </div>
                </div>

            <?php elseif ( $step === 'kontingent' && $quota_block ) : ?>

                <div class="sk-section-heading"><h3><?php esc_html_e( 'Dein Paket reicht nicht ganz', 'sk-core' ); ?></h3></div>
                <div class="sk-section-content">
                    <p>
                        <?php
                        printf(
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
                    <?php endif; ?>

                    <div class="sk-form-group">
                        <a class="sk-btn sk-btn-theme" href="<?php echo esc_url( $subscription_url ); ?>"><?php esc_html_e( 'Zu den Abos', 'sk-core' ); ?></a>
                        <a class="sk-btn sk-btn-default" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Zurück', 'sk-core' ); ?></a>
                    </div>
                </div>

            <?php elseif ( $step === 'zuordnen' && ( $csv || ! empty( $is_json ) ) ) : ?>

                <?php
                $limit    = ( $quota && $quota['remaining'] !== null ) ? (int) $quota['remaining'] : 0;
                $preset   = $limit > 0 ? min( $limit, (int) $summary['items'] ) : (int) $summary['items'];
                ?>
                <form method="post" action="<?php echo esc_url( $url ); ?>"
                      class="sk-import-pick"
                      data-limit="<?php echo (int) $limit; ?>"
                      data-label-one="<?php esc_attr_e( '%d Inserat importieren', 'sk-core' ); ?>"
                      data-label-many="<?php esc_attr_e( '%d Inserate importieren', 'sk-core' ); ?>"
                      data-label-over="<?php esc_attr_e( 'Zu viele für dein Paket', 'sk-core' ); ?>">
                    <?php wp_nonce_field( DashboardPage::NONCE, 'sk_shop_import_nonce' ); ?>
                    <input type="hidden" name="sk_step" value="run">

                    <div class="sk-section-heading"><h3><?php esc_html_e( 'Das passiert beim Import', 'sk-core' ); ?></h3></div>
                    <div class="sk-section-content">
                        <ul class="sk-import-summary">
                            <li>
                                <?php
                                printf(
                                    esc_html__( 'Aus %1$d Zeilen werden %2$d Inserate.', 'sk-core' ),
                                    (int) $summary['rows'],
                                    (int) $summary['items']
                                );
                                ?>
                            </li>
                            <?php if ( $summary['variants'] > 0 && $variants_allowed ) : ?>
                                <li><?php printf( esc_html__( 'Bei %d Artikeln werden die Varianten als Ausführungen zusammengefasst, statt eigene Inserate zu werden.', 'sk-core' ), (int) $summary['variants'] ); ?></li>
                            <?php elseif ( $blocked > 0 ) : ?>
                                <li>
                                    <?php
                                    printf(
                                        esc_html__( '%d Artikel haben mehrere Ausführungen und werden übersprungen.', 'sk-core' ),
                                        (int) $blocked
                                    );
                                    if ( $variants_pack ) {
                                        echo ' ';
                                        printf(
                                            esc_html__( 'Ausführungen gibt es ab dem Paket %s.', 'sk-core' ),
                                            esc_html( $variants_pack['name'] )
                                        );
                                    }
                                    ?>
                                    <a href="<?php echo esc_url( $subscription_url ); ?>"><?php esc_html_e( 'Zu den Abos', 'sk-core' ); ?></a>
                                </li>
                            <?php endif; ?>
                            <?php if ( $summary['drafts'] > 0 ) : ?>
                                <li><?php printf( esc_html__( '%d Artikel sind in deinem Shop nicht öffentlich — sie werden als Entwurf angelegt.', 'sk-core' ), (int) $summary['drafts'] ); ?></li>
                            <?php endif; ?>
                            <li><?php printf( esc_html__( 'Preise werden in Sats umgerechnet und täglich am Kurs nachgeführt. Höchstens %d Bilder je Inserat.', 'sk-core' ), (int) Importer::IMAGES_PER_PRODUCT ); ?></li>
                            <?php if ( $summary['without_price'] > 0 ) : ?>
                                <li><?php printf( esc_html__( '%d Artikel haben keinen Preis in der Datei.', 'sk-core' ), (int) $summary['without_price'] ); ?></li>
                            <?php endif; ?>
                            <li><?php esc_html_e( 'Ein zweiter Import mit derselben Datei aktualisiert die Inserate, statt sie zu verdoppeln.', 'sk-core' ); ?></li>
                        </ul>

                        <?php if ( $quota && $quota['remaining'] !== null && ! $quota['ok'] ) : ?>
                            <div class="sk-alert sk-alert-danger">
                                <?php
                                printf(
                                    esc_html__( 'Dein Paket erlaubt noch %1$d Inserate — es fehlen %2$d.', 'sk-core' ),
                                    (int) $quota['remaining'],
                                    (int) $quota['missing']
                                );
                                ?>
                                <?php esc_html_e( 'Wähle unten aus, was eingestellt werden soll — oder nimm ein grösseres Paket.', 'sk-core' ); ?>
                                <a href="<?php echo esc_url( $subscription_url ); ?>"><?php esc_html_e( 'Zu den Abos', 'sk-core' ); ?></a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="sk-section-heading"><h3><?php esc_html_e( 'Was soll eingestellt werden?', 'sk-core' ); ?></h3></div>
                    <div class="sk-section-content">
                        <div class="sk-import-pick__bar">
                            <label>
                                <input type="checkbox" id="sk-import-all" <?php checked( $limit === 0 || $preset >= (int) $summary['items'] ); ?>>
                                <?php esc_html_e( 'alle', 'sk-core' ); ?>
                            </label>

                            <?php if ( $limit > 0 && (int) $summary['items'] > $limit ) : ?>
                                <a href="#" id="sk-import-uptolimit"><?php printf( esc_html__( 'nur die ersten %d', 'sk-core' ), (int) $limit ); ?></a>
                            <?php endif; ?>

                            <span class="sk-import-pick__count">
                                <strong id="sk-import-count"><?php echo (int) $preset; ?></strong>
                                <?php
                                if ( $limit > 0 ) {
                                    printf( esc_html__( 'von %d möglichen ausgewählt', 'sk-core' ), (int) $limit );
                                } else {
                                    esc_html_e( 'ausgewählt', 'sk-core' );
                                }
                                ?>
                            </span>
                        </div>

                        <ul class="sk-import-pick__list">
                            <?php foreach ( $items as $index => $item ) : ?>
                                <?php
                                $key   = (string) ( $item['key'] ?? '' );
                                $price = Importer::parse_price( (string) ( $item['price'] ?? '' ) );
                                ?>
                                <?php $is_blocked = ! $variants_allowed && ! empty( $item['variants'] ); ?>
                                <li<?php echo $is_blocked ? ' class="is-blocked"' : ''; ?>>
                                    <label>
                                        <input type="checkbox" name="sk_pick[]" value="<?php echo esc_attr( $key ); ?>"
                                               <?php disabled( $is_blocked ); ?>
                                               <?php checked( ! $is_blocked && ( $limit === 0 || $index < $preset ) ); ?>>
                                        <span class="sk-import-pick__name"><?php echo esc_html( $item['name'] ); ?></span>
                                        <span class="sk-import-pick__meta">
                                            <?php if ( $price !== null && $price > 0 ) : ?>
                                                <?php echo esc_html( ( ! empty( $item['from'] ) ? __( 'ab ', 'sk-core' ) : '' ) . number_format_i18n( $price, fmod( $price, 1 ) === 0.0 ? 0 : 2 ) ); ?>
                                            <?php else : ?>
                                                <?php esc_html_e( 'kein Preis', 'sk-core' ); ?>
                                            <?php endif; ?>
                                            <?php if ( ! empty( $item['variants'] ) ) : ?>
                                                · <?php printf( esc_html__( '%d Ausführungen', 'sk-core' ), count( $item['variants'] ) ); ?>
                                                <?php if ( $is_blocked ) : ?>
                                                    · <strong><?php esc_html_e( 'grösseres Paket nötig', 'sk-core' ); ?></strong>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <?php if ( ! empty( $item['draft'] ) ) : ?>
                                                · <?php esc_html_e( 'Entwurf', 'sk-core' ); ?>
                                            <?php endif; ?>
                                        </span>
                                    </label>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <?php if ( ! empty( $csv_cats ) ) : ?>
                        <div class="sk-section-heading"><h3><?php esc_html_e( 'Kategorien zuordnen', 'sk-core' ); ?></h3></div>
                        <div class="sk-section-content">
                            <p><?php esc_html_e( 'Deine Shop-Kategorien passen nicht zu unseren. Ordne sie einmal zu — beim nächsten Import steht es schon da.', 'sk-core' ); ?></p>

                            <?php foreach ( $csv_cats as $name ) : ?>
                                <?php $selected = (int) ( $saved_map[ mb_strtolower( $name, 'UTF-8' ) ] ?? 0 ); ?>
                                <div class="sk-form-group sk-clearfix">
                                    <label class="sk-w3 sk-control-label"><?php echo esc_html( $name ); ?></label>
                                    <div class="sk-w9">
                                        <select class="sk-form-control" name="cat_map[<?php echo esc_attr( $name ); ?>]">
                                            <option value="0"><?php esc_html_e( '— Standardkategorie —', 'sk-core' ); ?></option>
                                            <?php foreach ( $categories as $term ) : ?>
                                                <option value="<?php echo (int) $term->term_id; ?>" <?php selected( $selected, (int) $term->term_id ); ?>><?php echo esc_html( $term->name ); ?></option>
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
                                            <option value="<?php echo (int) $term->term_id; ?>" <?php selected( $default_cat, (int) $term->term_id ); ?>><?php echo esc_html( $term->name ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="sk-section-heading"><h3><?php esc_html_e( 'Einstellungen', 'sk-core' ); ?></h3></div>
                    <div class="sk-section-content">
                        <div class="sk-form-group sk-clearfix">
                            <label class="sk-w3 sk-control-label" for="sk_currency"><?php esc_html_e( 'Währung deiner Preise', 'sk-core' ); ?></label>
                            <div class="sk-w9">
                                <select class="sk-form-control" name="sk_currency" id="sk_currency">
                                    <option value="EUR" <?php selected( $currency_guess['currency'], 'EUR' ); ?>>EUR</option>
                                    <option value="CHF" <?php selected( $currency_guess['currency'], 'CHF' ); ?>>CHF</option>
                                </select>
                                <span class="sk-import-hint">
                                    <?php echo esc_html( $currency_guess['reason'] ); ?>
                                    <?php if ( ! is_wp_error( $rate ) ) : ?>
                                        · <?php printf( esc_html__( 'Kurs: 1 BTC = %s', 'sk-core' ), esc_html( Display::format_fiat( (float) $rate, 'EUR' ) ) ); ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>

                        <div class="sk-form-group sk-clearfix">
                            <label class="sk-w3 sk-control-label" for="sk_status"><?php esc_html_e( 'Anlegen als', 'sk-core' ); ?></label>
                            <div class="sk-w9">
                                <select class="sk-form-control" name="sk_status" id="sk_status">
                                    <option value="publish"><?php esc_html_e( 'veröffentlicht', 'sk-core' ); ?></option>
                                    <option value="draft"><?php esc_html_e( 'Entwurf', 'sk-core' ); ?></option>
                                </select>
                            </div>
                        </div>

                        <input type="hidden" name="sk_image_cap" value="<?php echo (int) ( Importer::IMAGES_PER_PRODUCT * max( 1, $summary['items'] ) ); ?>">
                    </div>

                    <?php if ( empty( $is_json ) ) : ?>
                    <details class="sk-import-advanced">
                        <summary>
                            <?php esc_html_e( 'Spaltenzuordnung ansehen', 'sk-core' ); ?>
                            <?php if ( (int) $summary['unmapped'] > 0 ) : ?>
                                <span class="sk-import-hint"><?php printf( esc_html__( '%d Spalten nicht erkannt', 'sk-core' ), (int) $summary['unmapped'] ); ?></span>
                            <?php else : ?>
                                <span class="sk-import-hint"><?php esc_html_e( 'alles automatisch erkannt', 'sk-core' ); ?></span>
                            <?php endif; ?>
                        </summary>

                        <div class="sk-section-content">
                            <?php foreach ( Csv::FIELDS as $field => $label ) : ?>
                                <div class="sk-form-group sk-clearfix">
                                    <label class="sk-w3 sk-control-label" for="map_<?php echo esc_attr( $field ); ?>"><?php echo esc_html( $label ); ?></label>
                                    <div class="sk-w9">
                                        <select class="sk-form-control" name="map_<?php echo esc_attr( $field ); ?>" id="map_<?php echo esc_attr( $field ); ?>">
                                            <option value="-1"><?php esc_html_e( '— nicht übernehmen —', 'sk-core' ); ?></option>
                                            <?php foreach ( $csv['headers'] as $i => $header ) : ?>
                                                <option value="<?php echo (int) $i; ?>" <?php selected( (int) ( $mapping[ $field ] ?? -1 ), (int) $i ); ?>><?php echo esc_html( $header ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            <?php endforeach; ?>

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
                        </div>
                    </details>
                    <?php endif; ?>

                    <div class="sk-form-group">
                        <button type="submit" id="sk-import-submit" class="sk-btn sk-btn-theme">
                            <?php printf( esc_html__( '%d Inserate importieren', 'sk-core' ), (int) $preset ); ?>
                        </button>
                        <a class="sk-btn sk-btn-default" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Abbrechen', 'sk-core' ); ?></a>
                    </div>
                </form>

                <div id="sk-import-progress" class="sk-import-progress" aria-live="polite">
                    <div class="sk-import-progress__backdrop"></div>
                    <div class="sk-import-progress__box">
                        <div class="sk-import-progress__spinner" aria-hidden="true"></div>
                        <h3><?php esc_html_e( 'Inserate werden angelegt', 'sk-core' ); ?></h3>
                        <p id="sk-import-progress-text"><?php esc_html_e( 'Das dauert einen Moment.', 'sk-core' ); ?></p>
                        <p class="sk-import-progress__hint">
                            <?php esc_html_e( 'Bitte das Fenster offen lassen — Bilder werden dabei aus deinem Shop geladen.', 'sk-core' ); ?>
                        </p>
                    </div>
                </div>

            <?php else : ?>

                <div class="sk-section-heading"><h3><?php esc_html_e( 'Shopify-Shop', 'sk-core' ); ?></h3></div>
                <div class="sk-section-content">
                    <p><?php esc_html_e( 'Läuft dein Shop auf Shopify, brauchst du keine Datei: trag die Adresse ein, dann holen wir den Katalog direkt. Ausführungen und Bilder kommen dabei vollständig mit, Spalten musst du keine zuordnen. Im nächsten Schritt siehst du, was passieren würde, bevor etwas angelegt wird.', 'sk-core' ); ?></p>

                    <form method="post" action="<?php echo esc_url( $url ); ?>">
                        <?php wp_nonce_field( DashboardPage::NONCE, 'sk_shop_import_nonce' ); ?>
                        <input type="hidden" name="sk_step" value="holen">

                        <div class="sk-form-group sk-clearfix">
                            <label class="sk-w3 sk-control-label" for="sk_shop_url"><?php esc_html_e( 'Adresse deines Shops', 'sk-core' ); ?></label>
                            <div class="sk-w9">
                                <input class="sk-form-control" type="url" name="sk_shop_url" id="sk_shop_url"
                                       value="<?php echo esc_attr( $shop_url ); ?>"
                                       placeholder="https://mein-shop.myshopify.com" required>
                            </div>
                        </div>

                        <div class="sk-form-group">
                            <button type="submit" class="sk-btn sk-btn-theme"><?php esc_html_e( 'Katalog holen', 'sk-core' ); ?></button>
                        </div>
                    </form>

                    <p class="sk-import-hint"><?php esc_html_e( 'Kein Shopify-Shop? Dann nimm den Weg über die Datei darunter.', 'sk-core' ); ?></p>
                </div>

                <div class="sk-section-heading"><h3><?php esc_html_e( 'Katalog hochladen', 'sk-core' ); ?></h3></div>
                <div class="sk-section-content">
                    <ol class="sk-import-howto">
                        <li><?php esc_html_e( 'Öffne in deinem eigenen Shop den Bereich Produkte und klicke oben auf „Exportieren".', 'sk-core' ); ?></li>
                        <li><?php esc_html_e( 'Alle Spalten und alle Kategorien auswählen, dann die CSV erzeugen und herunterladen.', 'sk-core' ); ?></li>
                        <li><?php esc_html_e( 'Datei hier hochladen. Im nächsten Schritt siehst du, was passieren würde, bevor etwas angelegt wird.', 'sk-core' ); ?></li>
                    </ol>

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
                            <button type="submit" class="sk-btn sk-btn-theme"><?php esc_html_e( 'Datei prüfen', 'sk-core' ); ?></button>
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
