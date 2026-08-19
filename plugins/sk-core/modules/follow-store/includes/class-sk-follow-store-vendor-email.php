<?php

use SK\Core\Vendor\Vendor;

class SK_Follow_Store_Vendor_Email extends WC_Email {

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
     * @var null|int
     */
    public $vendor = null;

    /**
     * Follow status
     *
     *
     * @var null|string
     */
    public $status = null;

    /**
     * Constructor Method
     */
    public function __construct() {
        $this->id             = 'vendor_new_store_follower';
        $this->title          = __( 'SK Vendor New Store Follower', 'sk-core' );
        $this->description    = __( 'Send email to vendor when there is a new store follower or someone unfollows a vendor.', 'sk-core' );
        $this->template_html  = 'emails/follow-store-vendor-email-html.php';
        $this->template_plain = 'emails/plain/follow-store-vendor-email-html.php';
        $this->template_base  = trailingslashit( SK_FOLLOW_STORE_VIEWS );
        $this->placeholders   = array(
            '{follower_name}' => '',
        );

        // Call parent constructor
        parent::__construct();

        $this->recipient = 'vendor@ofthe.product';

        add_action( 'sk_follow_store_toggle_status', array( $this, 'trigger' ), 15, 3 );
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
            'email_type' => array(
                'title'         => __( 'Email type', 'sk-core' ),
                'type'          => 'select',
                'description'   => __( 'Choose which format of email to send.', 'sk-core' ),
                'default'       => 'html',
                'class'         => 'email_type wc-enhanced-select',
                'options'       => $this->get_email_type_options(),
                'desc_tip'      => true,
            ),
        );
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
     * @param int $vendor_id Vendor ID.
     * @param int $follower_id Follower ID.
     * @param string $status Status.
     *
     * @return void
     */
    public function trigger( $vendor_id, $follower_id, $status ) {

        if ( ! $this->is_enabled() ) {
            return;
        }
        $this->setup_locale();

        $this->follower = get_userdata( $follower_id );
        $this->vendor   = sk()->vendor->get( $vendor_id );
        $this->status   = $status;

        if ( ! $this->get_email_recipient() ) {
            return;
        }

        $this->placeholders['{follower_name}'] = $this->follower->display_name;

        $this->send( $this->get_email_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );

        $this->restore_locale();
    }

    /**
     * Follower email
     *
     *
     * @return string|null
     */
    public function get_email_recipient() {
        if ( $this->vendor instanceof Vendor && is_email( $this->vendor->get_email() ) ) {
            return $this->vendor->get_email();
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
                    'follower' => $this->follower,
                    'status'   => $this->status,
                ),
            ),
            'sk/',
            $this->template_base
        );
    }

    /**
     * Email content plain
     *
     *
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
                    'follower' => $this->follower,
                    'status'   => $this->status,
                ),
            ),
            'sk/',
            $this->template_base
        );
    }
}
