<?php
/**
 * SK Become A Vendor Section Template.
 *
 *
 */
?>

<p>&nbsp;</p>

<ul class="sk-account-migration-lists">
    <li>
        <div class="sk-w8 left-content">
            <p><strong><?php esc_html_e( 'Become a Vendor', 'sk-core' ); ?></strong></p>
            <p><?php esc_html_e( 'Vendors can sell products and manage a store with a vendor dashboard.', 'sk-core' ); ?></p>
        </div>
        <div class="sk-w4 right-content">
            <a href="<?php echo esc_url( sk_get_page_url( 'myaccount', 'woocommerce', 'account-migration' ) ); ?>" class="btn btn-primary"><?php esc_html_e( 'Become a Vendor', 'sk-core' ); ?></a>
        </div>
        <div class="sk-clearfix"></div>
    </li>

    <?php do_action( 'sk_customer_account_migration_list' ); ?>
</ul>
