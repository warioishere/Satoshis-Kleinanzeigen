<?php

namespace SK\Modules\AntiFraud;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps shop names unique.
 *
 * Shop names were only sanitized, never checked for collisions, so anyone could
 * take the name of an established vendor. Buyers who know that name from the
 * Telegram channel then extend their trust to the impostor — which is exactly
 * how the last ticket scam worked.
 *
 * Comparison is done on a normalised form, so "Florian_Stangl21", "florian
 * stangl 21" and "FlorianStangl21" all collide.
 */
class StoreNameGuard {

    public function __construct() {
        add_filter( 'sk_validate_store_name', [ $this, 'validate' ], 10, 3 );
    }

    /**
     * Strip everything that does not carry meaning for a human reader.
     */
    public static function normalize( string $name ): string {
        $name = mb_strtolower( trim( $name ) );
        $name = strtr( $name, [ 'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss' ] );

        return preg_replace( '/[^a-z0-9]/', '', $name );
    }

    /**
     * @param null|\WP_Error $result
     * @param string         $name
     * @param int            $user_id
     *
     * @return null|\WP_Error
     */
    public function validate( $result, $name, $user_id ) {
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $owner = self::find_owner( $name, (int) $user_id );

        if ( ! $owner ) {
            return null;
        }

        $this->notify_admin( (int) $user_id, $owner, $name );

        return new \WP_Error(
            'sk_store_name_taken',
            __( 'Dieser Shop-Name ist bereits vergeben. Bitte wähle einen anderen.', 'sk-core' )
        );
    }

    /**
     * Another vendor already using this name?
     *
     * @return \WP_User|null
     */
    public static function find_owner( string $name, int $exclude_user_id ) {
        global $wpdb;

        $normalized = self::normalize( $name );

        if ( '' === $normalized ) {
            return null;
        }

        $rows = $wpdb->get_results(
            "SELECT user_id, meta_value FROM {$wpdb->usermeta}
              WHERE meta_key = 'sk_store_name' AND meta_value <> ''"
        );

        foreach ( (array) $rows as $row ) {
            if ( (int) $row->user_id === $exclude_user_id ) {
                continue;
            }

            if ( self::normalize( (string) $row->meta_value ) !== $normalized ) {
                continue;
            }

            $owner = get_userdata( (int) $row->user_id );

            if ( $owner ) {
                return $owner;
            }
        }

        return null;
    }

    private function notify_admin( int $user_id, \WP_User $owner, string $name ): void {
        $to = get_option( 'admin_email' );

        if ( ! $to ) {
            return;
        }

        $site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

        $subject = sprintf(
            /* translators: 1: site name, 2: attempted shop name */
            __( '[%1$s] Versuchte Namens-Übernahme: %2$s', 'sk-core' ),
            $site_name,
            $name
        );

        $body  = sprintf(
            __( 'Ein Account wollte sich den Shop-Namen „%s" geben, der bereits vergeben ist. Das Speichern wurde abgelehnt.', 'sk-core' ),
            $name
        ) . "\n\n";

        $body .= __( '── Versuch von ──', 'sk-core' ) . "\n";
        $body .= VendorSummary::text( $user_id ) . "\n\n";

        $body .= __( '── Bestehender Inhaber des Namens ──', 'sk-core' ) . "\n";
        $body .= VendorSummary::text( $owner->ID ) . "\n\n";

        $body .= __( 'Profil des Versuchenden:', 'sk-core' ) . ' ' . admin_url( 'user-edit.php?user_id=' . $user_id ) . "\n";

        wp_mail( $to, $subject, $body );
    }
}
