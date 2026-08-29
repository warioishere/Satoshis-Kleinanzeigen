<?php
/**
 * Dashboard: eigene Adressen bestätigen.
 *
 * Werte kommen aus VerifiedLinksPage::view_data(), das als 'template_args'
 * vor dem Einbinden läuft.
 *
 * @var string  $url       Adresse dieser Seite.
 * @var string  $message   Rückmeldung des letzten Vorgangs.
 * @var array[] $links     Beanspruchte Adressen mit Zustand.
 * @var string  $target    Profilseite, auf die der Verweis zeigen muss.
 * @var string  $snippet   Fertiger <link>-Schnipsel.
 * @var string  $token     Geheimer Textbaustein.
 * @var bool    $verified  Trägt der Nutzer das Abzeichen?
 * @var int     $max_links Höchstzahl der Adressen.
 */

use SK\Core\Dashboard\Modules\VerifiedLinksPage;
use SK\Core\Verification\VerifiedLinks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

do_action( 'sk_dashboard_wrap_start' );
?>

<div class="sk-dashboard-wrap">
	<?php do_action( 'sk_dashboard_content_before' ); ?>

	<div class="sk-dashboard-content sk-verify-dashboard">
		<?php do_action( 'sk_dashboard_content_inside_before' ); ?>

		<div class="sk-section-heading">
			<h2><i class="fas fa-circle-check"></i> <?php esc_html_e( 'Verifizierung', 'sk-core' ); ?></h2>
		</div>

		<?php if ( $message ) : ?>
			<div class="sk-alert sk-alert-info" role="status"><?php echo esc_html( $message ); ?></div>
		<?php endif; ?>

		<div class="sk-section-content">
			<p>
				<?php esc_html_e( 'Zeig, dass eine Seite im Netz wirklich dir gehört: trag sie unten ein und setze dort einen Verweis zurück auf dein Profil. Ist beides da, bekommst du das Abzeichen — und andere sehen, dass du hinter dieser Adresse stehst.', 'sk-core' ); ?>
			</p>

			<?php if ( $verified ) : ?>
				<p class="sk-verify-badge-line">
					<span class="sk-verify-badge"><i class="fas fa-circle-check"></i> <?php esc_html_e( 'verifiziert', 'sk-core' ); ?></span>
					<?php esc_html_e( 'Dein Abzeichen ist aktiv.', 'sk-core' ); ?>
				</p>
			<?php endif; ?>
		</div>

		<div class="sk-section-heading"><h3><?php esc_html_e( 'So geht es', 'sk-core' ); ?></h3></div>
		<div class="sk-section-content">
			<p><strong><?php esc_html_e( 'Auf einer eigenen Website', 'sk-core' ); ?></strong><br>
				<?php esc_html_e( 'Diese Zeile in den <head> deiner Seite setzen — sie ist unsichtbar:', 'sk-core' ); ?></p>
			<pre class="sk-verify-snippet"><code><?php echo esc_html( $snippet ); ?></code></pre>

			<p><strong><?php esc_html_e( 'Auf GitHub, in einem Gist oder sonstwo', 'sk-core' ); ?></strong><br>
				<?php esc_html_e( 'Dort überlebt der Verweis das Rendern oft nicht. Schreib stattdessen diesen Beleg irgendwo auf die Seite — er ist nicht zu erraten und dient nur dem Nachweis:', 'sk-core' ); ?></p>
			<pre class="sk-verify-snippet"><code><?php echo esc_html( $token ); ?></code></pre>

			<p class="sk-verify-hint">
				<?php
				printf(
					/* translators: %s: Adresse der eigenen Profilseite. */
					esc_html__( 'Der Verweis muss auf deine Profilseite zeigen: %s', 'sk-core' ),
					'<code>' . esc_html( $target ) . '</code>'
				);
				?>
			</p>
		</div>

		<div class="sk-section-heading"><h3><?php esc_html_e( 'Deine Adressen', 'sk-core' ); ?></h3></div>
		<div class="sk-section-content">
			<?php if ( empty( $links ) ) : ?>
				<p><?php esc_html_e( 'Noch keine Adresse eingetragen.', 'sk-core' ); ?></p>
			<?php else : ?>
				<table class="sk-verify-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Adresse', 'sk-core' ); ?></th>
							<th><?php esc_html_e( 'Zustand', 'sk-core' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $links as $link ) : ?>
						<tr>
							<td><a href="<?php echo esc_url( $link['url'] ); ?>" target="_blank" rel="noopener nofollow"><?php echo esc_html( $link['host'] ); ?></a></td>
							<td>
								<?php
								if ( $link['status'] === VerifiedLinks::OK ) {
									echo '<span class="sk-verify-ok"><i class="fas fa-circle-check"></i> ' . esc_html__( 'bestätigt', 'sk-core' ) . '</span>';
								} elseif ( $link['status'] === VerifiedLinks::UNREACHABLE ) {
									echo '<span class="sk-verify-warn"><i class="fas fa-plug-circle-xmark"></i> ' . esc_html__( 'nicht erreichbar', 'sk-core' ) . '</span>';
								} else {
									echo '<span class="sk-verify-missing"><i class="fas fa-circle-xmark"></i> ' . esc_html__( 'kein Verweis gefunden', 'sk-core' ) . '</span>';
								}

								if ( ! empty( $link['checked'] ) ) {
									echo '<br><small>' . esc_html( sprintf(
										/* translators: %s: Zeitpunkt der letzten Prüfung. */
										__( 'zuletzt geprüft: %s', 'sk-core' ),
										wp_date( 'd.m.Y H:i', (int) $link['checked'] )
									) ) . '</small>';
								}
								?>
							</td>
							<td>
								<form method="post" action="<?php echo esc_url( $url ); ?>" class="sk-verify-inline">
									<?php wp_nonce_field( VerifiedLinksPage::NONCE, 'sk_verify_nonce' ); ?>
									<input type="hidden" name="sk_verify_url" value="<?php echo esc_attr( $link['url'] ); ?>">
									<button type="submit" name="sk_verify_action" value="check" class="sk-btn"><?php esc_html_e( 'Erneut prüfen', 'sk-core' ); ?></button>
									<button type="submit" name="sk_verify_action" value="remove" class="sk-btn sk-btn-link"><?php esc_html_e( 'Entfernen', 'sk-core' ); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<?php if ( count( $links ) < $max_links ) : ?>
				<form method="post" action="<?php echo esc_url( $url ); ?>">
					<?php wp_nonce_field( VerifiedLinksPage::NONCE, 'sk_verify_nonce' ); ?>
					<div class="sk-form-group sk-clearfix">
						<label class="sk-w3 sk-control-label" for="sk_verify_url"><?php esc_html_e( 'Adresse hinzufügen', 'sk-core' ); ?></label>
						<div class="sk-w9">
							<input class="sk-form-control" type="url" name="sk_verify_url" id="sk_verify_url" placeholder="https://meine-seite.de" required>
						</div>
					</div>
					<div class="sk-form-group">
						<button type="submit" name="sk_verify_action" value="add" class="sk-btn sk-btn-theme"><?php esc_html_e( 'Hinzufügen und prüfen', 'sk-core' ); ?></button>
					</div>
				</form>
			<?php endif; ?>
		</div>

		<?php do_action( 'sk_dashboard_content_inside_after' ); ?>
	</div>

	<?php do_action( 'sk_dashboard_content_after' ); ?>
</div>

<?php do_action( 'sk_dashboard_wrap_end' ); ?>
