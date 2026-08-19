<?php
/**
 * Dashboard Subscription Contents.
 *
 *
 * @var int    $user_id            User ID.
 * @var object $subscription_packs Subscription Packs.
 *
 */

use SK\Modules\Subscription\Helper;

?>
<div class="sk-subscription-content">
    <?php
    $subscription = sk()->vendor->get( $user_id )->subscription;
    ?>

    <?php if ( $subscription && $subscription->has_pending_subscription() ) : ?>
        <div class="sk-sub-active-info sk-sub-active-info--warning">
            <i class="fas fa-exclamation-triangle"></i>
            <?php
            printf(
                __( 'Das Abo <strong>%1$s</strong> ist wegen Zahlungsfehler inaktiv. <a href="?add-to-cart=%2$s">Jetzt bezahlen</a>.', 'sk' ),
                $subscription->get_package_title(),
                $subscription->get_id()
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

            <?php
            // a cancelled pack can be reactivated, a running one can be cancelled
            $nonce      = $is_cancelled ? 'dps-sub-activate' : 'dps-sub-cancel';
            $input_name = $is_cancelled ? 'dps_activate_subscription' : 'dps_cancel_subscription';
            $btn_class  = $is_cancelled ? 'sk-btn-success' : 'sk-btn-sm-danger';
            $btn_label  = $is_cancelled ? 'Abo reaktivieren' : 'Abo kündigen';
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
        </div>
    <?php endif; ?>

    <?php
    if ( $subscription_packs->have_posts() ) {
        ?>

        <?php if ( isset( $_GET['msg'] ) && 'dps_sub_cancelled' === sanitize_text_field( wp_unslash( $_GET['msg'] ) ) ) : //phpcs:ignore ?>
            <div class="sk-message">
                <?php
                if ( $subscription && $subscription->has_active_cancelled_subscrption() ) {
                    $date = sk_format_date( $subscription->get_pack_end_date() );
                    // translators: Package validity date.
                    $notice = sprintf( __( 'Your subscription has been cancelled! However the it\'s is still active till %s', 'sk' ), $date );
                } else {
                    $notice = __( 'Your subscription has been cancelled!', 'sk' );
                }
                ?>

                <p><?php printf( $notice ); ?></p>
            </div>
        <?php endif; ?>

        <?php if ( isset( $_GET['msg'] ) && 'dps_sub_activated' === sanitize_text_field( wp_unslash( $_GET['msg'] ) ) ) : //phpcs:ignore ?>
            <div class="sk-message">
                <?php
                esc_html_e( 'Your subscription has been re-activated!', 'sk' );
                ?>
            </div>
        <?php endif; ?>

        <div class="pack_content_wrapper">

            <?php
            while ( $subscription_packs->have_posts() ) {
                $subscription_packs->the_post();

                // get individual subscriptoin pack details
                $sub_pack           = sk()->subscription->get( get_the_ID() );
                $is_recurring       = $sub_pack->is_recurring();
                $recurring_interval = $sub_pack->get_recurring_interval();
                $recurring_period   = $sub_pack->get_period_type();
                $pack_id            = apply_filters( 'sk_vendor_subscription_package_id', get_the_ID() );
                ?>

                <div class="product_pack_item <?php echo ( Helper::is_vendor_subscribed_pack( $pack_id ) || Helper::pack_renew_seller( $pack_id ) ) ? 'current_pack ' : ''; ?><?php echo ( $sub_pack->is_trial() && Helper::has_used_trial_pack( get_current_user_id() ) ) ? 'fp_already_taken' : ''; ?>">
                    <div class="pack_price">

                            <span class="dps-amount">
                                <?php echo wc_price( $sub_pack->get_price() ); ?>
                            </span>

                        <?php if ( $is_recurring && $recurring_interval === 1 ) { ?>
                            <span class="dps-rec-period">
                                    <span class="sep">/</span><?php echo Helper::recurring_period( $recurring_period, $recurring_interval ); ?>
                                </span>
                        <?php } ?>
                    </div><!-- .pack_price -->

                    <div class="pack_content">
                        <h2><?php echo $sub_pack->get_package_title(); ?></h2>
                        <?php if ( has_post_thumbnail() ) : ?>
                            <div class="dst-sub-thumb"><?php the_post_thumbnail( 'medium', [ 'loading' => 'lazy' ] ); ?></div>
                        <?php endif; ?>
                        <?php the_content(); ?>

                        <div class="pack_data_option">
                            <?php
                            $no_of_product = $sub_pack->get_number_of_products();

                            if ( '-1' === $no_of_product ) {
                                echo sprintf( '<strong>%s</strong> %s <br />', __( 'Unlimited', 'sk' ), __( 'Products', 'sk' ) );
                            } else {
                                echo sprintf( '<strong>%d</strong> %s <br />', $no_of_product, __( 'Products', 'sk' ) );
                            }
                            ?>
                            <?php if ( $is_recurring && $sub_pack->is_trial() && Helper::has_used_trial_pack( get_current_user_id() ) ) : ?>
                                <span class="dps-rec-period">
                                        <?php esc_html_e( 'In every', 'sk' ); ?>
                                        <?php echo number_format_i18n( $recurring_interval ); ?>
                                        <?php echo Helper::recurring_period( $recurring_period, $recurring_interval ); ?>
                                    </span>
                            <?php elseif ( $is_recurring && $sub_pack->is_trial() ) : ?>
                                <span class="dps-rec-period">
                                        <?php esc_html_e( 'In every', 'sk' ); ?>
                                    <?php echo number_format_i18n( $recurring_interval ); ?>
                                    <?php echo Helper::recurring_period( $recurring_period, $recurring_interval ); ?>
                                        <p class="trail-details">
                                            <?php echo $sub_pack->get_trial_range(); ?>
                                            <?php echo Helper::recurring_period( $sub_pack->get_trial_period_types(), $sub_pack->get_trial_range() ); ?>
                                            <?php esc_html_e( 'trial', 'sk' ); ?>
                                        </p>
                                    </span>
                            <?php elseif ( $is_recurring && $recurring_interval >= 1 ) : ?>
                                <span class="dps-rec-period">
                                        <?php esc_html_e( 'In every', 'sk' ); ?>
                                        <?php echo number_format_i18n( $recurring_interval ); ?>
                                        <?php echo Helper::recurring_period( $recurring_period, $recurring_interval ); ?>
                                    </span>
                            <?php
                            else :
                                if ( empty( $sub_pack->get_pack_valid_days() ) ) {
                                    echo sprintf( '%1$s<br /><strong>%2$s</strong> %3$s', __( 'For', 'sk' ), __( 'Unlimited', 'sk' ), __( 'Days', 'sk' ) );
                                } else {
                                    $pack_validity = $sub_pack->get_pack_valid_days();
                                    echo sprintf( '%1$s<br /><strong>%2$s</strong> %3$s', __( 'For', 'sk' ), $pack_validity, __( 'Days', 'sk' ) );
                                }
                            endif;
                            ?>
                        </div><!-- .pack_data_option -->
                    </div><!-- .pack_content -->

                    <div class="buy_pack_button">
                        <?php if ( Helper::is_vendor_subscribed_pack( $pack_id ) ) : ?>

                            <a href="<?php echo get_permalink( get_the_ID() ); ?>" class="sk-btn sk-btn-theme buy_product_pack"><?php esc_html_e( 'Your Pack', 'sk' ); ?></a>

                        <?php elseif ( Helper::pack_renew_seller( $pack_id ) ) : ?>

                            <a href="<?php echo do_shortcode( '[add_to_cart_url id="' . $pack_id . '"]' ); ?>" class="sk-btn sk-btn-theme buy_product_pack"><?php esc_html_e( 'Renew', 'sk' ); ?></a>

                        <?php else : ?>

                            <?php if ( $sub_pack->is_trial() && Helper::vendor_has_subscription( sk_get_current_user_id() ) && Helper::has_used_trial_pack( sk_get_current_user_id() ) ) : ?>
                                <a href="<?php echo do_shortcode( '[add_to_cart_url id="' . get_the_ID() . '"]' ); ?>" class="sk-btn sk-btn-theme buy_product_pack"><?php esc_html_e( 'Switch Plan', 'sk' ); ?></a>
                            <?php elseif ( $sub_pack->is_trial() && Helper::has_used_trial_pack( sk_get_current_user_id() ) ) : ?>
                                <a href="<?php echo do_shortcode( '[add_to_cart_url id="' . get_the_ID() . '"]' ); ?>" class="sk-btn sk-btn-theme buy_product_pack"><?php esc_html_e( 'Buy Now', 'sk' ); ?></a>

                            <?php elseif ( ! Helper::vendor_has_subscription( sk_get_current_user_id() ) ) : ?>
                                <?php if ( $sub_pack->is_trial() ) : ?>
                                    <a href="<?php echo do_shortcode( '[add_to_cart_url id="' . get_the_ID() . '"]' ); ?>" class="sk-btn sk-btn-theme buy_product_pack trial_pack"><?php esc_html_e( 'Start Free Trial', 'sk' ); ?></a>
                                <?php else : ?>
                                    <a href="<?php echo do_shortcode( '[add_to_cart_url id="' . get_the_ID() . '"]' ); ?>" class="sk-btn sk-btn-theme buy_product_pack"><?php esc_html_e( 'Buy Now', 'sk' ); ?></a>
                                <?php endif; ?>

                            <?php else : ?>
                                <a href="<?php echo do_shortcode( '[add_to_cart_url id="' . get_the_ID() . '"]' ); ?>" class="sk-btn sk-btn-theme buy_product_pack"><?php esc_html_e( 'Switch Plan', 'sk' ); ?></a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div><!-- .buy_pack_button -->
                </div><!-- .product_pack_item -->
                <?php
            }
            ?>
        </div><!-- .sk-subscription-content -->
        <?php
    } else {
        echo '<h3>' . __( 'No subscription pack has been found!', 'sk' ) . '</h3>';
    }

    wp_reset_postdata();
    ?>
    <div class="clearfix"></div>
</div><!-- .pack_content_wrapper -->
<?php
