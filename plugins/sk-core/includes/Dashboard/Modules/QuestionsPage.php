<?php

namespace SK\Core\Dashboard\Modules;

use SK\Core\Dashboard\DashboardModule;
use SK\Core\Questions;

/**
 * Dashboard: questions people asked about your listings.
 *
 * Answering publishes the question, so the vendor moderates by answering
 * rather than by a separate approval step. Turning one down deletes it.
 */
class QuestionsPage extends DashboardModule {

    const NONCE = 'sk_questions';

    public function config(): ?array {
        return [
            'slug'          => 'fragen',
            'title'         => __( 'Fragen', 'sk-core' ),
            'icon'          => '<i class="fas fa-circle-question"></i>',
            'pos'           => 58,
            'permission'    => 'sk_view_product_menu',
            'template'      => 'dashboard/questions/dashboard-questions',
            'template_args' => [ $this, 'view_data' ],
        ];
    }

    protected function register_extras(): void {
        add_action( 'template_redirect', [ $this, 'handle_post' ] );
        add_filter( 'sk_get_dashboard_nav', [ $this, 'add_badge' ], 60 );
    }

    /** Unanswered questions as a count next to the nav entry. */
    public function add_badge( $nav ) {
        if ( ! is_user_logged_in() || ! isset( $nav['fragen'] ) ) {
            return $nav;
        }

        $open = Questions::count_open( get_current_user_id() );

        if ( $open > 0 ) {
            $nav['fragen']['title'] .= ' <span class="dvc-notification-badge">' . (int) $open . '</span>';
        }

        return $nav;
    }

    public function view_data(): array {
        $vendor_id = get_current_user_id();
        $status    = in_array( $_GET['status'] ?? 'open', [ 'open', 'answered', 'all' ], true )
            ? sanitize_key( wp_unslash( $_GET['status'] ) ?? 'open' )
            : 'open';

        return [
            'status'   => $status,
            'rows'     => Questions::for_vendor( $vendor_id, $status ),
            'open'     => Questions::count_open( $vendor_id ),
            'answered' => count( Questions::for_vendor( $vendor_id, 'answered' ) ),
            'notice'   => $this->take_notice( $vendor_id ),
        ];
    }

    public function handle_post(): void {
        if ( strtoupper( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) !== 'POST' ) {
            return;
        }

        if ( ! isset( $_POST['sk_q_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['sk_q_nonce'] ), self::NONCE ) ) {
            return;
        }

        $vendor_id = get_current_user_id();

        if ( ! $vendor_id || ! function_exists( 'sk_is_user_seller' ) || ! sk_is_user_seller( $vendor_id ) ) {
            return;
        }

        $question_id = (int) ( $_POST['sk_q_id'] ?? 0 );
        $action      = sanitize_key( wp_unslash( $_POST['sk_q_action'] ?? '' ) );

        switch ( $action ) {
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

        $this->notice( $vendor_id, is_wp_error( $result ) ? $result->get_error_message() : $done );

        wp_safe_redirect( add_query_arg( 'status', sanitize_key( wp_unslash( $_POST['sk_q_status'] ?? 'open' ) ), $this->url() ) );
        exit;
    }

    private function url(): string {
        return function_exists( 'sk_get_navigation_url' )
            ? sk_get_navigation_url( 'fragen' )
            : home_url( '/dashboard/fragen/' );
    }

    private function notice( int $vendor_id, string $message ): void {
        set_transient( 'sk_q_notice_' . $vendor_id, $message, 60 );
    }

    private function take_notice( int $vendor_id ): string {
        $message = (string) get_transient( 'sk_q_notice_' . $vendor_id );

        if ( '' !== $message ) {
            delete_transient( 'sk_q_notice_' . $vendor_id );
        }

        return $message;
    }
}
