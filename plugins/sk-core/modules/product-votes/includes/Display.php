<?php

namespace SK\Modules\ProductVotes;

defined( 'ABSPATH' ) || exit;

final class Display {

	public function __construct() {
		// Sits between summary and tabs (tabs render at prio 10).
		add_action( 'woocommerce_after_single_product_summary', [ $this, 'render_widget' ], 5 );
		add_action( 'wp_enqueue_scripts',                       [ $this, 'enqueue' ] );
	}

	public function enqueue(): void {
		if ( ! is_product() ) {
			return;
		}
		wp_enqueue_style(
			'sk-product-votes',
			SK_PV_URL . '/assets/css/vote.css',
			[],
			SK_PV_VERSION
		);
		wp_enqueue_script(
			'sk-product-votes',
			SK_PV_URL . '/assets/js/vote.js',
			[ 'jquery' ],
			SK_PV_VERSION,
			true
		);
		wp_localize_script(
			'sk-product-votes',
			'skProductVotes',
			[
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'sk_product_vote' ),
				'i18n'    => [
					'login_required'   => __( 'Bitte einloggen um zu bewerten.', 'sk-core' ),
					'not_qualified'    => __( 'Dein Account erfüllt die Anforderungen noch nicht (≥30 Tage alt + Profilbild ODER Nostr-npub ODER ≥3 aktive Tage in 14 Tagen).', 'sk-core' ),
					'cannot_vote_own'  => __( 'Eigene Inserate kannst du nicht bewerten.', 'sk-core' ),
					'error'            => __( 'Fehler beim Abstimmen.', 'sk-core' ),
				],
			]
		);
	}

	public function render_widget(): void {
		global $product;
		if ( ! $product ) {
			return;
		}
		$product_id = $product->get_id();
		$user_id    = get_current_user_id();
		$counts     = Voting::get_counts( $product_id );
		$show_nums  = Voting::should_show_counts( $product_id );
		$user_vote  = $user_id ? Voting::get_user_vote( $product_id, $user_id ) : 0;
		$is_author  = $user_id && (int) get_post_field( 'post_author', $product_id ) === $user_id;
		$reason     = '';
		if ( $is_author ) {
			$reason = __( 'Eigene Inserate kannst du nicht bewerten.', 'sk-core' );
		} else {
			$reason = Voting::disqualification_reason( $user_id );
		}
		$disabled    = ( '' !== $reason );
		$title_attr  = $disabled ? ' title="' . esc_attr( $reason ) . '"' : '';
		?>
		<div class="sk-pv-widget" data-product-id="<?php echo esc_attr( $product_id ); ?>"<?php echo $title_attr; ?>>
			<div class="sk-pv-buttons">
				<button type="button" class="sk-pv-btn sk-pv-hot<?php echo $user_vote === 1 ? ' sk-pv-active' : ''; ?>"
					data-value="1"<?php disabled( $disabled ); ?><?php echo $title_attr; ?>>
					<span class="sk-pv-icon">🔥</span>
					<span class="sk-pv-label"><?php esc_html_e( 'Heiß', 'sk-core' ); ?></span>
					<?php if ( $show_nums ) : ?>
						<span class="sk-pv-count" data-role="hot"><?php echo esc_html( $counts['hot'] ); ?></span>
					<?php endif; ?>
				</button>
				<button type="button" class="sk-pv-btn sk-pv-cold<?php echo $user_vote === -1 ? ' sk-pv-active' : ''; ?>"
					data-value="-1"<?php disabled( $disabled ); ?><?php echo $title_attr; ?>>
					<span class="sk-pv-icon">❄️</span>
					<span class="sk-pv-label"><?php esc_html_e( 'Kalt', 'sk-core' ); ?></span>
					<?php if ( $show_nums ) : ?>
						<span class="sk-pv-count" data-role="cold"><?php echo esc_html( $counts['cold'] ); ?></span>
					<?php endif; ?>
				</button>
			</div>
			<?php if ( ! $show_nums ) : ?>
				<p class="sk-pv-meta sk-pv-too-few"><?php esc_html_e( 'Noch zu wenig Bewertungen.', 'sk-core' ); ?></p>
			<?php endif; ?>
			<?php if ( $disabled ) : ?>
				<p class="sk-pv-meta sk-pv-reason"<?php echo $title_attr; ?>><?php echo esc_html( $reason ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}
}
