<?php
namespace SK\Modules\StoreReviews\Emails;

/**
 * SK email handler class
 *
 */
class Manager {

    /**
     * Load automatically when class initiate
     */
    public function __construct() {
        // SK Email filters for WC Email
        add_filter( 'sk_email_classes', [ $this, 'load_sk_emails' ], 35 );
        add_filter( 'sk_email_list', [ $this, 'add_email_template_file' ] );
        add_filter( 'sk_email_actions', [ $this, 'add_email_action' ] );
    }

    /**
     * Add SK Store Review Email classes in WC Email
     *
     *
     * @param array $wc_emails
     *
     * @return array $wc_emails
     */
    public function load_sk_emails( $wc_emails ) {
        require_once SK_SELLER_RATINGS_DIR . '/classes/Emails/NewStoreReview.php';
        $wc_emails['SK_Email_New_Store_Review'] = new NewStoreReview();

        return $wc_emails;
    }

    /**
     * Add email template
     *
     *
     * @param array $template_files Template files.
     *
     * @return array
     */
    public static function add_email_template_file( $template_files ): array {
        $template_files[] = 'new-store-review-email.php';

        return $template_files;
    }

    /**
     * Add email action
     *
     *
     * @param array $actions Email actions.
     *
     * @return array
     */
    public static function add_email_action( $actions ) {
        $actions[] = 'sk_store_review_saved';

        return $actions;
    }
}
