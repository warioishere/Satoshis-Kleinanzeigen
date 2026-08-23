<?php
/**
 * SK → Kontaktklicks.
 *
 * @var array  $totals
 * @var array  $channels
 * @var array  $products
 * @var array  $daily
 * @var int    $views
 * @var string $first_day
 * @var array  $labels
 * @var int    $days
 * @var string $from
 * @var string $to
 */

defined( 'ABSPATH' ) || exit;

$base_url = add_query_arg( [ 'page' => 'sk', 'tab' => 'contact-clicks' ], admin_url( 'admin.php' ) );
$max_day  = ! empty( $daily ) ? max( $daily ) : 1;
?>

<div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:16px;">
    <div>
        <h2 style="margin:0;"><?php esc_html_e( 'Kontaktklicks', 'sk-core' ); ?></h2>
        <p style="margin:4px 0 0;color:#646970;">
            <?php esc_html_e( 'Wie oft aus einem Inserat heraus wirklich Kontakt aufgenommen wurde.', 'sk-core' ); ?>
        </p>
    </div>
    <div style="display:flex;gap:8px;">
        <?php foreach ( [ 7, 30, 90, 365 ] as $option ) : ?>
            <a class="button<?php echo $days === $option ? ' button-primary' : ''; ?>"
               href="<?php echo esc_url( add_query_arg( 'days', $option, $base_url ) ); ?>">
                <?php printf( esc_html__( '%d Tage', 'sk-core' ), (int) $option ); ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<?php if ( empty( $first_day ) ) : ?>
    <div class="notice notice-info" style="padding:12px;">
        <p style="margin:0;">
            <?php esc_html_e( 'Noch keine Kontaktklicks erfasst. Die Messung läuft ab jetzt — belastbar wird sie nach einigen Wochen.', 'sk-core' ); ?>
        </p>
    </div>
<?php else : ?>
    <p style="color:#646970;margin-top:0;">
        <?php printf( esc_html__( 'Gemessen wird seit %s.', 'sk-core' ), esc_html( $first_day ) ); ?>
    </p>
<?php endif; ?>

<div style="display:flex;gap:16px;flex-wrap:wrap;margin:16px 0;">
    <?php
    $cards = [
        [ __( 'Kontakte', 'sk-core' ), number_format_i18n( $totals['clicks'] ) ],
        [ __( 'davon verschiedene Besucher (je Tag)', 'sk-core' ), number_format_i18n( $totals['unique'] ) ],
        [ __( 'Inserate mit Kontakt', 'sk-core' ), number_format_i18n( count( $products ) ) ],
    ];
    foreach ( $cards as $card ) :
        ?>
        <div style="background:#fff;border:1px solid #c3c4c7;padding:12px 16px;min-width:170px;">
            <div style="font-size:12px;color:#646970;"><?php echo esc_html( $card[0] ); ?></div>
            <div style="font-size:24px;font-weight:600;"><?php echo esc_html( $card[1] ); ?></div>
        </div>
    <?php endforeach; ?>
</div>

<p style="color:#646970;max-width:760px;">
    <?php
    printf(
        /* translators: %s: total listing views */
        esc_html__( 'Zur Einordnung: Alle Inserate zusammen wurden bisher %s mal aufgerufen. Diese Zahl ist kumulativ seit Bestehen und nicht auf den gewählten Zeitraum begrenzt — sie taugt als grobe Hausnummer, nicht als exakte Quote.', 'sk-core' ),
        '<strong>' . esc_html( number_format_i18n( $views ) ) . '</strong>'
    );
    ?>
</p>

<h3><?php esc_html_e( 'Nach Kanal', 'sk-core' ); ?></h3>
<?php if ( empty( $channels ) ) : ?>
    <p><?php esc_html_e( 'Noch nichts erfasst.', 'sk-core' ); ?></p>
<?php else : ?>
    <table class="wp-list-table widefat striped" style="max-width:520px;">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Kanal', 'sk-core' ); ?></th>
                <th style="width:110px;text-align:right;"><?php esc_html_e( 'Kontakte', 'sk-core' ); ?></th>
                <th style="width:110px;text-align:right;"><?php esc_html_e( 'Besucher/Tag', 'sk-core' ); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $channels as $key => $row ) : ?>
            <tr>
                <td><?php echo esc_html( $labels[ $key ] ?? $key ); ?></td>
                <td style="text-align:right;"><strong><?php echo esc_html( number_format_i18n( $row['clicks'] ) ); ?></strong></td>
                <td style="text-align:right;"><?php echo esc_html( number_format_i18n( $row['unique'] ) ); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php if ( ! empty( $daily ) ) : ?>
    <h3><?php esc_html_e( 'Verlauf', 'sk-core' ); ?></h3>
    <table class="widefat striped" style="max-width:520px;">
        <tbody>
        <?php foreach ( $daily as $day => $count ) : ?>
            <tr>
                <td style="width:110px;"><?php echo esc_html( $day ); ?></td>
                <td style="width:70px;text-align:right;"><?php echo esc_html( number_format_i18n( $count ) ); ?></td>
                <td>
                    <div style="background:#f0f0f1;height:12px;">
                        <div style="background:#db6218;height:12px;width:<?php echo (int) round( $count / $max_day * 100 ); ?>%;"></div>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php if ( ! empty( $products ) ) : ?>
    <h3><?php esc_html_e( 'Inserate mit den meisten Kontakten', 'sk-core' ); ?></h3>
    <table class="wp-list-table widefat fixed striped" style="max-width:900px;">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Inserat', 'sk-core' ); ?></th>
                <th style="width:180px;"><?php esc_html_e( 'Verkäufer', 'sk-core' ); ?></th>
                <th style="width:100px;text-align:right;"><?php esc_html_e( 'Kontakte', 'sk-core' ); ?></th>
                <th style="width:100px;text-align:right;"><?php esc_html_e( 'Aufrufe', 'sk-core' ); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $products as $row ) : ?>
            <?php
            $title  = get_the_title( $row['product_id'] );
            $vendor = $row['vendor_id'] ? get_userdata( $row['vendor_id'] ) : null;
            $pv     = (int) get_post_meta( $row['product_id'], 'pageview', true );
            ?>
            <tr>
                <td>
                    <a href="<?php echo esc_url( get_permalink( $row['product_id'] ) ); ?>" target="_blank" rel="noopener">
                        <?php echo esc_html( $title !== '' ? $title : '#' . $row['product_id'] ); ?>
                    </a>
                </td>
                <td><?php echo esc_html( $vendor ? $vendor->display_name : '—' ); ?></td>
                <td style="text-align:right;"><strong><?php echo esc_html( number_format_i18n( $row['clicks'] ) ); ?></strong></td>
                <td style="text-align:right;"><?php echo $pv ? esc_html( number_format_i18n( $pv ) ) : '—'; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<p style="color:#646970;margin-top:14px;max-width:760px;">
    <?php esc_html_e( 'Gezählt werden Klicks auf Telegram, Nostr, E-Mail, Telefon und X in Inseraten und Trefferlisten. Bots und Link-Vorschauen zählen nicht, ebensowenig Klicks des eigenen Verkäufers auf sein Inserat. Es wird keine IP gespeichert; derselbe Besucher ist über Tage hinweg nicht wiedererkennbar.', 'sk-core' ); ?>
</p>
