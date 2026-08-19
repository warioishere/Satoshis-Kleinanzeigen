<?php

namespace SK\Core\Dashboard\Modules;

/**
 * Adds user avatar to primary/header nav menu and as mobile header icon.
 * Ported from kadence-child/functions.php.
 */
class ProfileAvatar {

    public function __construct() {
        add_filter( 'wp_nav_menu_items', [ $this, 'add_profile_icon_to_menu' ], 10, 2 );
        add_action( 'wp_body_open',      [ $this, 'output_mobile_header_icon' ] );
    }

    public function add_profile_icon_to_menu( string $items, object $args ): string {
        if ( $args->theme_location !== 'primary' && $args->theme_location !== 'header' ) {
            return $items;
        }

        if ( is_user_logged_in() ) {
            $current_user = wp_get_current_user();
            $avatar       = get_avatar( $current_user->ID, 40 );

            $profile_settings = get_user_meta( $current_user->ID, 'sk_profile_settings', true );
            if ( ! empty( $profile_settings['profile_picture_id'] ) ) {
                $id  = (int) $profile_settings['profile_picture_id'];
                $url = wp_get_attachment_image_url( $id, 'thumbnail' );
                if ( $url ) {
                    $avatar = '<img src="' . esc_url( $url ) . '" width="40" height="40" style="border-radius:50%;">';
                }
            }
        } else {
            $avatar = '<img src="https://cdn-icons-png.flaticon.com/512/149/149071.png" width="40" height="40" style="border-radius:50%;">';
        }

        $items .= '<li class="menu-item menu-item-profile"><a href="' . esc_url( home_url( '/dashboard/' ) ) . '" title="Mein Konto">' . $avatar . '</a></li>';
        return $items;
    }

    public function output_mobile_header_icon(): void {
        if ( function_exists( 'sk_get_navigation_url' ) ) {
            $url = sk_get_navigation_url( '' );
        } elseif ( function_exists( 'wc_get_page_id' ) ) {
            $url = get_permalink( wc_get_page_id( 'myaccount' ) );
        } else {
            $url = home_url( '/' );
        }

        if ( is_user_logged_in() ) {
            $uid        = get_current_user_id();
            $avatar_url = get_avatar_url( $uid, [ 'size' => 120 ] );
            $profile    = get_user_meta( $uid, 'sk_profile_settings', true );
            if ( ! empty( $profile['profile_picture_id'] ) ) {
                $tmp = wp_get_attachment_image_url( (int) $profile['profile_picture_id'], 'thumbnail' );
                if ( $tmp ) {
                    $avatar_url = $tmp;
                }
            }
        } else {
            $avatar_url = 'https://cdn-icons-png.flaticon.com/512/149/149071.png';
        }

        echo '<div class="header-profile-icon" aria-hidden="false">';
        echo '<a href="' . esc_url( $url ) . '" title="' . esc_attr__( 'Mein Konto', 'sk-core' ) . '">';
        echo '<img src="' . esc_url( $avatar_url ) . '" alt="' . esc_attr__( 'Mein Konto', 'sk-core' ) . '" loading="eager" decoding="async">';
        echo '</a></div>';
    }
}
