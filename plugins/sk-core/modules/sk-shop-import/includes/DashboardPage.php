<?php

namespace SK\Modules\ShopImport;

use SK\Core\Dashboard\DashboardModule;

defined( 'ABSPATH' ) || exit;

/**
 * Verkäufer-Dashboard: Katalog hochladen und importieren.
 *
 * Erscheint nur bei freigeschalteten Händlern — für die übrigen 480 Konten
 * gibt es den Eintrag gar nicht.
 */
class DashboardPage extends DashboardModule {

    const NONCE = 'sk_shop_import';

    /**
     * Immer registrieren, nicht nur fuer Haendler.
     *
     * Query-Variable und Rewrite-Regel sind global; haengt die Registrierung
     * am aktuellen Nutzer, existiert die Adresse fuer niemanden und die Seite
     * meldet "nicht gefunden". Wer sie sehen darf, regelt die Faehigkeit —
     * die Registry prueft sie sowohl im Menue als auch beim Aufruf.
     */
    public function config(): ?array {
        return [
            'slug'       => 'shop-import',
            'title'      => __( 'Shop-Import', 'sk-core' ),
            'icon'       => '<i class="fas fa-file-import"></i>',
            // Direkt hinter "Produkte" (pos 30). Nachkommastellen helfen hier
            // nicht: sk_nav_sort_by_pos rechnet intval($a-$b), 30.5 gegen 31
            // ergibt 0 und gilt als gleich.
            'pos'        => 31,
            'permission' => Dealer::CAP,
            // Pfad statt Rueckruf und Daten ueber template_args — dasselbe
            // Muster wie Merkliste und Gesuche. Die Vorlage bringt dadurch
            // die Dashboard-Huelle mit Menue und Containern mit.
            'template'      => 'dashboard/shop-import/dashboard-shop-import',
            'template_args' => [ $this, 'view_data' ],
        ];
    }

    protected function register_extras(): void {
        add_action( 'template_redirect', [ $this, 'handle_post' ] );
    }

    private function url(): string {
        return function_exists( 'sk_get_navigation_url' )
            ? sk_get_navigation_url( 'shop-import' )
            : home_url( '/dashboard/shop-import/' );
    }

    /**
     * Upload und Import entgegennehmen.
     */
    public function handle_post(): void {
        if ( strtoupper( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) !== 'POST' ) {
            return;
        }
        if ( ! isset( $_POST['sk_shop_import_nonce'] ) || ! wp_verify_nonce( $_POST['sk_shop_import_nonce'], self::NONCE ) ) {
            return;
        }

        $vendor_id = get_current_user_id();
        if ( ! $vendor_id || ! Dealer::may_import( $vendor_id ) ) {
            return;
        }

        $step = sanitize_key( wp_unslash( $_POST['sk_step'] ?? '' ) );

        if ( $step === 'upload' ) {
            $path = Storage::accept( $_FILES['sk_csv'] ?? [], $vendor_id );

            if ( is_wp_error( $path ) ) {
                set_transient( 'sk_import_msg_' . $vendor_id, $path->get_error_message(), 120 );
                wp_safe_redirect( $this->url() );
                exit;
            }

            update_user_meta( $vendor_id, '_sk_import_file', $path );
            wp_safe_redirect( add_query_arg( 'schritt', 'zuordnen', $this->url() ) );
            exit;
        }

        if ( $step === 'run' ) {
            $this->run_import( $vendor_id );
            exit;
        }
    }

    private function run_import( int $vendor_id ): void {
        $path = (string) get_user_meta( $vendor_id, '_sk_import_file', true );

        if ( $path === '' || ! Storage::belongs_to( $path, $vendor_id ) ) {
            set_transient( 'sk_import_msg_' . $vendor_id, __( 'Die hochgeladene Datei wurde nicht gefunden. Bitte erneut hochladen.', 'sk-core' ), 120 );
            wp_safe_redirect( $this->url() );
            exit;
        }

        $csv = Csv::read( $path );
        if ( is_wp_error( $csv ) ) {
            set_transient( 'sk_import_msg_' . $vendor_id, $csv->get_error_message(), 120 );
            wp_safe_redirect( $this->url() );
            exit;
        }

        $mapping = Csv::guess_mapping( $csv['headers'] );
        foreach ( array_keys( Csv::FIELDS ) as $field ) {
            if ( isset( $_POST[ 'map_' . $field ] ) ) {
                $mapping[ $field ] = (int) $_POST[ 'map_' . $field ];
            }
        }

        // Kategorie-Zuordnung sichern, damit sie beim naechsten Import steht.
        $map = [];
        foreach ( (array) ( $_POST['cat_map'] ?? [] ) as $name => $term ) {
            $map[ sanitize_text_field( wp_unslash( $name ) ) ] = (int) $term;
        }
        Settings::save_category_map( $vendor_id, $map );
        Settings::save_default_category( $vendor_id, (int) ( $_POST['sk_default_cat'] ?? 0 ) );
        Settings::save_currency( $vendor_id, sanitize_text_field( wp_unslash( $_POST['sk_currency'] ?? '' ) ) );

        $items = Catalog::build( $csv['headers'], $csv['rows'], $mapping );

        // Nur die angehakten uebernehmen. Ohne Auswahl im Formular gilt alles.
        $chosen = array_filter( array_map( 'strval', (array) ( $_POST['sk_pick'] ?? [] ) ) );
        if ( ! empty( $chosen ) ) {
            $items = array_values(
                array_filter( $items, static fn( $item ) => in_array( (string) ( $item['key'] ?? '' ), $chosen, true ) )
            );
        }

        // Kontingent gilt auch fuer Haendler — wer mehr einstellen will,
        // braucht ein groesseres Paket oder waehlt weniger aus.
        $quota = Quota::check( $vendor_id, count( $items ) );
        if ( ! $quota['ok'] ) {
            set_transient( 'sk_import_quota_' . $vendor_id, $quota, 600 );
            wp_safe_redirect( add_query_arg( 'schritt', 'kontingent', $this->url() ) );
            exit;
        }

        $result = Importer::run(
            $items,
            [
                'vendor_id'    => $vendor_id,
                'currency'     => sanitize_text_field( wp_unslash( $_POST['sk_currency'] ?? 'EUR' ) ),
                'default_cat'  => Settings::default_category( $vendor_id ),
                'image_cap'    => max( 0, (int) ( $_POST['sk_image_cap'] ?? Importer::DEFAULT_IMAGE_CAP ) ),
                'status'       => sanitize_key( wp_unslash( $_POST['sk_status'] ?? 'publish' ) ),
                'source'       => Dealer::shop_url( $vendor_id ),
                'category_map' => Settings::category_map( $vendor_id ),
            ]
        );

        set_transient( 'sk_import_result_' . $vendor_id, $result, 600 );
        Storage::forget( $path );
        delete_user_meta( $vendor_id, '_sk_import_file' );

        wp_safe_redirect( add_query_arg( 'schritt', 'fertig', $this->url() ) );
        exit;
    }

    /**
     * Daten fuer die Vorlage. Laeuft vor dem Einbinden, die Vorlage rendert nur.
     */
    public function view_data( $query_vars = [] ): array {
        $vendor_id = get_current_user_id();
        $step      = isset( $_GET['schritt'] ) ? sanitize_key( wp_unslash( $_GET['schritt'] ) ) : 'start';
        $message   = get_transient( 'sk_import_msg_' . $vendor_id );
        if ( $message ) {
            delete_transient( 'sk_import_msg_' . $vendor_id );
        }

        $csv     = null;
        $mapping = [];
        $path    = (string) get_user_meta( $vendor_id, '_sk_import_file', true );

        if ( $step === 'zuordnen' && $path !== '' && Storage::belongs_to( $path, $vendor_id ) ) {
            $csv = Csv::read( $path, 5 );
            if ( ! is_wp_error( $csv ) ) {
                $mapping = Csv::guess_mapping( $csv['headers'] );
            } else {
                $message = $csv->get_error_message();
                $csv     = null;
            }
        }

        $items      = [];
        $csv_cats   = [];
        $quota      = null;
        $item_count = 0;

        if ( $csv ) {
            // Fuer Vorschau und Kontingentpruefung die ganze Datei lesen, nicht
            // nur die fuenf Zeilen der Anzeige.
            $full = Csv::read( $path );
            if ( ! is_wp_error( $full ) ) {
                $items      = Catalog::build( $full['headers'], $full['rows'], $mapping );
                $item_count = count( $items );
                $csv_cats   = Catalog::categories( $items );
                $quota      = Quota::check( $vendor_id, $item_count );
            }
        }

        $result = $step === 'fertig' ? get_transient( 'sk_import_result_' . $vendor_id ) : null;
        if ( $result ) {
            delete_transient( 'sk_import_result_' . $vendor_id );
        }

        $quota_block = $step === 'kontingent' ? get_transient( 'sk_import_quota_' . $vendor_id ) : null;
        if ( $quota_block ) {
            delete_transient( 'sk_import_quota_' . $vendor_id );
        }

        // Zusammenfassung dessen, was der Import tun wird — das ist die
        // Information, auf der jemand "ja, mach" entscheidet. Die
        // Spaltenzuordnung interessiert nur, wenn sie falsch geraten wurde.
        $summary = [];
        if ( $items ) {
            $with_variants = 0;
            $drafts        = 0;
            $with_images   = 0;
            $without_price = 0;

            foreach ( $items as $item ) {
                if ( ! empty( $item['variants'] ) ) {
                    $with_variants++;
                }
                if ( ! empty( $item['draft'] ) ) {
                    $drafts++;
                }
                if ( trim( (string) ( $item['images'] ?? '' ) ) !== '' ) {
                    $with_images++;
                }
                if ( Importer::parse_price( (string) ( $item['price'] ?? '' ) ) === null ) {
                    $without_price++;
                }
            }

            $summary = [
                'rows'          => (int) ( $csv['count'] ?? 0 ),
                'items'         => $item_count,
                'variants'      => $with_variants,
                'drafts'        => $drafts,
                'images'        => $with_images,
                'without_price' => $without_price,
                'categories'    => count( $csv_cats ),
                'unmapped'      => count( array_filter( $mapping, static fn( $i ) => $i < 0 ) ),
            ];
        }

        $currency_guess   = Settings::currency( $vendor_id );
        $subscription_url = function_exists( 'sk_get_navigation_url' ) ? sk_get_navigation_url( 'subscription' ) : home_url( '/dashboard/subscription/' );

        $packs        = $quota_block ? Quota::packs_for( (int) $quota_block['needed'] ) : [];
        $stay_online  = Quota::listings_stay_online();
        $saved_map    = Settings::category_map( $vendor_id );
        $default_cat  = Settings::default_category( $vendor_id );

        $categories = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false ] );
        $rate       = Rate::btc_rate( 'EUR' );
        $url        = $this->url();

        return compact(
            'step',
            'url',
            'message',
            'csv',
            'mapping',
            'items',
            'item_count',
            'csv_cats',
            'quota',
            'quota_block',
            'packs',
            'stay_online',
            'saved_map',
            'default_cat',
            'categories',
            'rate',
            'result',
            'summary',
            'subscription_url',
            'currency_guess'
        );
    }
}
