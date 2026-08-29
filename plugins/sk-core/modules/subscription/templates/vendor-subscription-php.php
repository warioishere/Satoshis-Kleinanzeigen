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

// Tab counts.
$pack_count   = $subscription_packs instanceof WP_Query ? $subscription_packs->found_posts : 0;
$orders_page  = ! empty( $_GET['pagenum'] ) ? absint( wp_unslash( $_GET['pagenum'] ) ) : 1;
$orders_data  = Helper::get_paginated_subscription_orders_by_vendor_id( $vendor_id, $orders_page );
$order_count  = ! empty( $orders_data['total_orders'] ) ? (int) $orders_data['total_orders'] : 0;
?>

<?php do_action( 'sk_dashboard_wrap_start' ); ?>

<div class="sk-dashboard-wrap">

    <?php
    do_action( 'sk_dashboard_content_before' );
    do_action( 'sk_subscription_content_before' );
    ?>

    <div class="sk-dashboard-content">

        <?php do_action( 'sk_subscription_content_inside_before' ); ?>

        <div class="sk-review-page-header">
            <h2><i class="fas fa-layer-group"></i> <?php esc_html_e( 'Abonnements', 'sk-core' ); ?></h2>
        </div>

        <div class="sk-sub-tab-filter">
            <a href="<?php echo esc_url( add_query_arg( [ 'tab' => 'subscription_packs' ], $link ) ); ?>"
               class="sk-sub-tab<?php echo 'subscription_orders' !== $active_tab ? ' active' : ''; ?>">
                <i class="fas fa-box"></i>
                <?php esc_html_e( 'Pakete', 'sk-core' ); ?>
                <?php if ( $pack_count > 0 ) : ?>
                    <span class="sk-sub-tab-count"><?php echo (int) $pack_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="<?php echo esc_url( add_query_arg( [ 'tab' => 'subscription_orders' ], $link ) ); ?>"
               class="sk-sub-tab<?php echo 'subscription_orders' === $active_tab ? ' active' : ''; ?>">
                <i class="fas fa-receipt"></i>
                <?php esc_html_e( 'Bestellungen', 'sk-core' ); ?>
                <?php if ( $order_count > 0 ) : ?>
                    <span class="sk-sub-tab-count"><?php echo (int) $order_count; ?></span>
                <?php endif; ?>
            </a>
        </div>

        <?php if ( 'subscription_orders' === $active_tab ) : ?>
            <div class="sk-subscription-orders-content">
                <?php
                sk_get_template_part(
                    'dashboard/order-listing', '',
                    [
                        'is_subscription'     => true,
                        'subscription_orders' => $orders_data,
                    ]
                );
                ?>
            </div>
            <?php do_action( 'sk_subscription_content_inside_after' ); ?>
            </div><!-- .sk-dashboard-content -->
            <?php
            do_action( 'sk_dashboard_content_after' );
            do_action( 'sk_subscription_content_after' );
            ?>
            </div><!-- .sk-dashboard-wrap -->
            <?php do_action( 'sk_dashboard_wrap_end' ); ?>
            <?php return; ?>
        <?php endif; ?>

        <div class="sk-subscription-content">

            <?php // ── Flash messages ───────────────────────────────────────── ?>
            <?php if ( isset( $_GET['msg'] ) ) :
                $msg = sanitize_text_field( wp_unslash( $_GET['msg'] ) );
                if ( 'dps_sub_cancelled' === $msg ) :
                    if ( $subscription && $subscription->has_active_cancelled_subscription() ) {
                        $date   = sk_format_date( $subscription->get_pack_end_date() );
                        $notice = sprintf( __( 'Your subscription has been cancelled! However it\'s is still active till %s', 'sk-core' ), $date );
                    } else {
                        $notice = __( 'Your subscription has been cancelled!', 'sk-core' );
                    }
                    ?>
                    <div class="sk-message"><p><?php echo esc_html( $notice ); ?></p></div>
                <?php endif;
                if ( 'dps_sub_activated' === $msg ) : ?>
                    <div class="sk-message"><?php esc_html_e( 'Your subscription has been re-activated!', 'sk-core' ); ?></div>
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

            <?php // ── Pack cards ───────────────────────────────────────────── ?>
            <?php if ( $subscription_packs->have_posts() ) : ?>
                <div class="pack_content_wrapper">
                    <?php
                    while ( $subscription_packs->have_posts() ) :
                        $subscription_packs->the_post();

                        $sub_pack           = sk()->subscription->get( get_the_ID() );
                        $pack_id            = apply_filters( 'sk_vendor_subscription_package_id', get_the_ID() );
                        $is_current         = Helper::is_vendor_subscribed_pack( $pack_id ) || Helper::pack_renew_seller( $pack_id );
                        ?>
                        <div class="product_pack_item <?php echo $is_current ? 'current_pack' : ''; ?>">

                            <div class="pack_price">
                                <span class="dps-amount"><?php echo wp_kses_post( wc_price( $sub_pack->get_price() ) ); ?></span>
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
                                <?php if ( has_post_thumbnail() ) : ?>
                                    <div class="dst-sub-thumb"><?php the_post_thumbnail( 'medium', [ 'loading' => 'lazy' ] ); ?></div>
                                <?php endif; ?>

                                <?php
                                $short_desc = wp_strip_all_tags( get_the_excerpt() );
                                if ( $short_desc ) : ?>
                                    <div class="pack_short_desc"><p><?php echo esc_html( $short_desc ); ?></p></div>
                                <?php endif; ?>

                                <div class="pack_data_option">
                                    <?php
                                    $no_of_product = $sub_pack->get_number_of_products();
                                    if ( '-1' === $no_of_product ) {
                                        echo sprintf( '<strong>%s</strong> %s <br />', esc_html__( 'Unlimited', 'sk-core' ), esc_html__( 'Products', 'sk-core' ) );
                                    } else {
                                        echo sprintf( '<strong>%d</strong> %s <br />', (int) $no_of_product, esc_html__( 'Products', 'sk-core' ) );
                                    }
                                    ?>
                                    <?php
                                    if ( empty( $sub_pack->get_pack_valid_days() ) ) {
                                        echo sprintf( '%1$s<br /><strong>%2$s</strong> %3$s', esc_html__( 'For', 'sk-core' ), esc_html__( 'Unlimited', 'sk-core' ), esc_html__( 'Days', 'sk-core' ) );
                                    } else {
                                        echo sprintf( '%1$s<br /><strong>%2$s</strong> %3$s', esc_html__( 'For', 'sk-core' ), esc_html( $sub_pack->get_pack_valid_days() ), esc_html__( 'Days', 'sk-core' ) );
                                    }
                                    ?>                                </div>

                                <?php
                                // Verkaufsargument: diese Moeglichkeiten gibt es erst ab einer
                                // bestimmten Paketgroesse, die Karte zaehlt sie auf.
                                if ( class_exists( \SK\Modules\ShopImport\Variants::class )
                                    && \SK\Modules\ShopImport\Variants::pack_allows( (int) get_the_ID() ) ) :
                                    ?>
                                    <ul class="pack_features">
                                        <li><i class="fas fa-file-import"></i> Woo &amp; Shopify Produkt Import</li>
                                        <li><i class="fas fa-layer-group"></i> Variable Produkte</li>
                                        <li><i class="fas fa-bolt"></i> Adaptive Preise in Sats</li>
                                        <li><i class="fas fa-chart-bar"></i> Umsatz &amp; CSV-Export</li>
                                        <li><i class="fas fa-envelope"></i> Bestellungen per E-Mail</li>
                                        <li><i class="fas fa-truck"></i> Sendungsverfolgung</li>
                                    </ul>
                                    <button type="button" class="pack_feature_more" data-sk-pack-info>
                                        Mehr erfahren
                                    </button>
                                <?php endif; ?>
                            </div>

                            <div class="buy_pack_button">
                                <?php if ( Helper::is_vendor_subscribed_pack( $pack_id ) ) : ?>
                                    <a href="<?php echo esc_url( get_permalink( get_the_ID() ) ); ?>" class="sk-btn sk-btn-theme buy_product_pack">
                                        <?php esc_html_e( 'Your Pack', 'sk-core' ); ?>
                                    </a>

                                <?php elseif ( Helper::pack_renew_seller( $pack_id ) ) : ?>
                                    <a href="<?php echo esc_url( do_shortcode( '[add_to_cart_url id="' . $pack_id . '"]' ) ); ?>" class="sk-btn sk-btn-theme buy_product_pack">
                                        <?php esc_html_e( 'Renew', 'sk-core' ); ?>
                                    </a>

                                <?php elseif ( ! Helper::vendor_has_subscription( sk_get_current_user_id() ) ) : ?>
                                    <a href="<?php echo esc_url( do_shortcode( '[add_to_cart_url id="' . get_the_ID() . '"]' ) ); ?>" class="sk-btn sk-btn-theme buy_product_pack">
                                        <?php esc_html_e( 'Buy Now', 'sk-core' ); ?>
                                    </a>

                                <?php else : ?>
                                    <a href="<?php echo esc_url( do_shortcode( '[add_to_cart_url id="' . get_the_ID() . '"]' ) ); ?>" class="sk-btn sk-btn-theme buy_product_pack">
                                        <?php esc_html_e( 'Switch Plan', 'sk-core' ); ?>
                                    </a>

                                <?php endif; ?>
                            </div>

                            <div class="pack_short_desc"><?php echo $pack_text_only; ?></div>

                        </div>
                    <?php endwhile; wp_reset_postdata(); ?>
                </div>

                <?php
                // Einmal je Seite, nicht je Karte — alle Knoepfe oeffnen dasselbe Modal.
                if ( class_exists( \SK\Modules\ShopImport\Variants::class ) ) {
                    include SK_SHOP_IMPORT_PATH . '/templates/pack-info-modal.php';
                }
                ?>

            <?php else : ?>
                <h3><?php esc_html_e( 'No subscription pack has been found!', 'sk-core' ); ?></h3>
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
