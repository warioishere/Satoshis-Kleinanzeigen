<?php
/**
 * Sidebar: Compose new post (for vendors).
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="sk-feed-sidebar-card">
	<h3 class="sk-feed-sidebar-title"><i class="fas fa-pen"></i> <?php esc_html_e( 'Beitrag erstellen', 'sk-core' ); ?></h3>
	<form class="sk-feed-compose-form" id="sk-feed-compose-form" enctype="multipart/form-data">
		<div class="sk-form-group">
			<textarea
				class="sk-form-control"
				name="content"
				id="sk-feed-content"
				rows="3"
				maxlength="2000"
				placeholder="<?php esc_attr_e( 'Was gibt\'s Neues?', 'sk-core' ); ?>"
				required
			></textarea>
			<div class="sk-feed-char-count"><span id="sk-feed-chars">0</span>/2000</div>
		</div>

		<div class="sk-feed-image-preview" id="sk-feed-image-preview" style="display:none;">
			<img id="sk-feed-preview-img" src="" alt="" />
			<button type="button" class="sk-feed-remove-image" id="sk-feed-remove-image"><i class="fas fa-times"></i></button>
		</div>

		<div class="sk-feed-sidebar-compose-actions">
			<label class="sk-feed-upload-btn" for="sk-feed-image-input">
				<i class="fas fa-image"></i>
			</label>
			<input type="file" id="sk-feed-image-input" name="image" accept="image/*" style="display:none;" />
			<button type="submit" class="sk-btn sk-btn-btc" id="sk-feed-submit">
				<i class="fas fa-paper-plane"></i> <?php esc_html_e( 'Posten', 'sk-core' ); ?>
			</button>
		</div>
	</form>
</div>
