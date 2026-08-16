<?php

namespace SK\Modules\AntiFraud;

defined( 'ABSPATH' ) || exit;

/**
 * Watches for returning banned users.
 *
 * Checked at the two moments a scammer reveals himself: when he logs in (the
 * wallet or npub he authenticates with) and when he fills in his shop profile
 * (the Telegram handle or Lightning address he needs to get paid).
 *
 * A match notifies the admin. Suspending automatically is opt-in — a false
 * positive that instantly kills an honest vendor is worse than an e-mail.
 */
class BanWatcher {

    /** Don't re-check the same user more than once per hour. */
    const THROTTLE_TRANSIENT = 'sk_af_checked_';

    public function __construct() {
        // LNURL login fires wp_login; Nostr and BTC set the cookie directly.
        add_action( 'wp_login', [ $this, 'on_login' ], 20, 2 );
        add_action( 'set_auth_cookie', [ $this, 'on_auth_cookie' ], 20, 4 );

        // Payout and contact details are entered here.
        add_action( 'sk_store_profile_saved', [ $this, 'on_profile_saved' ], 20, 1 );
    }

    public function on_login( $login, $user ): void {
        if ( $user instanceof \WP_User ) {
            $this->check( $user->ID );
        }
    }

    public function on_auth_cookie( $cookie, $expire, $expiration, $user_id ): void {
        $this->check( (int) $user_id );
    }

    public function on_profile_saved( $store_id ): void {
        // Profile changes are the point — always check, no throttle.
        $this->check( (int) $store_id, true );
    }

    /**
     * @param bool $force Skip the throttle.
     */
    public function check( int $user_id, bool $force = false ): void {
        if ( ! $user_id || BanSignals::is_banned( $user_id ) ) {
            return;
        }

        $throttle_key = self::THROTTLE_TRANSIENT . $user_id;

        if ( ! $force && get_transient( $throttle_key ) ) {
            return;
        }

        set_transient( $throttle_key, 1, HOUR_IN_SECONDS );

        $matches = BanSignals::match( $user_id );

        if ( empty( $matches ) ) {
            return;
        }

        $known = (array) get_user_meta( $user_id, BanSignals::META_FLAGGED, true );
        $fresh = [];

        foreach ( $matches as $match ) {
            $key = $match['type'] . ':' . $match['value'];

            if ( ! in_array( $key, $known, true ) ) {
                $fresh[] = $key;
            }
        }

        // Only shout about something we haven't reported yet.
        if ( empty( $fresh ) ) {
            return;
        }

        update_user_meta( $user_id, BanSignals::META_FLAGGED, array_merge( $known, $fresh ) );

        $suspended = false;

        if ( 'on' === sk_get_option( 'sk_antifraud_ban_autosuspend', 'sk_antifraud', 'off' )
             && ! Suspension::is_suspended( $user_id ) ) {
            Suspension::suspend( $user_id, 'ban_signal_match' );
            $suspended = true;
        }

        $this->notify_admin( $user_id, $matches, $suspended );
    }

    private function notify_admin( int $user_id, array $matches, bool $suspended ): void {
        $to = get_option( 'admin_email' );

        if ( ! $to ) {
            return;
        }

        $user      = get_userdata( $user_id );
        $name      = $user ? $user->user_login : (string) $user_id;
        $site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

        $subject = sprintf(
            /* translators: 1: site name, 2: user name */
            __( '[%1$s] Gesperrtes Merkmal wieder aufgetaucht: %2$s', 'sk-core' ),
            $site_name,
            $name
        );

        $body  = __( 'Ein Account nutzt Merkmale, die zu einem gesperrten Account gehören.', 'sk-core' ) . "\n\n";
        $body .= VendorSummary::text( $user_id ) . "\n\n";
        $body .= __( 'Übereinstimmungen:', 'sk-core' ) . "\n";

        foreach ( $matches as $match ) {
            $label  = BanSignals::TYPES[ $match['type'] ] ?? $match['type'];
            $banned = get_userdata( $match['banned_user_id'] );

            $body .= sprintf(
                "- %s: %s (gesperrt: %s)\n",
                $label,
                $match['value'],
                $banned ? $banned->user_login : ( $match['banned_user_id'] ? '#' . $match['banned_user_id'] : __( 'manuell eingetragen', 'sk-core' ) )
            );
        }

        $body .= "\n";
        $body .= $suspended
            ? __( 'Der Account wurde automatisch offline genommen.', 'sk-core' ) . "\n"
            : __( 'Es wurde nichts automatisch gesperrt — bitte prüfen.', 'sk-core' ) . "\n";

        $body .= "\n" . __( 'Anbieter-Profil:', 'sk-core' ) . ' ' . admin_url( 'user-edit.php?user_id=' . $user_id ) . "\n";
        $body .= __( 'Anti-Fraud:', 'sk-core' ) . ' ' . admin_url( 'admin.php?page=sk&tab=antifraud&sub=signals' ) . "\n";

        wp_mail( $to, $subject, $body );
    }
}
