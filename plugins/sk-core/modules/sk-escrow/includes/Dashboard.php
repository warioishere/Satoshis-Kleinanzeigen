<?php

namespace SK\Modules\Escrow;

use SK\Core\Dashboard\DashboardRegistry;
use SK\Modules\Payments\QrImage;

defined( 'ABSPATH' ) || exit;

/**
 * The escrow's two faces in the seller dashboard: a "Treuhand" tab under
 * Einstellungen (offer escrow, payout address, refund address) and the
 * escrow part of a card in "Käufe/Verkäufe", rendered by sk-payments'
 * dashboard-transactions.php for rows with context "escrow".
 *
 * Deliberately separate from the shop's Lightning/onchain wallet settings:
 * those addresses receive direct payments, the ones here are where escrow
 * payouts and refunds go.
 */
final class Dashboard {

    const ENABLED_META = 'weo_escrow_enabled';
    const PAYOUT_META  = 'weo_payout_address';

    public function __construct() {
        DashboardRegistry::register_config( [
            'slug'       => 'settings-treuhand',
            'parent'     => 'settings',
            'url_key'    => 'treuhand',
            'title'      => __( 'Treuhand', 'sk-core' ),
            'icon'       => '<i class="fas fa-handshake"></i>',
            'pos'        => 95,
            'permission' => 'sk_view_store_settings_menu',
            'heading'    => __( 'Treuhand', 'sk-core' ),
            'helper'     => __( 'Beim Kauf über Treuhand liegt das Geld auf einer 2-von-3-Multisig-Adresse (Käufer, Verkäufer, Marktplatz). Die Schlüssel entstehen pro Handel im Browser; hier legst du nur fest, ob du Treuhand anbietest und wohin Auszahlungen und Erstattungen gehen.', 'sk-core' ),
            'template'   => [ $this, 'render_settings' ],
        ] );

        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
    }

    public function enqueue(): void {
        if ( ! is_user_logged_in() || ! function_exists( 'sk_is_seller_dashboard' ) || ! sk_is_seller_dashboard() ) {
            return;
        }

        global $wp;
        $vars = is_object( $wp ) ? (array) $wp->query_vars : [];
        if ( isset( $vars['lightning-transactions'] ) || ( $vars['settings'] ?? '' ) === 'treuhand' ) {
            weo_enqueue_signer();
        }
    }

    // ---- settings tab ----

    public function render_settings( $query_vars = [] ): void {
        $user_id = get_current_user_id();

        if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_POST['weo_settings_nonce'] ) ) {
            $this->save_settings( $user_id );
        }

        $enabled = get_user_meta( $user_id, self::ENABLED_META, true ) === '1';
        $payout  = (string) get_user_meta( $user_id, self::PAYOUT_META, true );
        $refund  = (string) get_user_meta( $user_id, Purchase::REFUND_META, true );
        $global  = weo_enabled();
        ?>
        <form method="post" class="sk-settings-form weo-settings">
            <?php wp_nonce_field( 'weo_settings', 'weo_settings_nonce' ); ?>

            <?php if ( ! $global ) : ?>
                <div class="sk-alert sk-alert-warning"><p><?php esc_html_e( 'Der Marktplatz hat die Treuhand derzeit nicht freigeschaltet. Deine Einstellungen werden gespeichert und gelten, sobald sie aktiv ist.', 'sk-core' ); ?></p></div>
            <?php endif; ?>

            <div class="sk-form-group">
                <label class="sk-form-label" for="weo_escrow_enabled">
                    <input type="checkbox" name="weo_escrow_enabled" id="weo_escrow_enabled" value="1" <?php checked( $enabled ); ?>>
                    <?php esc_html_e( 'Treuhand für meine Inserate anbieten', 'sk-core' ); ?>
                </label>
                <p class="help-block"><?php esc_html_e( 'Käufer sehen dann im Sofortkauf die Zahlart „Treuhand“. Jede Anfrage musst du unter „Verkäufe“ annehmen, dabei entsteht dein Schlüssel für diesen Handel.', 'sk-core' ); ?></p>
            </div>

            <div class="sk-form-group">
                <label class="sk-form-label" for="weo_payout_address"><?php esc_html_e( 'Auszahlungsadresse als Verkäufer (bc1…)', 'sk-core' ); ?></label>
                <input type="text" class="sk-form-control" name="weo_payout_address" id="weo_payout_address" value="<?php echo esc_attr( $payout ); ?>" placeholder="bc1q…">
                <p class="help-block"><?php esc_html_e( 'Dorthin geht die Auszahlung, wenn der Käufer den Erhalt bestätigt hat. Beim Annehmen einer Anfrage kannst du sie noch ändern.', 'sk-core' ); ?></p>
            </div>

            <div class="sk-form-group">
                <label class="sk-form-label" for="weo_refund_address"><?php esc_html_e( 'Rückerstattungsadresse als Käufer (bc1…)', 'sk-core' ); ?></label>
                <input type="text" class="sk-form-control" name="weo_refund_address" id="weo_refund_address" value="<?php echo esc_attr( $refund ); ?>" placeholder="bc1q…">
                <p class="help-block"><?php esc_html_e( 'Wird bei einer Treuhand-Anfrage vorgeschlagen und kann dort geändert werden.', 'sk-core' ); ?></p>
            </div>

            <div class="sk-form-group">
                <input type="submit" class="sk-btn sk-btn-theme" value="<?php esc_attr_e( 'Speichern', 'sk-core' ); ?>">
            </div>
        </form>
        <?php
    }

    private function save_settings( int $user_id ): void {
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['weo_settings_nonce'] ) ), 'weo_settings' ) ) {
            sk_add_notice( __( 'Ungültiger Sicherheits-Token.', 'sk-core' ), 'error' );
            return;
        }

        $errors = false;
        $payout = weo_sanitize_btc_address( wp_unslash( $_POST['weo_payout_address'] ?? '' ) );
        $refund = weo_sanitize_btc_address( wp_unslash( $_POST['weo_refund_address'] ?? '' ) );

        foreach ( [ self::PAYOUT_META => $payout, Purchase::REFUND_META => $refund ] as $key => $addr ) {
            if ( $addr === '' ) {
                delete_user_meta( $user_id, $key );
            } elseif ( weo_validate_btc_address( $addr ) ) {
                update_user_meta( $user_id, $key, $addr );
            } else {
                $errors = true;
                sk_add_notice( __( 'Ungültige Bitcoin-Adresse, nicht übernommen.', 'sk-core' ), 'error' );
            }
        }

        $enabled = ! empty( $_POST['weo_escrow_enabled'] );
        if ( $enabled && ! weo_validate_btc_address( (string) get_user_meta( $user_id, self::PAYOUT_META, true ) ) ) {
            $enabled = false;
            $errors  = true;
            sk_add_notice( __( 'Ohne gültige Auszahlungsadresse lässt sich die Treuhand nicht anbieten.', 'sk-core' ), 'error' );
        }
        update_user_meta( $user_id, self::ENABLED_META, $enabled ? '1' : '' );

        if ( ! $errors ) {
            sk_add_notice( __( 'Treuhand-Einstellungen gespeichert.', 'sk-core' ), 'success' );
        }
    }

    // ---- card in Käufe/Verkäufe ----

    /**
     * Escrow section of one card. $tab is "sales" (viewer is the seller) or
     * "purchases" (viewer is the buyer).
     */
    public static function render_card( object $p, string $tab ): void {
        $role   = $tab === 'sales' ? 'seller' : 'buyer';
        $meta   = Rows::meta( $p );
        $status = (string) $p->status;
        $signed = (array) ( $meta['signed'] ?? [] );
        $type   = (string) ( $meta['psbt_type'] ?? '' );
        $done   = ! empty( $meta['settled_txid'] );
        $mine   = in_array( $role, $signed, true );
        $other  = $role === 'buyer' ? 'seller' : 'buyer';
        ?>
        <div class="weo-escrow-card"
             data-hash="<?php echo esc_attr( $p->payment_hash ); ?>"
             data-role="<?php echo esc_attr( $role ); ?>"
             data-status="<?php echo esc_attr( $status ); ?>"
             data-descriptor="<?php echo esc_attr( $meta['descriptor'] ?? '' ); ?>"
             data-address="<?php echo esc_attr( $meta['address'] ?? '' ); ?>"
             data-network="main">

            <p class="weo-state"><i class="fas fa-handshake"></i> <?php echo esc_html( Rows::state_label( $p ) ); ?></p>

            <?php if ( $status === 'requested' && $role === 'seller' ) : ?>
                <div class="weo-accept">
                    <p><?php esc_html_e( 'Zum Annehmen erzeugst du einen Schlüssel nur für diesen Handel und bestätigst deine Auszahlungsadresse. Danach erhält der Käufer die Einzahlungsadresse.', 'sk-core' ); ?></p>
                    <p><label><?php esc_html_e( 'Auszahlungsadresse (bc1…)', 'sk-core' ); ?><br>
                        <input type="text" class="weo-accept-payout" value="<?php echo esc_attr( (string) get_user_meta( (int) $p->vendor_id, self::PAYOUT_META, true ) ); ?>" placeholder="bc1q…"></label></p>
                    <p><label><?php esc_html_e( 'Dein Treuhand-Schlüssel (xpub)', 'sk-core' ); ?><br>
                        <input type="text" class="weo-accept-xpub" id="weo_accept_xpub_<?php echo esc_attr( $p->id ); ?>" value="" placeholder="xpub…" autocomplete="off"></label></p>
                    <?php echo weo_keygen_html( 'weo_accept_xpub_' . $p->id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <p>
                        <button type="button" class="sk-btn sk-btn-theme sk-btn-sm weo-accept-go"><i class="fas fa-check"></i> <?php esc_html_e( 'Anfrage annehmen', 'sk-core' ); ?></button>
                        <button type="button" class="sk-btn sk-btn-sm weo-decline"><i class="fas fa-times"></i> <?php esc_html_e( 'Ablehnen', 'sk-core' ); ?></button>
                    </p>
                </div>
            <?php elseif ( $status === 'requested' ) : ?>
                <p><button type="button" class="sk-btn sk-btn-sm weo-cancel"><i class="fas fa-times"></i> <?php esc_html_e( 'Anfrage zurückziehen', 'sk-core' ); ?></button></p>
            <?php endif; ?>

            <?php if ( $status === 'pending' ) : ?>
                <div class="weo-deposit">
                    <?php if ( $role === 'buyer' ) : ?>
                        <p><strong><?php echo esc_html( sprintf( __( 'Einzuzahlen: %s sats', 'sk-core' ), number_format_i18n( (int) ( $meta['deposit_sat'] ?? $p->amount_sats ) ) ) ); ?></strong>
                            <span class="description"><?php echo esc_html( sprintf( __( '(Preis %1$s + Reserve für die Netzwerkgebühr %2$s)', 'sk-core' ), number_format_i18n( (int) $p->amount_sats ), number_format_i18n( (int) ( $meta['fee_est_sat'] ?? 0 ) ) ) ); ?></span></p>
                        <p class="weo-verify" aria-live="polite"></p>
                        <div class="weo-qr-box">
                            <?php if ( class_exists( QrImage::class ) && ! empty( $p->payment_request ) ) : ?>
                                <img class="weo-qr" src="<?php echo esc_attr( QrImage::data_uri( (string) $p->payment_request ) ); ?>" alt="QR" width="180" height="180">
                            <?php endif; ?>
                            <code class="weo-address"><?php echo esc_html( $meta['address'] ?? '' ); ?></code>
                            <p>
                                <button type="button" class="sk-btn sk-btn-sm weo-copy" data-copy="<?php echo esc_attr( $meta['address'] ?? '' ); ?>"><i class="fas fa-copy"></i> <?php esc_html_e( 'Adresse kopieren', 'sk-core' ); ?></button>
                                <a class="sk-btn sk-btn-sm" href="<?php echo esc_attr( $p->payment_request ); ?>"><i class="fab fa-bitcoin"></i> <?php esc_html_e( 'In Wallet öffnen', 'sk-core' ); ?></a>
                            </p>
                        </div>
                    <?php else : ?>
                        <p><?php esc_html_e( 'Der Käufer zahlt jetzt ein. Sobald die Einzahlung bestätigt ist, erscheint das hier und im Chat.', 'sk-core' ); ?></p>
                    <?php endif; ?>
                    <p class="weo-poll" aria-live="polite"></p>
                </div>
            <?php endif; ?>

            <?php if ( in_array( $status, [ 'confirmed', 'delivered', 'disputed' ], true ) && ! $done ) : ?>
                <div class="weo-actions">
                    <?php if ( ! empty( $meta['funded_sat'] ) ) : ?>
                        <p class="description"><?php echo esc_html( sprintf( __( 'In der Treuhand: %s sats', 'sk-core' ), number_format_i18n( (int) $meta['funded_sat'] ) ) ); ?></p>
                    <?php endif; ?>

                    <?php if ( $role === 'buyer' && $status === 'confirmed' && $type === '' ) : ?>
                        <p><button type="button" class="sk-btn sk-btn-theme sk-btn-sm weo-sign-start" data-type="payout" data-confirm="release"><i class="fas fa-box-open"></i> <?php esc_html_e( 'Erhalten – Auszahlung freigeben', 'sk-core' ); ?></button></p>
                    <?php endif; ?>

                    <?php if ( $role === 'seller' && $status === 'confirmed' && $type === '' ) : ?>
                        <p><button type="button" class="sk-btn sk-btn-sm weo-sign-start" data-type="refund" data-confirm="refund"><i class="fas fa-undo"></i> <?php esc_html_e( 'Erstatten', 'sk-core' ); ?></button></p>
                    <?php endif; ?>

                    <?php
                    // In a dispute the admin picks the transaction; only the
                    // favoured party gets a button.
                    $may_counter = $status !== 'disputed' || $role === ( $type === 'refund' ? 'buyer' : 'seller' );
                    ?>
                    <?php if ( $type !== '' && ! $mine && $may_counter ) : ?>
                        <p><button type="button" class="sk-btn sk-btn-theme sk-btn-sm weo-sign-start" data-type="<?php echo esc_attr( $type ); ?>"><i class="fas fa-pen"></i>
                            <?php echo $type === 'refund' ? esc_html__( 'Erstattung signieren', 'sk-core' ) : esc_html__( 'Auszahlung signieren', 'sk-core' ); ?></button></p>
                    <?php elseif ( $type !== '' && $mine ) : ?>
                        <p class="description"><?php echo esc_html( sprintf( __( 'Deine Signatur liegt vor. Es fehlt noch die des %s.', 'sk-core' ), $other === 'buyer' ? __( 'Käufers', 'sk-core' ) : __( 'Verkäufers', 'sk-core' ) ) ); ?></p>
                    <?php endif; ?>

                    <?php echo weo_sign_panel_html( __( 'Jetzt signieren', 'sk-core' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            <?php endif; ?>

            <?php if ( $done ) : ?>
                <p><a href="https://mempool.space/tx/<?php echo esc_attr( $meta['settled_txid'] ); ?>" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i> <?php esc_html_e( 'Transaktion ansehen', 'sk-core' ); ?></a></p>
            <?php endif; ?>

            <?php if ( ! empty( $meta['descriptor'] ) && ! $done && $status !== 'expired' ) : ?>
                <?php echo weo_keybox_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endif; ?>

            <p class="weo-msg" aria-live="polite"></p>
        </div>
        <?php
    }
}
