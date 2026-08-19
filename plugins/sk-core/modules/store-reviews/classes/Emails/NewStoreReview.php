<?php
namespace SK\Modules\StoreReviews\Emails;

use WC_Email;

/**
 * New Quote Email.
 *
 * An email sent to the admin, vendor and customer when a new quote is created.
 *
 * @class       NewQuote
 * @author      weDevs
 * @extends     WC_Email
 */
class NewStoreReview extends WC_Email {

    /**
     * Reviewer name.
     *
     * @var string
     */
    public $reviewer_name = '';

    /**
     * Review id.
     *
     *
     * @var int
     */
    protected $post_id;

    /**
     * Review title.
     *
     *
     * @var string
     */
    protected $post_title;

    /**
     * Review details.
     *
     *
     * @var string
     */
    protected $post_details;

    /**
     * Review rating.
     *
     *
     * @var int
     */
    protected $rating;

    /**
     * Store name.
     *
     *
     * @var string
     */
    private $store_name;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->id             = 'sk_new_store_review';
        $this->title          = __( 'SK New Store Review', 'sk-core' );
        $this->description    = __( 'New emails are sent to chosen recipient(s) when a new store review is submitted.', 'sk-core' );
        $this->template_html  = 'emails/new-store-review-email.php';
        $this->template_plain = 'emails/plain/new-store-review-email.php';
        $this->template_base  = SK_SELLER_RATINGS_DIR . '/templates/';
        $this->reviewer_name  = wp_get_current_user()->user_login;
        $this->placeholders   = [
            '{store_name}' => '',
        ];

        // Triggers for this email.
        add_action( 'sk_store_review_saved', [ $this, 'trigger' ], 10, 3 );

        // Call parent constructor.
        parent::__construct();

        // Other settings.
        $this->recipient = $this->get_default_recipient();
    }

    /**
     * Get email subject.
     *
     *
     * @return string
     */
    public function get_default_subject() {
        return __( 'New review on #{store_name}', 'sk-core' );
    }

    /**
     * Get email recipient.
     *
     *
     * @return string
     */
    public function get_default_recipient() {
        return get_option( 'admin_email' ) . ', owner@ofthe.store';
    }

    /**
     * Get email heading.
     *
     *
     * @return string
     */
    public function get_default_heading() {
        return __( 'New review on #{store_name}', 'sk-core' );
    }

    /**
     * Trigger the sending of this email.
     *
     *
     * @param int   $post_id
     * @param array $post_data
     * @param int   $rating
     *
     * @return void
     */
    public function trigger( $post_id, $post_data, $rating ) {
        if ( ! $post_id || ! $this->is_enabled() ) {
            return;
        }

        $this->setup_locale();

        $this->post_id      = $post_id;
        $store_id           = sanitize_text_field( wp_unslash( $post_data['store_id'] ) );
        $this->post_title   = ! empty( $post_data['sk-review-title'] ) ? sanitize_text_field( wp_unslash( $post_data['sk-review-title'] ) ) : '';
        $this->post_details = ! empty( $post_data['sk-review-details'] ) ? sanitize_text_field( wp_unslash( $post_data['sk-review-details'] ) ) : '';
        $this->rating       = $rating;
        $vendor             = sk()->vendor->get( $store_id );
        $this->store_name   = $vendor->get_shop_name();

        $this->placeholders['{store_name}'] = $this->store_name;

        $recipients = explode( ',', $this->get_default_recipient() );
        foreach ( $recipients as $recipient ) {
            $recipient = trim( $recipient );
            if ( 'owner@ofthe.store' === $recipient ) {
                $recipient = $vendor->get_email();
            }
            $this->send( $recipient, $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
        }

        $this->restore_locale();
    }

    /**
     * Get content html.
     *
     *
     * @return false|string
     */
    public function get_content_html() {
        return wc_get_template_html(
            $this->template_html, [
                'post_id'            => $this->post_id,
                'post_title'         => $this->post_title,
                'post_details'       => $this->post_details,
                'reviewer_name'      => $this->reviewer_name,
                'rating'             => $this->rating,
                'store_name'         => $this->store_name,
                'email_heading'      => $this->get_heading(),
                'additional_content' => $this->get_additional_content(),
                'email'              => $this,
            ], 'sk/', $this->template_base
        );
    }

    /**
     * Get content plain.
     *
     *
     * @return false|string
     */
    public function get_content_plain() {
        return wc_get_template_html(
            $this->template_plain, [
                'post_id'            => $this->post_id,
                'post_title'         => $this->post_title,
                'post_details'       => $this->post_details,
                'reviewer_name'      => $this->reviewer_name,
                'rating'             => $this->rating,
                'store_name'         => $this->store_name,
                'email_heading'      => $this->get_heading(),
                'additional_content' => $this->get_additional_content(),
                'email'              => $this,
            ], 'sk/', $this->template_base
        );
    }

    /**
     * Initialise settings form fields.
     *
     *
     * @return void
     */
    public function init_form_fields() {
        /* translators: %s: list of placeholders */
        $placeholder_text  = sprintf( __( 'Available placeholders: %s', 'sk-core' ), '<code>' . implode( '</code>, <code>', array_keys( $this->placeholders ) ) . '</code>' );
        $this->form_fields = [
            'enabled'    => [
                'title'   => __( 'Enable/Disable', 'sk-core' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable this email notification', 'sk-core' ),
                'default' => 'yes',
            ],
            'recipient'  => [
                'title'       => __( 'Recipient(s)', 'sk-core' ),
                'type'        => 'text',
                /* translators: %s: list of recipient */
                'description' => sprintf( __( 'Enter recipients (comma separated) for this email. Defaults to %s.', 'sk-core' ), '<code>' . esc_attr( get_option( 'admin_email' ) ) . '</code>' ),
                'placeholder' => $this->get_default_recipient(),
                'default'     => '',
                'desc_tip'    => true,
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
            'additional_content' => [
                'title'       => __( 'Additional content', 'sk-core' ),
                'description' => __( 'Text to appear below the main email content.', 'sk-core' ) . ' ' . $placeholder_text,
                'css'         => 'width:400px; height: 75px;',
                'placeholder' => __( 'N/A', 'sk-core' ),
                'type'        => 'textarea',
                'default'     => $this->get_default_additional_content(),
                'desc_tip'    => true,
            ],
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
