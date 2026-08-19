<?php

class SK_Follow_Store_Email extends WC_Email {

    /**
     * Store Follower
     *
     *
     * @var null|WP_User
     */
    public $follower = null;

    /**
     * Following stores
     *
     *
     * @var null|array
     */
    public $vendors = null;

    public function __construct() {
        $this->id             = 'updates_for_store_followers';
        $this->title          = __( 'SK Updates for Store Followers', 'sk-core' );
        $this->description    = __( 'Send store updates to followers.', 'sk-core' );
        $this->template_html  = 'emails/follow-store-updates-email-html.php';
        $this->template_plain = 'emails/plain/follow-store-updates-email-html.php';
        $this->template_base  = trailingslashit( SK_FOLLOW_STORE_VIEWS );
        $this->customer_email = true;
        $this->placeholders   = array(
            '{follower_name}' => '',
        );
        // Call parent constructor
        parent::__construct();

        add_action( 'sk_follow_store_send_update_email', array( $this, 'trigger' ), 10, 2 );
    }

    /**
     * Email settings
     *
     *
     * @return void
     */
    public function init_form_fields() {
        /* translators: %s: list of placeholders */
        $placeholder_text  = sprintf( __( 'Available placeholders: %s', 'sk-core' ), '<code>' . implode( '</code>, <code>', array_keys( $this->placeholders ) ) . '</code>' );
        $this->form_fields = array(
            'enabled' => array(
                'title'         => __( 'Enable/Disable', 'sk-core' ),
                'type'          => 'checkbox',
                'label'         => __( 'Enable this email', 'sk-core' ),
                'default'       => 'yes',
            ),

            'subject' => array(
                'title'         => __( 'Subject', 'sk-core' ),
                'type'          => 'text',
                'desc_tip'      => true,
                'description'   => $placeholder_text,
                'placeholder'   => $this->get_default_subject(),
                'default'       => $this->get_default_subject(),
            ),

            'heading' => array(
                'title'         => __( 'Email heading', 'sk-core' ),
                'type'          => 'text',
                'desc_tip'      => true,
                'description'   => $placeholder_text,
                'placeholder'   => $this->get_default_heading(),
                'default'       => $this->get_default_heading(),
            ),
            'additional_content' => array(
                'title'       => __( 'Additional content', 'sk-core' ),
                'description' => __( 'Text to appear below the main email content.', 'sk-core' ) . ' ' . $placeholder_text,
                'css'         => 'width:400px; height: 75px;',
                'placeholder' => __( 'N/A', 'sk-core' ),
                'type'        => 'textarea',
                'default'     => $this->get_default_additional_content(),
                'desc_tip'    => true,
            ),
            'frequency' => array(
                'title'       => __( 'Frequency', 'sk-core' ),
                'type'        => 'select',
                'description' => __( 'Choose the delivery schedule for this notification.', 'sk-core' ),
                'default'     => 'daily',
                'options' => array(
                    'daily'  => __( 'Daily', 'sk-core' ),
                    'weekly' => __( 'Weekly', 'sk-core' ),
                ),
            'desc_tip'    => true,
            ),
            'email_type' => array(
                'title'       => __( 'Email type', 'sk-core' ),
                'type'        => 'select',
                'description' => __( 'Choose which format of email to send.', 'sk-core' ),
                'default'     => 'html',
                'class'       => 'email_type wc-enhanced-select',
                'options'     => $this->get_email_type_options(),
                'desc_tip'    => true,
            ),
        );
    }

    public function process_admin_options() {
        parent::process_admin_options();

        // do stuff the unschedule event
        $frequency = $this->get_option( 'frequency' );
        // Clear existing schedule
        $timestamp = wp_next_scheduled( 'sk_follow_store_send_updates' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'sk_follow_store_send_updates' );
        }

        // Always reschedule after clearing
        wp_schedule_event( time(), $frequency, 'sk_follow_store_send_updates' );
    }

    /**
     * Email default subject
     *
     *
     * @return string
     */
    public function get_default_subject() {
        return __( '{follower_name}, see new updates from {site_title}', 'sk-core' );
    }

    /**
     * Email default heading
     *
     *
     * @return string
     */
    public function get_default_heading() {
        return __( 'Latest updates from {site_title}', 'sk-core' );
    }

    /**
     * Send email
     *
     *
     * @param WP_User $follower
     * @param array   $vendors
     *
     * @return void
     */
    public function trigger( $follower, $vendors ) {
        $this->follower = $follower;
        $this->vendors  = $vendors;


        if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
            return;
        }
        $this->setup_locale();

        $this->placeholders['{follower_name}'] = $this->follower->display_name;

        $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );

        $this->restore_locale();
    }

    /**
     * Follower email
     *
     *
     * @return string|null
     */
    public function get_recipient() {
        if ( $this->follower instanceof WP_User && is_email( $this->follower->user_email ) ) {
            return $this->follower->user_email;
        }

        return null;
    }

    /**
     * Email content
     *
     *
     * @return string
     */
    public function get_content_html() {
        return wc_get_template_html(
            $this->template_html,
            array(
                'email_heading'      => $this->get_heading(),
                'additional_content' => $this->get_additional_content(),
                'sent_to_admin'      => false,
                'plain_text'         => false,
                'email'              => $this,
                'data'               => array(
                    'vendors' => $this->vendors,
                ),
            ),
            'sk/',
            $this->template_base
        );
    }

    /**
     * Get content plain.
     *
     * @access public
     * @return string
     */
    public function get_content_plain() {
        return wc_get_template_html(
            $this->template_plain,
            array(
                'email_heading'      => $this->get_heading(),
                'additional_content' => $this->get_additional_content(),
                'sent_to_admin'      => false,
                'plain_text'         => true,
                'email'              => $this,
                'data'               => array(
                    'vendors' => $this->vendors,
                ),
            ), 'sk/',
            $this->template_base
        );
    }

}
