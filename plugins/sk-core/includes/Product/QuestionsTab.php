<?php

namespace SK\Core\Product;

use SK\Core\Questions;

defined( 'ABSPATH' ) || exit;

/**
 * The "Fragen" tab on a listing.
 *
 * Its own tab rather than a section under the description: a question is
 * addressed to the vendor, the description is not, and the tab row is
 * where a visitor already looks for everything that is not the offer
 * itself.
 *
 * Deliberately not tied to comments_open() — catalog mode closes that for
 * listings, and the review box it closes is the one that collected the
 * spam. Questions are members only, which is what keeps this one clean.
 */
final class QuestionsTab {

    public static function init(): void {
        add_filter( 'woocommerce_product_tabs', [ __CLASS__, 'add_tab' ], 20 );
        add_action( 'template_redirect', [ __CLASS__, 'handle_post' ] );
    }

    public static function add_tab( $tabs ) {
        global $product;

        if ( ! $product instanceof \WC_Product ) {
            return $tabs;
        }

        // Nothing to read and nobody who could write: no empty tab.
        $count = count( Questions::for_product( $product->get_id() ) );

        if ( 0 === $count && ! self::may_ask( $product->get_id() ) ) {
            return $tabs;
        }

        $tabs['sk_questions'] = [
            'title'    => $count ? sprintf( __( 'Fragen (%d)', 'sk-core' ), $count ) : __( 'Fragen', 'sk-core' ),
            'priority' => 25,
            'callback' => [ __CLASS__, 'render' ],
        ];

        return $tabs;
    }

    /** Members only, and not on one's own listing. */
    private static function may_ask( int $product_id ): bool {
        return is_user_logged_in()
            && (int) get_post_field( 'post_author', $product_id ) !== get_current_user_id();
    }

    public static function render(): void {
        global $product;

        if ( ! $product instanceof \WC_Product ) {
            return;
        }

        $product_id = $product->get_id();
        $notice     = '';

        if ( is_user_logged_in() ) {
            $notice = (string) get_transient( 'sk_q_notice_ask_' . get_current_user_id() );

            if ( '' !== $notice ) {
                delete_transient( 'sk_q_notice_ask_' . get_current_user_id() );
            }
        }

        sk_get_template_part( 'single-product/questions', '', [
            'product_id' => $product_id,
            'rows'       => Questions::for_product( $product_id ),
            'may_ask'    => self::may_ask( $product_id ),
            'notice'     => $notice,
        ] );
    }

    public static function handle_post(): void {
        if ( strtoupper( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) !== 'POST' ) {
            return;
        }

        if ( ! isset( $_POST['sk_ask_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['sk_ask_nonce'] ), 'sk_ask' ) ) {
            return;
        }

        $product_id = (int) ( $_POST['sk_ask_product'] ?? 0 );
        $user_id    = get_current_user_id();

        $result = Questions::ask( $product_id, $user_id, (string) wp_unslash( $_POST['sk_ask_text'] ?? '' ) );

        if ( $user_id ) {
            set_transient(
                'sk_q_notice_ask_' . $user_id,
                is_wp_error( $result )
                    ? $result->get_error_message()
                    : __( 'Deine Frage ist beim Anbieter. Sobald er antwortet, steht sie hier.', 'sk-core' ),
                60
            );
        }

        wp_safe_redirect( get_permalink( $product_id ) . '#tab-sk_questions' );
        exit;
    }
}
