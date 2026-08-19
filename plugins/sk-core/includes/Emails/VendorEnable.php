<?php

namespace SK\Core\Emails;

use WC_Email;
use SK\Core\Vendor\Vendor;

class VendorEnable extends WC_Email {

    /**
     * Constructor.
     */
    public function __construct() {
        $this->id             = 'sk_email_vendor_enable';
        $this->title          = __( 'SK Vendor Enable', 'sk-core' );
        $this->description    = __( 'This email is set to a vendor when he enabled from admin settings', 'sk-core' );
        $this->template_html  = 'emails/vendor-enabled.php';
        $this->template_plain = 'emails/plain/vendor-enabled.php';
        $this->template_base  = SK_CORE_DIR . '/templates/';
        $this->placeholders   = [
            '{first_name}'   => '',
            '{last_name}'    => '',
            '{display_name}' => '',
            // Only for backward compatibility.
            '{site_name}' => $this->get_from_name(),
        ];

        // Triggers for this email
        add_action( 'sk_vendor_enabled', [ $this, 'trigger' ] );

        // Call parent constructor
        parent::__construct();

        $this->recipient = 'vendor@ofthe.product';
    }

    /**
     * Get an email subject.
     *
     * @return string
     */
    public function get_default_subject() {
        return __( '[{site_title}] Your account is activated', 'sk-core' );
    }

    /**
     * Get email heading.
     *
     * @return string
     */
    public function get_default_heading() {
        return __( 'Your vendor account is activated', 'sk-core' );
    }

    /**
     * Trigger this email.
     */
    public function trigger( $seller_id ) {
        if ( ! $this->is_enabled() ) {
            return;
        }

        $this->setup_locale();

        $seller = new Vendor( $seller_id );
        if ( ! $seller->get_id() ) {
            return;
        }

        $seller_email = $seller->get_email();

        $this->placeholders['{first_name}']   = $seller->get_first_name();
        $this->placeholders['{last_name}']    = $seller->get_last_name();
        $this->placeholders['{display_name}'] = $seller->get_name();

        $this->send( $seller_email, $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );

        $this->restore_locale();
    }

    /**
     * Get content html.
     *
     * @access public
     * @return string
     */
    public function get_content_html() {
        return wc_get_template_html(
            $this->template_html, [
                'email_heading'      => $this->get_heading(),
                'additional_content' => $this->get_additional_content(),
                'sent_to_admin'      => false,
                'plain_text'         => false,
                'email'              => $this,
                'data'               => $this->placeholders,
            ], 'sk/', $this->template_base
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
            $this->template_plain, [
                'email_heading'      => $this->get_heading(),
                'additional_content' => $this->get_additional_content(),
                'sent_to_admin'      => false,
                'plain_text'         => true,
                'email'              => $this,
                'data'               => $this->placeholders,
            ], 'sk/', $this->template_base
        );
    }

    /**
     * Initialize settings form fields.
     */
    public function init_form_fields() {
        $placeholders = $this->placeholders;
        unset( $placeholders['{site_name}'] );
        /* translators: %s: list of placeholders */
        $placeholder_text  = sprintf( __( 'Available placeholders: %s', 'sk-core' ), '<code>' . implode( '</code>, <code>', array_keys( $placeholders ) ) . '</code>' );
        $this->form_fields = [
            'enabled' => [
                'title'   => __( 'Enable/Disable', 'sk-core' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable this email notification', 'sk-core' ),
                'default' => 'yes',
            ],

            'subject'    => [
                'title'       => __( 'Subject', 'sk-core' ),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => $placeholder_text,
                'placeholder' => $this->get_default_subject(),
                'default'     => '',
            ],
            'heading'    => [
                'title'       => __( 'Email heading', 'sk-core' ),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => $placeholder_text,
                'placeholder' => $this->get_default_heading(),
                'default'     => '',
            ],
            'additional_content' => array(
                'title'       => __( 'Additional content', 'sk-core' ),
                'description' => __( 'Text to appear below the main email content.', 'sk-core' ) . ' ' . $placeholder_text,
                'css'         => 'width:400px; height: 75px;',
                'placeholder' => __( 'N/A', 'sk-core' ),
                'type'        => 'textarea',
                'default'     => $this->get_default_additional_content(),
                'desc_tip'    => true,
            ),
            'email_type' => [
                'title'       => __( 'Email type', 'sk-core' ),
                'type'        => 'select',
                'description' => __( 'Choose which format of email to send.', 'sk-core' ),
                'default'     => 'html',
                'class'       => 'email_type wc-enhanced-select',
                'options'     => $this->get_email_type_options(),
                'desc_tip'    => true,
            ],
        ];
    }
}
