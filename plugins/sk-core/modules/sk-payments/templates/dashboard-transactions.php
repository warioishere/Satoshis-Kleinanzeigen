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
                </div>
                <?php endif; ?>

                <?php /* ── Status-Filter ── */ ?>
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

            </div>
        </div>

        <?php do_action( 'sk_dashboard_content_inside_after' ); ?>
    </div>

    <?php do_action( 'sk_dashboard_content_after' ); ?>
</div>

<?php do_action( 'sk_dashboard_wrap_end' ); ?>

<script>
(function() {
    /* Currency detection — same logic as geo-preis.js */
    var lang = (navigator.language || (navigator.languages && navigator.languages[0]) || '').toLowerCase();
    var currency = (lang === 'de-ch' || lang === 'fr-ch' || lang === 'it-ch' || lang === 'rm-ch') ? 'CHF' : 'EUR';

    /* Fetch rate and append fiat equivalent to sats amounts */
    fetch('https://blockchain.info/ticker')
        .then(function(r) { return r.json(); })
        .then(function(prices) {
            var rate = prices && prices[currency] && prices[currency].last;
            if (!rate) return;

            document.querySelectorAll('.skl-sats-amount').forEach(function(el) {
                var sats = parseInt(el.getAttribute('data-sats'), 10);
                if (!isNaN(sats) && sats > 0) {
                    var fiat = (sats * rate / 100000000).toFixed(2);
                    var formatted = Number(fiat).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    el.insertAdjacentHTML('afterend',
                        '<span style="margin-left:8px;font-size:13px;font-weight:400;color:#5a6a7e;">≈ ' + formatted + ' ' + currency + '</span>'
                    );
                }
            });
        })
        .catch(function() { /* ignore */ });

    /* Buyer: confirm product received */
    jQuery(document).on('click', '.skl-confirm-delivery-btn', function(e) {
        e.preventDefault();
        if (!confirm('Hast du das Produkt erhalten?')) return;

        var $btn = jQuery(this);
        $btn.addClass('disabled').css('pointer-events', 'none').html('<i class="fas fa-spinner fa-spin"></i> Wird bestätigt...');

        jQuery.post(skLightning.ajaxurl, {
            action: 'sk_confirm_delivery',
            nonce: skLightning.nonce,
            payment_hash: $btn.data('payment-hash')
        }, function(res) {
            if (res.success) {
                location.reload();
            } else {
                alert(res.data && res.data.message ? res.data.message : 'Fehler.');
                $btn.removeClass('disabled').css('pointer-events', '').html('<i class="fas fa-box-open"></i> Produkt erhalten');
            }
        });
    });

    /* Vendor: confirm payment from dashboard */
    jQuery(document).on('click', '.skl-vendor-confirm-dashboard', function(e) {
        e.preventDefault();
        if (!confirm('Hast du die Zahlung in deiner Wallet erhalten?')) return;

        var $btn = jQuery(this);
        $btn.addClass('disabled').css('pointer-events', 'none').html('<i class="fas fa-spinner fa-spin"></i> Bestätige...');

        jQuery.post(skLightning.ajaxurl, {
            action: 'sk_confirm_payment',
            nonce: skLightning.nonce,
            payment_hash: $btn.data('payment-hash'),
            chat_id: 0
        }, function(res) {
            if (res.success) {
                location.reload();
            } else {
                alert(res.data && res.data.message ? res.data.message : 'Fehler.');
                $btn.removeClass('disabled').css('pointer-events', '').html('<i class="fas fa-check"></i> Zahlung bestätigen');
            }
        });
    });

    /* Report problem handler */
    jQuery(document).on('click', '.skl-report-problem-btn', function(e) {
        e.preventDefault();
        var reason = prompt('Bitte beschreibe das Problem:');
        if (!reason) return;

        var $btn = jQuery(this);
        $btn.addClass('disabled').css('pointer-events', 'none');

        jQuery.post(skLightning.ajaxurl, {
            action: 'sk_report_problem',
            nonce: skLightning.nonce,
            payment_hash: $btn.data('payment-hash'),
            reason: reason
        }, function(res) {
            if (res.success) {
                location.reload();
            } else {
                alert(res.data && res.data.message ? res.data.message : 'Fehler.');
                $btn.removeClass('disabled').css('pointer-events', '');
            }
        });
    });
})();
</script>
