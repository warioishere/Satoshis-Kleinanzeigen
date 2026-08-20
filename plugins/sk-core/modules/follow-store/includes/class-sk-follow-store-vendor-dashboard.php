<?php

class SK_Follow_Store_Vendor_Dashboard extends \SK\Core\Dashboard\DashboardModule {

    /**
     * User meta holding the digest opt-out.
     */
    const OPTOUT_META = 'sk_follow_store_digest_optout';

    public function config(): ?array {
        return [
            'slug'       => 'followers',
            'title'      => __( 'Follower', 'sk-core' ),
            'icon'       => '<i class="fas fa-heart"></i>',
            'icon_name'  => 'UserStar',
            'pos'        => 175,
            'permission' => 'sk_view_overview_menu',
            'template'   => [ $this, 'render_dashboard' ],
        ];
    }

    protected function register_extras(): void {
        add_action( 'init', [ $this, 'add_endpoint' ] );
        add_action( 'template_redirect', [ $this, 'handle_unsubscribe_link' ], 5 );
    }

    public function add_endpoint() {
        add_rewrite_endpoint( 'followers', EP_PAGES );
    }

    /**
     * Has this user switched the digest off?
     *
     * @param int $user_id
     *
     * @return bool
     */
    public static function is_opted_out( $user_id ) {
        return 'yes' === get_user_meta( absint( $user_id ), self::OPTOUT_META, true );
    }

    /**
     * Token for the one-click unsubscribe link in the digest mail.
     *
     * @param int $user_id
     *
     * @return string
     */
    public static function unsubscribe_token( $user_id ) {
        return wp_hash( 'sk_follow_store_unsub|' . absint( $user_id ) );
    }

    /**
     * Unsubscribe URL that works without being logged in.
     *
     * @param int $user_id
     *
     * @return string
     */
    public static function unsubscribe_url( $user_id ) {
        return add_query_arg(
            [
                'sk_fs_unsub' => absint( $user_id ),
                'token'       => self::unsubscribe_token( $user_id ),
            ],
            home_url( '/' )
        );
    }

    /**
     * Honour the unsubscribe link from the mail.
     *
     * Deliberately works for logged-out visitors — a link that first demands a
     * login is not an unsubscribe link.
     *
     * @return void
     */
    public function handle_unsubscribe_link() {
        if ( empty( $_GET['sk_fs_unsub'] ) || empty( $_GET['token'] ) ) {
            return;
        }

        $user_id = absint( $_GET['sk_fs_unsub'] );
        $token   = sanitize_text_field( wp_unslash( $_GET['token'] ) );

        if ( ! $user_id || ! hash_equals( self::unsubscribe_token( $user_id ), $token ) ) {
            return;
        }

        update_user_meta( $user_id, self::OPTOUT_META, 'yes' );

        // The dashboard is seller-only, so a logged-out reader coming from the
        // mail would be bounced to the front page and never see a confirmation.
        if ( get_current_user_id() === $user_id ) {
            wp_safe_redirect( add_query_arg( 'sk_fs_unsub_done', 1, sk_get_navigation_url( 'followers' ) ) );
            exit;
        }

        wp_die(
            esc_html__( 'Du bekommst keine E-Mails mehr über neue Inserate der Anbieter, denen du folgst.', 'sk-core' ),
            esc_html__( 'Abgemeldet', 'sk-core' ),
            [
                'response'  => 200,
                'back_link' => false,
                'link_url'  => home_url( '/' ),
                'link_text' => esc_html__( 'Zur Startseite', 'sk-core' ),
            ]
        );
    }

    /**
     * Save the opt-out checkbox.
     *
     * @return void
     */
    private function maybe_save_settings() {
        if ( empty( $_POST['sk_fs_settings'] ) ) {
            return;
        }

        if ( ! isset( $_POST['_sk_fs_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_sk_fs_nonce'] ) ), 'sk_fs_settings' ) ) {
            return;
        }

        $user_id = get_current_user_id();

        if ( ! $user_id ) {
            return;
        }

        if ( empty( $_POST['sk_fs_digest'] ) ) {
            update_user_meta( $user_id, self::OPTOUT_META, 'yes' );
        } else {
            delete_user_meta( $user_id, self::OPTOUT_META );
        }
    }

    /**
     * Stores the current user follows, ready for the store list template.
     *
     * @param int $user_id
     *
     * @return array
     */
    private function get_following( $user_id ) {
        $ids = sk_follow_store_get_following_ids( $user_id );

        if ( empty( $ids ) ) {
            return [ 'users' => null, 'count' => 0 ];
        }

        return sk_get_sellers( [ 'include' => $ids, 'number' => count( $ids ) ] );
    }

    public function render_dashboard( $query_vars ): void {
        $this->maybe_save_settings();

        $vendor_id = sk_get_current_user_id();
        $tab       = isset( $_GET['tab'] ) && 'following' === $_GET['tab'] ? 'following' : 'followers';

        $followers = sk_follow_store_get_vendor_followers( $vendor_id );
        $following = $this->get_following( $vendor_id );

        $response = [
            'vendor_id'       => $vendor_id,
            'tab'             => $tab,
            'followers'       => $followers['followers'],
            'customers'       => $followers['customers'],
            'following'       => $following,
            'following_count' => (int) $following['count'],
            'digest_enabled'  => ! self::is_opted_out( $vendor_id ),
            'unsubscribed'    => ! empty( $_GET['sk_fs_unsub_done'] ),
        ];

        sk_follow_store_get_template( 'vendor-dashboard', $response );
    }
}
