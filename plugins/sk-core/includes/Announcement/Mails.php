<?php

namespace SK\Core\Announcement;

use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Announcement Mails
 *
 */
class Mails {
    /**
     * Class constructor
     *
     */
    public function __construct() {
        add_action( 'sk_after_announcement_saved', [ $this, 'send_announcement_email' ] );
        add_action( 'future_to_publish', [ $this, 'send_scheduled_announcement_email' ] );
    }

    /**
     * Send announcement email
     *
     *
     * @param $announcement_id
     *
     * @return void
     */
    public function send_announcement_email( $announcement_id ) {
        $this->trigger_mail( $announcement_id );
    }

    /**
     * Send email for a scheduled announcement
     *
     *
     * @param WP_Post $post
     *
     * @return void
     */
    public function send_scheduled_announcement_email( $post ) {
        if ( 'sk_announcement' !== $post->post_type ) {
            return;
        }

        $this->trigger_mail( $post->ID );
    }

    /**
     * Trigger mail
     *
     *
     * @return void
     */
    protected function trigger_mail( $post_id ) {
        $manager      = sk_ext()->announcement->manager;
        $announcement = $manager->get_single_announcement( $post_id );

        if ( is_wp_error( $announcement ) ) {
            return;
        }

        if ( 'publish' !== $announcement->get_status() ) {
            return;
        }

        // Retrieve assigned sellers for this announcement.
        $assigned_sellers = $manager->get_assigned_seller_from_db( $announcement->get_id(), true );
        if ( empty( $assigned_sellers ) ) {
            return;
        }

        // Retrieve announcement arguments, processor for queue.
        $args      = [ 'id' => $post_id ];
        $processor = sk_ext()->announcement->processor;

        foreach ( $assigned_sellers as $vendor_id ) {
            // Ensures that `notice_id` is associated for this vendor & retrieve announcements.
            $args['vendor_id'] = $vendor_id;
            $announcements     = $manager->all( $args );

            $payload = [
                'post_id'   => $post_id,
                'sender_id' => $vendor_id,
                'notice_id' => ! empty( $announcements ) ? $announcements->get_notice_id() : 0, // Pass announcement `notice_id` for seller single notice info.
            ];

            $processor->push_to_queue( $payload );
        }

        $processor->save()->dispatch();
    }
}
