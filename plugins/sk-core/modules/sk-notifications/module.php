<?php

namespace SK\Modules\Notifications;

defined( 'ABSPATH' ) || exit;

/**
 * SK Notifications — Auto-post products to external channels.
 *
 * Sub-modules:
 *   - Nostr: Posts new products to Nostr relays (originally nostr-auto-poster by Wario)
 *   - Telegram: Sends products to Telegram channel (originally telegram-notifications by Wario)
 */
final class Module {

    public $version = '1.0.0';

    public function __construct() {
        $this->define_constants();

        // Settings always load.
        require_once SK_NOTIFICATIONS_INCLUDES . '/NotificationSettings.php';
        new NotificationSettings();

        $this->load_nostr();
        $this->load_telegram();
    }

    private function define_constants() {
        define( 'SK_NOTIFICATIONS_VERSION', $this->version );
        define( 'SK_NOTIFICATIONS_FILE', __FILE__ );
        define( 'SK_NOTIFICATIONS_PATH', dirname( SK_NOTIFICATIONS_FILE ) );
        define( 'SK_NOTIFICATIONS_INCLUDES', SK_NOTIFICATIONS_PATH . '/includes' );
    }

    private function load_nostr() {
        if ( sk_get_option( 'sk_notif_nostr_enabled', 'sk_notifications', 'on' ) !== 'on' ) {
            return;
        }
        require_once SK_NOTIFICATIONS_INCLUDES . '/Nostr/AutoPoster.php';
    }

    private function load_telegram() {
        if ( sk_get_option( 'sk_notif_telegram_enabled', 'sk_notifications', 'on' ) !== 'on' ) {
            return;
        }
        require_once SK_NOTIFICATIONS_INCLUDES . '/Telegram/Notifier.php';
    }
}
