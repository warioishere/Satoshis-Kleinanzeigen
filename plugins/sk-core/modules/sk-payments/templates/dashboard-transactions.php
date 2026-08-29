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

/*
 * Suche ueber Artikel, Gegenueber, Referenz und Sendungsnummer.
 *
 * Artikel und Namen stehen nicht in dieser Tabelle, deshalb werden die
 * passenden Kennnummern vorher gesammelt und als IN-Liste angehaengt. Bei
 * einem Katalog mit ein paar hundert Artikeln ist das billiger als ein JOIN
 * ueber posts und users bei jedem Seitenaufruf.
 */
$skp_search = isset( $_GET['suche'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['suche'] ) ) ) : '';
$skp_can_search = \SK\Modules\Payments\Notify::is_shop_pack( $user_id );

if ( $skp_search !== '' && $skp_can_search ) {
    $like = '%' . $wpdb->esc_like( $skp_search ) . '%';

    $post_ids = $wpdb->get_col(
        $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_title LIKE %s", $like )
    );

    $user_ids = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT ID FROM {$wpdb->users} WHERE display_name LIKE %s OR user_login LIKE %s",
            $like,
            $like
        )
    );

    $partner = $tab === 'sales' ? 'buyer_id' : 'vendor_id';
    $parts   = [ 'payment_hash LIKE %s', 'metadata LIKE %s' ];
    $extra   = [ $wpdb->esc_like( $skp_search ) . '%', $like ];

    if ( $post_ids ) {
        $parts[] = 'product_id IN (' . implode( ',', array_map( 'absint', $post_ids ) ) . ')';
    }
    if ( $user_ids ) {
        $parts[] = $partner . ' IN (' . implode( ',', array_map( 'absint', $user_ids ) ) . ')';
    }

    $where[] = '(' . implode( ' OR ', $parts ) . ')';
    $args    = array_merge( $args, $extra );
}

$where_sql = implode( ' AND ', $where );

// Blaettern statt stiller Abschneidung: die Liste endete bisher nach 50
// Eintraegen, ohne das irgendwo zu sagen.
$skp_per_page = 25;
$skp_page     = max( 1, isset( $_GET['seite'] ) ? (int) $_GET['seite'] : 1 );
$skp_total    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", ...$args ) );
$skp_pages    = max( 1, (int) ceil( $skp_total / $skp_per_page ) );
$skp_page     = min( $skp_page, $skp_pages );

$query    = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
$payments = $wpdb->get_results(
    $wpdb->prepare( $query, ...array_merge( $args, [ $skp_per_page, ( $skp_page - 1 ) * $skp_per_page ] ) )
);

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

                <?php
                /*
                 * Umsatzauswertung — nur im Shoptarif. Der Fiat-Betrag kommt
                 * aus dem Kurs bei der Zahlung, nicht aus dem heutigen; nur so
                 * taugt die Zahl fuer eine Steuererklaerung.
                 */
                $skp_shop = \SK\Modules\Payments\Notify::is_shop_pack( $user_id );

                if ( $skp_shop ) :
                    $skp_role  = $tab === 'purchases' ? 'purchases' : 'sales';
                    $skp_years = \SK\Modules\Payments\Revenue::years( $user_id, $skp_role );
                    $skp_year  = isset( $_GET['jahr'] ) ? (int) $_GET['jahr'] : ( $skp_years[0] ?? 0 );
                    $skp_month = \SK\Modules\Payments\Revenue::months( $user_id, $skp_role, $skp_year ?: null );
                    ?>
                    <div class="skp-revenue">
                        <div class="skp-revenue__head">
                            <h3>
                                <i class="fas fa-chart-column"></i>
                                <?php echo $skp_role === 'purchases' ? 'Ausgaben' : 'Umsatz'; ?>
                            </h3>

                            <div class="skp-revenue__actions">
                                <?php if ( count( $skp_years ) > 1 ) : ?>
                                    <select class="skp-revenue__year" onchange="window.location = this.value;">
                                        <?php foreach ( $skp_years as $y ) : ?>
                                            <option value="<?php echo esc_url( add_query_arg( [ 'tab' => $tab, 'jahr' => $y ], $base_url ) ); ?>" <?php selected( $y, $skp_year ); ?>>
                                                <?php echo esc_html( $y ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>

                                <?php
                                // Der Export gehoert erst ab dem Hai-Paket
                                // dazu; ohne diese Pruefung stuende hier ein
                                // Knopf, der mit 403 endet.
                                if ( class_exists( \SK\Modules\ShopImport\Variants::class )
                                    && \SK\Modules\ShopImport\Variants::revenue_allowed() ) :
                                ?>
                                <a class="sk-btn sk-btn-theme sk-btn-sm"
                                   href="<?php echo esc_url( \SK\Modules\Payments\RevenueExport::url( $skp_role, $skp_year ?: null ) ); ?>">
                                    <i class="fas fa-file-csv"></i> Als CSV
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ( empty( $skp_month ) ) : ?>
                            <p class="skp-revenue__empty">Für <?php echo esc_html( $skp_year ?: 'diesen Zeitraum' ); ?> ist noch nichts verbucht.</p>
                        <?php else : ?>
                            <?php
                            $skp_sum_sats = array_sum( array_column( $skp_month, 'sats' ) );
                            $skp_sum_fiat = array_sum( array_column( $skp_month, 'fiat' ) );
                            $skp_missing  = array_sum( array_column( $skp_month, 'ohne_kurs' ) );
                            ?>
                            <table class="skp-revenue__table">
                                <thead>
                                    <tr>
                                        <th>Monat</th>
                                        <th class="skp-revenue__num">Vorgänge</th>
                                        <th class="skp-revenue__num">Sats</th>
                                        <th class="skp-revenue__num">EUR</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $skp_month as $m ) : ?>
                                        <tr>
                                            <td><?php echo esc_html( wp_date( 'F Y', strtotime( $m['monat'] . '-01' ) ) ); ?></td>
                                            <td class="skp-revenue__num"><?php echo (int) $m['anzahl']; ?></td>
                                            <td class="skp-revenue__num"><?php echo esc_html( number_format( $m['sats'], 0, ',', "'" ) ); ?></td>
                                            <td class="skp-revenue__num"><?php echo esc_html( number_format( $m['fiat'], 2, ',', "'" ) ); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th><?php echo esc_html( $skp_year ?: 'Gesamt' ); ?></th>
                                        <th class="skp-revenue__num"><?php echo (int) array_sum( array_column( $skp_month, 'anzahl' ) ); ?></th>
                                        <th class="skp-revenue__num"><?php echo esc_html( number_format( $skp_sum_sats, 0, ',', "'" ) ); ?></th>
                                        <th class="skp-revenue__num"><?php echo esc_html( number_format( $skp_sum_fiat, 2, ',', "'" ) ); ?></th>
                                    </tr>
                                </tfoot>
                            </table>

                            <p class="skp-revenue__note">
                                Der EUR-Betrag ist zum Kurs im Moment der Zahlung gerechnet, nicht zum heutigen.
                                Gezählt wird ab dem Tag der Zahlungsbestätigung.
                                <?php if ( $skp_missing > 0 ) : ?>
                                    <br><strong><?php echo (int) $skp_missing; ?></strong> Vorgänge ohne erfassten Kurs sind in der EUR-Summe nicht enthalten.
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ( $skp_can_search ) : ?>
                    <form class="skp-search" method="get" action="<?php echo esc_url( $base_url ); ?>">
                        <input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">
                        <input type="hidden" name="filter" value="<?php echo esc_attr( $filter ); ?>">
                        <input type="search" name="suche" value="<?php echo esc_attr( $skp_search ); ?>"
                               placeholder="Artikel, Name, Sendungsnummer oder Referenz">
                        <button type="submit" class="sk-btn sk-btn-theme sk-btn-sm"><i class="fas fa-magnifying-glass"></i> Suchen</button>
                        <?php if ( $skp_search !== '' ) : ?>
                            <a class="skp-search__reset" href="<?php echo esc_url( add_query_arg( [ 'tab' => $tab, 'filter' => $filter ], $base_url ) ); ?>">zurücksetzen</a>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>

                <?php /* ── Transaktions-Liste ── */ ?>
                <?php if ( empty( $payments ) ) : ?>
                    <div class="sk-reviews-empty">
                        <i class="fas fa-bolt"></i>
                        <p>
                            <?php echo $skp_search !== ''
                                ? 'Nichts gefunden für „' . esc_html( $skp_search ) . '".'
                                : 'Keine Transaktionen gefunden.'; ?>
                        </p>
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

                        // Ausfuehrung und Lieferangabe stehen an der Zahlung —
                        // der Anbieter soll dafuer nicht in den Chat muessen.
                        $details  = \SK\Modules\Payments\ProductPage::order_details( $p->metadata ?? null );
                        $skp_ship = \SK\Modules\Payments\Shipping::get( $p );

                        // Einmal bestimmt, damit Knopf in der Fusszeile und
                        // Formular darunter nicht auseinanderlaufen koennen.
                        $skp_show_ship_form = $tab === 'sales'
                            && $skp_shop
                            && ! $skp_ship
                            && in_array( $p->status, [ 'confirmed', 'delivered' ], true );

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

                                <?php if ( $details['variant'] !== '' || $details['delivery_note'] !== '' ) : ?>
                                    <div class="skp-order-details">
                                        <?php if ( $details['variant'] !== '' ) : ?>
                                            <div class="skp-order-details__row">
                                                <span class="skp-order-details__label"><i class="fas fa-layer-group"></i> Ausführung</span>
                                                <span><?php echo esc_html( $details['variant'] ); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ( $details['delivery_note'] !== '' ) : ?>
                                            <div class="skp-order-details__row">
                                                <span class="skp-order-details__label"><i class="fas fa-truck"></i> Lieferung</span>
                                                <span class="skp-order-details__note"><?php echo nl2br( esc_html( $details['delivery_note'] ) ); ?></span>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ( $skp_ship ) : ?>
                                            <div class="skp-order-details__row">
                                                <span class="skp-order-details__label"><i class="fas fa-box"></i> Versendet</span>
                                                <span>
                                                    <?php echo esc_html( $skp_ship['label'] ); ?><?php
                                                    if ( $skp_ship['number'] !== '' ) {
                                                        echo ' · <span style="font-family:monospace;">' . esc_html( $skp_ship['number'] ) . '</span>';
                                                    }
                                                    ?>
                                                    <?php if ( $skp_ship['url'] !== '' ) : ?>
                                                        · <a href="<?php echo esc_url( $skp_ship['url'] ); ?>" target="_blank" rel="noopener" style="color:#f7931a;">Sendung verfolgen</a>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>


                                <div class="sk-review-card__footer">
                                    <div class="skp-card-buttons">
                                        <?php if ( $p->chat_id ) :
                                            $chat_url = add_query_arg( 'chat_id', $p->chat_id, sk_get_navigation_url( 'vendor-chat' ) );
                                        ?>
                                            <a href="<?php echo esc_url( $chat_url ); ?>" class="skp-card-btn">
                                                <i class="fas fa-comments"></i> Chat öffnen
                                            </a>
                                        <?php endif; ?>

                                        <?php if ( $skp_show_ship_form ) : ?>
                                            <button type="button" class="skp-card-btn skp-ship-toggle"
                                                    data-hash="<?php echo esc_attr( $p->payment_hash ); ?>">
                                                <i class="fas fa-box"></i> Versand eintragen
                                            </button>
                                        <?php endif; ?>
                                    </div>

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

                                <?php
                                /*
                                 * Versandangabe eintragen — nur der Anbieter, nur im Shoptarif
                                 * und nur wenn bezahlt wurde. Vorher waere es verfrueht.
                                 */
                                if ( $skp_show_ship_form ) :
                                    ?>
                                    <div class="skp-ship-form"
                                         data-hash="<?php echo esc_attr( $p->payment_hash ); ?>"
                                         data-nonce="<?php echo esc_attr( wp_create_nonce( \SK\Modules\Payments\Shipping::ACTION ) ); ?>"
                                         data-ajaxurl="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
                                        <div class="skp-ship-form__body" hidden>
                                            <select class="skp-ship-form__carrier">
                                                <?php foreach ( \SK\Modules\Payments\Shipping::carriers() as $key => $carrier ) : ?>
                                                    <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $carrier['label'] ); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <input type="text" class="skp-ship-form__number" placeholder="Sendungsnummer">
                                            <input type="url" class="skp-ship-form__url" placeholder="Link zur Sendungsverfolgung" style="display:none;">
                                            <button type="button" class="sk-btn sk-btn-theme sk-btn-sm skp-ship-form__save">Speichern</button>
                                        </div>
                                        <p class="skp-ship-form__msg" style="display:none;"></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                    </ul>

                    <?php if ( $skp_pages > 1 ) : ?>
                        <div class="skp-pager">
                            <?php
                            $skp_link = static function ( int $page ) use ( $base_url, $tab, $filter, $skp_search ) {
                                return add_query_arg(
                                    array_filter(
                                        [
                                            'tab'    => $tab,
                                            'filter' => $filter,
                                            'suche'  => $skp_search,
                                            'seite'  => $page > 1 ? $page : null,
                                        ]
                                    ),
                                    $base_url
                                );
                            };
                            ?>
                            <?php if ( $skp_page > 1 ) : ?>
                                <a href="<?php echo esc_url( $skp_link( $skp_page - 1 ) ); ?>">← Zurück</a>
                            <?php else : ?>
                                <span></span>
                            <?php endif; ?>

                            <span class="skp-pager__state">
                                Seite <?php echo (int) $skp_page; ?> von <?php echo (int) $skp_pages; ?>
                                · <?php echo (int) $skp_total; ?> Vorgänge
                            </span>

                            <?php if ( $skp_page < $skp_pages ) : ?>
                                <a href="<?php echo esc_url( $skp_link( $skp_page + 1 ) ); ?>">Weiter →</a>
                            <?php else : ?>
                                <span></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php endif; /* end commissions/transactions tab switch */ ?>

            </div>
        </div>

        <?php do_action( 'sk_dashboard_content_inside_after' ); ?>
    </div>

    <?php do_action( 'sk_dashboard_content_after' ); ?>
</div>

<?php do_action( 'sk_dashboard_wrap_end' ); ?>
