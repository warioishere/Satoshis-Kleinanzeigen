<?php

class SK_Follow_Store_Email_Loader {

    /**
     * Class constructor
     *
     *
     * @return array
     */
    public function __construct() {
        add_filter( 'sk_email_classes', array( $this, 'add_email_class' ) );
        add_filter( 'sk_email_list', array( $this, 'add_email_template_file' ) );
        add_filter( 'sk_email_actions', array( $this, 'add_email_action' ) );
    }

    /**
     * Add email class
     *
     *
     * @param array $wc_emails
     *
     * @return array
     */
    public function add_email_class( $wc_emails ) {
        require_once SK_FOLLOW_STORE_INCLUDES . '/class-sk-follow-store-email.php';

        $wc_emails['SK_Follow_Store_Email'] = new SK_Follow_Store_Email();

        return $wc_emails;
    }

    /**
     * Add email template
     *
     *
     * @param array $template_files
     *
     * @return array
     */
    public function add_email_template_file( $template_files ) {
        $template_files[] = 'follow-store-updates-email-html.php';

        return $template_files;
    }

    /**
     * Add email action
     *
     *
     * @param array $actions
     *
     * @return array
     */
    public function add_email_action( $actions ) {
        $actions[] = 'sk_follow_store_send_update_email';

        return $actions;
    }
}
