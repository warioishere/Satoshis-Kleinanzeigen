<?php

namespace SK\Core\Announcement\Frontend;

use SK\Core\Announcement\Manager;
use SK\Core\Announcement\Single;
use SK\Core\Dashboard\DashboardModule;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * SK Announcement class for Vendor
 *
 *
 */
class Template extends DashboardModule {

    public function config(): ?array {
        if ( ! current_user_can( 'skdar' ) ) {
            return null;
        }
        return [
            'slug'       => 'announcement',
            'title'      => __( 'Announcements', 'sk-core' ),
            'icon'       => '<i class="fas fa-bell"></i>',
            'icon_name'  => 'Megaphone',
            'pos'        => 181,
            'permission' => 'sk_view_announcement',
            'template'   => [ $this, 'render_dashboard' ],
        ];
    }

    protected function register_extras(): void {
        // 'single-announcement' uses a different query var than 'announcement';
        // Registry dispatches only on the main slug so handle single directly.
        add_action( 'sk_load_custom_template', [ $this, 'load_single_template' ], 10 );

        // Badge runs AFTER Registry's inject_menus (priority 50).
        add_filter( 'sk_get_dashboard_nav', [ $this, 'add_notification_badge' ], 60 );
        // Mark 'announcement' menu active when viewing a single announcement.
        add_filter( 'sk_dashboard_nav_active', [ $this, 'active_announcement_nav_menu' ], 60, 3 );

        // Announcement ajax handling
        add_action( 'wp_ajax_sk_announcement_get_notice', [ $this, 'ajax_get_notice' ] );
    }

    /**
     * Render the announcement listing — called by DashboardRegistry dispatch.
     */
    public function render_dashboard( $query_vars ): void {
        sk_get_template_part( 'announcement/announcement' );
    }

    /**
     * Handle single announcement query var — not handled by Registry.
     */
    public function load_single_template( $query_vars ): void {
        if ( isset( $query_vars['single-announcement'] ) ) {
            sk_get_template_part( 'announcement/announcement' );
        }
    }

    /**
     * Add announcement page in seller dashboard
     *
     *
     * @param array $urls
     *
     * @return array $urls
    /**
     * Append unread notification badge to the announcement nav title.
     *
     *
     * @param array $nav
     * @return array
     */
    public function add_notification_badge( $nav ) {
        if ( ! is_user_logged_in() || ! isset( $nav['announcement'] ) ) {
            return $nav;
        }

        $manager = new Manager();
        $unread = $manager->all( [
            'vendor_id'   => sk_get_current_user_id(),
            'status'      => 'publish',
            'read_status' => 'unread',
            'return'      => 'count',
        ] );

        // This filter runs on every dashboard page, so a query error must not take
        // the whole navigation down with it.
        if ( is_wp_error( $unread ) ) {
            return $nav;
        }

        if ( $unread > 0 ) {
            $nav['announcement']['title'] .= ' <span class="sk-announcement-nav-badge">' . $unread . '</span>';
        }

        return $nav;
    }

    /**
     * Set announcement menu as active.
     *
     *
     * @param string $active_menu Currently active menu slug.
     * @param string $request_uri Request URI.
     * @param array  $query_vars  Currently active query vars.
     *
     * @return string
     */
    public function active_announcement_nav_menu( string $active_menu, $request_uri, array $query_vars ): string {
        if ( ! in_array( 'single-announcement', $query_vars, true ) ) {
            return $active_menu;
        }

        return 'announcement';
    }

    /**
     * AJAX: Get single announcement content.
     *
     */
    public function ajax_get_notice() {
        check_ajax_referer( 'sk_announcement_nonce', 'nonce' );

        if ( ! current_user_can( 'skdar' ) ) {
            wp_send_json_error();
        }

        $notice_id = isset( $_POST['notice_id'] ) ? absint( $_POST['notice_id'] ) : 0;
        if ( ! $notice_id ) {
            wp_send_json_error();
        }

        $manager = new Manager();
        $notice  = $manager->get_notice( $notice_id );

        if ( ! $notice instanceof Single ) {
            wp_send_json_error( [ 'message' => __( 'Ankündigung nicht gefunden', 'sk-core' ) ] );
        }

        // Mark as read
        if ( 'unread' === $notice->get_read_status() ) {
            $manager->update_read_status( $notice_id, 'read' );
        }

        wp_send_json_success( [
            'title'   => $notice->get_title(),
            'content' => wp_kses_post( wpautop( $notice->get_content() ) ),
            'date'    => sk_format_date( $notice->get_date() ),
        ] );
    }
}
