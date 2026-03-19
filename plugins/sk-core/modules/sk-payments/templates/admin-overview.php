<?php
/**
 * Admin template: Lightning Overview
 */
defined( 'ABSPATH' ) || exit;

$status = \SK\Modules\Payments\Admin\AdminPage::get_system_status();
$stats  = \SK\Modules\Payments\Admin\AdminPage::get_stats();

global $wpdb;
$table = $wpdb->prefix . 'sk_lightning_payments';
$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

// Filters.
$filter_status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
$filter_vendor = isset( $_GET['vendor'] ) ? absint( $_GET['vendor'] ) : 0;
$page_url = admin_url( 'admin.php?page=sk-payments' );

// Build query for transactions.
$where = [ '1=1' ];
$args  = [];

if ( $filter_status ) {
    $where[] = 'status = %s';
    $args[]  = $filter_status;
}
if ( $filter_vendor ) {
    $where[] = 'vendor_id = %d';
    $args[]  = $filter_vendor;
}

$where_sql = implode( ' AND ', $where );
$payments  = [];

if ( $table_exists ) {
    if ( ! empty( $args ) ) {
        $payments = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT 100",
            ...$args
        ) );
    } else {
        $payments = $wpdb->get_results(
            "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 100"
        );
    }
}

// Disputes.
$disputes = [];
if ( $table_exists ) {
    $disputes = $wpdb->get_results(
        "SELECT * FROM {$table} WHERE status = 'disputed' ORDER BY created_at DESC"
    );
}

$msg = isset( $_GET['msg'] ) ? sanitize_text_field( wp_unslash( $_GET['msg'] ) ) : '';
?>
<div class="wrap">
    <h1>SK Payments — Übersicht</h1>

    <?php if ( $msg === 'updated' ) : ?>
        <div class="notice notice-success is-dismissible"><p>Dispute-Status aktualisiert.</p></div>
    <?php endif; ?>

    <!-- System Status -->
    <div class="card" style="max-width:100%;margin-bottom:20px;">
        <h2>Systemstatus</h2>
        <table class="widefat" style="max-width:600px;">
            <tbody>
                <tr>
                    <td>LNURL-Resolve</td>
                    <td><?php echo $status['lnurl_resolve'] ? '✅ Funktioniert' : '❌ Fehlgeschlagen'; ?></td>
                </tr>
                <tr>
                    <td>Kurs-API</td>
                    <td>
                        <?php echo $status['exchange_rate'] ? '✅ ' . number_format( $status['exchange_rate_value'], 0, ',', '.' ) . ' €/BTC' : '❌ Nicht erreichbar'; ?>
                    </td>
                </tr>
                <tr>
                    <td>Reputation-Cron</td>
                    <td>
                        <?php if ( $status['cron_registered'] ) : ?>
                            ✅ Registriert (nächster Lauf: <?php echo esc_html( wp_date( 'd.m.Y H:i', $status['cron_next'] ) ); ?>)
                        <?php else : ?>
                            ❌ Nicht registriert
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td>VendorChat</td>
                    <td><?php echo $status['vendor_chat'] ? '✅ Aktiv' : '❌ Nicht gefunden'; ?></td>
                </tr>
                <tr>
                    <td>Vendors mit Lightning-Adresse</td>
                    <td><?php echo esc_html( $status['vendors_with_ln'] ); ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Statistics -->
    <div class="card" style="max-width:100%;margin-bottom:20px;">
        <h2>Statistiken</h2>
        <div style="display:flex;gap:20px;flex-wrap:wrap;">
            <div style="background:#f0f0f1;padding:12px 20px;border-radius:6px;min-width:140px;">
                <div style="font-size:28px;font-weight:bold;"><?php echo esc_html( $stats['total_requests'] ); ?></div>
                <div style="color:#666;">Kaufanfragen gesamt</div>
            </div>
            <div style="background:#f0f0f1;padding:12px 20px;border-radius:6px;min-width:140px;">
                <div style="font-size:28px;font-weight:bold;"><?php echo esc_html( $stats['paid_total'] ); ?></div>
                <div style="color:#666;">Bezahlt gesamt</div>
            </div>
            <div style="background:#f0f0f1;padding:12px 20px;border-radius:6px;min-width:140px;">
                <div style="font-size:28px;font-weight:bold;"><?php echo esc_html( $stats['paid_7d'] ); ?></div>
                <div style="color:#666;">Bezahlt (7 Tage)</div>
            </div>
            <div style="background:#f0f0f1;padding:12px 20px;border-radius:6px;min-width:140px;">
                <div style="font-size:28px;font-weight:bold;"><?php echo number_format( $stats['paid_volume'], 0, ',', '.' ); ?></div>
                <div style="color:#666;">Sats Volumen</div>
            </div>
            <div style="background:#fef2f2;padding:12px 20px;border-radius:6px;min-width:140px;">
                <div style="font-size:28px;font-weight:bold;color:#dc2626;"><?php echo esc_html( $stats['open_disputes'] ); ?></div>
                <div style="color:#666;">Offene Disputes</div>
            </div>
            <div style="background:#f0fdf4;padding:12px 20px;border-radius:6px;min-width:140px;">
                <div style="font-size:28px;font-weight:bold;color:#16a34a;"><?php echo esc_html( $stats['rep_credited'] ); ?></div>
                <div style="color:#666;">Reputation gutgeschrieben</div>
            </div>
        </div>
    </div>

    <!-- Dispute Management -->
    <?php if ( ! empty( $disputes ) ) : ?>
    <div class="card" style="max-width:100%;margin-bottom:20px;">
        <h2>🔴 Offene Disputes (<?php echo count( $disputes ); ?>)</h2>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Datum</th>
                    <th>Käufer</th>
                    <th>Verkäufer</th>
                    <th>Produkt</th>
                    <th>Sats</th>
                    <th>Grund</th>
                    <th>Aktion</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $disputes as $d ) :
                $buyer  = get_userdata( $d->buyer_id );
                $vendor = get_userdata( $d->vendor_id );
                $product_title = $d->product_id ? get_the_title( $d->product_id ) : '—';
                $meta = json_decode( $d->metadata, true );
                $reason = $meta['dispute_reason'] ?? '—';
            ?>
                <tr>
                    <td>#<?php echo esc_html( $d->id ); ?></td>
                    <td><?php echo esc_html( wp_date( 'd.m.Y H:i', strtotime( $d->created_at ) ) ); ?></td>
                    <td><?php echo $buyer ? esc_html( $buyer->display_name ) : '#' . $d->buyer_id; ?></td>
                    <td><?php echo $vendor ? esc_html( $vendor->display_name ) : '#' . $d->vendor_id; ?></td>
                    <td><?php echo esc_html( $product_title ); ?></td>
                    <td style="text-align:right;"><?php echo number_format( $d->amount_sats, 0, ',', '.' ); ?></td>
                    <td><?php echo esc_html( $reason ); ?></td>
                    <td>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
                            <?php wp_nonce_field( 'skl_dispute_action' ); ?>
                            <input type="hidden" name="action" value="skl_resolve_dispute" />
                            <input type="hidden" name="payment_id" value="<?php echo esc_attr( $d->id ); ?>" />
                            <button type="submit" name="dispute_action" value="confirm_dispute" class="button" style="color:#dc2626;">
                                Rep blockieren
                            </button>
                            <button type="submit" name="dispute_action" value="reject_dispute" class="button button-primary">
                                Dispute ablehnen
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- All Transactions -->
    <div class="card" style="max-width:100%;">
        <h2>Alle Transaktionen</h2>

        <div style="margin-bottom:12px;display:flex;gap:8px;align-items:center;">
            <strong>Filter:</strong>
            <a href="<?php echo esc_url( $page_url ); ?>" class="button <?php echo ! $filter_status ? 'button-primary' : ''; ?>">Alle</a>
            <a href="<?php echo esc_url( add_query_arg( 'status', 'pending', $page_url ) ); ?>" class="button <?php echo $filter_status === 'pending' ? 'button-primary' : ''; ?>">Ausstehend</a>
            <a href="<?php echo esc_url( add_query_arg( 'status', 'confirmed', $page_url ) ); ?>" class="button <?php echo $filter_status === 'confirmed' ? 'button-primary' : ''; ?>">Bezahlt</a>
            <a href="<?php echo esc_url( add_query_arg( 'status', 'disputed', $page_url ) ); ?>" class="button <?php echo $filter_status === 'disputed' ? 'button-primary' : ''; ?>">Disputed</a>
        </div>

        <?php if ( empty( $payments ) ) : ?>
            <p>Keine Transaktionen gefunden.</p>
        <?php else : ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Datum</th>
                        <th>Käufer</th>
                        <th>Verkäufer</th>
                        <th>Produkt</th>
                        <th style="text-align:right;">Sats</th>
                        <th>Status</th>
                        <th>Rep</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $status_badges = [
                    'pending'   => '<span style="color:#d97706;">🟡 Ausstehend</span>',
                    'confirmed' => '<span style="color:#16a34a;">🟢 Bezahlt</span>',
                    'expired'   => '<span style="color:#9ca3af;">⚪ Abgelaufen</span>',
                    'disputed'  => '<span style="color:#dc2626;">🔴 Problem</span>',
                ];
                foreach ( $payments as $p ) :
                    $buyer  = get_userdata( $p->buyer_id );
                    $vendor = get_userdata( $p->vendor_id );
                    $product_title = $p->product_id ? get_the_title( $p->product_id ) : '—';
                    $rep = $p->reputation_valid ? '⚡' : ( $p->reputation_at ? wp_date( 'd.m.', strtotime( $p->reputation_at ) ) : '—' );
                ?>
                    <tr>
                        <td><?php echo esc_html( wp_date( 'd.m.Y H:i', strtotime( $p->created_at ) ) ); ?></td>
                        <td><?php echo $buyer ? esc_html( $buyer->display_name ) : '#' . $p->buyer_id; ?></td>
                        <td><?php echo $vendor ? esc_html( $vendor->display_name ) : '#' . $p->vendor_id; ?></td>
                        <td><?php echo esc_html( $product_title ); ?></td>
                        <td style="text-align:right;font-family:monospace;"><?php echo number_format( $p->amount_sats, 0, ',', '.' ); ?></td>
                        <td><?php echo $status_badges[ $p->status ] ?? esc_html( $p->status ); ?></td>
                        <td><?php echo esc_html( $rep ); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
