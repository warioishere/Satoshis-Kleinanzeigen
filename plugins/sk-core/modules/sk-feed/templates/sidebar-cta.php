<?php
/**
 * Sidebar: CTA for non-vendors / non-logged-in users.
 */

defined( 'ABSPATH' ) || exit;

$is_logged_in = is_user_logged_in();
?>

<?php if ( ! $is_logged_in ) : ?>
	<div class="sk-feed-sidebar-card sk-feed-cta-card">
		<div class="sk-feed-cta-icon"><i class="fas fa-bolt"></i></div>
		<h3 class="sk-feed-cta-title"><?php esc_html_e( 'Willkommen!', 'sk-core' ); ?></h3>
		<p class="sk-feed-cta-text"><?php esc_html_e( 'Melde dich an um zu liken, kommentieren und Stores zu folgen.', 'sk-core' ); ?></p>
		<a href="<?php echo esc_url( home_url( '/mein-konto/' ) ); ?>" class="sk-feed-cta-btn">
			<i class="fas fa-sign-in-alt"></i> <?php esc_html_e( 'Anmelden', 'sk-core' ); ?>
		</a>
	</div>
<?php else : ?>
	<div class="sk-feed-sidebar-card sk-feed-cta-card">
		<div class="sk-feed-cta-icon"><i class="fas fa-store"></i></div>
		<h3 class="sk-feed-cta-title"><?php esc_html_e( 'Werde Verkäufer', 'sk-core' ); ?></h3>
		<p class="sk-feed-cta-text"><?php esc_html_e( 'Erstelle deinen eigenen Store, poste Inserate und werde Teil der Community.', 'sk-core' ); ?></p>
		<a href="<?php echo esc_url( home_url( '/mein-konto/' ) ); ?>" class="sk-feed-cta-btn">
			<i class="fas fa-rocket"></i> <?php esc_html_e( 'Jetzt starten', 'sk-core' ); ?>
		</a>
	</div>
<?php endif; ?>
