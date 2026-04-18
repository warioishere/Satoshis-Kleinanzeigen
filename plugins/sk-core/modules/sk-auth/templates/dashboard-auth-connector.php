<?php
/**
 * Auth Connector Dashboard Template — shows linked login methods
 * (LNURL / Nostr / BTC) and optional Nostr profile sync controls.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

do_action( 'sk_dashboard_wrap_start' );
?>

<div class="sk-dashboard-wrap">
    <?php do_action( 'sk_dashboard_content_before' ); ?>

    <div class="sk-dashboard-content sk-auth-connector-content">
        <?php do_action( 'sk_dashboard_content_inside_before' ); ?>

        <?php
        $account_linker = new UAC_Account_Linker();
        $sk_dashboard   = new SK_Auth_Dashboard( $account_linker );
        $sk_dashboard->render_auth_page();
        ?>
    </div>

    <?php do_action( 'sk_dashboard_content_after' ); ?>
</div>

<?php do_action( 'sk_dashboard_wrap_end' ); ?>
