<?php

namespace SK\Core\Announcement;

defined( 'ABSPATH' ) || exit;

use SK\Core\Abstracts\SkBackgroundProcesses;

if ( ! class_exists( 'WC_Email', false ) ) {
    include_once dirname( WC_PLUGIN_FILE ) . '/includes/emails/class-wc-email.php';
}

/**
 * Background Process Class
 */
class BackgroundProcess extends SkBackgroundProcesses {

    /**
     * @var string
     */
    protected $action = 'sk_announcement_emails';

    /**
     * Task
     *
     * Override this method to perform any actions required on each
     * queue item. Return the modified item for further processing
     * in the next pass through. Or, return false to remove the
     * item from the queue.
     *
     * @param mixed $item Queue item to iterate over
     *
     * @return mixed
     */
    public function task( $payload ) {
        $post_id   = $payload['post_id'];
        $seller_id = $payload['sender_id'];
        $notice_id = $payload['notice_id'];

        if ( ! empty( $seller_id ) ) {
            do_action( 'sk_pro_process_announcement_background_process', $seller_id, $post_id, $notice_id );
            sk_log( sprintf( 'Mail send to %d', $seller_id ) );
        }

        return false;
    }

    /**
     * Complete
     *
     * Override if applicable, but ensure that the below actions are
     * performed, or, call parent::complete().
     */
    public function complete() {
        sk_log( 'Sending process completed' );
    }
}
