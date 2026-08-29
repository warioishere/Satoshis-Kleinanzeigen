<?php

namespace SK\Core\Dashboard\Modules;

use SK\Core\Dashboard\DashboardModule;
use SK\Core\Verification\VerifiedLinks;

/**
 * Dashboard: eigene Adressen bestätigen.
 *
 * Bewusst eine eigene Seite für alle Verkäufer und nicht ein Abschnitt im
 * Shop-Import: die Bestätigung ist eine Aussage über das Konto, kein Schritt
 * eines Katalogimports. Wer kein Händler ist, will trotzdem sein Abzeichen —
 * und wer einer werden will, muss die Seite erreichen können, bevor er
 * importieren darf.
 */
class VerifiedLinksPage extends DashboardModule {

	const NONCE = 'sk_verified_links';

	public function config(): ?array {
		return [
			'slug'          => 'verifizierung',
			'title'         => __( 'Verifizierung', 'sk-core' ),
			'icon'          => '<i class="fas fa-circle-check"></i>',
			'pos'           => 92,
			'permission'    => 'sk_view_overview_menu',
			'template'      => 'dashboard/verifizierung/dashboard-verifizierung',
			'template_args' => [ $this, 'view_data' ],
		];
	}

	protected function register_extras(): void {
		add_action( 'template_redirect', [ $this, 'handle_post' ] );
	}

	private function url(): string {
		return function_exists( 'sk_get_navigation_url' )
			? sk_get_navigation_url( 'verifizierung' )
			: home_url( '/dashboard/verifizierung/' );
	}

	public function handle_post(): void {
		if ( strtoupper( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) !== 'POST' ) {
			return;
		}

		if ( ! isset( $_POST['sk_verify_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['sk_verify_nonce'] ), self::NONCE ) ) {
			return;
		}

		$user_id = get_current_user_id();

		if ( ! $user_id || ! function_exists( 'sk_is_user_seller' ) || ! sk_is_user_seller( $user_id ) ) {
			return;
		}

		$action = sanitize_key( wp_unslash( $_POST['sk_verify_action'] ?? '' ) );
		$url    = isset( $_POST['sk_verify_url'] ) ? esc_url_raw( trim( wp_unslash( $_POST['sk_verify_url'] ) ) ) : '';

		if ( $action === 'add' ) {
			$ok = VerifiedLinks::add( $user_id, $url );

			if ( is_wp_error( $ok ) ) {
				$this->notice( $user_id, $ok->get_error_message() );
			} else {
				// Gleich mitprüfen: wer den Schnipsel schon gesetzt hat, ist
				// mit einem Klick fertig statt mit zweien.
				$this->notice( $user_id, $this->message( VerifiedLinks::check( $user_id, $url ) ) );
			}
		} elseif ( $action === 'check' && $url !== '' ) {
			$this->notice( $user_id, $this->message( VerifiedLinks::check( $user_id, $url ) ) );
		} elseif ( $action === 'remove' && $url !== '' ) {
			VerifiedLinks::remove( $user_id, $url );
			$this->notice( $user_id, __( 'Adresse entfernt.', 'sk-core' ) );
		}

		wp_safe_redirect( $this->url() );
		exit;
	}

	private function message( int $ergebnis ): string {
		switch ( $ergebnis ) {
			case VerifiedLinks::OK:
				return __( 'Geschafft — die Adresse ist bestätigt.', 'sk-core' );

			case VerifiedLinks::UNREACHABLE:
				return __( 'Die Seite liess sich von hier aus nicht abrufen. Das sagt nichts über deinen Verweis — versuch es später noch einmal oder melde dich bei uns.', 'sk-core' );

			default:
				return __( 'Wir haben den Verweis dort nicht gefunden. Prüfe, ob der Schnipsel im <head> steht oder der Beleg auf der Seite auftaucht, und probier es dann erneut.', 'sk-core' );
		}
	}

	private function notice( int $user_id, string $text ): void {
		if ( $text !== '' ) {
			set_transient( 'sk_verify_msg_' . $user_id, $text, 120 );
		}
	}

	public function view_data( $query_vars = [] ): array {
		$user_id = get_current_user_id();

		$message = get_transient( 'sk_verify_msg_' . $user_id );

		if ( $message ) {
			delete_transient( 'sk_verify_msg_' . $user_id );
		}

		return [
			'url'        => $this->url(),
			'message'    => $message,
			'links'      => VerifiedLinks::all( $user_id ),
			'target'     => VerifiedLinks::target_url( $user_id ),
			'snippet'    => VerifiedLinks::snippet( $user_id ),
			'token'      => VerifiedLinks::token( $user_id ),
			'verified'   => VerifiedLinks::is_verified( $user_id ),
			'max_links'  => VerifiedLinks::MAX_LINKS,
		];
	}
}
