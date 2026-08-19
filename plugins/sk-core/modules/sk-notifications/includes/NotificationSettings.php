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
            'description'          => __( 'Produkte auf Nostr und Telegram posten', 'sk-core' ),
            'settings_title'       => __( 'Benachrichtigungen', 'sk-core' ),
            'settings_description' => __( 'Konfiguriere automatische Benachrichtigungen wenn Produkte veröffentlicht werden.', 'sk-core' ),
        ];

        return $sections;
    }

    public function add_fields( $settings_fields ) {
        $nostr_options = get_option( 'nap_nostr_options', [] );

        $settings_fields['sk_notifications'] = [

            // ── Nostr ──
            'sk_notif_nostr_header' => [
                'name'  => 'sk_notif_nostr_header',
                'label' => __( 'Nostr Auto Poster', 'sk-core' ),
                'type'  => 'sub_section',
                'desc'  => __( 'Postet neue Produkte automatisch auf Nostr Relays.', 'sk-core' ),
            ],
            'sk_notif_nostr_enabled' => [
                'name'    => 'sk_notif_nostr_enabled',
                'label'   => __( 'Nostr Poster aktivieren', 'sk-core' ),
                'type'    => 'switcher',
                'default' => 'on',
                'desc'    => __( 'Neue Produkte automatisch auf Nostr posten.', 'sk-core' ),
            ],
            'sk_notif_nostr_relays' => [
                'name'    => 'sk_notif_nostr_relays',
                'label'   => __( 'Nostr Relays', 'sk-core' ),
                'type'    => 'text',
                'default' => $nostr_options['relays'] ?? "wss://relay.damus.io\nwss://relay.nostr.band\nwss://nos.lol",
                'desc'    => __( 'Relay URLs (eine pro Zeile oder kommasepariert).', 'sk-core' ),
            ],
            'sk_notif_nostr_timeout' => [
                'name'    => 'sk_notif_nostr_timeout',
                'label'   => __( 'Relay Timeout (Sekunden)', 'sk-core' ),
                'type'    => 'number',
                'default' => $nostr_options['timeout'] ?? '3',
                'desc'    => __( 'Timeout pro Relay in Sekunden (1-10).', 'sk-core' ),
            ],

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

        // Nostr legacy options.
        $nostr_options = get_option( 'nap_nostr_options', [] );
        if ( isset( $new_values['sk_notif_nostr_relays'] ) ) {
            $nostr_options['relays'] = $new_values['sk_notif_nostr_relays'];
        }
        if ( isset( $new_values['sk_notif_nostr_timeout'] ) ) {
            $nostr_options['timeout'] = (int) $new_values['sk_notif_nostr_timeout'];
        }
        update_option( 'nap_nostr_options', $nostr_options );

        // Telegram legacy options.
        if ( isset( $new_values['sk_notif_telegram_bot_token'] ) ) {
            update_option( 'telegram_bot_token', $new_values['sk_notif_telegram_bot_token'] );
        }
        if ( isset( $new_values['sk_notif_telegram_chat_id'] ) ) {
            update_option( 'telegram_chat_id', $new_values['sk_notif_telegram_chat_id'] );
        }
    }
}
