<?php

namespace SK\Core\Announcement\Frontend;

use SK\Core\Announcement\Manager;
use SK\Core\Announcement\Single;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * SK Announcement class for Vendor
 *
 *
 */
class Template {
    /**
     * Constructor method
     *
     */
    public function __construct() {
        add_action( 'sk_load_custom_template', [ $this, 'load_announcement_template' ], 10 );
        add_action( 'sk_announcement_content_area_header', [ $this, 'load_header_template' ] );
        add_action( 'sk_announcement_content', [ $this, 'load_announcement_content' ], 10 );
        add_action( 'sk_single_announcement_content', [ $this, 'load_single_announcement_content' ], 10 );
        add_filter( 'sk_get_dashboard_nav', [ $this, 'add_announcement_page' ], 15 );
        add_filter( 'sk_get_dashboard_nav', [ $this, 'add_notification_badge' ], 20 );
        add_filter( 'sk_dashboard_nav_active', [ $this, 'active_announcement_nav_menu' ], 11, 3 );

        // Announcement ajax handling
        add_action( 'wp_ajax_sk_announcement_remove_row', [ $this, 'remove_announcement' ] );
        add_action( 'wp_ajax_sk_announcement_get_notice', [ $this, 'ajax_get_notice' ] );
        add_filter( 'sk_get_dashboard_nav_template_dependency', [ $this, 'announcement_template_dependency' ] );
    }

    /**
     * Render announcement template
     *
     *
     * @param array $query_vars
     *
     * @return void
     */
    public function load_announcement_template( $query_vars ) {
        if ( isset( $query_vars['announcement'] ) || isset( $query_vars['single-announcement'] ) ) {
            sk_get_template_part( 'announcement/announcement' );
            return;
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
    public function add_announcement_page( $urls ) {
        if ( current_user_can( 'skdar' ) ) {
            $urls['announcement'] = [
                'title'         => __( 'Announcements', 'sk' ),
                'icon'          => '<i class="fas fa-bell"></i>',
                'url'           => sk_get_navigation_url( 'announcement' ),
                'pos'           => 181,
                'icon_name'     => 'Megaphone',
                'permission'    => 'sk_view_announcement',
            ];
        }

        return $urls;
    }

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
     * Set announcement template dependency
     *
     * @param array $dependencies
     *
     * @return array
     */
    public function announcement_template_dependency( array $dependencies ): array {
		$dependencies['announcement'] = [
            [
                'slug' => 'announcement/announcement',
                'name' => '',
                'args' => [],
            ],
			[
				'slug' => 'announcement/listing-announcement',
				'name' => '',
                'args' => [],
			],
            [
                'slug' => 'announcement/single-announcement',
                'name' => '',
                'args' => [],
            ],
            [
                'slug' => 'announcement/no-announcement',
                'name' => '',
                'args' => [],
            ],
            [
                'slug' => 'announcement/header',
                'name' => '',
                'args' => [],
            ],
            [
                'slug' => 'announcement/single-notice',
                'name' => '',
                'args' => [],
            ],
		];

		return $dependencies;
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
                <p><?php esc_html_e( 'No Announcement found', 'sk' ); ?></p>
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
            wp_send_json_error( [ 'message' => __( 'Ankündigung nicht gefunden', 'sk' ) ] );
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
