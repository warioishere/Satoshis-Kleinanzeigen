<?php

namespace SK\Modules\Payments\Commission;

use SK\Modules\Payments\Chat\ChatIntegration;

defined( 'ABSPATH' ) || exit;

/**
 * Commission Enforcement — Chat Reminders + Vendor Suspension.
 *
 * All communication happens via VendorChat from the admin account.
 *
 * Flow per vendor with unpaid commissions:
 *   1. First reminder via chat
 *   2. Second reminder via chat (1 week later)
 *   3. Final warning: "Bitte bezahle, sonst Sperre in 1 Woche"
 *   4. Suspension: all products → draft, vendor hidden
 *
 * Auto-unsuspend when all commissions are paid.
 */
class Enforcement {

    /**
     * Run enforcement check. Called from cron.
     */
    public static function process() {
        if ( ! Generator::is_enabled() || ! self::is_enabled() ) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'sk_commissions';

        $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( ! $table_exists ) {
            return;
        }

        // Find vendors with unpaid commissions older than 7 days.
        $vendors = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT vendor_id FROM {$table}
                 WHERE status IN ('pending', 'invoiced')
                 AND created_at <= %s",
                wp_date( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS )
            )
        );

        foreach ( $vendors as $vendor_id ) {
            self::process_vendor( (int) $vendor_id );
        }

        // Check if any suspended vendors have now paid everything.
        self::check_unsuspend();
    }

    private static function process_vendor( int $vendor_id ) {
        $reminders_sent  = (int) get_user_meta( $vendor_id, 'sk_commission_reminders_sent', true );
        $last_reminder   = get_user_meta( $vendor_id, 'sk_commission_last_reminder', true );
        $max_reminders   = (int) sk_get_option( 'sk_commission_reminders', 'sk_lightning', '3' );
        $is_suspended    = get_user_meta( $vendor_id, 'sk_commission_suspended', true ) === '1';

        if ( $is_suspended ) {
            return;
        }

        // 1 week minimum between reminders.
        if ( $last_reminder && strtotime( $last_reminder ) > time() - 7 * DAY_IN_SECONDS ) {
            return;
        }

        // Get unpaid total.
        global $wpdb;
        $table = $wpdb->prefix . 'sk_commissions';
        $unpaid = $wpdb->get_row( $wpdb->prepare(
            "SELECT COUNT(*) as cnt, COALESCE(SUM(commission_sats), 0) as total_sats
             FROM {$table}
             WHERE vendor_id = %d AND status IN ('pending', 'invoiced')",
            $vendor_id
        ) );

        if ( ! $unpaid || $unpaid->cnt < 1 ) {
            delete_user_meta( $vendor_id, 'sk_commission_reminders_sent' );
            delete_user_meta( $vendor_id, 'sk_commission_last_reminder' );
            return;
        }

        $total_sats = (int) $unpaid->total_sats;
        $count      = (int) $unpaid->cnt;

        // Past all reminders + grace period → suspend.
        if ( $reminders_sent >= $max_reminders ) {
            self::suspend_vendor( $vendor_id, $total_sats, $count );
            return;
        }

        // Send reminder via chat.
        $reminders_sent++;
        $is_final = ( $reminders_sent >= $max_reminders );

        self::send_chat_reminder( $vendor_id, $reminders_sent, $max_reminders, $total_sats, $count, $is_final );

        update_user_meta( $vendor_id, 'sk_commission_reminders_sent', $reminders_sent );
        update_user_meta( $vendor_id, 'sk_commission_last_reminder', current_time( 'mysql' ) );
    }

    /**
     * Send a reminder via VendorChat from the admin.
     */
    private static function send_chat_reminder( int $vendor_id, int $current, int $max, int $total_sats, int $count, bool $is_final ) {
        $admin_id = self::get_admin_user_id();
        $chat_id  = self::get_or_create_commission_chat( $admin_id, $vendor_id );

        if ( ! $chat_id ) {
            return;
        }

        $sats_formatted = number_format( $total_sats, 0, ',', '.' );

        if ( $is_final ) {
            $text = "Letzte Erinnerung ({$current}/{$max}): Du hast {$count} offene Kommission(en) über {$sats_formatted} Sats.\n\n" .
                "Bitte bezahle innerhalb der nächsten 7 Tage. Danach wird dein Store vorübergehend gesperrt " .
                "(Produkte auf Entwurf, Shop nicht mehr sichtbar).\n\n" .
                "Nach Bezahlung wird alles automatisch wieder freigeschaltet.";
        } else {
            $text = "Erinnerung ({$current}/{$max}): Du hast {$count} offene Kommission(en) über {$sats_formatted} Sats.\n\n" .
                "Bitte bezahle die offenen Invoices unter Käufe/Verkäufe → Kommissionen in deinem Dashboard.";
        }

        $message_data = wp_json_encode( [
            'type'       => 'commission_reminder',
            'current'    => $current,
            'max'        => $max,
            'total_sats' => $total_sats,
            'count'      => $count,
            'is_final'   => $is_final,
        ] );

        $message = "[commission_reminder]{$message_data}[/commission_reminder]\n{$text}";

        ChatIntegration::add_chat_message_static( $chat_id, $admin_id, $message );
    }

    /**
     * Suspend a vendor.
     */
    private static function suspend_vendor( int $vendor_id, int $total_sats, int $count ) {
        global $wpdb;

        // Track which products were published before suspension.
        $published_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_author = %d AND post_type = 'product' AND post_status = 'publish'",
            $vendor_id
        ) );
        update_user_meta( $vendor_id, 'sk_commission_suspended_products', $published_ids );

        // Products → draft.
        if ( ! empty( $published_ids ) ) {
            $ids_placeholder = implode( ',', array_map( 'intval', $published_ids ) );
            $wpdb->query( "UPDATE {$wpdb->posts} SET post_status = 'draft' WHERE ID IN ({$ids_placeholder})" );
        }

        update_user_meta( $vendor_id, 'sk_commission_suspended', '1' );
        update_user_meta( $vendor_id, 'sk_commission_suspended_at', current_time( 'mysql' ) );

        // Close store.
        $store_settings = get_user_meta( $vendor_id, 'sk_profile_settings', true );
        if ( is_array( $store_settings ) ) {
            $store_settings['sk_commission_was_enabled'] = $store_settings['store_open_close'] ?? '';
            $store_settings['store_open_close'] = 'close';
            update_user_meta( $vendor_id, 'sk_profile_settings', $store_settings );
        }

        // Notify vendor via chat.
        $admin_id = self::get_admin_user_id();
        $chat_id  = self::get_or_create_commission_chat( $admin_id, $vendor_id );
        if ( $chat_id ) {
            $sats_formatted = number_format( $total_sats, 0, ',', '.' );
            $message = "Dein Store wurde gesperrt. {$count} Kommission(en) über {$sats_formatted} Sats sind unbezahlt.\n\n" .
                "Deine Produkte wurden auf Entwurf gesetzt und dein Shop ist nicht mehr sichtbar.\n\n" .
                "Bezahle die offenen Invoices unter Käufe/Verkäufe → Kommissionen — dein Store wird danach automatisch wieder freigeschaltet.";

            ChatIntegration::add_chat_message_static( $chat_id, $admin_id, $message );
        }
    }

    /**
     * Check suspended vendors — unsuspend if all paid.
     */
    private static function check_unsuspend() {
        $suspended = get_users( [
            'meta_key'   => 'sk_commission_suspended',
            'meta_value' => '1',
            'fields'     => 'ID',
        ] );

        if ( empty( $suspended ) ) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'sk_commissions';

        foreach ( $suspended as $vendor_id ) {
            $unpaid = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table}
                 WHERE vendor_id = %d AND status IN ('pending', 'invoiced')",
                $vendor_id
            ) );

            if ( $unpaid === 0 ) {
                self::unsuspend_vendor( (int) $vendor_id );
            }
        }
    }

    private static function unsuspend_vendor( int $vendor_id ) {
        global $wpdb;

        // Only restore products that were published before suspension.
        $suspended_products = get_user_meta( $vendor_id, 'sk_commission_suspended_products', true );
        if ( ! empty( $suspended_products ) && is_array( $suspended_products ) ) {
            $ids_placeholder = implode( ',', array_map( 'intval', $suspended_products ) );
            $wpdb->query( "UPDATE {$wpdb->posts} SET post_status = 'publish' WHERE ID IN ({$ids_placeholder}) AND post_status = 'draft'" );
        }

        delete_user_meta( $vendor_id, 'sk_commission_suspended' );
        delete_user_meta( $vendor_id, 'sk_commission_suspended_at' );
        delete_user_meta( $vendor_id, 'sk_commission_suspended_products' );
        delete_user_meta( $vendor_id, 'sk_commission_reminders_sent' );
        delete_user_meta( $vendor_id, 'sk_commission_last_reminder' );

        // Restore store.
        $store_settings = get_user_meta( $vendor_id, 'sk_profile_settings', true );
        if ( is_array( $store_settings ) && isset( $store_settings['sk_commission_was_enabled'] ) ) {
            $store_settings['store_open_close'] = $store_settings['sk_commission_was_enabled'];
            unset( $store_settings['sk_commission_was_enabled'] );
            update_user_meta( $vendor_id, 'sk_profile_settings', $store_settings );
        }

        // Notify via chat.
        $admin_id = self::get_admin_user_id();
        $chat_id  = self::get_or_create_commission_chat( $admin_id, $vendor_id );
        if ( $chat_id ) {
            ChatIntegration::add_chat_message_static(
                $chat_id,
                $admin_id,
                "Alle Kommissionen bezahlt — dein Store ist wieder aktiv und deine Produkte sind wieder sichtbar."
            );
        }
    }

    /**
     * Get or create a commission chat between admin and vendor.
     */
    private static function get_or_create_commission_chat( int $admin_id, int $vendor_id ): int {
        // Search for existing commission chat.
        $args = [
            'post_type'      => 'vendor_chat',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'   => '_dvc_commission_chat',
                    'value' => '1',
                ],
                [
                    'relation' => 'OR',
                    [
                        'relation' => 'AND',
                        [ 'key' => '_dvc_participant_1', 'value' => $admin_id ],
                        [ 'key' => '_dvc_participant_2', 'value' => $vendor_id ],
                    ],
                    [
                        'relation' => 'AND',
                        [ 'key' => '_dvc_participant_1', 'value' => $vendor_id ],
                        [ 'key' => '_dvc_participant_2', 'value' => $admin_id ],
                    ],
                ],
            ],
        ];

        $query = new \WP_Query( $args );
        if ( $query->have_posts() ) {
            return $query->posts[0]->ID;
        }

        // Create new chat.
        $chat_id = wp_insert_post( [
            'post_type'   => 'vendor_chat',
            'post_status' => 'publish',
            'post_title'  => 'Kommissionen',
            'post_author' => $admin_id,
        ] );

        if ( is_wp_error( $chat_id ) ) {
            return 0;
        }

        update_post_meta( $chat_id, '_dvc_participant_1', $admin_id );
        update_post_meta( $chat_id, '_dvc_participant_2', $vendor_id );
        update_post_meta( $chat_id, '_dvc_commission_chat', '1' );
        update_post_meta( $chat_id, '_dvc_archived_by', [] );

        return $chat_id;
    }

    /**
     * Get the main admin user ID (site admin).
     */
    public static function is_enabled(): bool {
        return sk_get_option( 'sk_commission_enforcement', 'sk_lightning', 'off' ) === 'on';
    }

    private static function get_admin_user_id(): int {
        $admin_email = get_option( 'admin_email' );
        $admin = get_user_by( 'email', $admin_email );
        return $admin ? $admin->ID : 1;
    }
}
