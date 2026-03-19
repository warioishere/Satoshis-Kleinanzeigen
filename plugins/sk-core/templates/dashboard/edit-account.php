<?php
/**
 *  SK Dashboard Template
 *
 *  SK Main Dashboard template for Front-end
 *
 *
 */

$user = get_user_by( 'id', get_current_user_id() );
?>

<?php do_action( 'sk_dashboard_wrap_start' ); ?>

<div class="sk-dashboard-wrap">

    <?php
    /**
     *  Added sk_dashboard_content_before hook
     *
     * @hooked get_dashboard_side_navigation
     *
     */
    do_action( 'sk_dashboard_content_before' );
    ?>

    <div class="sk-dashboard-content">

        <?php
        /**
         *  Added sk_dashboard_content_before hook
         *
         * @hooked show_seller_dashboard_notice
         *
         */
        do_action( 'sk_dashboard_content_inside_before' );
        ?>

        <article class="dashboard-content-area woocommerce edit-account-wrap">

            <?php wc_print_notices(); ?>

            <h1 class="entry-title"><?php esc_html_e( 'Edit Account Details', 'sk-core' ); ?></h1>

            <form class="edit-account" action="" method="post">

                <?php do_action( 'woocommerce_edit_account_form_start' ); ?>

                <p class="form-row form-row-first">
                    <label for="account_first_name"><?php esc_html_e( 'First name', 'sk-core' ); ?> <span class="required">*</span></label>
                    <input type="text" class="input-text" name="account_first_name" id="account_first_name" value="<?php echo esc_attr( $user->first_name ); ?>"/>
                </p>

                <p class="form-row form-row-last">
                    <label for="account_last_name"><?php esc_html_e( 'Last name', 'sk-core' ); ?> <span class="required">*</span></label>
                    <input type="text" class="input-text" name="account_last_name" id="account_last_name" value="<?php echo esc_attr( $user->last_name ); ?>"/>
                </p>
                <div class="clear"></div>

                <p class="form-row form-row-wide">
                    <label for="account_email"><?php esc_html_e( 'Email address', 'sk-core' ); ?> <span class="required">*</span></label>
                    <input type="email" class="input-text" name="account_email" id="account_email" value="<?php echo esc_attr( $user->user_email ); ?>"/>
                </p>

                <fieldset>
                    <legend><?php esc_html_e( 'Password Change', 'sk-core' ); ?></legend>

                    <p class="form-row form-row-wide">
                        <label for="password_current"><?php esc_html_e( 'Current Password (leave blank to leave unchanged)', 'sk-core' ); ?></label>
                        <input type="password" class="input-text" name="password_current" id="password_current"/>
                    </p>

                    <p class="form-row form-row-wide">
                        <label for="password_1"><?php esc_html_e( 'New Password (leave blank to leave unchanged)', 'sk-core' ); ?></label>
                        <input type="password" class="input-text" name="password_1" id="password_1"/>
                    </p>

                    <p class="form-row form-row-wide">
                        <label for="password_2"><?php esc_html_e( 'Confirm New Password', 'sk-core' ); ?></label>
                        <input type="password" class="input-text" name="password_2" id="password_2"/>
                    </p>
                </fieldset>

                <div class="clear"></div>

                <?php do_action( 'woocommerce_edit_account_form' ); ?>

                <p>
                    <?php wp_nonce_field( 'sk_save_account_details' ); ?>
                    <input type="submit" class="sk-btn sk-btn-theme" name="sk_save_account_details" value="<?php esc_attr_e( 'Save changes', 'sk-core' ); ?>"/>
                    <input type="hidden" name="action" value="sk_save_account_details"/>
                </p>

                <?php do_action( 'woocommerce_edit_account_form_end' ); ?>

            </form>

        </article><!-- .dashboard-content-area -->

        <?php

        /**
         *  Added sk_dashboard_content_inside_after hook
         *
         */
        do_action( 'sk_dashboard_content_inside_after' );
        ?>


    </div><!-- .sk-dashboard-content -->

    <?php

    /**
     *  Added sk_dashboard_content_after hook
     *
     */
    do_action( 'sk_dashboard_content_after' );
    ?>

</div><!-- .sk-dashboard-wrap -->

<?php do_action( 'sk_dashboard_wrap_end' ); ?>
