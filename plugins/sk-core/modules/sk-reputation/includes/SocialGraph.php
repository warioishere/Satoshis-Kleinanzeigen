<?php

namespace SK\Modules\Reputation;

use SK\Core\Trust\TrustSignals;
use SK\Core\Trust\VendorKey;

defined( 'ABSPATH' ) || exit;

/**
 * "You follow this vendor" / "3 of your contacts follow this vendor".
 *
 * The signal is relative to the viewer and is computed in the viewer's
 * browser from the viewer's own Nostr contact list; the server only marks
 * where a chip may appear and which key the vendor is bound to. Nothing
 * here waits for a relay, and a vendor without a proven key or a viewer
 * without a Nostr key sees no trace of it.
 */
class SocialGraph {

    public function __construct() {
        TrustSignals::register( 'graph', [ $this, 'chip' ], 10 );
    }

    /**
     * An empty, hidden chip carrying the vendor's key. The script fills it
     * in once it knows the viewer's graph — or leaves it hidden.
     */
    public function chip( int $vendor_id, string $context ): string {
        $pubkey = VendorKey::bound( $vendor_id );

        if ( '' === $pubkey ) {
            return '';
        }

        self::ensure_assets();

        $tag = TrustSignals::CONTEXT_STORE === $context ? 'li' : 'span';

        return sprintf(
            '<%1$s class="sk-trust-graph sk-trust-graph--%2$s" hidden data-pubkey="%3$s"></%1$s>',
            $tag,
            esc_attr( $context ),
            esc_attr( $pubkey )
        );
    }

    public static function ensure_assets(): void {
        if ( wp_script_is( 'sk-social-graph', 'enqueued' ) ) {
            return;
        }

        wp_enqueue_style(
            'sk-trust',
            SK_REPUTATION_URL . '/assets/css/sk-trust.css',
            [],
            SK_REPUTATION_VERSION
        );

        wp_enqueue_script(
            'sk-social-graph',
            SK_REPUTATION_URL . '/assets/js/sk-social-graph.js',
            [],
            SK_REPUTATION_VERSION,
            true
        );

        $relays = sk_module_active( 'sk_auth' ) && class_exists( 'SK\Modules\Auth\NostrIdentity' )
            ? \SK\Modules\Auth\NostrIdentity::get_relays()
            : [ 'wss://relay.damus.io', 'wss://nos.lol', 'wss://relay.primal.net' ];

        // A logged-in viewer with a proven key needs no extension at all:
        // the contact list is public, only the key has to be known.
        $viewer = is_user_logged_in() ? VendorKey::bound( get_current_user_id() ) : '';

        wp_localize_script( 'sk-social-graph', 'skTrustGraph', [
            'relays' => array_values( $relays ),
            'viewer' => $viewer,
            'i18n'   => [
                'follow'    => __( 'Du folgst diesem Anbieter', 'sk-core' ),
                'contact1'  => __( '%d deiner Kontakte folgt', 'sk-core' ),
                'contactN'  => __( '%d deiner Kontakte folgen', 'sk-core' ),
                // Short forms for the product box and the feed card.
                'followS'   => __( 'Du folgst', 'sk-core' ),
                'contact1S' => __( '%d Kontakt folgt', 'sk-core' ),
                'contactNS' => __( '%d Kontakte folgen', 'sk-core' ),
                'why'       => __( 'Aus deiner eigenen Kontaktliste auf Nostr berechnet, nur in deinem Browser.', 'sk-core' ),
                'more'      => __( 'und %d weitere', 'sk-core' ),
                // Reports (kind 1984) by the viewer's own contacts.
                'report1'   => __( '%d deiner Kontakte hat diesen Anbieter gemeldet', 'sk-core' ),
                'reportN'   => __( '%d deiner Kontakte haben diesen Anbieter gemeldet', 'sk-core' ),
                'report1S'  => __( '%d Kontakt meldet', 'sk-core' ),
                'reportNS'  => __( '%d Kontakte melden', 'sk-core' ),
                'reportWhy' => __( 'Meldungen aus deiner eigenen Kontaktliste. Was gemeldet wurde, entscheidet der Melder, nicht der Marktplatz.', 'sk-core' ),
                'types'     => [
                    'spam'          => __( 'Spam', 'sk-core' ),
                    'impersonation' => __( 'Identitätsmissbrauch', 'sk-core' ),
                    'illegal'       => __( 'Illegal', 'sk-core' ),
                    'malware'       => __( 'Schadsoftware', 'sk-core' ),
                ],
            ],
        ] );
    }
}
