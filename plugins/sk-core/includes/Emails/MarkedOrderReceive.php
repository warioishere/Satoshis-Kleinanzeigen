<?php

namespace SK\Core\Emails;

use WC_Email;

/**
 * Notify admin & seller when order marked as receive by customer.
 *
 */
class MarkedOrderReceive extends WC_Email {

    /**
     * Constructor.
     */
    public function __construct() {
        $this->id             = 'sk_marked_order_receive';
        $this->title          = __( 'SK Marked Order Receive', 'sk' );
        $this->description    = __( 'This email will be sent to the vendor & the recipient of the corresponding order has been received by the customer.', 'sk' );
        $this->template_html  = 'emails/marked-order-receive.php';
        $this->template_base  = SK_CORE_DIR . '/templates/';
        $this->template_plain = 'emails/plain/marked-order-receive.php';
        $this->placeholders   = [
            '{order_id}'      => '',
            '{order_link}'    => '',
            '{customer_name}' => '',
            '{seller_name}'   => '',
        ];

        // Triggers for this email
        add_action( 'sk_marked_order_as_receive', [ $this, 'trigger' ], 30, 2 );

        // Call parent constructor
        parent::__construct();

        // Other settings
        $this->recipient = $this->get_option( 'recipient', 'seller@ofthe.order' );
    }

    /**
     * Get email subject.
     *
     *
     * @return string
     */
    public function get_default_subject() {
        return __( '[{site_title}] Your order id ({order_id}) has been marked as receive by {customer_name}', 'sk' );
    }

    /**
     * Get email heading.
     *
     *
     * @return string
     */
    public function get_default_heading() {
        return __( 'Order id #{order_id} has been marked as receive by {customer_name}', 'sk' );
    }

    /**
     * Default content to show below main email content.
     *
     *
     * @return string
     */
    public function get_default_additional_content() {
        return __( 'Thank you!', 'sk' );
    }

    /**
     * Trigger the sending of this email.
     *
     *
     * @param \WC_Order $order
     * @param int       $shipment_id
     *
     * @return void
     */
    public function trigger( $order, $shipment_id ) {
        if ( ! $this->is_enabled() ) {
            return;
        }

        if ( ! $order ) {
            return;
        }

        $order_id    = $order->get_id();
        $seller_id   = sk_get_seller_id_by_order( $order_id );
        $seller_info = sk()->vendor->get( $seller_id );
        $seller_name = $seller_info->get_name();
        $seller_mail = $seller_info->get_email();

        // Get the customer first and last name.
        $customer_first_name = $order->get_billing_first_name();
        $customer_last_name  = $order->get_billing_last_name();

        // Retrieve shipment info & handle info's as readable.
        $tracking_info = sk_ext()->shipment->get_shipping_tracking_info( $shipment_id, 'shipment_item' );
        $ship_info     = __( 'Shipping Provider: ', 'sk' ) . '<strong>' . $tracking_info->provider_label . '</strong><br />' . __( 'Shipping number: ', 'sk' ) . '<strong>' . $tracking_info->number . '</strong><br />' . __( 'Shipped date: ', 'sk' ) . '<strong>' . $tracking_info->date . '</strong><br />' . __( 'Shipped status: ', 'sk' ) . '<strong>' . $tracking_info->status_label . '</strong>';

        // Combine first and last name to get the full name.
        $customer_name = $customer_first_name . ' ' . $customer_last_name;
        $order_url     = esc_url(
            add_query_arg(
                [
                    'order_id'   => $order_id,
                    '_view_mode' => 'email',
                    'permission' => '1',
                ],
                sk_get_navigation_url( 'orders' )
            )
        );

        $this->placeholders['{order_id}']      = $order_id;
        $this->placeholders['{order_link}']    = $order_url;
        $this->placeholders['{customer_name}'] = $customer_name;
        $this->placeholders['{seller_name}']   = $seller_name;

        $this->data = [
            'order'         => $order,
            'order_id'      => $order_id,
            'ship_info'     => $ship_info,
            'order_link'    => $order_url,
            'vendor_name'   => $seller_name,
            'customer_name' => $customer_name,
            'tracking_info' => $tracking_info,
        ];

        // Triggered mails on multiple email based on user settings.
        $recipients = str_replace( 'seller@ofthe.order', '', $this->get_recipient() );
        $recipients = ! empty( $recipients ) ? $seller_mail . ',' . $recipients : $seller_mail;

        $this->setup_locale();
        $this->send( $recipients, $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
        $this->restore_locale();
    }

    /**
     * Get content html.
     *
     *
     * @access public
     *
     * @return string
     */
    public function get_content_html() {
        ob_start();
        wc_get_template(
            $this->template_html,
            [
                'email'              => $this,
                'order'              => $this->data['order'],
                'order_id'           => $this->data['order_id'],
                'ship_info'          => $this->data['ship_info'],
                'order_link'         => $this->data['order_link'],
                'plain_text'         => false,
                'vendor_name'        => $this->data['vendor_name'],
                'sent_to_admin'      => false,
                'email_heading'      => $this->get_heading(),
                'tracking_info'      => $this->data['tracking_info'],
                'customer_name'      => $this->data['customer_name'],
                'additional_content' => $this->get_additional_content(),
            ],
            'sk/',
            $this->template_base
        );
        return ob_get_clean();
    }

    /**
     * Get content plain.
     *
     *
     * @access public
     *
     * @return string
     */
    public function get_content_plain() {
        ob_start();
        wc_get_template(
            $this->template_plain,
            [
                'email'              => $this,
                'order_id'           => $this->data['order_id'],
                'plain_text'         => true,
                'order_link'         => $this->data['order_link'],
                'sent_to_admin'      => true,
                'email_heading'      => $this->get_heading(),
                'customer_name'      => $this->data['customer_name'],
                'additional_content' => $this->get_additional_content(),
            ],
            'sk/',
            $this->template_base
        );
        return ob_get_clean();
    }

    /**
     * Initialise settings form fields.
     *
     *
     * @return void
     */
    public function init_form_fields() {
        $placeholder_text = sprintf(
            /* translators: %s: list of placeholders */
            __( 'Available placeholders: %s', 'sk' ),
            '<code>' . esc_html( implode( '</code>, <code>', array_keys( $this->placeholders ) ) ) . '</code>'
        );

        $this->form_fields = [
            'enabled'            => [
                'title'   => __( 'Enable order marked as receive email notification', 'sk' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable order marked as receive notification', 'sk' ),
                'default' => 'yes',
            ],
            'recipient'          => [
                'title'       => __( 'Recipient(s)', 'sk' ),
                'type'        => 'text',
                /* translators: %s: WP admin email */
                'description' => sprintf( __( 'Enter recipients (comma separated) for this email. Defaults to %s.', 'sk' ), '<code>' . esc_attr( get_option( 'admin_email' ) ) . '</code>' ),
                'placeholder' => 'seller@ofthe.order',
                'default'     => 'seller@ofthe.order',
                'desc_tip'    => true,
            ],
            'subject'            => [
                'title'       => __( 'Subject', 'sk' ),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => $placeholder_text,
                'placeholder' => $this->get_default_subject(),
                'default'     => '',
            ],
            'heading'            => [
                'title'       => __( 'Email heading', 'sk' ),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => $placeholder_text,
                'placeholder' => $this->get_default_heading(),
                'default'     => '',
            ],
            'additional_content' => [
                'title'       => __( 'Additional content', 'sk' ),
                'description' => __( 'Text to appear below the main email content.', 'sk' ) . ' ' . $placeholder_text,
                'css'         => 'width:400px; height: 75px;',
                'placeholder' => __( 'N/A', 'sk' ),
                'type'        => 'textarea',
                'default'     => $this->get_default_additional_content(),
                'desc_tip'    => true,
            ],
            'email_type'         => [
                'title'       => __( 'Email type', 'sk' ),
                'type'        => 'select',
                'description' => __( 'Choose which format of email to send.', 'sk' ),
                'default'     => 'html',
                'class'       => 'email_type wc-enhanced-select',
                'options'     => $this->get_email_type_options(),
                'desc_tip'    => true,
            ],
        ];
    }
}
