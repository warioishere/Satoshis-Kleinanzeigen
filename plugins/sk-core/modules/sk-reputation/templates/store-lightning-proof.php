<?php
/**
 * Template: ⚡ LN Reputation — öffentliche Proof-Seite auf /store/vendorx/lightning-proof/
 *
 * Zeigt alle verifizierten Lightning-Zahlungen mit Payment-Hashes und bolt11 Invoices.
 * Jeder kann die Invoices dekodieren und Betrag + Ziel prüfen.
 * Don't trust, verify.
 */

$custom_store_url = sk_get_option( 'custom_store_url', 'sk_general', 'store' );
$store_name       = get_query_var( $custom_store_url );
$store_user       = get_user_by( 'slug', $store_name );

if ( ! $store_user ) {
    return;
}

$vendor_id   = $store_user->ID;
$vendor      = sk()->vendor->get( $vendor_id );
$vendor_info = $vendor->get_shop_info();
$map_location = $vendor->get_location();
$store_info  = sk_get_store_info( $vendor_id );
$layout      = get_theme_mod( 'store_layout', 'left' );

$rep    = \SK\Modules\Payments\StoreSettings::get_reputation( $vendor_id );
$proofs = \SK\Modules\Reputation\ProofPage::get_proofs( $vendor_id );
$total  = count( $proofs );

wp_enqueue_script(
    'sk-reputation-lightning-proof',
    SK_REPUTATION_URL . '/assets/js/lightning-proof.js',
    [],
    SK_REPUTATION_VERSION,
    true
);

get_header( 'shop' );
?>

<?php do_action( 'woocommerce_before_main_content' ); ?>

<div class="sk-store-wrap layout-<?php echo esc_attr( $layout ); ?>">
    <?php if ( 'left' === $layout ) { ?>
        <?php
        sk_get_template_part( 'store', 'sidebar', [
            'store_user'   => $store_user,
            'store_info'   => $store_info,
            'map_location' => $map_location,
        ] );
        ?>
    <?php } ?>

    <div id="primary" class="content-area sk-single-store">
        <div id="sk-content" class="site-content store-review-wrap woocommerce" role="main">

            <?php sk_get_template_part( 'store-header' ); ?>

            <div id="store-lightning-proof-wrapper" style="padding:20px 0;">

                <!-- Zusammenfassung -->
                <div style="background:#1e2b3c;border:1px solid rgba(255,255,255,0.07);border-radius:10px;padding:24px;margin-bottom:24px;">
                    <h2 style="margin:0 0 16px;font-size:20px;color:#e8ecf0;">
                        ⚡ Lightning Reputation
                        <?php if ( $rep && $rep->badge_label ) : ?>
                            <span style="margin-left:10px;padding:3px 10px;background:rgba(247,147,26,0.13);border-radius:5px;font-size:14px;color:#f7931a;">
                                <?php echo esc_html( $rep->badge . ' ' . $rep->badge_label ); ?>
                            </span>
                        <?php endif; ?>
                    </h2>

                    <?php if ( $rep ) : ?>
                        <div style="display:flex;gap:24px;flex-wrap:wrap;margin-bottom:16px;">
                            <div>
                                <div style="font-size:28px;font-weight:700;color:#f7931a;"><?php echo esc_html( $rep->valid_transactions ); ?></div>
                                <div style="font-size:13px;color:#5a6a7e;">Verifizierte Käufer</div>
                            </div>
                            <div>
                                <div style="font-size:28px;font-weight:700;color:#e8ecf0;"><?php echo esc_html( number_format( $rep->valid_volume_sats, 0, ',', '.' ) ); ?></div>
                                <div style="font-size:13px;color:#5a6a7e;">Sats Volumen</div>
                            </div>
                        </div>
                    <?php else : ?>
                        <p style="color:#5a6a7e;">Noch keine verifizierten Transaktionen.</p>
                    <?php endif; ?>

                    <div style="padding:12px 16px;background:rgba(247,147,26,0.08);border:1px solid rgba(247,147,26,0.2);border-radius:8px;font-size:13px;color:#9ca3af;">
                        <strong style="color:#f7931a;">Don't trust, verify.</strong>
                        Jede Transaktion enthält die original bolt11 Invoice.
                        Kopiere sie und dekodiere sie auf
                        <a href="https://lightningdecoder.com/" target="_blank" rel="noopener" style="color:#f7931a;">lightningdecoder.com</a>
                        — dort siehst du den Betrag, die Ziel-Adresse und den Payment-Hash.
                    </div>
                </div>

                <!-- Transaktions-Liste -->
                <?php if ( ! empty( $proofs ) ) : ?>
                <div style="background:#1e2b3c;border:1px solid rgba(255,255,255,0.07);border-radius:10px;overflow:hidden;">
                    <div style="padding:16px 20px;border-bottom:1px solid rgba(255,255,255,0.07);">
                        <h3 style="margin:0;font-size:16px;color:#e8ecf0;"><?php echo esc_html( $total ); ?> verifizierte Transaktionen</h3>
                    </div>

                    <?php foreach ( $proofs as $i => $p ) :
                        $sats       = number_format( $p->amount_sats, 0, ',', '.' );
                        $date       = wp_date( 'd.m.Y H:i', strtotime( $p->confirmed_at ) );
                        $product    = $p->product_id ? get_the_title( $p->product_id ) : '';
                        $hash_short = substr( $p->payment_hash, 0, 12 ) . '…' . substr( $p->payment_hash, -8 );
                        $is_onchain = strpos( $p->payment_request, 'bitcoin:' ) === 0;
                    ?>
                        <div class="skl-proof-entry" style="padding:16px 20px;border-bottom:1px solid rgba(255,255,255,0.04);<?php echo $i % 2 === 0 ? '' : 'background:rgba(255,255,255,0.02);'; ?>">
                            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:8px;">
                                <div>
                                    <span style="font-size:10px;padding:2px 6px;border-radius:3px;margin-right:6px;<?php echo 'background:rgba(92,184,92,0.1);color:#5cb85c;'; ?>">
                                        <?php echo $is_onchain ? 'Onchain' : 'Lightning'; ?>
                                    </span>
                                    <span style="font-weight:700;color:#f7931a;font-size:16px;"><?php echo esc_html( $sats ); ?> Sats</span>
                                </div>
                                <span style="font-size:13px;color:#5a6a7e;"><?php echo esc_html( $date ); ?></span>
                            </div>

                            <?php if ( $product ) : ?>
                                <div style="font-size:13px;color:#8a9bb0;margin-bottom:6px;">
                                    Produkt: <?php echo esc_html( $product ); ?>
                                </div>
                            <?php endif; ?>

                            <?php if ( $is_onchain ) : ?>
                                <?php
                                // Onchain: payment_request = "bitcoin:addr?amount=...", preimage = txid
                                $oc_addr = str_replace( 'bitcoin:', '', explode( '?', $p->payment_request )[0] );
                                ?>
                                <div style="font-size:12px;margin-bottom:6px;">
                                    <span style="color:#5a6a7e;">Adresse:</span>
                                    <code style="background:#0f1923;padding:2px 6px;border-radius:3px;color:#8a9bb0;font-size:11px;"><?php echo esc_html( $oc_addr ); ?></code>
                                    <a href="https://mempool.space/address/<?php echo esc_attr( $oc_addr ); ?>" target="_blank" rel="noopener" style="margin-left:4px;color:#f7931a;font-size:11px;text-decoration:none;">
                                        <i class="fas fa-external-link-alt"></i>
                                    </a>
                                </div>
                                <?php
                                // preimage stores txid for onchain payments
                                $txid = $p->payment_hash; // fallback
                                global $wpdb;
                                $full_payment = $wpdb->get_row( $wpdb->prepare(
                                    "SELECT preimage FROM {$wpdb->prefix}sk_lightning_payments WHERE payment_hash = %s",
                                    $p->payment_hash
                                ) );
                                if ( $full_payment && ! empty( $full_payment->preimage ) && strlen( $full_payment->preimage ) === 64 ) {
                                    $txid = $full_payment->preimage;
                                }
                                ?>
                                <div style="font-size:12px;">
                                    <span style="color:#5a6a7e;">TX:</span>
                                    <a href="https://mempool.space/tx/<?php echo esc_attr( $txid ); ?>" target="_blank" rel="noopener" style="color:#f7931a;font-size:11px;text-decoration:none;">
                                        <?php echo esc_html( substr( $txid, 0, 12 ) . '…' . substr( $txid, -8 ) ); ?>
                                        <i class="fas fa-external-link-alt"></i>
                                    </a>
                                </div>

                            <?php else : ?>

                                <div style="font-size:12px;margin-bottom:6px;">
                                    <span style="color:#5a6a7e;">Payment-Hash:</span>
                                    <code style="background:#0f1923;padding:2px 6px;border-radius:3px;color:#8a9bb0;font-size:11px;word-break:break-all;cursor:pointer;" title="Klicken zum Kopieren" data-copy="<?php echo esc_attr( $p->payment_hash ); ?>">
                                        <?php echo esc_html( $hash_short ); ?>
                                    </code>
                                    <button class="skl-copy-btn" data-copy="<?php echo esc_attr( $p->payment_hash ); ?>" style="background:none;border:none;color:#5a6a7e;cursor:pointer;font-size:11px;padding:0 4px;" title="Kopieren">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>

                                <div style="font-size:12px;">
                                    <span style="color:#5a6a7e;">bolt11:</span>
                                    <code class="skl-bolt11-proof" style="background:#0f1923;padding:2px 6px;border-radius:3px;color:#8a9bb0;font-size:10px;word-break:break-all;display:block;margin-top:4px;max-height:40px;overflow:hidden;cursor:pointer;" title="Klicken zum Aufklappen" data-expanded="false">
                                        <?php echo esc_html( $p->payment_request ); ?>
                                    </code>
                                    <div style="margin-top:4px;display:flex;gap:8px;">
                                        <button class="skl-copy-btn" data-copy="<?php echo esc_attr( $p->payment_request ); ?>" style="background:none;border:none;color:#5a6a7e;cursor:pointer;font-size:11px;padding:0;">
                                            <i class="fas fa-copy"></i> Kopieren
                                        </button>
                                        <a href="https://lightningdecoder.com/?invoice=<?php echo esc_attr( $p->payment_request ); ?>" target="_blank" rel="noopener" style="color:#f7931a;font-size:11px;text-decoration:none;">
                                            <i class="fas fa-external-link-alt"></i> Dekodieren
                                        </a>
                                    </div>
                                </div>

                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Anleitung -->
                <div style="margin-top:24px;padding:20px;background:#1e2b3c;border:1px solid rgba(255,255,255,0.07);border-radius:10px;">
                    <h3 style="margin:0 0 12px;font-size:16px;color:#e8ecf0;"><i class="fas fa-info-circle" style="color:#5a6a7e;"></i> So verifizierst du selbst</h3>

                    <div style="font-size:13px;color:#9ca3af;line-height:1.7;">
                        <p style="margin:0 0 12px;"><strong style="color:#e8ecf0;">1. bolt11 Invoice dekodieren</strong><br>
                        Klicke bei einer Transaktion auf «Dekodieren» oder kopiere die bolt11 Invoice und füge sie auf
                        <a href="https://lightningdecoder.com/" target="_blank" rel="noopener" style="color:#f7931a;">lightningdecoder.com</a> ein.
                        Dort siehst du den Betrag, die Ziel-Node und den Payment-Hash — alles in der Invoice kodiert.</p>

                        <p style="margin:0 0 12px;"><strong style="color:#e8ecf0;">2. Was du prüfen kannst</strong></p>
                        <ul style="margin:0 0 12px;padding-left:20px;">
                            <li>Stimmt der <strong>Betrag</strong> mit dem angezeigten überein?</li>
                            <li>Geht die Invoice an die <strong>Lightning-Adresse</strong> des Anbieters?</li>
                            <li>Stimmt der <strong>Payment-Hash</strong> überein?</li>
                        </ul>

                        <p style="margin:0;"><strong style="color:#e8ecf0;">3. JSON-API</strong><br>
                        Alle Daten auch maschinenlesbar:
                        <a href="<?php echo esc_url( rest_url( 'sk/v1/lightning/proof/' . $vendor_id ) ); ?>" target="_blank" rel="noopener" style="color:#f7931a;">
                            /wp-json/sk/v1/lightning/proof/<?php echo esc_html( $vendor_id ); ?>
                        </a></p>
                    </div>
                </div>

            </div><!-- #store-lightning-proof-wrapper -->

        </div><!-- #content .site-content -->
    </div><!-- #primary .content-area -->

    <div class="sk-clearfix"></div>

    <?php if ( 'right' === $layout ) { ?>
        <?php
        sk_get_template_part( 'store', 'sidebar', [
            'store_user'   => $store_user,
            'store_info'   => $store_info,
            'map_location' => $map_location,
        ] );
        ?>
    <?php } ?>

</div><!-- .sk-store-wrap -->

<?php do_action( 'woocommerce_after_main_content' ); ?>


<?php get_footer(); ?>
