<?php
/**
 */
do_action( 'sk_profile_settings_before_form', $current_user, $profile_info ); ?>

<form method="post" id="profile-form"  action="" class="sk-form-horizontal"><?php ///settings-form ?>

    <?php wp_nonce_field( 'sk_profile_settings_nonce' ); ?>

    <?php foreach( $social_fields as $key => $field ) { ?>
        <div class="sk-form-group">
            <label class="sk-w3 sk-control-label"><?php echo $field['title']; ?></label>

            <div class="sk-w5">
                <div class="sk-form-group">
                    <div class="sk-input-group">
                        <span class="sk-input-group-addon"><i class="fab fa-<?php echo isset( $field['icon'] ) ? $field['icon'] : ''; ?>"></i></span>
                        <input id="settings[social][<?php echo $key; ?>]" value="<?php echo isset( $profile_info['social'][ $key ] ) ? esc_url( $profile_info['social'][ $key ] ) : ''; ?>" name="settings[social][<?php echo $key; ?>]" class="sk-form-control" placeholder="http://" type="url">
                    </div>
                </div>
            </div>
        </div>
    <?php } ?>

    <?php
    /**
     */
    do_action( 'sk_profile_settings_form_bottom', $current_user, $profile_info ); ?>

    <div class="sk-form-group">
        <div class="sk-w4 ajax_prev sk-text-left" style="margin-left:24%;">
            <input type="submit" name="sk_update_profile_settings" class="sk-btn sk-btn-danger sk-btn-theme" value="<?php esc_attr_e( 'Update Settings', 'sk' ); ?>">
        </div>
    </div>

</form>

<?php
/**
 */
do_action( 'sk_profile_settings_after_form', $current_user, $profile_info ); ?>
<!--settings updated content end-->
