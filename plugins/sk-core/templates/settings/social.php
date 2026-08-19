<?php
/**
 * SK Settings — Social Profile Form.
 *
 * Available vars:
 *   @var int    $current_user
 *   @var array  $profile_info
 *   @var array  $social_fields  [key => ['title' => …, 'icon' => fa-icon-name]]
 */

defined( 'ABSPATH' ) || exit;

do_action( 'sk_profile_settings_before_form', $current_user, $profile_info );
?>

<form method="post" id="profile-form" action="" class="sk-settings-form">
	<?php wp_nonce_field( 'sk_profile_settings_nonce' ); ?>

	<?php foreach ( $social_fields as $key => $field ) :
		$icon = $field['icon'] ?? '';
		sk_form_input( [
			'type'    => 'url',
			'name'    => "settings[social][{$key}]",
			'id'      => "settings[social][{$key}]",
			'value'   => $profile_info['social'][ $key ] ?? '',
			'label'   => $field['title'] ?? '',
			'placeholder' => 'https://',
			'prefix'  => '<span class="sk-input-group-addon"><i class="fab fa-' . esc_attr( $icon ) . '"></i></span>',
		] );
	endforeach; ?>

	<?php do_action( 'sk_profile_settings_form_bottom', $current_user, $profile_info ); ?>

	<div class="sk-form-group">
		<div class="sk-w4 ajax_prev sk-text-left" style="margin-left:24%;">
			<input type="submit" name="sk_update_profile_settings" class="sk-btn sk-btn-danger sk-btn-theme" value="<?php esc_attr_e( 'Update Settings', 'sk-core' ); ?>">
		</div>
	</div>
</form>

<?php do_action( 'sk_profile_settings_after_form', $current_user, $profile_info ); ?>
