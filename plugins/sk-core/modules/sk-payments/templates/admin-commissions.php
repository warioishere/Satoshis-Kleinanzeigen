<?php
/**
 * Admin template: Kommissions-Übersicht
 */
defined( 'ABSPATH' ) || exit;

$stats = \SK\Modules\Payments\Commission\Generator::get_stats();
$rate  = \SK\Modules\Payments\Commission\Generator::get_rate();

global $wpdb;
$table = $wpdb->prefix . 'sk_commissions';
$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

// Filters.
$filter_status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
$page_url = admin_url( 'admin.php?page=sk-commissions' );

$commissions = [];
if ( $table_exists ) {
    $where = [ '1=1' ];
    $args  = [];

    if ( $filter_status ) {
        $where[] = 'c.status = %s';
        $args[]  = $filter_status;
    }

    $where_sql = implode( ' AND ', $where );

    if ( ! empty( $args ) ) {
        $commissions = $wpdb->get_results( $wpdb->prepare(
            "SELECT c.*, p.product_id, p.context, p.status as payment_status
             FROM {$table} c
             LEFT JOIN {$wpdb->prefix}sk_lightning_payments p ON p.payment_hash = c.payment_hash
             WHERE {$where_sql}
             ORDER BY c.created_at DESC LIMIT 100",
            ...$args
        ) );
    } else {
        $commissions = $wpdb->get_results(
            "SELECT c.*, p.product_id, p.context, p.status as payment_status
             FROM {$table} c
             LEFT JOIN {$wpdb->prefix}sk_lightning_payments p ON p.payment_hash = c.payment_hash
             ORDER BY c.created_at DESC LIMIT 100"
        );
    }
}

$status_badges = [
    'pending'  => '<span style="color:#d97706;">Ausstehend</span>',
    'invoiced' => '<span style="color:#3b82f6;">Invoice erstellt</span>',
    'paid'     => '<span style="color:#16a34a;">Bezahlt</span>',
    'waived'   => '<span style="color:#9ca3af;">Erlassen</span>',
];

$payment_status_badges = [
    'pending'   => '<span style="color:#d97706;">Ausstehend</span>',
    'confirmed' => '<span style="color:#16a34a;">Bezahlt</span>',
    'delivered' => '<span style="color:#16a34a;">Erhalten</span>',
    'expired'   => '<span style="color:#9ca3af;">Abgelaufen</span>',
    'disputed'  => '<span style="color:#dc2626;">Problem</span>',
];
?>
<div class="wrap">
    <h1>Kommissionen (<?php echo esc_html( $rate ); ?>%)</h1>

    <!-- Stats -->
    <div class="card" style="max-width:100%;margin-bottom:20px;">
        <h2>Übersicht</h2>
        <div style="display:flex;gap:20px;flex-wrap:wrap;">
            <div style="background:#f0f0f1;padding:12px 20px;border-radius:6px;min-width:140px;">
                <div style="font-size:28px;font-weight:bold;"><?php echo esc_html( $stats['total'] ); ?></div>
                <div style="color:#666;">Gesamt</div>
            </div>
            <div style="background:#f0fdf4;padding:12px 20px;border-radius:6px;min-width:140px;">
                <div style="font-size:28px;font-weight:bold;color:#16a34a;"><?php echo esc_html( $stats['paid'] ); ?></div>
                <div style="color:#666;">Bezahlt</div>
            </div>
            <div style="background:#eff6ff;padding:12px 20px;border-radius:6px;min-width:140px;">
                <div style="font-size:28px;font-weight:bold;color:#3b82f6;"><?php echo esc_html( $stats['invoiced'] ); ?></div>
                <div style="color:#666;">Invoice erstellt</div>
            </div>
            <div style="background:#fef3c7;padding:12px 20px;border-radius:6px;min-width:140px;">
                <div style="font-size:28px;font-weight:bold;color:#d97706;"><?php echo esc_html( $stats['pending'] ); ?></div>
                <div style="color:#666;">Ausstehend</div>
            </div>
            <div style="background:#f0fdf4;padding:12px 20px;border-radius:6px;min-width:140px;">
                <div style="font-size:28px;font-weight:bold;color:#16a34a;"><?php echo number_format( $stats['paid_sats'], 0, ',', '.' ); ?></div>
                <div style="color:#666;">Sats erhalten</div>
            </div>
            <div style="background:#fef3c7;padding:12px 20px;border-radius:6px;min-width:140px;">
                <div style="font-size:28px;font-weight:bold;color:#d97706;"><?php echo number_format( $stats['unpaid_sats'], 0, ',', '.' ); ?></div>
                <div style="color:#666;">Sats offen</div>
            </div>
        </div>
    </div>

    <!-- Transactions -->
    <div class="card" style="max-width:100%;">
        <h2>Transaktionen</h2>

        <div style="margin-bottom:12px;display:flex;gap:8px;align-items:center;">
            <strong>Filter:</strong>
            <a href="<?php echo esc_url( $page_url ); ?>" class="button <?php echo ! $filter_status ? 'button-primary' : ''; ?>">Alle</a>
            <a href="<?php echo esc_url( add_query_arg( 'status', 'pending', $page_url ) ); ?>" class="button <?php echo $filter_status === 'pending' ? 'button-primary' : ''; ?>">Ausstehend</a>
            <a href="<?php echo esc_url( add_query_arg( 'status', 'invoiced', $page_url ) ); ?>" class="button <?php echo $filter_status === 'invoiced' ? 'button-primary' : ''; ?>">Invoiced</a>
            <a href="<?php echo esc_url( add_query_arg( 'status', 'paid', $page_url ) ); ?>" class="button <?php echo $filter_status === 'paid' ? 'button-primary' : ''; ?>">Bezahlt</a>
        </div>

        <?php if ( empty( $commissions ) ) : ?>
            <p>Keine Kommissionen gefunden.</p>
        <?php else : ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Datum</th>
                        <th>Vendor</th>
                        <th>Produkt</th>
                        <th>Typ</th>
                        <th style="text-align:right;">Zahlung</th>
                        <th style="text-align:right;">Kommission</th>
                        <th>Zahlung Status</th>
                        <th>Kommission Status</th>
                        <th>Invoice</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $commissions as $c ) :
                    $vendor = get_userdata( $c->vendor_id );
                    $vendor_name = $vendor ? $vendor->display_name : '#' . $c->vendor_id;
                    $product = ! empty( $c->product_id ) ? get_the_title( $c->product_id ) : '—';
                    $type = $c->context === 'onchain' ? 'Onchain' : 'Lightning';
                    $date = wp_date( 'd.m.Y H:i', strtotime( $c->created_at ) );
                ?>
                    <tr>
                        <td><?php echo esc_html( $date ); ?></td>
                        <td><?php echo esc_html( $vendor_name ); ?></td>
                        <td><?php echo esc_html( $product ); ?></td>
                        <td><?php echo esc_html( $type ); ?></td>
                        <td style="text-align:right;font-family:monospace;"><?php echo number_format( $c->original_amount_sats, 0, ',', '.' ); ?></td>
                        <td style="text-align:right;font-family:monospace;font-weight:bold;"><?php echo number_format( $c->commission_sats, 0, ',', '.' ); ?></td>
                        <td><?php echo $payment_status_badges[ $c->payment_status ] ?? esc_html( $c->payment_status ?? '—' ); ?></td>
                        <td><?php echo $status_badges[ $c->status ] ?? esc_html( $c->status ); ?></td>
                        <td>
                            <?php if ( ! empty( $c->invoice_payment_request ) ) : ?>
                                <code title="<?php echo esc_attr( $c->invoice_payment_request ); ?>" style="font-size:10px;cursor:pointer;max-width:120px;display:inline-block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                    <?php echo esc_html( substr( $c->invoice_payment_request, 0, 20 ) . '...' ); ?>
                                </code>
                            <?php elseif ( $c->status === 'pending' ) : ?>
                                <em style="color:#9ca3af;">Kein LNDHub</em>
                            <?php else : ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
