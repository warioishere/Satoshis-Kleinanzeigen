<?php
/**
 * Admin: Nostr reports against vendors, filtered by the marketplace's web of trust.
 *
 * Expects $vendors (see Reports::vendors_with_reports) and $last_run.
 */

defined( 'ABSPATH' ) || exit;

$type_labels = [
    'spam'          => __( 'Spam', 'sk-core' ),
    'impersonation' => __( 'Identitätsmissbrauch', 'sk-core' ),
    'illegal'       => __( 'Illegal', 'sk-core' ),
    'malware'       => __( 'Schadsoftware', 'sk-core' ),
];
?>
<div class="wrap">
    <h1><?php esc_html_e( 'SK Reputation – Nostr-Meldungen', 'sk-core' ); ?></h1>

    <?php if ( ! empty( $_GET['fetched'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Meldungen wurden abgerufen.', 'sk-core' ); ?></p></div>
    <?php endif; ?>

    <p>
        <?php esc_html_e( 'Kind-1984-Meldungen gegen die nachgewiesenen Schlüssel der Anbieter, einmal täglich von den Relays gelesen. Gezählt wird nur, was aus dem Web of Trust kommt: Schlüssel, denen der Marktplatz folgt, deren Kontakte, und die nachgewiesenen Schlüssel registrierter Anbieter. Käufer sehen hiervon nichts; sie sehen nur Meldungen aus ihren eigenen Kontakten.', 'sk-core' ); ?>
    </p>

    <p>
        <?php if ( ! empty( $last_run['time'] ) ) : ?>
            <?php
            printf(
                /* translators: 1: date, 2: vendor keys, 3: events read, 4: kept, 5: web of trust size, 6: seconds */
                esc_html__( 'Letzter Lauf: %1$s – %2$d Anbieter-Schlüssel, %3$d Meldungen gelesen, %4$d behalten, Web of Trust %5$d Schlüssel, %6$s s.', 'sk-core' ),
                esc_html( wp_date( 'd.m.Y H:i', (int) $last_run['time'] ) ),
                (int) ( $last_run['targets'] ?? 0 ),
                (int) ( $last_run['events'] ?? 0 ),
                (int) ( $last_run['kept'] ?? 0 ),
                (int) ( $last_run['wot'] ?? 0 ),
                esc_html( (string) ( $last_run['seconds'] ?? '?' ) )
            );
            ?>
        <?php else : ?>
            <?php esc_html_e( 'Noch kein Lauf.', 'sk-core' ); ?>
        <?php endif; ?>
    </p>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:20px;">
        <input type="hidden" name="action" value="sk_reputation_fetch_reports">
        <?php wp_nonce_field( 'sk_reputation_fetch_reports' ); ?>
        <button type="submit" class="button"><?php esc_html_e( 'Jetzt abrufen', 'sk-core' ); ?></button>
        <span class="description"><?php esc_html_e( 'Liest alle Relays; dauert einige Sekunden.', 'sk-core' ); ?></span>
    </form>

    <?php if ( empty( $vendors ) ) : ?>
        <p><strong><?php esc_html_e( 'Keine Meldungen aus dem Web of Trust.', 'sk-core' ); ?></strong></p>
    <?php else : ?>
        <?php foreach ( $vendors as $vendor_id => $row ) : ?>
            <h2 style="margin-top:24px;">
                <a href="<?php echo esc_url( sk_get_store_url( $vendor_id ) ); ?>" target="_blank"><?php echo esc_html( $row['vendor']->display_name ); ?></a>
                <span style="font-weight:normal;color:#666;">#<?php echo (int) $vendor_id; ?> · <?php echo esc_html( sprintf( _n( '%d Meldung', '%d Meldungen', count( $row['reports'] ), 'sk-core' ), count( $row['reports'] ) ) ); ?></span>
            </h2>
            <table class="widefat striped" style="max-width:1100px;">
                <thead>
                    <tr>
                        <th style="width:110px;"><?php esc_html_e( 'Datum', 'sk-core' ); ?></th>
                        <th style="width:160px;"><?php esc_html_e( 'Typ', 'sk-core' ); ?></th>
                        <th style="width:200px;"><?php esc_html_e( 'Melder', 'sk-core' ); ?></th>
                        <th><?php esc_html_e( 'Inhalt', 'sk-core' ); ?></th>
                        <th style="width:90px;"><?php esc_html_e( 'Event', 'sk-core' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $row['reports'] as $r ) : ?>
                        <tr>
                            <td><?php echo esc_html( wp_date( 'd.m.Y', (int) $r['created_at'] ) ); ?></td>
                            <td><?php echo esc_html( $type_labels[ $r['type'] ] ?? $r['type'] ); ?></td>
                            <td><a href="<?php echo esc_url( 'https://njump.me/' . $r['reporter'] ); ?>" target="_blank" rel="noopener"><code><?php echo esc_html( substr( $r['reporter'], 0, 12 ) . '…' ); ?></code></a></td>
                            <td><?php echo esc_html( $r['content'] !== '' ? $r['content'] : '–' ); ?></td>
                            <td><a href="<?php echo esc_url( 'https://njump.me/' . $r['id'] ); ?>" target="_blank" rel="noopener">njump</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
