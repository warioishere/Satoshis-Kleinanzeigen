<?php

namespace SK\Modules\Auth;

defined( 'ABSPATH' ) || exit;

/**
 * One place for everything Nostr in the SK settings.
 *
 * Relays used to be configured in three sections (Auth, Notifications,
 * Nostr Market) plus the Auto Poster's own page under the WordPress
 * settings, each with its own list. Every sender and every poller now reads
 * the single list kept in the `nostr_login_relays` option, and this section
 * is the only place that writes it.
 *
 * Switches that belong to other modules (Auto Poster, Nostr Market) are
 * shown here too; their option keys are redirected into this section via
 * the settings rearrange map in core, so the modules keep reading them as
 * before.
 */
class NostrSettings {

    const SECTION = 'sk_nostr';

    /** Set once the values of the old sections have been carried over. */
    const MIGRATED_OPTION = 'sk_nostr_settings_migrated';

    public function __construct() {
        add_filter( 'sk_settings_sections', [ $this, 'add_section' ] );
        add_filter( 'sk_settings_fields', [ $this, 'add_fields' ] );
        add_filter( 'sk_save_settings_value', [ $this, 'strip_private_key' ], 10, 2 );
        add_action( 'sk_after_saving_settings', [ $this, 'sync_legacy_options' ], 10, 3 );
        add_action( 'admin_init', [ $this, 'migrate_once' ] );
    }

    public function add_section( $sections ) {
        $sections[] = [
            'id'                   => self::SECTION,
            'title'                => __( 'Nostr', 'sk-core' ),
            'icon_url'             => '',
            'description'          => __( 'Relays, Schlüssel, Auto Poster, Marktplatz', 'sk-core' ),
            'settings_title'       => __( 'Nostr', 'sk-core' ),
            'settings_description' => __( 'Alles, was SK auf Nostr tut, läuft über die Relays und den Schlüssel auf dieser Seite: Login, Profile, Auto Poster, Kleinanzeigen und Nachrichten.', 'sk-core' ),
        ];

        return $sections;
    }

    public function add_fields( $settings_fields ) {
        $fields = [
            'sk_nostr_relays_header' => [
                'name'  => 'sk_nostr_relays_header',
                'label' => __( 'Relays', 'sk-core' ),
                'type'  => 'sub_section',
                'desc'  => __( 'Jedes Ereignis geht an alle Relays dieser Liste; es gilt als gesendet, sobald eines es annimmt. Abgerufen wird ebenfalls von allen. Ein Relay, das nicht antwortet, wird nach fünf Sekunden (Senden) bzw. zehn Sekunden (Abrufen) übersprungen und im Fehlerprotokoll vermerkt.', 'sk-core' ),
            ],
            'sk_nostr_relays' => [
                'name'    => 'sk_nostr_relays',
                'label'   => __( 'Relay-Adressen', 'sk-core' ),
                'type'    => 'textarea',
                'default' => implode( "\n", NostrIdentity::get_relays() ),
                'desc'    => __( 'Eine Adresse pro Zeile, nur wss://. Drei bis fünf gut erreichbare Relays reichen.', 'sk-core' ),
                'rows'    => 6,
            ],
            'sk_nostr_key_header' => [
                'name'  => 'sk_nostr_key_header',
                'label' => __( 'Marktplatz-Schlüssel', 'sk-core' ),
                'type'  => 'sub_section',
                'desc'  => $this->key_description(),
            ],
        ];

        if ( ! self::key_in_config() ) {
            $fields['sk_nostr_private_key'] = [
                'name'    => 'sk_nostr_private_key',
                'label'   => __( 'Privater Schlüssel', 'sk-core' ),
                'type'    => 'text',
                'default' => '',
                'desc'    => __( 'Hex oder nsec. Wird nicht in dieser Sektion abgelegt, sondern beim Speichern in die Auto-Poster-Option übernommen. Sicherer ist NAP_NOSTR_PRIVKEY in der wp-config.php; dann verschwindet dieses Feld.', 'sk-core' ),
            ];
        }

        if ( function_exists( 'sk_module_active' ) && sk_module_active( 'sk_notifications' ) ) {
            $fields['sk_nostr_poster_header'] = [
                'name'  => 'sk_nostr_poster_header',
                'label' => __( 'Auto Poster', 'sk-core' ),
                'type'  => 'sub_section',
                'desc'  => __( 'Postet jedes neu veröffentlichte Inserat als Notiz (Kind 1) unter dem Marktplatz-Schlüssel.', 'sk-core' ),
            ];
            $fields['sk_notif_nostr_enabled'] = [
                'name'    => 'sk_notif_nostr_enabled',
                'label'   => __( 'Auto Poster aktivieren', 'sk-core' ),
                'type'    => 'switcher',
                'default' => 'on',
                'desc'    => __( 'Neue Inserate automatisch auf Nostr posten.', 'sk-core' ),
            ];
        }

        if ( function_exists( 'sk_module_active' ) && sk_module_active( 'sk_nostr_market' ) ) {
            $fields['sk_nostr_market_header'] = [
                'name'  => 'sk_nostr_market_header',
                'label' => __( 'Nostr Market', 'sk-core' ),
                'type'  => 'sub_section',
                'desc'  => __( 'Inserate als Kleinanzeigen (NIP-99, Kind 30402) unter dem Schlüssel des Anbieters. Ob ein Inserat dorthin geht, entscheidet der Anbieter am Inserat selbst unter „Weitere Optionen“, Vorgabe ist aus.', 'sk-core' ),
            ];
            $fields['sk_nostr_market_enabled'] = [
                'name'    => 'sk_nostr_market_enabled',
                'label'   => __( 'Nostr Market aktivieren', 'sk-core' ),
                'type'    => 'switcher',
                'default' => 'off',
                'desc'    => __( 'Schaltet die Veröffentlichung von Kleinanzeigen frei. Angezeigt in Clients, die Kleinanzeigen unterstützen, etwa Amethyst oder Shopstr; Primal derzeit nicht.', 'sk-core' ),
            ];
            $fields['sk_nostr_market_currency'] = [
                'name'    => 'sk_nostr_market_currency',
                'label'   => __( 'Währung', 'sk-core' ),
                'type'    => 'select',
                'default' => 'sat',
                'options' => [
                    'sat' => 'Satoshis (sat)',
                    'btc' => 'Bitcoin (BTC)',
                ],
                'desc' => __( 'Währung der Preisangabe in der Kleinanzeige. Zwischen Sats und BTC wird umgerechnet.', 'sk-core' ),
            ];
            $fields['sk_nostr_market_bridge_enabled'] = [
                'name'    => 'sk_nostr_market_bridge_enabled',
                'label'   => __( 'Nachrichten-Brücke aktivieren', 'sk-core' ),
                'type'    => 'switcher',
                'default' => 'off',
                'desc'    => __( 'Holt alle zwei Minuten Nostr-Nachrichten an die Anbieter und den Marktplatz von den Relays und stellt sie im Chat zu; Antworten aus dem Chat gehen als Nachricht zurück.', 'sk-core' ),
            ];
        }

        $settings_fields[ self::SECTION ] = $fields;

        return $settings_fields;
    }

    /**
     * Carry the values of the old sections over, once, so nothing flips when
     * the reads are redirected here. The redirect itself is the rearrange
     * map in sk_admin_settings_rearrange_map() (core).
     */
    public function migrate_once(): void {
        if ( get_option( self::MIGRATED_OPTION ) ) {
            return;
        }

        $current = get_option( self::SECTION, [] );
        $current = is_array( $current ) ? $current : [];

        $sources = [
            'sk_notifications' => [ 'sk_notif_nostr_enabled' ],
            'sk_nostr_market'  => [ 'sk_nostr_market_enabled', 'sk_nostr_market_bridge_enabled', 'sk_nostr_market_currency' ],
        ];

        foreach ( $sources as $section => $keys ) {
            $old = get_option( $section, [] );

            foreach ( $keys as $key ) {
                if ( ! isset( $current[ $key ] ) && isset( $old[ $key ] ) ) {
                    $current[ $key ] = $old[ $key ];
                }
            }
        }

        if ( ! isset( $current['sk_nostr_relays'] ) ) {
            $current['sk_nostr_relays'] = implode( "\n", NostrIdentity::get_relays() );
        }

        update_option( self::SECTION, $current );
        update_option( self::MIGRATED_OPTION, 1 );
    }

    /**
     * The private key never stays in this section's option array.
     *
     * @param mixed  $value
     * @param string $option_name
     */
    public function strip_private_key( $value, $option_name ) {
        if ( self::SECTION === $option_name && is_array( $value ) && isset( $value['sk_nostr_private_key'] ) ) {
            $this->store_private_key( (string) $value['sk_nostr_private_key'] );
            unset( $value['sk_nostr_private_key'] );
        }

        return $value;
    }

    public function sync_legacy_options( $section, $new_values, $old_values ) {
        if ( $section !== self::SECTION || ! is_array( $new_values ) ) {
            return;
        }

        if ( isset( $new_values['sk_nostr_relays'] ) ) {
            $relays = preg_split( '/[\r\n,\s]+/', (string) $new_values['sk_nostr_relays'] );
            $relays = array_values( array_unique( array_filter( array_map( 'trim', (array) $relays ), static function ( $r ) {
                return (bool) preg_match( '#^wss?://#i', $r );
            } ) ) );

            update_option( 'nostr_login_relays', implode( "\n", $relays ) );
        }
    }

    private function store_private_key( string $key ): void {
        $key = trim( $key );

        if ( '' === $key ) {
            return;
        }

        if ( 0 === strpos( $key, 'nsec' ) && class_exists( '\swentel\nostr\Key\Key' ) ) {
            try {
                $key = (string) ( new \swentel\nostr\Key\Key() )->convertToHex( $key );
            } catch ( \Throwable $e ) {
                return;
            }
        }

        if ( ! preg_match( '/^[0-9a-fA-F]{64}$/', $key ) ) {
            return;
        }

        $opts                = get_option( 'nap_nostr_options', [] );
        $opts                = is_array( $opts ) ? $opts : [];
        $opts['private_key'] = strtolower( $key );

        update_option( 'nap_nostr_options', $opts );
    }

    public static function key_in_config(): bool {
        return defined( 'NAP_NOSTR_PRIVKEY' ) && NAP_NOSTR_PRIVKEY;
    }

    private function key_description(): string {
        $pubkey = self::marketplace_pubkey();

        if ( '' === $pubkey ) {
            return __( 'Kein Schlüssel hinterlegt. Der Marktplatz kann weder posten noch Nachrichten empfangen.', 'sk-core' );
        }

        $npub = '';

        if ( class_exists( '\swentel\nostr\Key\Key' ) ) {
            try {
                $npub = (string) ( new \swentel\nostr\Key\Key() )->convertPublicKeyToBech32( $pubkey );
            } catch ( \Throwable $e ) {
                $npub = '';
            }
        }

        $where = self::key_in_config()
            ? __( 'Der private Schlüssel steht als NAP_NOSTR_PRIVKEY in der wp-config.php.', 'sk-core' )
            : __( 'Der private Schlüssel liegt in der Datenbank.', 'sk-core' );

        return esc_html( $where ) . '<br><code>' . esc_html( $npub ?: $pubkey ) . '</code>';
    }

    /**
     * Public key of the marketplace identity, derived from the private key.
     */
    public static function marketplace_pubkey(): string {
        $key = '';

        if ( self::key_in_config() ) {
            $key = (string) NAP_NOSTR_PRIVKEY;
        } else {
            $opts = get_option( 'nap_nostr_options', [] );
            $key  = is_array( $opts ) ? (string) ( $opts['private_key'] ?? '' ) : '';
        }

        $key = trim( $key );

        if ( '' === $key || ! class_exists( '\swentel\nostr\Key\Key' ) ) {
            return '';
        }

        try {
            $k = new \swentel\nostr\Key\Key();

            if ( 0 === strpos( $key, 'nsec' ) ) {
                $key = (string) $k->convertToHex( $key );
            }

            return preg_match( '/^[0-9a-fA-F]{64}$/', $key ) ? (string) $k->getPublicKey( strtolower( $key ) ) : '';
        } catch ( \Throwable $e ) {
            return '';
        }
    }
}
