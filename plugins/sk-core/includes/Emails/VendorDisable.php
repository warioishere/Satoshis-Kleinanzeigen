<?php

namespace SK\Core\Emails;

use WC_Email;
use SK\Core\Vendor\Vendor;

class VendorDisable extends WC_Email {

    /**
    * Constructor.
    */
    public function __construct() {
        $this->id             = 'sk_email_vendor_disable';
        $this->title          = __( 'SK Vendor Disable', 'sk' );
        $this->description    = __( 'This email is set to a vendor when his account is deactivated by admin', 'sk' );
        $this->template_html  = 'emails/vendor-disabled.php';
        $this->template_plain = 'emails/plain/vendor-disabled.php';
        $this->template_base  = SK_CORE_DIR . '/templates/';
        $this->placeholders   = [
            '{first_name}'   => '',
            '{last_name}'    => '',
            '{display_name}' => '',
            // Only for backward compatibility.
            '{site_name}' => $this->get_from_name(),
        ];

        // Triggers for this email
        add_action( 'sk_vendor_disabled', array( $this, 'trigger' ) );

        // Call parent constructor
        parent::__construct();

        $this->recipient = 'vendor@ofthe.product';
    }

    /**
    * Get email subject.
    * @return string
    */
    public function get_default_subject() {
        return __( '[{site_title}] Your account is deactivated', 'sk' );
    }

    /**
    * Get email heading.
    * @return string
    */
    public function get_default_heading() {
        return __( 'Your vendor account is deactivated', 'sk' );
    }

    /**
    * Trigger the email.
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

        $seller_email                          = $seller->get_email();
        $this->placeholders['{first_name}']    = $seller->get_first_name();
        $this->placeholders['{last_name}']     = $seller->get_last_name();
        $this->placeholders['{display_name}']  = $seller->get_name();

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
            $this->template_html, array(
                'email_heading'      => $this->get_heading(),
                'additional_content' => $this->get_additional_content(),
                'sent_to_admin'      => false,
                'plain_text'         => false,
                'email'              => $this,
                'data'               => $this->placeholders,
            ), 'sk/', $this->template_base
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
            $this->template_plain, array(
                'email_heading'      => $this->get_heading(),
                'additional_content' => $this->get_additional_content(),
                'sent_to_admin'      => false,
                'plain_text'         => true,
                'email'              => $this,
                'data'               => $this->placeholders,
            ), 'sk/', $this->template_base
        );
    }

    /**
    * Initialize settings form fields.
    */
    public function init_form_fields() {
        $placeholdrs = $this->placeholders;
        unset( $placeholdrs['{site_name}'] );
        /* translators: %s: list of placeholders */
        $placeholder_text  = sprintf( __( 'Available placeholders: %s', 'sk' ), '<code>' . implode( '</code>, <code>', array_keys( $this->placeholders ) ) . '</code>' );
        $this->form_fields = array(
            'enabled' => array(
                'title'         => __( 'Enable/Disable', 'sk' ),
                'type'          => 'checkbox',
                'label'         => __( 'Enable this email notification', 'sk' ),
                'default'       => 'yes',
            ),

            'subject' => array(
                'title'         => __( 'Subject', 'sk' ),
                'type'          => 'text',
                'desc_tip'      => true,
                'description'   => $placeholder_text,
                'placeholder'   => $this->get_default_subject(),
                'default'       => '',
            ),
            'heading' => array(
                'title'         => __( 'Email heading', 'sk' ),
                'type'          => 'text',
                'desc_tip'      => true,
                'description'   => $placeholder_text,
                'placeholder'   => $this->get_default_heading(),
                'default'       => '',
            ),
            'additional_content' => array(
                'title'       => __( 'Additional content', 'sk' ),
                'description' => __( 'Text to appear below the main email content.', 'sk' ) . ' ' . $placeholder_text,
                'css'         => 'width:400px; height: 75px;',
                'placeholder' => __( 'N/A', 'sk' ),
                'type'        => 'textarea',
                'default'     => $this->get_default_additional_content(),
                'desc_tip'    => true,
            ),
            'email_type' => array(
                'title'         => __( 'Email type', 'sk' ),
                'type'          => 'select',
                'description'   => __( 'Choose which format of email to send.', 'sk' ),
                'default'       => 'html',
                'class'         => 'email_type wc-enhanced-select',
                'options'       => $this->get_email_type_options(),
                'desc_tip'      => true,
            ),
        );
    }
}
