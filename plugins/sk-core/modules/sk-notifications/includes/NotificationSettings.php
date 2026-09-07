<?php

namespace SK\Modules\Notifications;

defined( 'ABSPATH' ) || exit;

class NotificationSettings {

    public function __construct() {
        add_filter( 'sk_settings_sections', [ $this, 'add_section' ] );
        add_filter( 'sk_settings_fields', [ $this, 'add_fields' ] );
        add_action( 'sk_after_saving_settings', [ $this, 'sync_legacy_options' ], 10, 3 );
    }

    public function add_section( $sections ) {
        $sections[] = [
            'id'                   => 'sk_notifications',
            'title'                => __( 'SK Notifications', 'sk-core' ),
            'icon_url'             => '',
            'description'          => __( 'Produkte an Telegram senden', 'sk-core' ),
            'settings_title'       => __( 'Benachrichtigungen', 'sk-core' ),
            'settings_description' => __( 'Konfiguriere automatische Benachrichtigungen wenn Produkte veröffentlicht werden.', 'sk-core' ),
        ];

        return $sections;
    }

    public function add_fields( $settings_fields ) {
        // The Nostr Auto Poster is configured in the Nostr section (sk-auth,
        // NostrSettings) together with relays and key.
        $settings_fields['sk_notifications'] = [

            // ── Telegram ──
            'sk_notif_telegram_header' => [
                'name'  => 'sk_notif_telegram_header',
                'label' => __( 'Telegram Benachrichtigungen', 'sk-core' ),
                'type'  => 'sub_section',
                'desc'  => __( 'Sendet neue Produkte an einen Telegram-Kanal.', 'sk-core' ),
            ],
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
        if ( $section !== 'sk_notifications' ) {
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
