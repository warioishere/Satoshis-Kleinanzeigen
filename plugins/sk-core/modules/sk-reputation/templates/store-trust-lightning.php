<?php
/**
 * Part of the trust page: verified Lightning and on-chain payments.
 *
 * Expects $lightning = [ 'rep' => object, 'proofs' => array ] and $vendor_id.
 * Every entry carries the original bolt11 invoice or the transaction, so
 * anyone can decode it and check amount, destination and payment hash.
 */

defined( 'ABSPATH' ) || exit;

$rep    = $lightning['rep'];
$proofs = $lightning['proofs'];
$total  = count( $proofs );

wp_enqueue_script(
    'sk-reputation-lightning-proof',
    SK_REPUTATION_URL . '/assets/js/lightning-proof.js',
    [],
    SK_REPUTATION_VERSION,
    true
);
?>

<div class="sk-trust-card">
    <h3>
        <i class="fas fa-bolt"></i> <?php esc_html_e( 'Belegte Zahlungen', 'sk-core' ); ?>
        <?php if ( ! empty( $rep->badge_label ) ) : ?>
            <span class="sk-trust-badge"><?php echo esc_html( $rep->badge . ' ' . $rep->badge_label ); ?></span>
        <?php endif; ?>
    </h3>

    <div class="sk-trust-figures">
        <div>
            <div class="sk-trust-big"><?php echo esc_html( number_format_i18n( (int) $rep->valid_transactions ) ); ?></div>
            <div class="sk-trust-note"><?php esc_html_e( 'verifizierte Zahlungen', 'sk-core' ); ?></div>
        </div>
        <div>
            <div class="sk-trust-big"><?php echo esc_html( number_format( (int) $rep->valid_volume_sats, 0, '', '.' ) ); ?></div>
            <div class="sk-trust-note"><?php esc_html_e( 'Sats Volumen', 'sk-core' ); ?></div>
        </div>
    </div>

    <p class="sk-trust-note">
        <?php esc_html_e( 'Prüfen: Jede Zahlung enthält die originale bolt11-Invoice oder die Transaktion. Dekodiere die Invoice auf lightningdecoder.com; dort stehen Betrag, Ziel und Payment-Hash. Maschinenlesbar:', 'sk-core' ); ?>
        <a href="<?php echo esc_url( rest_url( 'sk/v1/lightning/proof/' . $vendor_id ) ); ?>" target="_blank" rel="noopener">/wp-json/sk/v1/lightning/proof/<?php echo esc_html( $vendor_id ); ?></a>
    </p>
</div>

<?php if ( ! empty( $proofs ) ) : ?>
<div class="sk-trust-card sk-trust-proofs">
    <h3><?php echo esc_html( sprintf( _n( '%s verifizierte Transaktion', '%s verifizierte Transaktionen', $total, 'sk-core' ), number_format_i18n( $total ) ) ); ?></h3>

    <?php foreach ( $proofs as $p ) :
        $sats       = number_format( (int) $p->amount_sats, 0, '', '.' );
        $date       = wp_date( 'd.m.Y H:i', strtotime( $p->confirmed_at ) );
        $product    = $p->product_id ? get_the_title( $p->product_id ) : '';
        $hash_short = substr( $p->payment_hash, 0, 12 ) . '…' . substr( $p->payment_hash, -8 );
        $is_onchain = 0 === strpos( (string) $p->payment_request, 'bitcoin:' );
    ?>
        <div class="skl-proof-entry sk-trust-proof">
            <div class="sk-trust-proof-head">
                <div>
                    <span class="sk-trust-proof-type"><?php echo $is_onchain ? 'Onchain' : 'Lightning'; ?></span>
                    <span class="sk-trust-proof-sats"><?php echo esc_html( $sats ); ?> Sats</span>
                </div>
                <span class="sk-trust-note"><?php echo esc_html( $date ); ?></span>
            </div>

            <?php if ( $product ) : ?>
                <div class="sk-trust-note"><?php esc_html_e( 'Produkt:', 'sk-core' ); ?> <?php echo esc_html( $product ); ?></div>
            <?php endif; ?>

            <?php if ( $is_onchain ) : ?>
                <?php
                // payment_request = "bitcoin:addr?amount=…"; the preimage column holds the txid.
                $oc_addr = str_replace( 'bitcoin:', '', explode( '?', $p->payment_request )[0] );
                $txid    = $p->payment_hash;

                global $wpdb;
                $full_payment = $wpdb->get_row( $wpdb->prepare(
                    "SELECT preimage FROM {$wpdb->prefix}sk_lightning_payments WHERE payment_hash = %s",
                    $p->payment_hash
                ) );

                if ( $full_payment && ! empty( $full_payment->preimage ) && 64 === strlen( $full_payment->preimage ) ) {
                    $txid = $full_payment->preimage;
                }
                ?>
                <div class="sk-trust-proof-line">
                    <span class="sk-trust-note"><?php esc_html_e( 'Adresse:', 'sk-core' ); ?></span>
                    <code><?php echo esc_html( $oc_addr ); ?></code>
                    <a href="https://mempool.space/address/<?php echo esc_attr( $oc_addr ); ?>" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i></a>
                </div>
                <div class="sk-trust-proof-line">
                    <span class="sk-trust-note">TX:</span>
                    <a href="https://mempool.space/tx/<?php echo esc_attr( $txid ); ?>" target="_blank" rel="noopener"><?php echo esc_html( substr( $txid, 0, 12 ) . '…' . substr( $txid, -8 ) ); ?> <i class="fas fa-external-link-alt"></i></a>
                </div>
            <?php else : ?>
                <div class="sk-trust-proof-line">
                    <span class="sk-trust-note"><?php esc_html_e( 'Payment-Hash:', 'sk-core' ); ?></span>
                    <code title="<?php esc_attr_e( 'Klicken zum Kopieren', 'sk-core' ); ?>" data-copy="<?php echo esc_attr( $p->payment_hash ); ?>"><?php echo esc_html( $hash_short ); ?></code>
                    <button type="button" class="skl-copy-btn" data-copy="<?php echo esc_attr( $p->payment_hash ); ?>" title="<?php esc_attr_e( 'Kopieren', 'sk-core' ); ?>"><i class="fas fa-copy"></i></button>
                </div>
                <div class="sk-trust-proof-line">
                    <span class="sk-trust-note">bolt11:</span>
                    <code class="skl-bolt11-proof" title="<?php esc_attr_e( 'Klicken zum Aufklappen', 'sk-core' ); ?>" data-expanded="false"><?php echo esc_html( $p->payment_request ); ?></code>
                    <div class="sk-trust-proof-actions">
                        <button type="button" class="skl-copy-btn" data-copy="<?php echo esc_attr( $p->payment_request ); ?>"><i class="fas fa-copy"></i> <?php esc_html_e( 'Kopieren', 'sk-core' ); ?></button>
                        <a href="https://lightningdecoder.com/?invoice=<?php echo esc_attr( $p->payment_request ); ?>" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i> <?php esc_html_e( 'Dekodieren', 'sk-core' ); ?></a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
