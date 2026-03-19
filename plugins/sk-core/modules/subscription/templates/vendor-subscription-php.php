<?php
/**
 * Vendor Subscription Dashboard — Pure PHP Template
 *
 * Replaces the React frontend-components.js bundle.
 * Subscription page layout:
 * sidebar navigation + current-sub info + 2-per-row pack cards.
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use SK\Modules\Subscription\Helper;

wp_enqueue_style( 'dps-custom-style' );
wp_enqueue_script( 'dps-custom-js' );

$vendor_id          = sk_get_current_user_id();
$subscription       = sk()->vendor->get( $vendor_id )->subscription;
$subscription_packs = sk()->subscription->all();
$link               = sk_get_navigation_url( 'subscription' );
$active_tab         = ! empty( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'subscription_packs';
?>

<?php do_action( 'sk_dashboard_wrap_start' ); ?>

<div class="sk-dashboard-wrap">

    <?php
    do_action( 'sk_dashboard_content_before' );
    do_action( 'sk_subcription_content_before' );
    ?>

    <div class="sk-dashboard-content">

        <?php do_action( 'sk_subscription_content_inside_before' ); ?>

        <div class="sk-sub-page-header">
            <h2><i class="fas fa-layer-group"></i> <?php esc_html_e( 'Abonnements', 'sk' ); ?></h2>
        </div>

        <div class="sk-subscription-content">

            <?php // ── Flash messages ───────────────────────────────────────── ?>
            <?php if ( isset( $_GET['msg'] ) ) :
                $msg = sanitize_text_field( wp_unslash( $_GET['msg'] ) );
                if ( 'dps_sub_cancelled' === $msg ) :
                    if ( $subscription && $subscription->has_active_cancelled_subscrption() ) {
                        $date   = sk_format_date( $subscription->get_pack_end_date() );
                        $notice = sprintf( __( 'Your subscription has been cancelled! However it\'s is still active till %s', 'sk' ), $date );
                    } else {
                        $notice = __( 'Your subscription has been cancelled!', 'sk' );
                    }
                    ?>
                    <div class="sk-message"><p><?php echo esc_html( $notice ); ?></p></div>
                <?php endif;
                if ( 'dps_sub_activated' === $msg ) : ?>
                    <div class="sk-message"><?php esc_html_e( 'Your subscription has been re-activated!', 'sk' ); ?></div>
                <?php endif;
            endif; ?>

            <?php // ── Current subscription ─────────────────────────────────── ?>
            <?php if ( $subscription && $subscription->has_pending_subscription() ) : ?>
                <div class="sk-sub-active-info sk-sub-active-info--warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php
                    printf(
                        wp_kses(
                            'Das Abo <strong>%1$s</strong> ist wegen Zahlungsfehler inaktiv. <a href="?add-to-cart=%2$s">Jetzt bezahlen</a>.',
                            [ 'strong' => [], 'a' => [ 'href' => [] ] ]
                        ),
                        esc_html( $subscription->get_package_title() ),
                        esc_attr( $subscription->get_id() )
                    );
                    ?>
                </div>

            <?php elseif ( $subscription && $subscription->can_post_product() ) :
                $no_of_product  = '-1' !== $subscription->get_number_of_products() ? $subscription->get_number_of_products() : 'Unbegrenzt';
                $pack_title     = $subscription->get_package_title();
                $is_cancelled   = $subscription->has_active_cancelled_subscrption();
                $is_trial       = $subscription->is_trial();
                $is_recurring   = $subscription->is_recurring();
                $end_date       = $subscription->get_pack_end_date();

                // Laufzeit-Text
                if ( $is_cancelled ) {
                    $laufzeit = sprintf( 'Aktiv bis %s (gekündigt)', sk_format_date( $end_date ) );
                } elseif ( $is_trial ) {
                    $trial_label = $subscription->get_trial_range() . ' ' . Helper::recurring_period( $subscription->get_trial_period_types(), $subscription->get_trial_range() );
                    $laufzeit    = 'Testphase: ' . $trial_label;
                } elseif ( $is_recurring ) {
                    $laufzeit = 'Alle ' . $subscription->get_recurring_interval() . ' ' . Helper::recurring_period( $subscription->get_period_type(), $subscription->get_recurring_interval() );
                } elseif ( $end_date === 'unlimited' ) {
                    $laufzeit = 'Unbegrenzt';
                } else {
                    $laufzeit = 'Bis ' . sk_format_date( $end_date );
                }
            ?>
                <div class="sk-sub-active-info">
                    <div class="sk-sub-active-info__header">
                        <i class="fas fa-check-circle"></i>
                        <h3><?php echo $is_trial ? 'Deine Testphase' : 'Dein aktives Abo'; ?></h3>
                    </div>

                    <div class="sk-sub-active-info__stats">
                        <div class="sk-sub-active-info__stat">
                            <span class="sk-sub-active-info__stat-label"><i class="fas fa-box-open"></i> Paket</span>
                            <span class="sk-sub-active-info__stat-value"><?php echo esc_html( $pack_title ); ?></span>
                        </div>
                        <div class="sk-sub-active-info__stat">
                            <span class="sk-sub-active-info__stat-label"><i class="fas fa-tag"></i> Inserate</span>
                            <span class="sk-sub-active-info__stat-value"><?php echo esc_html( $no_of_product ); ?></span>
                        </div>
                        <div class="sk-sub-active-info__stat">
                            <span class="sk-sub-active-info__stat-label"><i class="fas fa-clock"></i> Laufzeit</span>
                            <span class="sk-sub-active-info__stat-value"><?php echo esc_html( $laufzeit ); ?></span>
                        </div>
                    </div>

                    <?php if ( $is_cancelled ) : ?>
                        <p class="sk-sub-active-info__notice sk-sub-active-info__notice--warn">
                            <i class="fas fa-exclamation-triangle"></i>
                            Dein Abo wurde gekündigt und ist bis <?php echo esc_html( sk_format_date( $end_date ) ); ?> noch aktiv.
                        </p>
                    <?php endif; ?>

                    <?php if ( ! ( ! $is_recurring && $is_cancelled ) ) :
                        $maybe_reactivate = $is_recurring && $is_cancelled;
                        $nonce      = $maybe_reactivate ? 'dps-sub-activate' : 'dps-sub-cancel';
                        $input_name = $maybe_reactivate ? 'dps_activate_subscription' : 'dps_cancel_subscription';
                        $btn_class  = $maybe_reactivate ? 'sk-btn-success' : 'sk-btn-sm-danger';
                        $btn_label  = $maybe_reactivate ? 'Abo reaktivieren' : 'Abo kündigen';
                    ?>
                        <div class="sk-sub-active-info__action">
                            <form id="dps_submit_form" action="" method="post">
                                <?php wp_nonce_field( $nonce ); ?>
                                <input type="hidden" name="<?php echo esc_attr( $input_name ); ?>" value="1">
                                <input type="submit" name="dps_submit"
                                       class="<?php echo esc_attr( "sk-sub-cancel-btn {$btn_class}" ); ?>"
                                       value="<?php echo esc_attr( $btn_label ); ?>">
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php // ── Pack cards ───────────────────────────────────────────── ?>
            <?php if ( $subscription_packs->have_posts() ) : ?>
                <div class="pack_content_wrapper">
                    <?php
                    while ( $subscription_packs->have_posts() ) :
                        $subscription_packs->the_post();

                        $sub_pack           = sk()->subscription->get( get_the_ID() );
                        $is_recurring       = $sub_pack->is_recurring();
                        $recurring_interval = $sub_pack->get_recurring_interval();
                        $recurring_period   = $sub_pack->get_period_type();
                        $pack_id            = apply_filters( 'sk_vendor_subscription_package_id', get_the_ID() );
                        $is_trial_used      = $sub_pack->is_trial() && Helper::has_used_trial_pack( get_current_user_id() );
                        $is_current         = Helper::is_vendor_subscribed_pack( $pack_id ) || Helper::pack_renew_seller( $pack_id );
                        ?>
                        <div class="product_pack_item <?php echo $is_current ? 'current_pack' : ''; ?> <?php echo $is_trial_used ? 'fp_already_taken' : ''; ?>">

                            <div class="pack_price">
                                <span class="dps-amount"><?php echo wp_kses_post( wc_price( $sub_pack->get_price() ) ); ?></span>
                                <?php if ( $is_recurring && 1 === (int) $recurring_interval ) : ?>
                                    <span class="dps-rec-period">
                                        <span class="sep">/</span><?php echo esc_html( Helper::recurring_period( $recurring_period, $recurring_interval ) ); ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <?php
                            // Strip Gutenberg image blocks from raw markup before rendering
                            $raw = get_the_content();
                            $raw = preg_replace( '/<!--\s*wp:image\b[\s\S]*?<!--\s*\/wp:image\s*-->/i', '', $raw );
                            $raw = preg_replace( '/<!--\s*wp:cover\b[\s\S]*?<!--\s*\/wp:cover\s*-->/i', '', $raw );
                            $raw = preg_replace( '/<!--\s*wp:media-text\b[\s\S]*?<!--\s*\/wp:media-text\s*-->/i', '', $raw );
                            // Render blocks, then strip any remaining images (raw HTML img tags, etc.)
                            $rendered = do_blocks( $raw );
                            $text_tags = [
                                'p'      => [ 'class' => [] ],
                                'ul'     => [ 'class' => [] ],
                                'ol'     => [ 'class' => [] ],
                                'li'     => [ 'class' => [] ],
                                'strong' => [],
                                'b'      => [],
                                'em'     => [],
                                'i'      => [],
                                'br'     => [],
                                'span'   => [ 'class' => [] ],
                                'img'    => [ 'src' => [], 'alt' => [], 'class' => [], 'width' => [], 'height' => [], 'srcset' => [], 'sizes' => [], 'loading' => [], 'decoding' => [] ],
                                'figure' => [ 'class' => [] ],
                            ];
                            $pack_text_only = wp_kses( $rendered, $text_tags );
                            ?>

                            <div class="pack_content">
                                <h2><?php echo esc_html( $sub_pack->get_package_title() ); ?></h2>

                                <?php
                                $short_desc = wp_strip_all_tags( get_the_excerpt() );
                                if ( $short_desc ) : ?>
                                    <div class="pack_short_desc"><p><?php echo esc_html( $short_desc ); ?></p></div>
                                <?php endif; ?>

                                <div class="pack_data_option">
                                    <?php
                                    $no_of_product = $sub_pack->get_number_of_products();
                                    if ( '-1' === $no_of_product ) {
                                        echo sprintf( '<strong>%s</strong> %s <br />', esc_html__( 'Unlimited', 'sk' ), esc_html__( 'Products', 'sk' ) );
                                    } else {
                                        echo sprintf( '<strong>%d</strong> %s <br />', (int) $no_of_product, esc_html__( 'Products', 'sk' ) );
                                    }
                                    ?>
                                    <?php if ( $is_recurring && $sub_pack->is_trial() && $is_trial_used ) : ?>
                                        <span class="dps-rec-period">
                                            <?php esc_html_e( 'In every', 'sk' ); ?>
                                            <?php echo esc_html( number_format_i18n( $recurring_interval ) ); ?>
                                            <?php echo esc_html( Helper::recurring_period( $recurring_period, $recurring_interval ) ); ?>
                                        </span>
                                    <?php elseif ( $is_recurring && $sub_pack->is_trial() ) : ?>
                                        <span class="dps-rec-period">
                                            <?php esc_html_e( 'In every', 'sk' ); ?>
                                            <?php echo esc_html( number_format_i18n( $recurring_interval ) ); ?>
                                            <?php echo esc_html( Helper::recurring_period( $recurring_period, $recurring_interval ) ); ?>
                                            <p class="trail-details">
                                                <?php echo esc_html( $sub_pack->get_trial_range() ); ?>
                                                <?php echo esc_html( Helper::recurring_period( $sub_pack->get_trial_period_types(), $sub_pack->get_trial_range() ) ); ?>
                                                <?php esc_html_e( 'trial', 'sk' ); ?>
                                            </p>
                                        </span>
                                    <?php elseif ( $is_recurring && $recurring_interval >= 1 ) : ?>
                                        <span class="dps-rec-period">
                                            <?php esc_html_e( 'In every', 'sk' ); ?>
                                            <?php echo esc_html( number_format_i18n( $recurring_interval ) ); ?>
                                            <?php echo esc_html( Helper::recurring_period( $recurring_period, $recurring_interval ) ); ?>
                                        </span>
                                    <?php else :
                                        if ( empty( $sub_pack->get_pack_valid_days() ) ) {
                                            echo sprintf( '%1$s<br /><strong>%2$s</strong> %3$s', esc_html__( 'For', 'sk' ), esc_html__( 'Unlimited', 'sk' ), esc_html__( 'Days', 'sk' ) );
                                        } else {
                                            echo sprintf( '%1$s<br /><strong>%2$s</strong> %3$s', esc_html__( 'For', 'sk' ), esc_html( $sub_pack->get_pack_valid_days() ), esc_html__( 'Days', 'sk' ) );
                                        }
                                    endif; ?>
                                </div>
                            </div>

                            <div class="buy_pack_button">
                                <?php if ( Helper::is_vendor_subscribed_pack( $pack_id ) ) : ?>
                                    <a href="<?php echo esc_url( get_permalink( get_the_ID() ) ); ?>" class="sk-btn sk-btn-theme buy_product_pack">
                                        <?php esc_html_e( 'Your Pack', 'sk' ); ?>
                                    </a>

                                <?php elseif ( Helper::pack_renew_seller( $pack_id ) ) : ?>
                                    <a href="<?php echo esc_url( do_shortcode( '[add_to_cart_url id="' . $pack_id . '"]' ) ); ?>" class="sk-btn sk-btn-theme buy_product_pack">
                                        <?php esc_html_e( 'Renew', 'sk' ); ?>
                                    </a>

                                <?php else : ?>
                                    <?php if ( $sub_pack->is_trial() && Helper::vendor_has_subscription( sk_get_current_user_id() ) && $is_trial_used ) : ?>
                                        <a href="<?php echo esc_url( do_shortcode( '[add_to_cart_url id="' . get_the_ID() . '"]' ) ); ?>" class="sk-btn sk-btn-theme buy_product_pack">
                                            <?php esc_html_e( 'Switch Plan', 'sk' ); ?>
                                        </a>
                                    <?php elseif ( $sub_pack->is_trial() && $is_trial_used ) : ?>
                                        <a href="<?php echo esc_url( do_shortcode( '[add_to_cart_url id="' . get_the_ID() . '"]' ) ); ?>" class="sk-btn sk-btn-theme buy_product_pack">
                                            <?php esc_html_e( 'Buy Now', 'sk' ); ?>
                                        </a>
                                    <?php elseif ( ! Helper::vendor_has_subscription( sk_get_current_user_id() ) ) : ?>
                                        <?php if ( $sub_pack->is_trial() ) : ?>
                                            <a href="<?php echo esc_url( do_shortcode( '[add_to_cart_url id="' . get_the_ID() . '"]' ) ); ?>" class="sk-btn sk-btn-theme buy_product_pack trial_pack">
                                                <?php esc_html_e( 'Start Free Trial', 'sk' ); ?>
                                            </a>
                                        <?php else : ?>
                                            <a href="<?php echo esc_url( do_shortcode( '[add_to_cart_url id="' . get_the_ID() . '"]' ) ); ?>" class="sk-btn sk-btn-theme buy_product_pack">
                                                <?php esc_html_e( 'Buy Now', 'sk' ); ?>
                                            </a>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <a href="<?php echo esc_url( do_shortcode( '[add_to_cart_url id="' . get_the_ID() . '"]' ) ); ?>" class="sk-btn sk-btn-theme buy_product_pack">
                                            <?php esc_html_e( 'Switch Plan', 'sk' ); ?>
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>

                            <div class="pack_short_desc"><?php echo $pack_text_only; ?></div>

                        </div>
                    <?php endwhile; wp_reset_postdata(); ?>
                </div>

            <?php else : ?>
                <h3><?php esc_html_e( 'No subscription pack has been found!', 'sk' ); ?></h3>
            <?php endif; ?>

        </div><!-- .sk-subscription-content -->

        <?php do_action( 'sk_subscription_content_inside_after' ); ?>

    </div><!-- .sk-dashboard-content -->

    <?php
    do_action( 'sk_dashboard_content_after' );
    do_action( 'sk_subscription_content_after' );
    ?>

</div><!-- .sk-dashboard-wrap -->

<?php do_action( 'sk_dashboard_wrap_end' ); ?>
