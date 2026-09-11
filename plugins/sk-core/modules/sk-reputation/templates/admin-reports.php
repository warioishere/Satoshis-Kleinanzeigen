<?php
/**
 * Admin: Nostr reports against vendors — from the marketplace's web of trust, or from
 * registered vendors (shown as such).
 *
 * Expects $vendors (see Reports::vendors_with_reports) and $last_run.
 */

use SK\Core\Nostr\ReportTypes;
use SK\Modules\Reputation\ReportsAdmin;

defined( 'ABSPATH' ) || exit;

$type_labels = ReportTypes::labels();
?>
<div class="wrap sk-rep">
    <h1><?php esc_html_e( 'SK Reputation – Nostr-Meldungen', 'sk-core' ); ?></h1>

    <?php if ( ! empty( $_GET['fetched'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Meldungen wurden abgerufen.', 'sk-core' ); ?></p></div>
    <?php endif; ?>

    <p>
        <?php esc_html_e( 'Kind-1984-Meldungen gegen die nachgewiesenen Schlüssel der Anbieter, einmal täglich von den Relays gelesen. Gezählt wird, was aus dem Web of Trust kommt (Schlüssel, denen der Marktplatz folgt, und deren Kontakte) sowie Meldungen registrierter Anbieter mit nachgewiesenem Schlüssel; letztere sind als „Anbieter #ID" gekennzeichnet, denn ein Anbieter, der einen Mitbewerber meldet, ist nicht die Community. Käufer sehen hiervon nichts; sie sehen nur Meldungen aus ihren eigenen Kontakten.', 'sk-core' ); ?>
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

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sk-rep-fetch">
        <input type="hidden" name="action" value="<?php echo esc_attr( ReportsAdmin::FETCH_ACTION ); ?>">
        <?php wp_nonce_field( ReportsAdmin::FETCH_ACTION ); ?>
        <button type="submit" class="button"><?php esc_html_e( 'Jetzt abrufen', 'sk-core' ); ?></button>
        <span class="description"><?php esc_html_e( 'Liest alle Relays; dauert einige Sekunden.', 'sk-core' ); ?></span>
    </form>

    <?php if ( empty( $vendors ) ) : ?>
        <p><strong><?php esc_html_e( 'Keine Meldungen von bekannten Meldern.', 'sk-core' ); ?></strong></p>
    <?php else : ?>
        <?php foreach ( $vendors as $vendor_id => $row ) : ?>
            <h2 class="sk-rep-vendor">
                <a href="<?php echo esc_url( sk_get_store_url( $vendor_id ) ); ?>" target="_blank"><?php echo esc_html( $row['vendor']->display_name ); ?></a>
                <span class="sk-rep-vendor-meta">#<?php echo (int) $vendor_id; ?> · <?php echo esc_html( sprintf( _n( '%d Meldung', '%d Meldungen', count( $row['reports'] ), 'sk-core' ), count( $row['reports'] ) ) ); ?></span>
            </h2>
            <table class="widefat striped sk-rep-table">
                <thead>
                    <tr>
                        <th class="sk-rep-col-date"><?php esc_html_e( 'Datum', 'sk-core' ); ?></th>
                        <th class="sk-rep-col-type"><?php esc_html_e( 'Typ', 'sk-core' ); ?></th>
                        <th class="sk-rep-col-reporter"><?php esc_html_e( 'Melder', 'sk-core' ); ?></th>
                        <th><?php esc_html_e( 'Inhalt', 'sk-core' ); ?></th>
                        <th class="sk-rep-col-event"><?php esc_html_e( 'Event', 'sk-core' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $row['reports'] as $r ) : ?>
                        <tr>
                            <td><?php echo esc_html( wp_date( 'd.m.Y', (int) $r['created_at'] ) ); ?></td>
                            <td><?php echo esc_html( $type_labels[ $r['type'] ] ?? $r['type'] ); ?></td>
                            <td>
                                <?php if ( ! empty( $r['reporter_user'] ) ) : ?>
                                    <?php $by = get_userdata( (int) $r['reporter_user'] ); ?>
                                    <strong><a href="<?php echo esc_url( sk_get_store_url( (int) $r['reporter_user'] ) ); ?>" target="_blank"><?php echo esc_html( sprintf( __( 'Anbieter #%d', 'sk-core' ), (int) $r['reporter_user'] ) ); ?></a></strong>
                                    <?php echo $by ? esc_html( $by->display_name ) : ''; ?><br>
                                <?php endif; ?>
                                <a href="<?php echo esc_url( \SK\Core\Nostr\Keys::profile_url( $r['reporter'] ) ); ?>" target="_blank" rel="noopener"><code><?php echo esc_html( substr( $r['reporter'], 0, 12 ) . '…' ); ?></code></a>
                            </td>
                            <td><?php echo esc_html( $r['content'] !== '' ? $r['content'] : '–' ); ?></td>
                            <td><a href="<?php echo esc_url( \SK\Core\Nostr\Keys::event_url( $r['id'] ) ); ?>" target="_blank" rel="noopener">nostrich</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
