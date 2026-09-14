<?php
/**
 * Views and contacts of the vendor's own listings.
 *
 * @var int   $listings Published listings.
 * @var int   $drafts   Drafts and pending ones.
 * @var ?int  $allowed  Package limit, null when unlimited.
 * @var int   $views    Views across all listings, since the beginning.
 * @var int   $contacts Contacts within the window.
 * @var int   $window   Days the contact figure covers.
 * @var array $top      The most seen listings.
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="sk-vendor-stats">
    <h3 class="sk-vendor-stats__title">
        <i class="fas fa-chart-simple"></i> <?php esc_html_e( 'Deine Zahlen', 'sk-core' ); ?>
    </h3>

    <div class="sk-vendor-stats__figures">
        <div class="sk-stat">
            <span class="sk-stat__value">
                <?php echo (int) $listings; ?><?php if ( null !== $allowed ) : ?><span class="sk-stat__of">/&thinsp;<?php echo (int) $allowed; ?></span><?php endif; ?>
            </span>
            <span class="sk-stat__label"><?php esc_html_e( 'Inserate online', 'sk-core' ); ?></span>
            <?php if ( $drafts > 0 ) : ?>
                <span class="sk-stat__note">
                    <?php
                    printf(
                        /* translators: %d: number of drafts */
                        esc_html( _n( '%d Entwurf', '%d Entwürfe', $drafts, 'sk-core' ) ),
                        (int) $drafts
                    );
                    ?>
                </span>
            <?php endif; ?>
        </div>

        <div class="sk-stat">
            <span class="sk-stat__value"><?php echo esc_html( number_format_i18n( $views ) ); ?></span>
            <span class="sk-stat__label"><?php esc_html_e( 'Aufrufe', 'sk-core' ); ?></span>
            <span class="sk-stat__note"><?php esc_html_e( 'seit Beginn', 'sk-core' ); ?></span>
        </div>

        <div class="sk-stat">
            <span class="sk-stat__value"><?php echo esc_html( number_format_i18n( $contacts ) ); ?></span>
            <span class="sk-stat__label"><?php esc_html_e( 'Kontaktaufnahmen', 'sk-core' ); ?></span>
            <span class="sk-stat__note">
                <?php
                printf(
                    /* translators: %d: number of days */
                    esc_html__( 'letzte %d Tage', 'sk-core' ),
                    (int) $window
                );
                ?>
            </span>
        </div>
    </div>

    <?php if ( ! empty( $top ) ) : ?>
        <table class="sk-vendor-stats__table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Meistgesehene Inserate', 'sk-core' ); ?></th>
                    <th class="sk-num"><?php esc_html_e( 'Aufrufe', 'sk-core' ); ?></th>
                    <th class="sk-num"><?php esc_html_e( 'Kontakte', 'sk-core' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $top as $sk_row ) : ?>
                    <tr>
                        <td>
                            <a href="<?php echo esc_url( $sk_row['url'] ); ?>"><?php echo esc_html( $sk_row['title'] ); ?></a>
                        </td>
                        <td class="sk-num"><?php echo esc_html( number_format_i18n( $sk_row['views'] ) ); ?></td>
                        <td class="sk-num"><?php echo esc_html( number_format_i18n( $sk_row['contacts'] ) ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p class="sk-vendor-stats__hint">
            <?php esc_html_e( 'Viele Aufrufe und kaum Kontakte? Dann stimmt meist der Preis oder das erste Bild nicht. Kaum Aufrufe? Dann eher der Titel oder die Kategorie.', 'sk-core' ); ?>
        </p>
    <?php endif; ?>
</section>
