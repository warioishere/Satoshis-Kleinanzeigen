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

        add_action( 'sk_announcement_content_area_header', [ $this, 'load_header_template' ] );
        add_action( 'sk_announcement_content', [ $this, 'load_announcement_content' ], 10 );
        add_action( 'sk_single_announcement_content', [ $this, 'load_single_announcement_content' ], 10 );

        // Badge runs AFTER Registry's inject_menus (priority 50).
        add_filter( 'sk_get_dashboard_nav', [ $this, 'add_notification_badge' ], 60 );
        // Mark 'announcement' menu active when viewing a single announcement.
        add_filter( 'sk_dashboard_nav_active', [ $this, 'active_announcement_nav_menu' ], 60, 3 );

        // Announcement ajax handling
        add_action( 'wp_ajax_sk_announcement_remove_row', [ $this, 'remove_announcement' ] );
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
     * Render Announcement listing template header
     *
     *
     * @return void
     */
    public function load_header_template() {
        sk_get_template_part( 'announcement/header', '', [] );
    }

    /**
     * Load announcement Content
     *
     *
     * @return void
     */
    public function load_announcement_content() {
        $pagenum  = isset( $_GET['pagenum'] ) ? absint( $_GET['pagenum'] ) : 1; //phpcs:ignore
        $per_page = apply_filters( 'sk_announcement_list_number', 10 );

        $args = [
            'vendor_id' => sk_get_current_user_id(),
            'page'      => $pagenum,
            'per_page'  => $per_page,
            'status'    => 'publish',
            'return'    => 'all',
        ];

        $manager         = sk_ext()->announcement->manager;
        $announcements   = $manager->all( $args );
        $pagination_data = $manager->get_pagination_data( $args );

        sk_get_template_part(
            'announcement/listing-announcement', '', array_merge(
                [
                    'notices'      => $announcements,
                    'current_page' => $pagenum,
                ],
                $pagination_data
            )
        );
    }

    /**
     * Load Single announcement content
     *
     *
     * @return void
     */
    public function load_single_announcement_content() {
        $notice_id = get_query_var( 'single-announcement' );

        $manager = new Manager();
        $notice  = $manager->get_notice( $notice_id );

        if ( ! $notice instanceof Single ) {
            sk_get_template_part( 'announcement/no-announcement', '', [] );

            return;
        }

        if ( 'unread' === $notice->get_read_status() ) {
            $manager->update_read_status( $notice_id, 'read' );
            $notice = $notice->set_read_status( 'read' );
        }

        sk_get_template_part(
            'announcement/single-notice', '', [
                'notice' => $notice,
            ]
        );
    }

    /**
     * Add announcement page in seller dashboard
     *
     *
     * @param array $urls
     *
     * @return array $urls
     */
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
            'vendor_id'   => get_current_user_id(),
            'status'      => 'publish',
            'read_status' => 'unread',
            'return'      => 'count',
        ] );

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
     * Remove Announcement ajax
     *
     *
     * @return void
     */
    public function remove_announcement() {
        check_ajax_referer( 'sk_reviews' );

        $notice_id = isset( $_POST['row_id'] ) ? absint( $_POST['row_id'] ) : 0;
        if ( ! $notice_id ) {
            wp_send_json_error();
        }
        $result = sk_ext()->announcement->manager->delete_notice( $notice_id );

        ob_start();
        ?>
        <div class="sk-no-announcement">
            <div class="annoument-no-wrapper">
                <i class="fas fa-bell sk-announcement-icon"></i>
                <p><?php esc_html_e( 'No Announcement found', 'sk-core' ); ?></p>
            </div>
        </div>
        <?php
        $content = ob_get_clean();

        if ( $result ) {
            wp_send_json_success( $content );
        } else {
            wp_send_json_error();
        }
    }

    /**
     * AJAX: Get single announcement content.
     *
     */
    public function ajax_get_notice() {
        check_ajax_referer( 'sk_announcement_nonce', 'nonce' );

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
