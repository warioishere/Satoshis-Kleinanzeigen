<?php

namespace SK\Core\Dashboard\Modules;

use SK\Core\Questions;
use SK\Core\StoreReviews;

/**
 * Everything people say to a vendor, under one menu entry.
 *
 * Product reviews, shop reviews and questions used to want a sidebar entry
 * each. They are the same errand — "what came in, and what do I answer" —
 * so they became three tabs on the existing reviews page instead. Its URL
 * stays, which keeps the legacy filter and pagination links working.
 *
 * A red dot on the menu entry says something is unanswered, without the
 * vendor having to open the page to find out.
 */
class FeedbackTabs {

    /** Replying to a shop review. */
    const NONCE = 'sk_store_review_reply';

    /** Answering, publishing or turning down a question. */
    const NONCE_Q = 'sk_questions';

    /** Tab currently shown; the product reviews are the default. */
    const PRODUCT = 'produkt';
    const SHOP    = 'shop';
    const ASKED   = 'fragen';

    public function __construct() {
        add_action( 'sk_review_content_area_header', [ $this, 'render_tabs' ], 5 );
        add_action( 'wp', [ $this, 'take_over_content' ], 20 );
        add_action( 'template_redirect', [ $this, 'handle_post' ], 9 );
        add_filter( 'sk_get_dashboard_nav', [ $this, 'adjust_nav' ], 70 );
    }

    public static function current(): string {
        $tab = sanitize_key( wp_unslash( $_GET['bereich'] ?? self::PRODUCT ) ); // phpcs:ignore WordPress.Security.NonceVerification

        return in_array( $tab, [ self::PRODUCT, self::SHOP, self::ASKED ], true ) ? $tab : self::PRODUCT;
    }

    public static function url( string $tab = '' ): string {
        $base = function_exists( 'sk_get_navigation_url' ) ? sk_get_navigation_url( 'reviews' ) : home_url( '/dashboard/reviews/' );

        return $tab && self::PRODUCT !== $tab ? add_query_arg( 'bereich', $tab, $base ) : $base;
    }

    /**
     * One entry, one dot. The count is what is waiting for an answer across
     * all three tabs.
     */
    public function adjust_nav( $nav ) {
        if ( ! is_user_logged_in() ) {
            return $nav;
        }

        // The questions page is a tab here now, not a menu of its own.
        unset( $nav['fragen'] );

        if ( ! isset( $nav['reviews'] ) ) {
            return $nav;
        }

        $vendor_id = get_current_user_id();
        $open      = Questions::count_open( $vendor_id ) + StoreReviews::count_open( $vendor_id );

        $nav['reviews']['title'] = __( 'Rückmeldungen', 'sk-core' );

        if ( $open > 0 ) {
            $nav['reviews']['title'] .= ' <span class="dvc-notification-badge">' . (int) $open . '</span>';
        }

        return $nav;
    }

    /**
     * On the other two tabs the legacy product-review renderers step aside.
     *
     * Removing their actions rather than hiding their output: they run
     * queries of their own, and a tab that is not shown should not cost
     * anything.
     */
    public function take_over_content(): void {
        global $wp;

        if ( ! isset( $wp->query_vars['reviews'] ) || self::PRODUCT === self::current() ) {
            return;
        }

        $review = sk_get_container()->get( \SK\Core\Review::class );

        remove_action( 'sk_review_content_area_header', [ $review, 'sk_review_header_render' ], 10 );
        remove_action( 'sk_review_content', [ $review, 'sk_review_content_render' ], 10 );

        add_action( 'sk_review_content', [ $this, 'render_content' ], 10 );
    }

    public function render_tabs(): void {
        $vendor_id = get_current_user_id();

        sk_get_template_part( 'review/feedback-tabs', '', [
            'current'    => self::current(),
            'open_asked' => Questions::count_open( $vendor_id ),
            'open_shop'  => StoreReviews::count_open( $vendor_id ),
        ] );
    }

    public function render_content(): void {
        $vendor_id = get_current_user_id();

        if ( self::ASKED === self::current() ) {
            $status = in_array( $_GET['status'] ?? 'open', [ 'open', 'answered', 'all' ], true )
                ? sanitize_key( wp_unslash( $_GET['status'] ) )
                : 'open';

            sk_get_template_part( 'review/questions-list', '', [
                'status'   => $status,
                'rows'     => Questions::for_vendor( $vendor_id, $status ),
                'open'     => Questions::count_open( $vendor_id ),
                'answered' => count( Questions::for_vendor( $vendor_id, 'answered' ) ),
                'notice'   => $this->take_notice( $vendor_id ),
            ] );

            return;
        }

        sk_get_template_part( 'review/store-reviews-list', '', [
            'reviews' => StoreReviews::for_vendor( $vendor_id ),
            'notice'  => $this->take_notice( $vendor_id ),
        ] );
    }

    public function handle_post(): void {
        if ( strtoupper( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) !== 'POST' ) {
            return;
        }

        $vendor_id = get_current_user_id();

        if ( ! $vendor_id || ! function_exists( 'sk_is_user_seller' ) || ! sk_is_user_seller( $vendor_id ) ) {
            return;
        }

        if ( isset( $_POST['sk_q_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['sk_q_nonce'] ), self::NONCE_Q ) ) {
            $this->handle_question( $vendor_id );
            return;
        }

        if ( ! isset( $_POST['sk_sr_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['sk_sr_nonce'] ), self::NONCE ) ) {
            return;
        }

        $result = StoreReviews::reply(
            (int) ( $_POST['sk_sr_id'] ?? 0 ),
            $vendor_id,
            (string) wp_unslash( $_POST['sk_sr_text'] ?? '' )
        );

        set_transient(
            'sk_feedback_notice_' . $vendor_id,
            is_wp_error( $result ) ? $result->get_error_message() : __( 'Antwort gespeichert.', 'sk-core' ),
            60
        );

        wp_safe_redirect( self::url( self::SHOP ) );
        exit;
    }

    private function handle_question( int $vendor_id ): void {
        $question_id = (int) ( $_POST['sk_q_id'] ?? 0 );

        switch ( sanitize_key( wp_unslash( $_POST['sk_q_action'] ?? '' ) ) ) {
            case 'answer':
                $result = Questions::answer( $question_id, $vendor_id, (string) wp_unslash( $_POST['sk_q_text'] ?? '' ) );
                $done   = __( 'Antwort veröffentlicht.', 'sk-core' );
                break;

            case 'approve':
                $result = Questions::approve( $question_id, $vendor_id );
                $done   = __( 'Frage veröffentlicht.', 'sk-core' );
                break;

            case 'reject':
                $result = Questions::reject( $question_id, $vendor_id );
                $done   = __( 'Frage gelöscht.', 'sk-core' );
                break;

            default:
                return;
        }

        self::notice( $vendor_id, is_wp_error( $result ) ? $result->get_error_message() : $done );

        wp_safe_redirect( add_query_arg( 'status', sanitize_key( wp_unslash( $_POST['sk_q_status'] ?? 'open' ) ), self::url( self::ASKED ) ) );
        exit;
    }

    public static function notice( int $vendor_id, string $message ): void {
        set_transient( 'sk_feedback_notice_' . $vendor_id, $message, 60 );
    }

    private function take_notice( int $vendor_id ): string {
        $message = (string) get_transient( 'sk_feedback_notice_' . $vendor_id );

        if ( '' !== $message ) {
            delete_transient( 'sk_feedback_notice_' . $vendor_id );
        }

        return $message;
    }
}
