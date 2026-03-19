<?php

namespace SK\Core\Emails;

use WC_Email;
use SK\Core\Vendor\Vendor;

/**
 * Customer Email to vendor from contact form widget.
 *
 * @class       SK_Email_Contact_Seller
 * @author      weDevs
 * @extends     WC_Email
 */
class ContactSeller extends WC_Email {

    /**
     * Reply email
     *
     *
     * @var string
     */
    private $from_email;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->id             = 'sk_contact_seller';
        $this->title          = __( 'SK Contact Vendor', 'sk-core' );
        $this->description    = __( 'These emails are sent to a vendor who is contacted by customer via contact form widget ', 'sk-core' );
        $this->template_html  = 'emails/contact-seller.php';
        $this->template_plain = 'emails/plain/contact-seller.php';
        $this->template_base  = SK_CORE_DIR . '/templates/';
        $this->placeholders   = [
            '{store_name}'     => '',
            '{customer_name}'  => '',
            '{customer_email}' => '',
            '{message}'        => '',
            // only for backward compatibility
            '{site_name}'      => $this->get_from_name(),
            '{seller_name}'    => '',
        ];

        // Triggers for this email
        add_action( 'sk_trigger_contact_seller_mail', array( $this, 'trigger' ), 30, 4 );

        // Call parent constructor
        parent::__construct();

        // Other settings
        $this->recipient = 'vendor@ofthe.product';
    }

    /**
     * Get email subject.
     *
     * @return string
     */
    public function get_default_subject() {
        return __( '[{customer_name}] sent you a message from your store at - {site_title}', 'sk-core' );
    }

    /**
     * Get email heading.
     *
     * @return string
     */
    public function get_default_heading() {
        return __( '{customer_name} - Sent a message from {site_title}', 'sk-core' );
    }

    /**
     * Trigger this email.
     */
    public function trigger( $seller_email, $contact_name, $contact_email, $contact_message ) {
        if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
            return;
        }
        $this->setup_locale();

        $this->from_email = $contact_email;

        $seller = get_user_by( 'email', $seller_email );
        $seller = new Vendor( $seller );

        $this->placeholders['{store_name}']     = $seller->get_shop_name();
        $this->placeholders['{customer_name}']  = $contact_name;
        $this->placeholders['{customer_email}'] = $contact_email;
        $this->placeholders['{message}']        = $contact_message;
        $this->placeholders['{seller_name}']    = $seller->get_shop_name(); // only for backward compatibility.
        $this->send( $seller_email, $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
        $this->restore_locale();
    }

    /**
     * Get the from address for outgoing emails.
     *
     * @return string
     */
    public function get_from_address( $from_email = '' ) {
        return $this->from_email;
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
                'sent_to_admin'      => true,
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
                'sent_to_admin'      => true,
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
        $placeholders = $this->placeholders;
        unset( $placeholders['{site_name}'], $placeholders['{seller_name}'] );
        /* translators: %s: list of placeholders */
        $placeholder_text  = sprintf( __( 'Available placeholders: %s', 'sk-core' ), '<code>' . implode( '</code>, <code>', array_keys( $placeholders ) ) . '</code>' );
        $this->form_fields = array(
            'enabled' => array(
                'title'         => __( 'Enable/Disable', 'sk-core' ),
                'type'          => 'checkbox',
                'label'         => __( 'Enable this email notification', 'sk-core' ),
                'default'       => 'yes',
            ),

            'subject' => array(
                'title'         => __( 'Subject', 'sk-core' ),
                'type'          => 'text',
                'desc_tip'      => true,
                'description'   => $placeholder_text,
                'placeholder'   => $this->get_default_subject(),
                'default'       => '',
            ),
            'heading' => array(
                'title'         => __( 'Email heading', 'sk-core' ),
                'type'          => 'text',
                'desc_tip'      => true,
                'description'   => $placeholder_text,
                'placeholder'   => $this->get_default_heading(),
                'default'       => '',
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
}
