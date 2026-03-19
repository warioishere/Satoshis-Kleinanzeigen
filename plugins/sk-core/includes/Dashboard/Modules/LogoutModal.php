<?php

namespace SK\Core\Dashboard\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Logout Modal — replaces the ugly wp-login.php?action=logout confirmation page
 * with a dark-themed modal in the vendor dashboard.
 */
class LogoutModal {

	public function __construct() {
		add_action( 'wp_footer', [ $this, 'render_modal' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	public function enqueue_assets(): void {
		if ( ! is_user_logged_in() ) {
			return;
		}

		wp_enqueue_style( 'sk-logout-modal', plugins_url( 'assets/css/sk-logout-modal.css', SK_CORE_FILE ), [], SK_CORE_VERSION );
	}

	public function render_modal(): void {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$logout_url = wp_logout_url( home_url() );
		?>
		<div id="sk-logout-modal" class="sk-logout-modal" style="display:none;">
			<div class="sk-logout-modal-backdrop"></div>
			<div class="sk-logout-modal-box">
				<div class="sk-logout-modal-icon"><i class="fas fa-sign-out-alt"></i></div>
				<h3><?php esc_html_e( 'Abmelden', 'sk-core' ); ?></h3>
				<p><?php esc_html_e( 'Bist du sicher, dass du dich abmelden möchtest?', 'sk-core' ); ?></p>
				<div class="sk-logout-modal-actions">
					<button type="button" class="sk-logout-cancel"><?php esc_html_e( 'Abbrechen', 'sk-core' ); ?></button>
					<a href="<?php echo esc_url( $logout_url ); ?>" class="sk-logout-confirm"><?php esc_html_e( 'Ja, abmelden', 'sk-core' ); ?></a>
				</div>
			</div>
		</div>
		<script>
		(function(){
			var modal = document.getElementById('sk-logout-modal');
			if (!modal) return;

			// Open modal on logout trigger click
			document.addEventListener('click', function(e) {
				var trigger = e.target.closest('.sk-logout-trigger');
				if (trigger) {
					e.preventDefault();
					modal.style.display = 'flex';
				}
			});

			// Close on cancel
			modal.querySelector('.sk-logout-cancel').addEventListener('click', function() {
				modal.style.display = 'none';
			});

			// Close on backdrop click
			modal.querySelector('.sk-logout-modal-backdrop').addEventListener('click', function() {
				modal.style.display = 'none';
			});

			// Close on Escape
			document.addEventListener('keydown', function(e) {
				if (e.key === 'Escape' && modal.style.display === 'flex') {
					modal.style.display = 'none';
				}
			});
		})();
		</script>
		<?php
	}
}
