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
        add_action( 'wp_ajax_sk_shop_import_batch', [ $this, 'ajax_batch' ] );
    }

    private function url(): string {
        return function_exists( 'sk_get_navigation_url' )
            ? sk_get_navigation_url( 'shop-import' )
            : home_url( '/dashboard/shop-import/' );
    }

    /**
     * Upload und Import entgegennehmen.
     */
    /**
     * Einen Stapel abarbeiten. Der Browser ruft das so lange, bis fertig.
     */
    public function ajax_batch(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        $vendor_id = get_current_user_id();
        if ( ! $vendor_id || ! Dealer::may_import( $vendor_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Keine Berechtigung.', 'sk-core' ) ] );
        }

        $step = Job::step( $vendor_id );

        if ( is_wp_error( $step ) ) {
            wp_send_json_error( [ 'message' => $step->get_error_message() ] );
        }

        $step['weiter'] = add_query_arg( 'schritt', 'fertig', $this->url() );

        wp_send_json_success( $step );
    }

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

        if ( $step === 'holen' ) {
            /*
             * Die Adresse gibt der Haendler selbst ein — er weiss, wo sein
             * Shop liegt, der Betreiber muss sie nicht vorher eintragen.
             *
             * Damit ist sie Nutzereingabe, anders als beim Rest des Moduls.
             * Der Abruf laeuft deshalb ueber wp_safe_remote_get(), das interne
             * Adressbereiche abweist, und die Seite steht ohnehin nur
             * freigeschalteten Haendlern offen (Dealer::CAP).
             */
            $shop = isset( $_POST['sk_shop_url'] )
                ? esc_url_raw( trim( wp_unslash( $_POST['sk_shop_url'] ) ) )
                : '';

            if ( $shop === '' || ! in_array( wp_parse_url( $shop, PHP_URL_SCHEME ), [ 'http', 'https' ], true ) ) {
                set_transient( 'sk_import_msg_' . $vendor_id, __( 'Bitte gib die Adresse deines Shops an, zum Beispiel https://mein-shop.myshopify.com.', 'sk-core' ), 120 );
                wp_safe_redirect( $this->url() );
                exit;
            }

            $products = Shopify::fetch( $shop );

            if ( is_wp_error( $products ) ) {
                set_transient( 'sk_import_msg_' . $vendor_id, $products->get_error_message(), 120 );
                wp_safe_redirect( $this->url() );
                exit;
            }

            $path = Storage::put_catalog( (string) wp_json_encode( [ 'products' => $products ] ), $vendor_id );

            if ( is_wp_error( $path ) ) {
                set_transient( 'sk_import_msg_' . $vendor_id, $path->get_error_message(), 120 );
                wp_safe_redirect( $this->url() );
                exit;
            }

            // Die vorige Quelle liegt sonst als Leiche im Ordner.
            $vorige = (string) get_user_meta( $vendor_id, '_sk_import_file', true );
            if ( $vorige !== '' && Storage::belongs_to( $vorige, $vendor_id ) ) {
                Storage::forget( $vorige );
            }

            // Beim naechsten Mal steht die Adresse schon im Feld, und der
            // Auftrag traegt sie als Herkunft der Inserate.
            update_user_meta( $vendor_id, Dealer::META_SHOP_URL, $shop );

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

        $mapping = [];

        // Ein geholter Katalog braucht keine Spaltenzuordnung.
        if ( ! Source::is_json( $path ) ) {
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
        }

        // Kategorie-Zuordnung sichern, damit sie beim naechsten Import steht.
        $map = [];
        foreach ( (array) ( $_POST['cat_map'] ?? [] ) as $name => $term ) {
            $map[ sanitize_text_field( wp_unslash( $name ) ) ] = (int) $term;
        }
        Settings::save_category_map( $vendor_id, $map );
        Settings::save_default_category( $vendor_id, (int) ( $_POST['sk_default_cat'] ?? 0 ) );
        Settings::save_currency( $vendor_id, sanitize_text_field( wp_unslash( $_POST['sk_currency'] ?? '' ) ) );

        $items = Source::items( $path, $mapping );

        if ( is_wp_error( $items ) ) {
            set_transient( 'sk_import_msg_' . $vendor_id, $items->get_error_message(), 120 );
            wp_safe_redirect( $this->url() );
            exit;
        }

        // Nur die angehakten uebernehmen. Ohne Auswahl im Formular gilt alles.
        // Die Paketsperre fuer Ausfuehrungen zieht der Importer selbst.
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

        // Nicht sofort importieren, sondern einen Auftrag anlegen: die Bilder
        // machen selbst ein halbes Dutzend Artikel langsamer als PHP erlaubt.
        Job::create(
            $vendor_id,
            $path,
            $mapping,
            $chosen,
            [
                'vendor_id'    => $vendor_id,
                'currency'     => sanitize_text_field( wp_unslash( $_POST['sk_currency'] ?? 'EUR' ) ),
                'default_cat'  => Settings::default_category( $vendor_id ),
                'image_cap'    => max( 0, (int) ( $_POST['sk_image_cap'] ?? Importer::DEFAULT_IMAGE_CAP ) ),
                'status'       => self::import_status(),
                'source'       => Dealer::shop_url( $vendor_id ),
                'category_map' => Settings::category_map( $vendor_id ),
            ],
            count( $items )
        );

        wp_safe_redirect( add_query_arg( 'schritt', 'laeuft', $this->url() ) );
        exit;
    }

    /**
     * Status der importierten Inserate.
     *
     * Nur die beiden Werte, die das Formular anbietet — und muessen neue
     * Inserate auf dieser Seite geprueft werden, gilt das auch hier. Sonst
     * waere der Import der Weg an der Pruefung vorbei.
     */
    private static function import_status(): string {
        $wanted = sanitize_key( wp_unslash( $_POST['sk_status'] ?? 'publish' ) ); // phpcs:ignore WordPress.Security.NonceVerification

        if ( ! in_array( $wanted, [ 'publish', 'draft' ], true ) ) {
            $wanted = 'draft';
        }

        if ( $wanted === 'publish' && function_exists( 'sk_get_default_product_status' ) ) {
            $default = sk_get_default_product_status( get_current_user_id() );
            if ( $default !== 'publish' ) {
                return $default;
            }
        }

        return $wanted;
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

        $items      = [];
        $csv_cats   = [];
        $quota      = null;
        $item_count = 0;
        $rows       = 0;
        // Ein geholter Katalog bringt seine Struktur mit; die Zuordnungsmaske
        // gehoert dann nicht auf die Seite.
        $is_json    = false;

        if ( $step === 'zuordnen' && $path !== '' && Storage::belongs_to( $path, $vendor_id ) ) {
            $is_json = Source::is_json( $path );

            if ( ! $is_json ) {
                $csv = Csv::read( $path, 5 );
                if ( ! is_wp_error( $csv ) ) {
                    $mapping = Csv::guess_mapping( $csv['headers'] );
                } else {
                    $message = $csv->get_error_message();
                    $csv     = null;
                }
            }

            if ( $is_json || $csv ) {
                $built = Source::items( $path, $mapping );

                if ( is_wp_error( $built ) ) {
                    $message = $built->get_error_message();
                } else {
                    $items      = $built;
                    $item_count = count( $items );
                    $csv_cats   = Catalog::categories( $items );
                    $quota      = Quota::check( $vendor_id, $item_count );
                    $rows       = Source::count( $path );
                }
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
                'rows'          => $rows,
                'items'         => $item_count,
                'variants'      => $with_variants,
                'drafts'        => $drafts,
                'images'        => $with_images,
                'without_price' => $without_price,
                'categories'    => count( $csv_cats ),
                'unmapped'      => count( array_filter( $mapping, static fn( $i ) => $i < 0 ) ),
            ];
        }

        // Artikel mit Ausfuehrungen sind ohne passendes Paket nicht
        // importierbar; sie werden markiert statt stillschweigend zu fehlen.
        $variants_allowed = Variants::is_allowed( $vendor_id );
        $variants_pack    = $variants_allowed ? null : Variants::cheapest_allowed_pack();
        $blocked          = 0;

        if ( ! $variants_allowed ) {
            foreach ( $items as $item ) {
                if ( ! empty( $item['variants'] ) ) {
                    $blocked++;
                }
            }
        }

        $currency_guess   = Settings::currency( $vendor_id );
        $subscription_url = function_exists( 'sk_get_navigation_url' ) ? sk_get_navigation_url( 'subscription' ) : home_url( '/dashboard/subscription/' );

        $packs        = $quota_block ? Quota::packs_for( (int) $quota_block['needed'] ) : [];
        $stay_online  = Quota::listings_stay_online();
        $saved_map    = Settings::category_map( $vendor_id );
        $default_cat  = Settings::default_category( $vendor_id );

        // Ein Auftrag ueberlebt das Schliessen des Fensters. Steht einer offen,
        // zeigt die Seite ihn an, statt so zu tun, als sei nichts passiert.
        $job = Job::get( $vendor_id );
        if ( $job && $step === 'start' ) {
            $step = 'laeuft';
        }

        $categories = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false ] );
        $rate       = Rate::btc_rate( 'EUR' );
        $url        = $this->url();
        // Vorbelegung des Eingabefelds: was der Haendler zuletzt geholt hat,
        // sonst die vom Betreiber hinterlegte Adresse.
        $shop_url   = Dealer::shop_url( $vendor_id );

        return compact(
            'step',
            'url',
            'message',
            'csv',
            'is_json',
            'shop_url',
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
            'job',
            'currency_guess',
            'variants_allowed',
            'variants_pack',
            'blocked'
        );
    }
}
