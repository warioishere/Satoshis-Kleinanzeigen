<?php
/**
 * Dashboard template: Lightning Käufe/Verkäufe
 *
 * Follows the same wrapper/card structure as Gesuche, Merkliste & Rezensionen.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! is_user_logged_in() ) {
    echo '<p>Bitte <a href="/mein-konto/">einloggen</a>, um Käufe & Verkäufe zu sehen.</p>';
    return;
}

wp_enqueue_script(
    'sk-payments-dashboard-transactions',
    SK_PAYMENTS_ASSETS . '/js/dashboard-transactions.js',
    [ 'jquery', 'sk-lightning-pay' ],
    SK_PAYMENTS_VERSION,
    true
);

global $wpdb;

$user_id   = get_current_user_id();
$table     = $wpdb->prefix . 'sk_lightning_payments';
$tab       = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'sales';
$filter    = isset( $_GET['filter'] ) ? sanitize_text_field( wp_unslash( $_GET['filter'] ) ) : 'all';
$base_url  = sk_get_navigation_url( 'lightning-transactions' );
$is_vendor = sk_is_user_seller( $user_id );

if ( ! $is_vendor ) {
    $tab = 'purchases';
}

// Build query.
$where = [];
$args  = [];

if ( $tab === 'sales' ) {
    $where[] = 'vendor_id = %d';
    $args[]  = $user_id;
} else {
    $where[] = 'buyer_id = %d';
    $args[]  = $user_id;
}

if ( $filter !== 'all' ) {
    $where[] = 'status = %s';
    $args[]  = $filter;
}

$where_sql = implode( ' AND ', $where );
$query     = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT 50";
$payments  = $wpdb->get_results( $wpdb->prepare( $query, ...$args ) );

$status_labels = [
    'pending'   => [ '🟡', 'Ausstehend' ],
    'confirmed' => [ '🟢', 'Bezahlt' ],
    'delivered' => [ '⚡', 'Erhalten' ],
    'expired'   => [ '⚪', 'Abgelaufen' ],
    'disputed'  => [ '🔴', 'Problem' ],
];

do_action( 'sk_dashboard_wrap_start' );
?>

<div class="sk-dashboard-wrap">
    <?php do_action( 'sk_dashboard_content_before' ); ?>

    <div class="sk-dashboard-content sk-dashboard-content--lightning">
        <?php do_action( 'sk_dashboard_content_inside_before' ); ?>

        <div class="sk-review-page-header">
            <h2><i class="fas fa-bolt" style="color:#F7931A;"></i> Käufe &amp; Verkäufe</h2>
        </div>

        <div class="skl-dashboard-wrapper">
            <div class="skl-dashboard-inner">

                <?php /* ── Tab-Auswahl (Verkäufe / Käufe) ── */ ?>
                <?php if ( $is_vendor ) : ?>
                <div class="sk-review-status-filter">
                    <a href="<?php echo esc_url( add_query_arg( [ 'tab' => 'sales', 'filter' => 'all' ], $base_url ) ); ?>"
                       class="sk-review-filter-tab <?php echo $tab === 'sales' ? 'active' : ''; ?>">
                        <i class="fas fa-arrow-down"></i> Verkäufe
                    </a>
                    <a href="<?php echo esc_url( add_query_arg( [ 'tab' => 'purchases', 'filter' => 'all' ], $base_url ) ); ?>"
                       class="sk-review-filter-tab <?php echo $tab === 'purchases' ? 'active' : ''; ?>">
                        <i class="fas fa-arrow-up"></i> Käufe
                    </a>
                    <?php if ( class_exists( 'SK\Modules\Payments\Commission\Generator' ) && \SK\Modules\Payments\Commission\Generator::is_enabled() ) :
                        $com_unpaid = 0;
                        $com_tbl = $wpdb->prefix . 'sk_commissions';
                        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $com_tbl ) ) ) {
                            $com_unpaid = (int) $wpdb->get_var( $wpdb->prepare(
                                "SELECT COUNT(*) FROM {$com_tbl} WHERE vendor_id = %d AND status IN ('pending', 'invoiced')",
                                $user_id
                            ) );
                        }
                    ?>
                    <a href="<?php echo esc_url( add_query_arg( [ 'tab' => 'commissions', 'filter' => 'all' ], $base_url ) ); ?>"
                       class="sk-review-filter-tab <?php echo $tab === 'commissions' ? 'active' : ''; ?>">
                        <i class="fas fa-file-invoice"></i> Kommissionen
                        <?php if ( $com_unpaid > 0 ) : ?>
                            <span style="display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;padding:0 5px;margin-left:4px;background:#dc2626;color:#fff;border-radius:9px;font-size:11px;font-weight:700;line-height:1;"><?php echo esc_html( $com_unpaid ); ?></span>
                        <?php endif; ?>
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php /* ── Status-Filter (nicht bei Kommissionen) ── */ ?>
                <?php if ( $tab !== 'commissions' ) : ?>
                <div class="sk-review-status-filter">
                    <?php
                    $filters = [
                        'all'       => [ 'Alle', 'fas fa-list' ],
                        'pending'   => [ 'Ausstehend', 'fas fa-clock' ],
                        'confirmed' => [ 'Bezahlt', 'fas fa-check-circle' ],
                        'disputed'  => [ 'Problem', 'fas fa-exclamation-triangle' ],
                    ];
                    foreach ( $filters as $key => $meta ) :
                        $url    = add_query_arg( [ 'tab' => $tab, 'filter' => $key ], $base_url );
                        $active = $filter === $key;
                    ?>
                        <a href="<?php echo esc_url( $url ); ?>"
                           class="sk-review-filter-tab <?php echo $active ? 'active' : ''; ?>">
                            <i class="<?php echo esc_attr( $meta[1] ); ?>"></i>
                            <?php echo esc_html( $meta[0] ); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php /* ── Kommissionen Tab ── */ ?>
                <?php if ( $tab === 'commissions' ) :
                    $com_table = $wpdb->prefix . 'sk_commissions';
                    $com_table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $com_table ) );
                    $vendor_commissions = [];
                    if ( $com_table_exists ) {
                        $vendor_commissions = $wpdb->get_results( $wpdb->prepare(
                            "SELECT c.*, p.product_id, p.context
                             FROM {$com_table} c
                             LEFT JOIN {$table} p ON p.payment_hash = c.payment_hash
                             WHERE c.vendor_id = %d
                             ORDER BY c.created_at DESC LIMIT 50",
                            $user_id
                        ) );
                    }
                    $com_status_labels = [
                        'pending'  => [ '🟡', 'Offen' ],
                        'invoiced' => [ '🔵', 'Invoice erstellt' ],
                        'paid'     => [ '🟢', 'Bezahlt' ],
                        'waived'   => [ '⚪', 'Erlassen' ],
                    ];
                ?>
                    <?php if ( empty( $vendor_commissions ) ) : ?>
                        <div class="sk-reviews-empty">
                            <i class="fas fa-file-invoice"></i>
                            <p>Keine Kommissionen vorhanden.</p>
                        </div>
                    <?php else : ?>
                        <ul class="sk-reviews-list">
                        <?php foreach ( $vendor_commissions as $c ) :
                            $product    = ! empty( $c->product_id ) ? get_the_title( $c->product_id ) : '—';
                            $product_url = ! empty( $c->product_id ) ? get_permalink( $c->product_id ) : '';
                            $com_status = $com_status_labels[ $c->status ] ?? [ '⚪', $c->status ];
                            $com_sats   = number_format( $c->commission_sats, 0, ',', '.' );
                            $orig_sats  = number_format( $c->original_amount_sats, 0, ',', '.' );
                            $com_date   = wp_date( 'd.m.Y H:i', strtotime( $c->created_at ) );
                        ?>
                            <li class="sk-review-card">
                                <div class="sk-review-card__body">
                                    <div class="sk-review-card__header">
                                        <div class="sk-review-card__author-info">
                                            <span class="sk-review-card__name"><?php echo esc_html( $com_date ); ?></span>
                                            <?php if ( $product_url ) : ?>
                                                <a href="<?php echo esc_url( $product_url ); ?>" class="sk-review-card__email" target="_blank" rel="noopener">
                                                    <?php echo esc_html( $product ); ?>
                                                </a>
                                            <?php else : ?>
                                                <span class="sk-review-card__email"><?php echo esc_html( $product ); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="sk-review-card__content">
                                        <span style="font-size:13px;color:#5a6a7e;">Verkauf: <?php echo esc_html( $orig_sats ); ?> Sats</span>
                                        <span style="margin-left:8px;font-size:13px;color:#5a6a7e;"><?php echo esc_html( $c->commission_rate ); ?>%</span>
                                        <span style="margin-left:12px;font-weight:700;color:#F7931A;font-size:17px;"><?php echo esc_html( $com_sats ); ?> Sats</span>
                                        <span style="margin-left:10px;"><?php echo $com_status[0]; ?> <?php echo esc_html( $com_status[1] ); ?></span>
                                    </div>

                                    <?php if ( $c->status === 'invoiced' && ! empty( $c->invoice_payment_request ) ) : ?>
                                    <div class="sk-review-card__footer">
                                        <div style="margin-top:8px;background:#0f1923;border:1px solid rgba(255,255,255,0.08);border-radius:6px;padding:10px;word-break:break-all;font-family:monospace;font-size:11px;color:#8a9bb0;max-height:60px;overflow:hidden;">
                                            <?php echo esc_html( $c->invoice_payment_request ); ?>
                                        </div>
                                        <div style="margin-top:6px;display:flex;gap:8px;">
                                            <button class="skl-copy-btn" data-copy="<?php echo esc_attr( $c->invoice_payment_request ); ?>" style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08);padding:6px 10px;border-radius:5px;cursor:pointer;font-size:12px;color:#e8ecf0;">
                                                <i class="fas fa-copy"></i> Invoice kopieren
                                            </button>
                                            <a href="lightning:<?php echo esc_attr( $c->invoice_payment_request ); ?>" style="padding:6px 10px;background:#f7931a;border-radius:5px;color:#fff;font-size:12px;text-decoration:none;">
                                                <i class="fas fa-bolt"></i> In Wallet öffnen
                                            </a>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <?php if ( $c->status === 'paid' && $c->paid_at ) : ?>
                                    <div class="sk-review-card__footer">
                                        <span style="font-size:12px;color:#5cb85c;">Bezahlt am <?php echo esc_html( wp_date( 'd.m.Y H:i', strtotime( $c->paid_at ) ) ); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                <?php else : ?>

                <?php /* ── Transaktions-Liste ── */ ?>
                <?php if ( empty( $payments ) ) : ?>
                    <div class="sk-reviews-empty">
                        <i class="fas fa-bolt"></i>
                        <p>Keine Transaktionen gefunden.</p>
                    </div>
                <?php else : ?>
                    <ul class="sk-reviews-list">
                    <?php foreach ( $payments as $p ) :
                        $other_id    = $tab === 'sales' ? $p->buyer_id : $p->vendor_id;
                        $other_user  = get_userdata( $other_id );
                        $other_store = sk_get_store_info( $other_id );
                        $other_name  = ! empty( $other_store['store_name'] ) ? $other_store['store_name'] : ( $other_user ? $other_user->display_name : '#' . $other_id );
                        $avatar_url = $other_user ? get_avatar_url( $other_id, [ 'size' => 46 ] ) : '';
                        $product    = $p->product_id ? get_the_title( $p->product_id ) : '—';
                        $product_url = $p->product_id ? get_permalink( $p->product_id ) : '';
                        $status     = $status_labels[ $p->status ] ?? [ '⚪', $p->status ];
                        $sats       = number_format( $p->amount_sats, 0, ',', '.' );
                        $date       = wp_date( 'd.m.Y H:i', strtotime( $p->created_at ) );

                        $rep_label = '—';
                        if ( $p->reputation_valid ) {
                            $rep_label = '⚡ Gutgeschrieben';
                        } elseif ( $p->status === 'confirmed' && $p->reputation_at ) {
                            $rep_label = 'ab ' . wp_date( 'd.m.Y', strtotime( $p->reputation_at ) );
                        }

                        $can_confirm_delivery = $tab === 'purchases' && $p->status === 'confirmed';
                        $can_dispute = $tab === 'purchases' && $p->status === 'confirmed';
                    ?>
                        <li class="sk-review-card">
                            <?php if ( $avatar_url ) : ?>
                            <div class="sk-review-card__avatar">
                                <img src="<?php echo esc_url( $avatar_url ); ?>" alt="" width="46" height="46" style="border-radius:50%;" />
                            </div>
                            <?php endif; ?>

                            <div class="sk-review-card__body">
                                <div class="sk-review-card__header">
                                    <div class="sk-review-card__author-info">
                                        <span class="sk-review-card__name"><?php echo esc_html( $other_name ); ?></span>
                                        <?php if ( $product_url ) : ?>
                                            <a href="<?php echo esc_url( $product_url ); ?>" class="sk-review-card__email" target="_blank" rel="noopener">
                                                <?php echo esc_html( $product ); ?>
                                            </a>
                                        <?php else : ?>
                                            <span class="sk-review-card__email"><?php echo esc_html( $product ); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="sk-review-card__meta">
                                        <span class="sk-review-card__date"><?php echo esc_html( $date ); ?></span>
                                    </div>
                                </div>

                                <div class="sk-review-card__content">
                                    <?php $is_onchain = $p->context === 'onchain'; ?>
                                    <span style="font-size:11px;padding:2px 6px;border-radius:3px;margin-right:6px;<?php echo $is_onchain ? 'background:rgba(92,184,92,0.1);color:#5cb85c;' : 'background:rgba(92,184,92,0.1);color:#5cb85c;'; ?>">
                                        <?php echo $is_onchain ? '<i class="fab fa-bitcoin"></i> Onchain' : '<i class="fas fa-bolt"></i> Lightning'; ?>
                                    </span>
                                    <span class="skl-sats-amount" data-sats="<?php echo esc_attr( $p->amount_sats ); ?>" style="font-weight:700;color:#F7931A;font-size:17px;"><?php echo esc_html( $sats ); ?> Sats</span>
                                    <span style="margin-left:10px;"><?php echo $status[0]; ?> <?php echo esc_html( $status[1] ); ?></span>
                                    <?php if ( $rep_label !== '—' ) : ?>
                                        <span style="margin-left:10px;font-size:13px;color:#5a6a7e;"><?php echo esc_html( $rep_label ); ?></span>
                                    <?php endif; ?>
                                    <?php if ( $is_onchain && ! empty( $p->preimage ) && strlen( $p->preimage ) === 64 ) : ?>
                                        <a href="https://mempool.space/tx/<?php echo esc_attr( $p->preimage ); ?>" target="_blank" rel="noopener" style="margin-left:8px;font-size:11px;color:#f7931a;"><i class="fas fa-external-link-alt"></i> TX</a>
                                    <?php endif; ?>
                                </div>

                                <div class="sk-review-card__footer">
                                    <?php if ( $p->chat_id ) :
                                        $chat_url = add_query_arg( 'chat_id', $p->chat_id, sk_get_navigation_url( 'vendor-chat' ) );
                                    ?>
                                        <a href="<?php echo esc_url( $chat_url ); ?>" class="sk-review-card__view-link">
                                            <i class="fas fa-comments"></i> Chat öffnen
                                        </a>
                                    <?php else : ?>
                                        <span></span>
                                    <?php endif; ?>

                                    <div class="sk-review-card__actions">
                                        <?php if ( $tab === 'sales' && $p->status === 'pending' ) : ?>
                                            <a href="#" class="sk-review-action approve skl-vendor-confirm-dashboard"
                                               data-payment-hash="<?php echo esc_attr( $p->payment_hash ); ?>">
                                                <i class="fas fa-check"></i> Zahlung bestätigen
                                            </a>
                                        <?php endif; ?>
                                        <?php if ( $can_confirm_delivery ) : ?>
                                            <a href="#" class="sk-review-action approve skl-confirm-delivery-btn"
                                               data-payment-hash="<?php echo esc_attr( $p->payment_hash ); ?>">
                                                <i class="fas fa-box-open"></i> Produkt erhalten
                                            </a>
                                        <?php endif; ?>
                                        <?php if ( $can_dispute ) : ?>
                                            <a href="#" class="sk-review-action spam skl-report-problem-btn"
                                               data-payment-hash="<?php echo esc_attr( $p->payment_hash ); ?>">
                                                <i class="fas fa-flag"></i> Problem melden
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php endif; /* end commissions/transactions tab switch */ ?>

            </div>
        </div>

        <?php do_action( 'sk_dashboard_content_inside_after' ); ?>
    </div>

    <?php do_action( 'sk_dashboard_content_after' ); ?>
</div>

<?php do_action( 'sk_dashboard_wrap_end' ); ?>
