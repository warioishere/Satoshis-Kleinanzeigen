<?php

namespace SK\Core\Emails;

/**
 * SK email handler class
 *
 */
class Manager {

    /**
     * Load autometically when class initiate
     */
    /**
     * Placeholder email suffixes — generated addresses that cannot receive mail.
     */
    private static $placeholder_suffixes = [
        '@satoshiskleinanzeigen.space',
        '@satoshiskleinanzeigen',
        '@nostr.local',
        '@btc.local',
        '@lightning.local',
    ];

    public function __construct() {
        //SK Email filters for WC Email
        add_filter( 'woocommerce_email_classes', array( $this, 'load_sk_emails' ), 35 );
        add_filter( 'woocommerce_template_directory', array( $this, 'set_email_template_directory' ), 15, 2 );
        add_filter( 'woocommerce_email_actions', array( $this, 'register_email_actions' ) );

        // Block emails to placeholder addresses (generated for LNURL/Nostr/BTC users).
        add_filter( 'wp_mail', [ $this, 'block_placeholder_emails' ] );
    }

    /**
     * Prevent sending emails to auto-generated placeholder addresses.
     *
     * @param array $args wp_mail() arguments.
     * @return array Modified arguments (empty 'to' if placeholder).
     */
    public function block_placeholder_emails( $args ) {
        $to = is_array( $args['to'] ) ? $args['to'] : [ $args['to'] ];

        $filtered = array_filter( $to, function ( $email ) {
            return ! self::is_placeholder_email( $email );
        } );

        if ( empty( $filtered ) ) {
            // All recipients are placeholders — cancel the email.
            $args['to']      = '';
            $args['subject'] = '';
            $args['message'] = '';
        } else {
            $args['to'] = $filtered;
        }

        return $args;
    }

    /**
     * Check if an email address is a generated placeholder.
     *
     * The *.local suffixes are always generated. The site's own domain is a
     * special case: auto-created LNURL logins live there (satoshi-440@…), but
     * so do real mailboxes like info@ or admin@ — blocking the whole domain
     * silently swallowed every admin notification. Only the generated
     * local-part patterns count as placeholders there.
     */
    public static function is_placeholder_email( string $email ): bool {
        $email = strtolower( trim( $email ) );

        if ( '' === $email ) {
            return true;
        }

        // Real, configured mailboxes are never placeholders.
        if ( in_array( $email, self::get_real_mailboxes(), true ) ) {
            return false;
        }

        foreach ( self::$placeholder_suffixes as $suffix ) {
            if ( substr( $email, -strlen( $suffix ) ) !== $suffix ) {
                continue;
            }

            // Dedicated placeholder domains — nothing real ever lives there.
            if ( '.local' === substr( $suffix, -6 ) ) {
                return true;
            }

            // Own domain: only auto-generated local parts.
            return self::is_generated_local_part( $email );
        }

        return false;
    }

    /**
     * Mailboxes that must always be deliverable, even on the site's own domain.
     */
    private static function get_real_mailboxes(): array {
        $mailboxes = [
            (string) get_option( 'admin_email' ),
            (string) get_option( 'woocommerce_email_from_address' ),
        ];

        /**
         * Filter the addresses that are never treated as placeholders.
         *
         * @param array $mailboxes
         */
        $mailboxes = apply_filters( 'sk_email_real_mailboxes', $mailboxes );

        return array_filter( array_map( 'strtolower', array_map( 'trim', $mailboxes ) ) );
    }

    /**
     * Does the local part match one of our auto-generated login patterns?
     */
    private static function is_generated_local_part( string $email ): bool {
        $local = strstr( $email, '@', true );

        if ( false === $local || '' === $local ) {
            return true;
        }

        // LNURL-auth: "<prefix><counter>", prefix configurable, default "satoshi-".
        $prefix = (string) get_option( 'lnurl-auth-usercreation-prefix' );
        $prefix = strtolower( $prefix !== '' ? $prefix : 'satoshi-' );

        if ( 0 === strpos( $local, $prefix ) ) {
            return true;
        }

        // Nostr: raw 64-char hex pubkey as local part.
        if ( preg_match( '/^[0-9a-f]{64}$/', $local ) ) {
            return true;
        }

        return false;
    }

    /**
     * Get from name for email.
     *
     * @access public
     * @return string
     */
    public function get_from_name() {
        return wp_specialchars_decode( esc_html( get_option( 'woocommerce_email_from_name' ) ), ENT_QUOTES );
    }

    /**
     * Get from email address.
     *
     * @access public
     * @return string
     */
    public function get_from_address() {
        return sanitize_email( get_option( 'woocommerce_email_from_address' ) );
    }

    /**
     * Get admin email address
     *
     * @return string
     */
    public function admin_email() {
        return apply_filters( 'sk_email_admin_mail', get_option( 'admin_email' ) );
    }

    /**
     * Get user agent string
     *
     * @return string
     */
    public function get_user_agent() {
        $agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
        return substr( $agent, 0, 150 );
    }

    /**
     * Replace currency HTML entities with symbol
     *
     * @param string $amount
     *
     * @return string
     */
    public function currency_symbol( $amount ) {
        $price = wc_price( $amount, [ 'in_span' => false ] );

        return html_entity_decode( $price );
    }

    /**
     * Add SK Email classes in WC Email
     *
     *
     * @param array $wc_emails
     *
     * @return $wc_emails
     */
    public function load_sk_emails( $wc_emails ) {
        $wc_emails['SK_Email_New_Product']                = new NewProduct();
        $wc_emails['SK_Email_New_Product_Pending']        = new NewProductPending();
        $wc_emails['SK_Email_Product_Published']          = new ProductPublished();
        $wc_emails['SK_Email_New_Seller']                 = new NewSeller();
        $wc_emails['SK_Email_Contact_Seller']             = new ContactSeller();
        $wc_emails['SK_Email_New_Order']                  = new VendorNewOrder();
        $wc_emails['SK_Email_Completed_Order']            = new VendorCompletedOrder();
        $wc_emails['SK_Email_Vendor_Product_Review']      = new VendorProductReview();

        return apply_filters( 'sk_email_classes', $wc_emails );
    }

    /**
     * Set template override directory for SK Emails
     *
     *
     * @param string $template_dir
     *
     * @param string $template
     *
     * @return string
     */
    public function set_email_template_directory( $template_dir, $template ) {
        $sk_emails = apply_filters(
            'sk_email_list',
            array(
                'new-product.php',
                'new-product-pending.php',
                'product-published.php',
                'contact-seller.php',
                'new-seller-registered.php',
                'vendor-new-order.php',
                'vendor-completed-order.php',
            )
        );

        $template_name = basename( $template );

        if ( in_array( $template_name, $sk_emails, true ) ) {
            return 'sk';
        }

        return $template_dir;
    }

    /**
     * Register SK Email actions for WC
     *
     *
     * @param array $actions
     *
     * @return $actions
     */
    public function register_email_actions( $actions ) {
        $sk_email_actions = apply_filters(
            'sk_email_actions', array(
				'sk_new_product_added',
                'sk_product_updated',
				'sk_new_seller_created',
				'sk_pending_product_published_notification',
				'sk_trigger_contact_seller_mail',
				'woocommerce_order_status_completed_notification',
                'wp_set_comment_status',
                'comment_post',
            )
        );

        foreach ( $sk_email_actions as $action ) {
            $actions[] = $action;
        }

        return $actions;
    }

    /**
     * Send email to seller from the seller contact form
     *
     * @param string $seller_email
     * @param string $from_name
     * @param string $from_email
     * @param string $message
     *
     * @return void
     */
    public function contact_seller( $seller_email, $from_name, $from_email, $message ) {
        ob_start();
        sk_get_template_part( 'emails/contact-seller' );
        $body = ob_get_clean();

        $find = array(
            '%from_name%',
            '%from_email%',
            '%user_ip%',
            '%user_agent%',
            '%message%',
            '%site_name%',
            '%site_url%',
        );

        $replace = array(
            $from_name,
            $from_email,
            sk_get_client_ip(),
            $this->get_user_agent(),
            $message,
            $this->get_from_name(),
            home_url(),
        );
        // translators: %1: from name, %2: from name
        $subject = sprintf( __( '"%1$s" sent you a message from your "%2$s" store', 'sk-core' ), $from_name, $this->get_from_name() );
        $body = str_replace( $find, $replace, $body );
        $headers = array( "Reply-To: {$from_name}<{$from_email}>" );

        $this->send( $seller_email, $subject, $body, $headers );

        do_action(
            'sk_contact_seller_email_sent', array(
				'to'           => $seller_email,
				'subject'      => $subject,
				'message'      => $body,
				'sender_email' => $from_email,
				'sender_name'  => $from_name,
				'headers'      => $headers,
            )
        );
    }

    /**
     * Send email to admin once a new seller registered
     *
     * @param int $seller_id
     *
     * @return void
     */
    public function new_seller_registered_mail( $seller_id ) {
        ob_start();
        sk_get_template_part( 'emails/new-seller-registered' );
        $body = ob_get_clean();

        $seller = get_user_by( 'id', $seller_id );

        $find = array(
            '%seller_name%',
            '%store_url%',
            '%seller_edit%',
            '%site_name%',
            '%site_url%',
        );

        $replace = array(
            $seller->display_name,
            sk_get_store_url( $seller_id ),
            admin_url( 'user-edit.php?user_id=' . $seller_id ),
            $this->get_from_name(),
            home_url(),
        );
        // translators: %s: from name
        $body = str_replace( $find, $replace, $body );
        // translators: %s: from name
        $subject = sprintf( __( '[%s] New Vendor Registered', 'sk-core' ), $this->get_from_name() );

        $this->send( $this->admin_email(), $subject, $body );
        do_action( 'after_new_seller_registered_mail', $this->admin_email(), $subject, $body );
    }

    /**
     * Send email to admin once a product is added
     *
     * @param int $product_id
     * @param string $status
     *
     * @return void
     */
    public function new_product_added( $product_id, $status = 'pending' ) {
        $template = 'emails/new-product-pending';

        if ( 'publish' === $status ) {
            $template = 'emails/new-product';
        }
        ob_start();
        sk_get_template_part( $template );
        $body = ob_get_clean();

        $product       = wc_get_product( $product_id );
        $seller_id     = get_post_field( 'post_author', $product_id );
        $seller        = get_user_by( 'id', $seller_id );
        $category      = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'names' ) );
        $category_name = $category ? reset( $category ) : 'N/A';

        $find = array(
            '%title%',
            '%price%',
            '%seller_name%',
            '%seller_url%',
            '%category%',
            '%product_link%',
            '%site_name%',
            '%site_url%',
        );

        $replace = array(
            $product->get_title(),
            $this->currency_symbol( $product->get_price() ),
            $seller->display_name,
            sk_get_store_url( $seller->ID ),
            $category_name,
            admin_url( 'post.php?action=edit&post=' . $product_id ),
            $this->get_from_name(),
            home_url(),
        );

        $body = str_replace( $find, $replace, $body );
        // translators: %s: from name
        $subject = sprintf( __( '[%s] New Product Added', 'sk-core' ), $this->get_from_name() );

        $this->send( $this->admin_email(), $subject, $body );
        do_action( 'after_new_product_added', $this->admin_email(), $subject, $body );
    }

    /**
     * Send email to seller once a product is published
     *
     * @param WP_Post $post
     * @param WP_User $seller
     *
     * @return void
     */
    public function product_published( $post, $seller ) {
        ob_start();
        sk_get_template_part( 'emails/product-published' );
        $body = ob_get_clean();

        $product = wc_get_product( $post->ID );

        $find = array(
            '%seller_name%',
            '%title%',
            '%product_link%',
            '%product_edit_link%',
            '%site_name%',
            '%site_url%',
        );

        $replace = array(
            $seller->display_name,
            $product->get_title(),
            get_permalink( $post->ID ),
            sk_edit_product_url( $post->ID ),
            $this->get_from_name(),
            home_url(),
        );

        $body = str_replace( $find, $replace, $body );
        // translators: %s: from name
        $subject = sprintf( __( '[%s] Your product has been approved!', 'sk-core' ), $this->get_from_name() );

        $this->send( $seller->user_email, $subject, $body );
        do_action( 'after_product_published', $seller->user_email, $subject, $body );
    }

    /**
     * Send the email.
     *
     * @access public
     *
     * @param mixed $to
     * @param mixed $subject
     * @param mixed $message
     * @param string $headers
     * @param string $attachments
     *
     * @return void
     */
    public function send( $to, $subject, $message, $headers = array() ) {
        add_filter( 'wp_mail_from', array( $this, 'get_from_address' ) );
        add_filter( 'wp_mail_from_name', array( $this, 'get_from_name' ) );

        wp_mail( $to, $subject, $message, $headers );

        remove_filter( 'wp_mail_from', array( $this, 'get_from_address' ) );
        remove_filter( 'wp_mail_from_name', array( $this, 'get_from_name' ) );
    }
}
