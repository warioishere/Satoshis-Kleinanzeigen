<?php

namespace SK\Core\Vendor;

defined( 'ABSPATH' ) || exit;

/**
 * Kein Inserat ohne Shopnamen und Profilbild.
 *
 * Bis der Kontaktzwang fiel, erzwang der sich nebenbei mit: wer nichts
 * hinterlegt hatte, konnte nicht veröffentlichen, und das Formular für die
 * Shopdaten verlangte im selben Zug Name und Bild. Mit dem Kontaktzwang ging
 * diese mittelbare Wirkung verloren — ein frisch angelegtes Konto konnte unter
 * "satoshi-cU8uP" und ohne Bild inserieren.
 *
 * Beides ist keine Formalie: Der Shopname ist das, woran Käufer einen Anbieter
 * wiedererkennen und woran im Telegram-Kanal Vertrauen hängt, und ein Konto
 * ohne Bild und ohne Namen ist für einen Betrachter nicht von einem
 * Wegwerfkonto zu unterscheiden.
 *
 * Bewusst dieselben Nahtstellen wie SuspensionGuard, nur mit einer anderen
 * Frage — und bewusst nur eine Sperre fürs Veröffentlichen: Dashboard und
 * Entwürfe bleiben erreichbar, sonst käme niemand mehr dorthin, wo er das
 * Fehlende nachträgt.
 */
class ProfileGuard {

    public function __construct() {
        add_action( 'sk_new_product_added', [ $this, 'force_draft' ], 6, 2 );
        add_action( 'sk_product_updated',   [ $this, 'force_draft' ], 6, 2 );
        add_action( 'sk_bulk_product_status_change', [ $this, 'force_bulk_draft' ], 6, 2 );

        /*
         * Priorität 20, nicht 6: Products::new_product_status haengt auf 10
         * und macht aus allem, was nicht schon "publish" ist, den in den
         * Einstellungen hinterlegten Status — ein frueher gesetztes "draft"
         * waere danach wieder weg. Die Kontaktsperre lief aus demselben Grund
         * auf 20.
         */
        add_filter( 'sk_get_default_product_status', [ $this, 'filter_default_status' ], 20, 2 );
        add_filter( 'sk_post_status', [ $this, 'filter_post_statuses' ], 98, 2 );
    }

    /**
     * Was diesem Anbieter noch fehlt.
     *
     * @return string[] Leer, wenn alles da ist.
     */
    public static function missing( int $vendor_id ): array {
        if ( $vendor_id <= 0 || ! function_exists( 'sk_get_store_info' ) ) {
            return [];
        }

        $info = sk_get_store_info( $vendor_id );
        $info = is_array( $info ) ? $info : [];

        $fehlt = [];

        if ( ! self::has_shop_name( $info ) ) {
            $fehlt[] = __( 'ein Shopname', 'sk-core' );
        }

        if ( ! self::has_picture( $info ) ) {
            $fehlt[] = __( 'ein Profilbild', 'sk-core' );
        }

        return $fehlt;
    }

    public static function is_complete( int $vendor_id ): bool {
        return empty( self::missing( $vendor_id ) );
    }

    /**
     * Ein Shopname, der diesen Namen verdient.
     *
     * Der automatisch vergebene zaehlt nicht: den traegt jedes frisch
     * angelegte Konto, er unterscheidet niemanden von niemandem. Auf Live
     * kommt er in zwei Formen vor — fortlaufend nummeriert ("satoshi-104",
     * die aelteren) und mit fuenf Zufallszeichen ("satoshi-ngbru"). Das
     * Muster deckt beide ab.
     */
    public static function has_shop_name( array $info ): bool {
        $name = trim( (string) ( $info['store_name'] ?? '' ) );

        if ( $name === '' ) {
            return false;
        }

        return ! preg_match( '/^satoshi-[a-z0-9]{1,8}$/i', $name );
    }

    public static function has_picture( array $info ): bool {
        foreach ( [ 'gravatar', 'icon' ] as $key ) {
            $wert = $info[ $key ] ?? '';
            $wert = is_array( $wert ) ? reset( $wert ) : $wert;

            if ( (string) $wert !== '' && (string) $wert !== '0' ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ein einzelnes Inserat wieder auf Entwurf setzen.
     *
     * @param int   $product_id
     * @param array $data
     */
    public function force_draft( $product_id, $data = [] ): void {
        $product_id = (int) $product_id;

        if ( $product_id <= 0 ) {
            return;
        }

        $author = (int) get_post_field( 'post_author', $product_id );

        if ( ! $author || ! $this->guarded( $author ) ) {
            return;
        }

        if ( ! in_array( get_post_status( $product_id ), [ 'publish', 'pending', 'future', 'private' ], true ) ) {
            return;
        }

        wp_update_post( [ 'ID' => $product_id, 'post_status' => 'draft' ] );

        $this->add_notice( $author );
    }

    /**
     * Dasselbe fuer die Massenaktion im Dashboard.
     *
     * @param string $status
     * @param array  $product_ids
     */
    public function force_bulk_draft( $status, $product_ids ): void {
        if ( ! in_array( $status, [ 'publish', 'pending', 'future' ], true ) ) {
            return;
        }

        $vendor_id = get_current_user_id();

        if ( ! $vendor_id || ! $this->guarded( $vendor_id ) ) {
            return;
        }

        foreach ( (array) $product_ids as $product_id ) {
            $product_id = (int) $product_id;

            if ( $product_id <= 0 || (int) get_post_field( 'post_author', $product_id ) !== $vendor_id ) {
                continue;
            }

            if ( in_array( get_post_status( $product_id ), [ 'publish', 'pending', 'future', 'private' ], true ) ) {
                wp_update_post( [ 'ID' => $product_id, 'post_status' => 'draft' ] );
            }
        }

        $this->add_notice( $vendor_id );
    }

    /**
     * Neue Inserate starten als Entwurf, solange das Profil unfertig ist.
     */
    public function filter_default_status( $status, $seller_id = 0 ) {
        $seller_id = (int) ( $seller_id ?: get_current_user_id() );

        return $this->guarded( $seller_id ) ? 'draft' : $status;
    }

    /**
     * "Veroeffentlichen" verschwindet aus der Auswahl.
     */
    public function filter_post_statuses( $statuses, $product_id = 0 ) {
        $statuses = (array) $statuses;

        if ( ! $this->guarded( get_current_user_id() ) ) {
            return $statuses;
        }

        unset( $statuses['publish'], $statuses['pending'], $statuses['future'] );

        if ( ! isset( $statuses['draft'] ) && function_exists( 'sk_get_post_status' ) ) {
            $statuses['draft'] = sk_get_post_status( 'draft' );
        }

        return $statuses;
    }

    /** Betrifft die Sperre diesen Nutzer? */
    private function guarded( int $vendor_id ): bool {
        if ( $vendor_id <= 0 ) {
            return false;
        }

        if ( ! function_exists( 'sk_is_user_seller' ) || ! sk_is_user_seller( $vendor_id ) ) {
            return false;
        }

        return ! self::is_complete( $vendor_id );
    }

    private function add_notice( int $vendor_id ): void {
        if ( ! function_exists( 'sk_add_notice' ) ) {
            return;
        }

        static $gemeldet = false;

        if ( $gemeldet ) {
            return;
        }

        $gemeldet = true;

        $fehlt = self::missing( $vendor_id );

        sk_add_notice(
            wp_kses_post( sprintf(
                /* translators: 1: Aufzaehlung des Fehlenden, 2: Adresse der Shopdaten. */
                __( 'Veröffentlichung blockiert: Dir fehlt noch %1$s. Trag das in deinem <a href="%2$s">Shop-Profil</a> nach, dann kannst du dein Inserat veröffentlichen.', 'sk-core' ),
                implode( __( ' und ', 'sk-core' ), $fehlt ),
                esc_url( site_url( '/dashboard/settings/store/' ) )
            ) ),
            'error'
        );
    }
}
