<?php

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'SK_Subscription_Cancelled_vendor' ) ) :

    /**
     * New Product Published Email to vendor.
     *
     * An email sent to the vendor when a pending Product is published by admin.
     *
     * @class       SK_Subscription_Cancelled_vendor
     * @author      weDevs
     * @extends     WC_Email
     */
    class SK_Subscription_Cancelled_vendor extends WC_Email {

        /**
         * Subscription Object
         *
         * @var null
         */
        public $subscription = null;

        /**
         * Constructor Method
         */
        public function __construct() {
            $this->id             = 'SK_Subscription_Cancelled_vendor';
            $this->title          = __( 'SK Subscription Cancelled to Vendor', 'sk-core' );
            $this->description    = __( 'This email is sent to vendor when vendors or admin cancel subscriptions', 'sk-core' );
            $this->template_base  = DPS_PATH . '/templates/';
            $this->template_html  = 'emails/sk-subscription-cancelled-vendor.php';
            $this->template_plain = 'emails/plain/sk-subscription-cancelled-vendor.php';
            $this->placeholders   = [
                '{store_name}' => '',
            ];

            // Triggers for this email
            add_action( 'sk_subscription_cancelled', array( $this, 'trigger' ), 30, 2 );

            // Call parent constructor
            parent::__construct();

            $this->recipient = 'vendor@ofthe.site';
        }

        /**
         * Get email subject.
         *
         * @return string
         */
        public function get_default_subject() {
            return __( '[{site_title}] Subscription Cancelled', 'sk-core' );
        }

        /**
         * Get email heading.
         *
         * @return string
         */
        public function get_default_heading() {
            return __( 'Your Subscription Has Been Cancelled', 'sk-core' );
        }

        /**
         * Trigger the sending of this email.
         *
         * @param int $customer_id The customer ID.
         * @param int $product_id The product ID.
         */
        public function trigger( $customer_id, $product_id ) {
            if ( ! $this->is_enabled() ) {
                return;
            }

            $this->setup_locale();
            $vendor = sk()->vendor->get( $customer_id );
            if ( ! $vendor->get_id() ) {
                return;
            }
            $this->subscription = sk()->subscription->get( $product_id );

            $this->object                       = $vendor;
            $this->placeholders['{store_name}'] = $vendor->get_store_name();
            $this->recipient                    = $vendor->get_email();

            $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
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
                $this->template_html,
                array(
                    'vendor'             => $this->object,
                    'email_heading'      => $this->get_heading(),
                    'additional_content' => $this->get_additional_content(),
                    'sent_to_admin'      => false,
                    'plain_text'         => false,
                    'email'              => $this,
                    'subscription'       => $this->subscription,
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
                    'vendor'             => $this->object,
                    'email_heading'      => $this->get_heading(),
                    'additional_content' => $this->get_additional_content(),
                    'sent_to_admin'      => false,
                    'plain_text'         => true,
                    'email'              => $this,
                    'subscription'       => $this->subscription,
                ),
                'sk/',
                $this->template_base
            );
        }


        /**
         * Initialise settings form fields.
         */
        public function init_form_fields() {
            /* translators: %s: list of placeholders */
            $placeholder_text  = sprintf( __( 'Available placeholders: %s', 'sk-core' ), '<code>' . implode( '</code>, <code>', array_keys( $this->placeholders ) ) . '</code>' );
            $this->form_fields = array(
                'enabled'            => array(
                    'title'   => __( 'Enable/Disable', 'sk-core' ),
                    'type'    => 'checkbox',
                    'label'   => __( 'Enable this email notification', 'sk-core' ),
                    'default' => 'yes',
                ),
                'subject'            => array(
                    'title'       => __( 'Subject', 'sk-core' ),
                    'type'        => 'text',
                    'desc_tip'    => true,
                    'description' => $placeholder_text,
                    'placeholder' => $this->get_default_subject(),
                    'default'     => '',
                ),
                'heading'            => array(
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
                'email_type'         => array(
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
    }

endif;

return new SK_Subscription_Cancelled_vendor();
