<?php
/**
 * Follower Dashboard Template
 *
 * @var int      $vendor_id
 * @var string   $tab             followers|following
 * @var object[] $followers       Keyed by user ID, carries followed_at
 * @var int[]    $customers       Follower user IDs
 * @var array    $following       Result of sk_get_sellers()
 * @var int      $following_count
 * @var bool     $digest_enabled
 * @var bool     $unsubscribed
 */

defined( 'ABSPATH' ) || exit;

do_action( 'sk_dashboard_wrap_start' );

$followers_url = sk_get_navigation_url( 'followers' );
?>

<div class="sk-dashboard-wrap">
    <?php do_action( 'sk_dashboard_content_before' ); ?>

    <div class="sk-dashboard-content">

        <div class="sk-review-page-header">
            <h2>
                <i class="fas fa-heart"></i>
                <?php esc_html_e( 'Follower', 'sk-core' ); ?>
            </h2>
        </div>

        <div class="sk-sub-tab-filter">
            <a href="<?php echo esc_url( $followers_url ); ?>"
               class="sk-sub-tab<?php echo 'followers' === $tab ? ' active' : ''; ?>">
                <i class="fas fa-heart"></i>
                <?php esc_html_e( 'Follower', 'sk-core' ); ?>
                <?php if ( ! empty( $customers ) ) : ?>
                    <span class="sk-sub-tab-count"><?php echo count( $customers ); ?></span>
                <?php endif; ?>
            </a>
            <a href="<?php echo esc_url( add_query_arg( [ 'tab' => 'following' ], $followers_url ) ); ?>"
               class="sk-sub-tab<?php echo 'following' === $tab ? ' active' : ''; ?>">
                <i class="fas fa-store"></i>
                <?php esc_html_e( 'Ich folge', 'sk-core' ); ?>
                <?php if ( $following_count > 0 ) : ?>
                    <span class="sk-sub-tab-count"><?php echo (int) $following_count; ?></span>
                <?php endif; ?>
            </a>
        </div>

        <?php if ( $unsubscribed ) : ?>
            <div class="sk-alert sk-alert-success">
                <?php esc_html_e( 'Du bekommst keine Neuigkeiten mehr per E-Mail.', 'sk-core' ); ?>
            </div>
        <?php endif; ?>

        <?php if ( 'following' === $tab ) : ?>

            <?php if ( empty( $following['users'] ) ) : ?>

                <div class="sk-followers-empty">
                    <i class="fas fa-store-slash"></i>
                    <p><?php esc_html_e( 'Du folgst noch keinem Anbieter.', 'sk-core' ); ?></p>
                </div>

            <?php else : ?>

                <?php
                if ( function_exists( 'sk_geo_remove_seller_listing_footer_content_hook' ) ) {
                    sk_geo_remove_seller_listing_footer_content_hook();
                }

                sk_get_template_part(
                    'store-lists-loop', false, [
                        'sellers'         => $following,
                        'limit'           => $following_count,
                        'offset'          => 0,
                        'paged'           => false,
                        'search_query'    => null,
                        'pagination_base' => null,
                        'per_row'         => 3,
                        'search_enabled'  => false,
                        'image_size'      => 'full',
                    ]
                );
                ?>

            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( add_query_arg( [ 'tab' => 'following' ], $followers_url ) ); ?>" class="sk-followers-digest-form">
                <?php wp_nonce_field( 'sk_fs_settings', '_sk_fs_nonce' ); ?>
                <input type="hidden" name="sk_fs_settings" value="1">
                <label class="sk-settings-checkbox">
                    <input type="checkbox" name="sk_fs_digest" value="1" <?php checked( $digest_enabled ); ?>>
                    <?php esc_html_e( 'Schick mir eine E-Mail, wenn Anbieter, denen ich folge, neue Inserate einstellen.', 'sk-core' ); ?>
                </label>
                <button type="submit" class="sk-btn sk-btn-theme"><?php esc_html_e( 'Speichern', 'sk-core' ); ?></button>
            </form>

        <?php elseif ( empty( $customers ) ) : ?>

            <div class="sk-followers-empty">
                <i class="fas fa-heart-broken"></i>
                <p><?php esc_html_e( 'Noch keine Follower vorhanden.', 'sk-core' ); ?></p>
            </div>

        <?php else : ?>

            <div class="sk-followers-grid">
                <?php
                foreach ( $customers as $customer_id ) :
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

                    // A follower row can outlive the account it points at.
                    if ( empty( $followers[ $customer_id ] ) ) {
                        continue;
                    }

                    $followed_at = $followers[ $customer_id ]->followed_at;
                    $diff        = human_time_diff( strtotime( $followed_at ), current_time( 'timestamp' ) );
                    $store_url   = sk_get_store_url( $customer_id );
                    ?>
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
