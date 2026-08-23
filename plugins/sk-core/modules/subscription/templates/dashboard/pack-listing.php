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
                __( 'Das Abo <strong>%1$s</strong> ist wegen Zahlungsfehler inaktiv. <a href="?add-to-cart=%2$s">Jetzt bezahlen</a>.', 'sk-core' ),
                $subscription->get_package_title(),
                $subscription->get_id()
            );
            ?>
        </div>
    <?php elseif ( $subscription && $subscription->can_post_product() ) :
        $no_of_product  = '-1' !== $subscription->get_number_of_products() ? $subscription->get_number_of_products() : 'Unbegrenzt';
        $pack_title     = $subscription->get_package_title();
        $is_cancelled   = $subscription->has_active_cancelled_subscription();
        $end_date       = $subscription->get_pack_end_date();

        // Laufzeit-Text
        if ( $is_cancelled ) {
            $laufzeit = sprintf( 'Aktiv bis %s (gekündigt)', sk_format_date( $end_date ) );
        } elseif ( $end_date === 'unlimited' ) {
            $laufzeit = 'Unbegrenzt';
        } else {
            $laufzeit = 'Bis ' . sk_format_date( $end_date );
        }
    ?>
        <div class="sk-sub-active-info">
            <div class="sk-sub-active-info__header">
                <i class="fas fa-check-circle"></i>
                <h3>Dein aktives Abo</h3>
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
                if ( $subscription && $subscription->has_active_cancelled_subscription() ) {
                    $date = sk_format_date( $subscription->get_pack_end_date() );
                    // translators: Package validity date.
                    $notice = sprintf( __( 'Your subscription has been cancelled! However the it\'s is still active till %s', 'sk-core' ), $date );
                } else {
                    $notice = __( 'Your subscription has been cancelled!', 'sk-core' );
                }
                ?>

                <p><?php printf( $notice ); ?></p>
            </div>
        <?php endif; ?>

        <?php if ( isset( $_GET['msg'] ) && 'dps_sub_activated' === sanitize_text_field( wp_unslash( $_GET['msg'] ) ) ) : //phpcs:ignore ?>
            <div class="sk-message">
                <?php
                esc_html_e( 'Your subscription has been re-activated!', 'sk-core' );
                ?>
            </div>
        <?php endif; ?>

        <div class="pack_content_wrapper">

            <?php
            while ( $subscription_packs->have_posts() ) {
                $subscription_packs->the_post();

                // get individual subscriptoin pack details
                $sub_pack = sk()->subscription->get( get_the_ID() );
                $pack_id  = apply_filters( 'sk_vendor_subscription_package_id', get_the_ID() );
                ?>

                <div class="product_pack_item <?php echo ( Helper::is_vendor_subscribed_pack( $pack_id ) || Helper::pack_renew_seller( $pack_id ) ) ? 'current_pack' : ''; ?>">
                    <div class="pack_price">

                            <span class="dps-amount">
                                <?php echo wc_price( $sub_pack->get_price() ); ?>
                            </span>

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
                                echo sprintf( '<strong>%s</strong> %s <br />', __( 'Unlimited', 'sk-core' ), __( 'Products', 'sk-core' ) );
                            } else {
                                echo sprintf( '<strong>%d</strong> %s <br />', $no_of_product, __( 'Products', 'sk-core' ) );
                            }
                            ?>
                            <?php
                            if ( empty( $sub_pack->get_pack_valid_days() ) ) {
                                echo sprintf( '%1$s<br /><strong>%2$s</strong> %3$s', __( 'For', 'sk-core' ), __( 'Unlimited', 'sk-core' ), __( 'Days', 'sk-core' ) );
                            } else {
                                $pack_validity = $sub_pack->get_pack_valid_days();
                                echo sprintf( '%1$s<br /><strong>%2$s</strong> %3$s', __( 'For', 'sk-core' ), $pack_validity, __( 'Days', 'sk-core' ) );
                            }
                            ?>                        </div><!-- .pack_data_option -->

                        <?php
                        // Verkaufsargument: diese Moeglichkeiten gibt es erst ab einer
                        // bestimmten Paketgroesse, die Karte zaehlt sie auf.
                        if ( class_exists( \SK\Modules\ShopImport\Variants::class )
                            && \SK\Modules\ShopImport\Variants::pack_allows( (int) get_the_ID() ) ) :
                            ?>
                            <ul class="pack_features">
                                <li><i class="fas fa-file-import"></i> WooCommerce Produkt Importe</li>
                                <li><i class="fas fa-layer-group"></i> Variable Produkte</li>
                                <li><i class="fas fa-bolt"></i> Adaptive Preise in Sats</li>
                                <li><i class="fas fa-clipboard-list"></i> Verkaufsübersicht</li>
                                <li><i class="fab fa-bitcoin"></i> Direkte Onchain- &amp; Offchain-Zahlungen</li>
                            </ul>
                            <button type="button" class="pack_feature_more" data-sk-pack-info>
                                Mehr erfahren
                            </button>
                        <?php endif; ?>
                    </div><!-- .pack_content -->

                    <div class="buy_pack_button">
                        <?php if ( Helper::is_vendor_subscribed_pack( $pack_id ) ) : ?>

                            <a href="<?php echo get_permalink( get_the_ID() ); ?>" class="sk-btn sk-btn-theme buy_product_pack"><?php esc_html_e( 'Your Pack', 'sk-core' ); ?></a>

                        <?php elseif ( Helper::pack_renew_seller( $pack_id ) ) : ?>

                            <a href="<?php echo do_shortcode( '[add_to_cart_url id="' . $pack_id . '"]' ); ?>" class="sk-btn sk-btn-theme buy_product_pack"><?php esc_html_e( 'Renew', 'sk-core' ); ?></a>

                        <?php elseif ( ! Helper::vendor_has_subscription( sk_get_current_user_id() ) ) : ?>

                            <a href="<?php echo do_shortcode( '[add_to_cart_url id="' . get_the_ID() . '"]' ); ?>" class="sk-btn sk-btn-theme buy_product_pack"><?php esc_html_e( 'Buy Now', 'sk-core' ); ?></a>

                        <?php else : ?>

                            <a href="<?php echo do_shortcode( '[add_to_cart_url id="' . get_the_ID() . '"]' ); ?>" class="sk-btn sk-btn-theme buy_product_pack"><?php esc_html_e( 'Switch Plan', 'sk-core' ); ?></a>

                        <?php endif; ?>
                    </div><!-- .buy_pack_button -->
                </div><!-- .product_pack_item -->
                <?php
            }
            ?>
        </div><!-- .sk-subscription-content -->

        <?php
        // Einmal je Seite, nicht je Karte — alle Knoepfe oeffnen dasselbe Modal.
        if ( class_exists( \SK\Modules\ShopImport\Variants::class ) ) {
            include SK_SHOP_IMPORT_PATH . '/templates/pack-info-modal.php';
        }
        ?>

        <?php
    } else {
        echo '<h3>' . __( 'No subscription pack has been found!', 'sk-core' ) . '</h3>';
    }

    wp_reset_postdata();
    ?>
    <div class="clearfix"></div>
</div><!-- .pack_content_wrapper -->
<?php
