<?php
/**
 * SK Settings Payment Template
 *
 */
?>

<?php if ( isset( $status_message ) && ! empty( $status_message ) ) : ?>
    <div class="sk-alert <?php echo ( 'success' === $connect_status ) ? 'sk-alert-success' : 'sk-alert-danger'; ?>">
        <?php echo wp_kses_post( $status_message ); ?>
    </div>
<?php endif; ?>

<a href="<?php echo esc_url_raw( sk_get_navigation_url( 'settings/payment' ) ); ?>">
    &larr; <?php esc_html_e( 'Back', 'sk-core' ); ?>
</a>

<form method="post" id="payment-form" action="" class="sk-form-horizontal">

    <?php wp_nonce_field( 'sk_payment_settings_nonce' ); ?>

    <fieldset class="payment-field-<?php echo esc_attr( $method_key ); ?>">
        <div class="sk-form-group">
            <?php
            if ( 'bank' === $method_key ) :
                call_user_func( $method['callback'], $profile_info );
            else :
                ?>
                <label class="sk-w3 sk-control-label" for="sk_setting"><?php echo esc_html( apply_filters( 'sk_payment_method_title', $method['title'], $method ) ); ?></label>
                <div class="sk-w6">
                    <?php call_user_func( $method['callback'], $profile_info ); ?>
                </div>
            <?php endif; ?>
        </div>
    </fieldset>

    <?php
    /**
     */
    do_action( 'sk_payment_settings_form_bottom', $current_user, $profile_info );

    if ( 'bank' !== $method_key ) :
        ?>
        <div class="sk-form-group">
            <div class="sk-w4 ajax_prev save sk-text-left">
                <input type="submit" name="sk_update_payment_settings" class="sk-btn sk-btn-theme" value="<?php esc_attr_e( 'Update Settings', 'sk-core' ); ?>">
            </div>
        </div>
    <?php endif; ?>
</form>
