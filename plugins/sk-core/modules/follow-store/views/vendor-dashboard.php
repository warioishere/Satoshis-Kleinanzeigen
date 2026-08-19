<?php
/**
 * Vendor Followers Dashboard Template
 *
 * @var int[]    $customers   Array of follower user IDs
 * @var object[] $followers   Associative array keyed by user ID with followed_at property
 *
 */

do_action( 'sk_dashboard_wrap_start' );
?>

<div class="sk-dashboard-wrap">
    <?php do_action( 'sk_dashboard_content_before' ); ?>

    <div class="sk-dashboard-content">

        <div class="sk-followers-page-header">
            <h2>
                <i class="fas fa-heart"></i>
                <?php esc_html_e( 'Follower', 'sk-core' ); ?>
                <?php if ( ! empty( $customers ) ) : ?>
                    <span class="sk-followers-total"><?php echo count( $customers ); ?></span>
                <?php endif; ?>
            </h2>
        </div>

        <?php if ( empty( $customers ) ) : ?>

            <div class="sk-followers-empty">
                <i class="fas fa-heart-broken"></i>
                <p><?php esc_html_e( 'Noch keine Follower vorhanden.', 'sk-core' ); ?></p>
            </div>

        <?php else : ?>

            <div class="sk-followers-grid">
                <?php foreach ( $customers as $customer_id ) :
                    $first = get_user_meta( $customer_id, 'first_name', true );
                    $last  = get_user_meta( $customer_id, 'last_name', true );
                    $name  = trim( $first . ' ' . $last );
                    if ( ! $name ) {
                        $user = get_userdata( $customer_id );
                        $name = $user ? $user->display_name : '';
                    }
                    if ( ! $name ) {
                        $name = sprintf( '(%s)', __( 'kein Name', 'sk-core' ) );
                    }

                    $follower    = $followers[ $customer_id ];
                    $followed_at = $follower->followed_at;
                    $diff        = human_time_diff( strtotime( $followed_at ), current_time( 'timestamp' ) );
                ?>
                    <?php $store_url = sk_get_store_url( $customer_id ); ?>
                    <div class="sk-follower-card">
                        <a href="<?php echo esc_url( $store_url ); ?>" class="sk-follower-card__avatar">
                            <?php echo get_avatar( $customer_id, 48 ); ?>
                        </a>
                        <div class="sk-follower-card__info">
                            <a href="<?php echo esc_url( $store_url ); ?>" class="sk-follower-card__name"><strong><?php echo esc_html( $name ); ?></strong></a>
                            <span class="sk-follower-card__since">
                                <i class="fas fa-clock"></i>
                                <?php echo esc_html( sprintf( __( 'vor %s', 'sk-core' ), $diff ) ); ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>

    </div><!-- .sk-dashboard-content -->
</div><!-- .sk-dashboard-wrap -->

<?php do_action( 'sk_dashboard_wrap_end' ); ?>
