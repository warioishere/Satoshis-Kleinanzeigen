<?php defined( 'ABSPATH' ) || exit; ?>
<div class="sk-buyer-warning" style="background:rgba(220,53,69,0.1);border:1px solid rgba(220,53,69,0.3);border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#dc3545;font-size:14px;">
    <strong>&#9888; <?php esc_html_e( 'Neuer Anbieter', 'sk-core' ); ?></strong>
    — <?php printf(
        esc_html__( 'Bisher %d bestätigte Transaktionen. Vorsicht bei hochpreisigen Artikeln.', 'sk-core' ),
        $valid_tx
    ); ?>
</div>
