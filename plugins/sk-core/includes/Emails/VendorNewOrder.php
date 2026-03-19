<?php

namespace SK\Core\Emails;

use WC_Email;

/**
 * New Order Email.
 *
 * An email sent to the admin when a new order is received/paid for.
 *
 * @class       VendorNewOrder
 * @author      weDevs
 * @extends     WC_Email
 */
class VendorNewOrder extends WC_Email {
    public $order_info;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->id             = 'sk_vendor_new_order';
        $this->title          = __( 'SK Vendor New Order', 'sk-core' );
        $this->description    = __( 'New order emails are sent to the vendor when a new order is received.', 'sk-core' );
        $this->template_html  = 'emails/vendor-new-order.php';
        $this->template_plain = 'emails/plain/vendor-new-order.php';
        $this->template_base  = SK_CORE_DIR . '/templates/';
        $this->placeholders   = array(
            '{order_date}'   => '',
            '{order_number}' => '',
        );

        // Triggers for this email.
        add_action( 'woocommerce_order_status_pending_to_processing_notification', array( $this, 'trigger' ), 10, 2 );
        add_action( 'woocommerce_order_status_pending_to_completed_notification', array( $this, 'trigger' ), 10, 2 );
        add_action( 'woocommerce_order_status_pending_to_on-hold_notification', array( $this, 'trigger' ), 10, 2 );
        add_action( 'woocommerce_order_status_failed_to_processing_notification', array( $this, 'trigger' ), 10, 2 );
        add_action( 'woocommerce_order_status_failed_to_completed_notification', array( $this, 'trigger' ), 10, 2 );
        add_action( 'woocommerce_order_status_failed_to_on-hold_notification', array( $this, 'trigger' ), 10, 2 );
        add_action( 'woocommerce_order_status_cancelled_to_processing_notification', array( $this, 'trigger' ), 10, 2 );
        add_action( 'woocommerce_order_status_cancelled_to_completed_notification', array( $this, 'trigger' ), 10, 2 );
        add_action( 'woocommerce_order_status_cancelled_to_on-hold_notification', array( $this, 'trigger' ), 10, 2 );
		//Prevent admin email for sub-order
        add_filter( 'woocommerce_email_enabled_new_order', [ $this, 'prevent_sub_order_admin_email' ], 10, 2 );
        // Call parent constructor.
        parent::__construct();

        // Other settings.
        $this->recipient = 'vendor@ofthe.product';
    }

    /**
     * Get email subject.
     *
     * @return string
     */
    public function get_default_subject() {
        return __( '[{site_title}] New customer order ({order_number}) - {order_date}', 'sk-core' );
    }

    /**
     * Get email heading.
     *
     * @return string
     */
    public function get_default_heading() {
        return __( 'New Customer Order: #{order_number}', 'sk-core' );
    }

    /**
     * Trigger the sending of this email.
     *
     * @param int $order_id The Order ID.
     * @param array $order.
     */
    public function trigger( $order_id, $order = false ) {
        if ( ! $this->is_enabled() ) {
            return;
        }

        $this->setup_locale();
        if ( $order_id && ! is_a( $order, 'WC_Order' ) ) {
            $order = wc_get_order( $order_id );
        }

        if ( ! is_a( $order, 'WC_Order' ) ) {
            return;
        }

        $this->object                         = $order;
        $this->placeholders['{order_date}']   = wc_format_datetime( $this->object->get_date_created() );
        $this->placeholders['{order_number}'] = $this->object->get_order_number();

        $seller_id = sk_get_seller_id_by_order( $order_id );
        if ( empty( $seller_id ) ) {
            return;
        }

        // check has sub order
        if ( $order->get_meta( 'has_sub_order' ) ) {
            // same hook will be called again for sub-orders, so we don't need to process this from here.
            return;
        } else {
            $seller_info = get_userdata( $seller_id );
            if ( ! $seller_info ) {
                return;
            }
            $seller_email     = $seller_info->user_email;
            $this->order_info = sk_get_vendor_order_details( $order_id );
            $this->send( $seller_email, $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
        }
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
                'order'              => $this->object,
                'email_heading'      => $this->get_heading(),
                'additional_content' => $this->get_additional_content(),
                'sent_to_admin'      => true,
                'plain_text'         => false,
                'email'              => $this,
                'order_info'         => $this->order_info,
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
                'order'              => $this->object,
                'email_heading'      => $this->get_heading(),
                'additional_content' => $this->get_additional_content(),
                'sent_to_admin'      => true,
                'plain_text'         => true,
                'email'              => $this,
                'order_info'         => $this->order_info,
            ), 'sk/', $this->template_base
        );
    }

    /**
     * Initialise settings form fields.
     */
    public function init_form_fields() {
        /* translators: %s: list of placeholders */
        $placeholder_text  = sprintf( __( 'Available placeholders: %s', 'sk-core' ), '<code>' . implode( '</code>, <code>', array_keys( $this->placeholders ) ) . '</code>' );
        $this->form_fields = array(
            'enabled'    => array(
                'title'   => __( 'Enable/Disable', 'sk-core' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable this email notification', 'sk-core' ),
                'default' => 'yes',
            ),
            'subject'    => array(
                'title'       => __( 'Subject', 'sk-core' ),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => $placeholder_text,
                'placeholder' => $this->get_default_subject(),
                'default'     => '',
            ),
            'heading'    => array(
                'title'       => __( 'Email heading', 'sk-core' ),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => $placeholder_text,
                'placeholder' => $this->get_default_heading(),
                'default'     => '',
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

    /**
     * Prevent sub-order email for admin
     *
     * @param $bool
     * @param $order
     *
     * @return bool
     */
    public function prevent_sub_order_admin_email( $bool, $order ) {
        if ( ! $order ) {
            return $bool;
        }

        if ( $order->get_parent_id() ) {
            return false;
        }

        return $bool;
    }
}
