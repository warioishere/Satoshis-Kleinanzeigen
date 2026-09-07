<?php

namespace SK\Modules\Sponsors;

use SK\Core\Admin\PhpDashboard\AbstractPage;

defined( 'ABSPATH' ) || exit;

/**
 * SK → Sponsors: list, click counts, import of existing sponsors.
 */
class AdminPage extends AbstractPage {

    public function get_slug(): string {
        return 'sponsors';
    }

    public function get_title(): string {
        return __( 'Sponsoren', 'sk-core' );
    }

    public function get_menu_position(): int {
        return 7;
    }

    public function render(): void {
        $days = isset( $_GET['days'] ) ? absint( $_GET['days'] ) : 30;
        $days = in_array( $days, [ 7, 30, 90, 365 ], true ) ? $days : 30;

        $to   = current_time( 'Y-m-d' );
        $from = gmdate( 'Y-m-d', strtotime( $to . ' -' . ( $days - 1 ) . ' days' ) );

        $sponsors = get_posts(
            [
                'post_type'      => PostType::POST_TYPE,
                'post_status'    => [ 'publish', 'draft' ],
                'posts_per_page' => -1,
                'orderby'        => 'title',
                'order'          => 'ASC',
            ]
        );

        $clicks       = Stats::by_sponsor( $from, $to );
        $total_clicks = Stats::total_clicks( $from, $to );

        // Display in the same order as on the homepage: top tier first,
        // sorted by monthly rate within it. compare_rank is the same logic
        // the frontend uses — two copies would drift apart over time.
        usort(
            $sponsors,
            static function ( $a, $b ) {
                $ta = PostType::get_tier( $a->ID ) === PostType::TIER_TOP ? 1 : 0;
                $tb = PostType::get_tier( $b->ID ) === PostType::TIER_TOP ? 1 : 0;
                if ( $ta !== $tb ) {
                    return $tb <=> $ta;
                }

                return PostType::compare_rank( $a, $b );
            }
        );

        $legacy_pending  = $this->legacy_pending_count();
        $notice          = isset( $_GET['sk_sponsors_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['sk_sponsors_notice'] ) ) : '';
        $billing_enabled = Billing::is_enabled();
        $unchecked       = Backlink::unchecked_count();
        $rate_top        = Pricing::list_price( PostType::TIER_TOP );
        $rate_standard   = Pricing::list_price( PostType::TIER_STANDARD );

        // After creating an invoice, show the payment link to hand over.
        $new_invoice = null;
        if ( strpos( $notice, 'invoice-' ) === 0 ) {
            $order_id = (int) substr( $notice, strlen( 'invoice-' ) );
            if ( $order_id > 0 ) {
                $new_invoice = wc_get_order( $order_id ) ?: null;
            }
        }

        // What the placement currently brings in and what it would bring in
        // if everyone paid — the gap is the actual argument.
        $monthly_income = 0;
        $paying         = 0;
        foreach ( $sponsors as $sponsor ) {
            $rate = (int) get_post_meta( $sponsor->ID, PostType::META_MONTHLY, true );
            if ( $rate > 0 ) {
                $monthly_income += $rate;
                $paying++;
            }
        }

        include SK_SPONSORS_PATH . '/templates/admin-sponsors.php';
    }

    public function handle_post(): void {
        if ( ! isset( $_POST['sk_sponsors_nonce'] ) || ! wp_verify_nonce( $_POST['sk_sponsors_nonce'], 'sk_sponsors_action' ) ) {
            return;
        }

        if ( ! current_user_can( $this->get_capability() ) ) {
            return;
        }

        $action = isset( $_POST['sk_sponsors_action'] ) ? sanitize_text_field( wp_unslash( $_POST['sk_sponsors_action'] ) ) : '';
        $notice = '';

        if ( $action === 'import_legacy' ) {
            $result = Migration::run();
            $notice = sprintf( 'imported-%d-%d-%d', $result['created'], $result['skipped'], $result['missing_url'] );
        }

        if ( $action === 'check_backlinks' ) {
            $result = Backlink::check_batch();
            $notice = sprintf( 'backlinks-%d-%d-%d', $result['checked'], $result['ok'], $result['open'] );
        }

        if ( $action === 'create_invoice' ) {
            $sponsor_id = isset( $_POST['sk_sponsor_id'] ) ? absint( $_POST['sk_sponsor_id'] ) : 0;
            $sats       = isset( $_POST['sk_topup_sats'] ) ? absint( $_POST['sk_topup_sats'] ) : 0;
            $email      = isset( $_POST['sk_topup_email'] ) ? sanitize_email( wp_unslash( $_POST['sk_topup_email'] ) ) : '';

            $order  = TopUp::create_invoice( $sponsor_id, $sats, $email );
            $notice = is_wp_error( $order )
                ? 'invoice-error'
                : 'invoice-' . (int) $order->get_id();
        }

        if ( $action === 'save_rates' ) {
            Pricing::set_list_price( PostType::TIER_TOP, isset( $_POST['sk_rate_top'] ) ? absint( $_POST['sk_rate_top'] ) : 0 );
            Pricing::set_list_price( PostType::TIER_STANDARD, isset( $_POST['sk_rate_standard'] ) ? absint( $_POST['sk_rate_standard'] ) : 0 );
            $notice = 'rates-saved';
        }

        if ( $action === 'apply_rate_top' || $action === 'apply_rate_standard' ) {
            $tier      = $action === 'apply_rate_top' ? PostType::TIER_TOP : PostType::TIER_STANDARD;
            $overwrite = ! empty( $_POST['sk_rate_overwrite'] );
            $changed   = Pricing::apply_to_tier( $tier, $overwrite );
            $notice    = 'rates-applied-' . (int) $changed;
        }

        if ( $action === 'toggle_billing' ) {
            update_option( Billing::OPTION_ENABLED, ! Billing::is_enabled() );
            $notice = Billing::is_enabled() ? 'billing-on' : 'billing-off';
        }

        wp_safe_redirect(
            add_query_arg(
                [
                    'page'               => 'sk',
                    'tab'                => $this->get_slug(),
                    'sk_sponsors_notice' => $notice,
                ],
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    /**
     * How many legacy posts have not been imported yet?
     *
     * Uses the same selection criteria as the import (target URL present),
     * otherwise the page would report "everything imported" while the
     * import would still have something to do.
     */
    private function legacy_pending_count(): int {
        $legacy = Migration::legacy_posts();
        if ( empty( $legacy ) ) {
            return 0;
        }

        global $wpdb;

        $imported = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
                PostType::META_LEGACY
            )
        );

        $legacy_ids = array_map( static fn( $post ) => (int) $post->ID, $legacy );

        return count( array_diff( $legacy_ids, array_map( 'intval', $imported ) ) );
    }
}
