<?php

namespace SK\Modules\Notifications;

defined( 'ABSPATH' ) || exit;

class NotificationSettings {

    const SECTION = 'sk_telegram';

    /** The section this one replaces — used to only hold Telegram fields anyway. */
    const LEGACY_SECTION = 'sk_notifications';

    public function __construct() {
        $this->migrate_legacy_section();

        add_filter( 'sk_settings_sections', [ $this, 'add_section' ] );
        add_filter( 'sk_settings_fields', [ $this, 'add_fields' ] );
        add_action( 'sk_after_saving_settings', [ $this, 'sync_legacy_options' ], 10, 3 );
    }

    /**
     * The Telegram fields used to live in the generic "SK Notifications"
     * section, alongside now-orphaned Nostr keys from an earlier layout
     * (Nostr moved to sk_nostr, see sk-auth NostrSettings). Telegram gets
     * its own section now; only the Telegram keys are carried over.
     */
    private function migrate_legacy_section(): void {
        $legacy = get_option( self::LEGACY_SECTION, null );

        if ( ! is_array( $legacy ) ) {
            return;
        }

        $section = get_option( self::SECTION );
        $section = is_array( $section ) ? $section : [];

        if ( ! isset( $section['sk_notif_telegram_enabled'] ) ) {
            foreach ( [ 'sk_notif_telegram_enabled', 'sk_notif_telegram_bot_token', 'sk_notif_telegram_chat_id' ] as $key ) {
                if ( isset( $legacy[ $key ] ) ) {
                    $section[ $key ] = $legacy[ $key ];
                }
            }
            update_option( self::SECTION, $section );
        }

        delete_option( self::LEGACY_SECTION );
    }

    public function add_section( $sections ) {
        $sections[] = [
            'id'                   => self::SECTION,
            'title'                => __( 'Telegram', 'sk-core' ),
            'icon_url'             => '',
            'description'          => __( 'Produkte an Telegram senden', 'sk-core' ),
            'settings_title'       => __( 'Telegram Benachrichtigungen', 'sk-core' ),
            'settings_description' => __( 'Konfiguriere automatische Benachrichtigungen an einen Telegram-Kanal, wenn Produkte veröffentlicht werden.', 'sk-core' ),
        ];

        return $sections;
    }

    public function add_fields( $settings_fields ) {
        $settings_fields[ self::SECTION ] = [
            'sk_notif_telegram_enabled' => [
                'name'    => 'sk_notif_telegram_enabled',
                'label'   => __( 'Telegram aktivieren', 'sk-core' ),
                'type'    => 'switcher',
                'default' => 'on',
                'desc'    => __( 'Neue Produkte automatisch an Telegram senden.', 'sk-core' ),
            ],
            'sk_notif_telegram_bot_token' => [
                'name'    => 'sk_notif_telegram_bot_token',
                'label'   => __( 'Bot Token', 'sk-core' ),
                'type'    => 'text',
                'default' => get_option( 'telegram_bot_token', '' ),
                'desc'    => __( 'Telegram Bot Token von @BotFather.', 'sk-core' ),
            ],
            'sk_notif_telegram_chat_id' => [
                'name'    => 'sk_notif_telegram_chat_id',
                'label'   => __( 'Chat ID / Channel', 'sk-core' ),
                'type'    => 'text',
                'default' => get_option( 'telegram_chat_id', '' ),
                'desc'    => __( 'Chat ID oder @channel Name.', 'sk-core' ),
            ],
        ];

        return $settings_fields;
    }

    public function sync_legacy_options( $section, $new_values, $old_values ) {
        if ( $section !== self::SECTION ) {
            return;
        }

        // Telegram legacy options.
        if ( isset( $new_values['sk_notif_telegram_bot_token'] ) ) {
            update_option( 'telegram_bot_token', $new_values['sk_notif_telegram_bot_token'] );
        }
        if ( isset( $new_values['sk_notif_telegram_chat_id'] ) ) {
            update_option( 'telegram_chat_id', $new_values['sk_notif_telegram_chat_id'] );
        }
    }
}
