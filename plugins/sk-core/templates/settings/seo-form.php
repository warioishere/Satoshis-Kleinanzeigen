<?php
/**
 * SK Dashboard — Store SEO Form.
 *
 * Available vars:
 *   @var object $seo       SEO class instance with ->print_saved_meta($value) method.
 *   @var array  $seo_meta  Saved meta values keyed by form field name.
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="sk-alert sk-hide" id="sk-seo-feedback"></div>

<form method="post" id="sk-store-seo-form" action="" class="sk-settings-form">

	<?php
	sk_form_input( [
		'name'    => 'sk-seo-meta-title',
		'id'      => 'sk-seo-meta-title',
		'value'   => $seo->print_saved_meta( $seo_meta['sk-seo-meta-title'] ),
		'label'   => __( 'SEO Title', 'sk' ),
		'tooltip' => __( 'SEO Title is shown as the title of your store page', 'sk' ),
	] );

	sk_form_textarea( [
		'name'    => 'sk-seo-meta-desc',
		'id'      => 'sk-seo-meta-desc',
		'value'   => $seo->print_saved_meta( $seo_meta['sk-seo-meta-desc'] ),
		'label'   => __( 'Meta Description', 'sk' ),
		'rows'    => 3,
		'tooltip' => __( 'The meta description is often shown as the black text under the title in a search result. For this to work it has to contain the keyword that was searched for and should be less than 156 chars.', 'sk' ),
	] );

	sk_form_input( [
		'name'    => 'sk-seo-meta-keywords',
		'id'      => 'sk-seo-meta-keywords',
		'value'   => $seo->print_saved_meta( $seo_meta['sk-seo-meta-keywords'] ),
		'label'   => __( 'Meta Keywords', 'sk' ),
		'tooltip' => __( 'Insert some comma separated keywords for better ranking of your store page.', 'sk' ),
	] );

	sk_form_input( [
		'name'  => 'sk-seo-og-title',
		'id'    => 'sk-seo-og-title',
		'value' => $seo->print_saved_meta( $seo_meta['sk-seo-og-title'] ),
		'label' => __( 'Facebook Title', 'sk' ),
	] );

	sk_form_textarea( [
		'name'  => 'sk-seo-og-desc',
		'id'    => 'sk-seo-og-desc',
		'value' => $seo->print_saved_meta( $seo_meta['sk-seo-og-desc'] ),
		'label' => __( 'Facebook Description', 'sk' ),
		'rows'  => 3,
	] );

	sk_form_media_upload( [
		'name'          => 'sk-seo-og-image',
		'attachment_id' => (int) ( $seo_meta['sk-seo-og-image'] ?? 0 ),
		'label'         => __( 'Facebook Image', 'sk' ),
		'variant'       => 'gravatar',
		'upload_label'  => __( 'Upload Photo', 'sk' ),
	] );

	sk_form_input( [
		'name'  => 'sk-seo-twitter-title',
		'id'    => 'sk-seo-twitter-title',
		'value' => $seo->print_saved_meta( $seo_meta['sk-seo-twitter-title'] ),
		'label' => __( 'Twitter Title', 'sk' ),
	] );

	sk_form_textarea( [
		'name'  => 'sk-seo-twitter-desc',
		'id'    => 'sk-seo-twitter-desc',
		'value' => $seo->print_saved_meta( $seo_meta['sk-seo-twitter-desc'] ),
		'label' => __( 'Twitter Description', 'sk' ),
		'rows'  => 3,
	] );

	sk_form_media_upload( [
		'name'          => 'sk-seo-twitter-image',
		'attachment_id' => (int) ( $seo_meta['sk-seo-twitter-image'] ?? 0 ),
		'label'         => __( 'Twitter Image', 'sk' ),
		'variant'       => 'gravatar',
		'upload_label'  => __( 'Upload Photo', 'sk' ),
	] );
	?>

	<?php wp_nonce_field( 'sk_store_seo_form_action', 'sk_store_seo_form_nonce' ); ?>

	<div class="sk-form-group" style="margin-left: 23%">
		<input type="submit" id="sk-store-seo-form-submit" class="sk-left sk-btn sk-btn-theme" value="<?php esc_attr_e( 'Save Changes', 'sk' ); ?>">
	</div>
</form>
