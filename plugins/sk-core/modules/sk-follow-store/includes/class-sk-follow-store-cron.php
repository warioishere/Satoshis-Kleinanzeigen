<?php

class SK_Follow_Store_Cron {

    /**
     * Cron hook name.
     */
    const HOOK = 'sk_follow_store_send_updates';

    /**
     * WooCommerce settings option of the digest email.
     */
    const SETTINGS_OPTION = 'woocommerce_updates_for_store_followers_settings';

    /**
     * Class constructor
     *
     *
     * @return void
     */
    public function __construct() {
        add_filter( 'cron_schedules', array( $this, 'add_weekly_schedule' ) );
        add_action( 'init', array( __CLASS__, 'maybe_schedule_event' ), 20 );
        add_action( self::HOOK, array( $this, 'send_based_on_frequency' ) );
    }

    /**
     * Is the digest email switched on?
     *
     * Reads the WooCommerce option directly so this stays cheap enough for
     * every request — booting WC_Emails just to read a checkbox would not be.
     *
     *
     * @return bool
     */
    public static function is_enabled() {
        $settings = get_option( self::SETTINGS_OPTION, array() );

        return ! isset( $settings['enabled'] ) || 'yes' === $settings['enabled'];
    }

    /**
     * Keep the cron event in step with the email settings.
     *
     * The event used to be scheduled only by process_admin_options(), so it
     * existed only if an admin had saved the email settings at least once —
     * which had never happened, and the digest therefore never went out.
     *
     *
     * @return void
     */
    public static function maybe_schedule_event() {
        $event = wp_get_scheduled_event( self::HOOK );

        if ( ! self::is_enabled() ) {
            if ( $event ) {
                wp_unschedule_event( $event->timestamp, self::HOOK );
            }

            return;
        }

        $frequency = self::get_frequency();

        if ( $event && $event->schedule === $frequency ) {
            return;
        }

        if ( $event ) {
            wp_unschedule_event( $event->timestamp, self::HOOK );
        }

        wp_schedule_event( self::next_run_timestamp(), $frequency, self::HOOK );
    }

    /**
     * First run: the coming 8 o'clock, site time.
     *
     * Not time(), which would fire the digest on the next cron tick right after
     * a deploy or a settings save.
     *
     *
     * @return int
     */
    private static function next_run_timestamp() {
        $next = sk_current_datetime()->modify( 'tomorrow 8:00' );

        return $next ? $next->getTimestamp() : time() + DAY_IN_SECONDS;
    }
    public function send_based_on_frequency() {
        if ( $this->get_frequency() === 'daily' ) {
            $this->send_updates();
        } else {
            $this->send_weekly_updates();
        }
    }
    /**
     * Add a weekly cron schedule to WordPress.
     *
     * @param array $schedules The existing cron schedules.
     * @return array The schedules with the new 'weekly' interval.
     */ 
    public function add_weekly_schedule( $schedules ) {
        $schedules['weekly'] = array(
            'interval' => 604800,// 7 days in seconds
            'display' => 'Once Weekly',
        );
        return $schedules;
    }
    /**
     * Get frequency setting
     *
     * @return string 'daily' or 'weekly'
     */
    public static function get_frequency() {
        $settings = get_option( self::SETTINGS_OPTION, array() );

        return isset( $settings['frequency'] ) && 'weekly' === $settings['frequency'] ? 'weekly' : 'daily';
    }
    /**
     * Unschedule cron
     *
     * Fires when module deactivate
     *
     *
     * @return void
     */
    public static function unschedule_event() {
        $timestamp = wp_next_scheduled( self::HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::HOOK );
        }
    }

    /**
     * Cron action hook method
     *
     *
     * @return void
     */
    public function send_updates() {
        $processor_file = SK_FOLLOW_STORE_INCLUDES . '/class-sk-follow-store-send-updates.php';

        global $sk_follow_store_updates_bg;
        if ( empty( $sk_follow_store_updates_bg ) ) {
            return;
        }

        $sk_follow_store_updates_bg->cancel_process();

        $yesterday = date( 'Y-m-d', strtotime( '-24 hours', current_time( 'timestamp' ) ) );
        $from      = $yesterday . ' 00:00:00';
        $to        = $yesterday . ' 23:59:59';

        $args = array(
            'page'  => 1,
            'from'  => $from,
            'to'    => $to,
        );

        $sk_follow_store_updates_bg->push_to_queue( $args )->dispatch_process( $processor_file );
    }
    /**
     * Send weekly updates (conditional on frequency).
     *
     *
     * @return void
     */
    public function send_weekly_updates() {

    $processor_file = SK_FOLLOW_STORE_INCLUDES . '/class-sk-follow-store-send-updates.php';

    global $sk_follow_store_updates_bg;
    if ( empty( $sk_follow_store_updates_bg ) ) {
            return;
    }

    $sk_follow_store_updates_bg->cancel_process();

        $from = date( 'Y-m-d 00:00:00', strtotime( '-7 days', sk_current_datetime()->getTimestamp() ) );
        $to   = date( 'Y-m-d 23:59:59', strtotime( '-1 day', sk_current_datetime()->getTimestamp() ) );

    $args = array(
        'page'  => 1,
        'from'  => $from,
        'to'    => $to,
    );

    $sk_follow_store_updates_bg->push_to_queue( $args )->dispatch_process( $processor_file );
    }
}
