<?php

namespace SK\Modules\ShopImport;

defined( 'ABSPATH' ) || exit;

/**
 * Katalog eines Shopify-Shops über /products.json holen.
 *
 * Der Weg über die CSV bleibt der allgemeine — er ist der einzige für
 * WooCommerce und der Rückfall, wenn ein Shop diesen Endpunkt abgeschaltet
 * hat. Für Shopify lohnt der zweite Weg trotzdem: dort liegen Ausführungen
 * und Bilder verschachtelt im Produkt, während die CSV sie flachklopft und
 * über den Handle wieder zusammengesucht werden müssten. Eine Zuordnung von
 * Spalten entfällt damit ebenso wie das Raten.
 *
 * Der Endpunkt ist öffentlich, es braucht also keine Zugangsdaten. Er zeigt
 * nur, was im Online-Store veröffentlicht ist — Entwürfe und archivierte
 * Artikel fehlen, was für einen Katalogimport eher richtig als falsch ist.
 *
 * Die Shopadresse kommt aus dem Händlerprofil, das der Betreiber pflegt
 * (Dealer::shop_url). Sie ist damit keine Nutzereingabe, und der Abruf läuft
 * zusätzlich über wp_safe_remote_get(), das interne Adressbereiche abweist.
 */
final class Shopify {

    /** Höchstzahl je Abruf; mehr gibt Shopify nicht heraus. */
    const PER_PAGE = 250;

    /** Notbremse gegen einen endlos blätternden Katalog. */
    const MAX_PAGES = 20;

    /** Sekunden je Abruf. */
    const TIMEOUT = 20;

    /**
     * Adresse des Katalogs zu einer Shopadresse.
     *
     * @return string Leer, wenn sich daraus keine brauchbare Adresse ergibt.
     */
    public static function catalog_url( string $shop_url, int $page = 1 ): string {
        $shop_url = trim( $shop_url );

        if ( $shop_url === '' ) {
            return '';
        }

        // Ohne Schema wird die Adresse von wp_parse_url als Pfad gelesen.
        if ( ! preg_match( '#^https?://#i', $shop_url ) ) {
            $shop_url = 'https://' . ltrim( $shop_url, '/' );
        }

        $host = (string) wp_parse_url( $shop_url, PHP_URL_HOST );

        if ( $host === '' ) {
            return '';
        }

        return add_query_arg(
            [ 'limit' => self::PER_PAGE, 'page' => max( 1, $page ) ],
            'https://' . $host . '/products.json'
        );
    }

    /**
     * Den ganzen Katalog holen, Seite für Seite.
     *
     * @return array<int,array>|\WP_Error Rohe Produkte, wie Shopify sie liefert.
     */
    public static function fetch( string $shop_url ) {
        if ( self::catalog_url( $shop_url ) === '' ) {
            return new \WP_Error( 'sk_shopify_url', __( 'Für diesen Händler ist keine gültige Shop-Adresse hinterlegt.', 'sk-core' ) );
        }

        $products = [];

        for ( $page = 1; $page <= self::MAX_PAGES; $page++ ) {
            $response = wp_safe_remote_get(
                self::catalog_url( $shop_url, $page ),
                [
                    'timeout'    => self::TIMEOUT,
                    'user-agent' => 'SatoshisKleinanzeigen Shop-Import',
                    'headers'    => [ 'Accept' => 'application/json' ],
                ]
            );

            if ( is_wp_error( $response ) ) {
                return $response;
            }

            $code = (int) wp_remote_retrieve_response_code( $response );

            if ( $code !== 200 ) {
                return new \WP_Error(
                    'sk_shopify_http',
                    sprintf(
                        /* translators: %d: HTTP-Statuscode. */
                        __( 'Der Shop antwortet mit Status %d. Entweder ist es kein Shopify-Shop, oder der Katalog ist dort nicht öffentlich.', 'sk-core' ),
                        $code
                    )
                );
            }

            $data = json_decode( (string) wp_remote_retrieve_body( $response ), true );

            if ( ! is_array( $data ) || ! isset( $data['products'] ) || ! is_array( $data['products'] ) ) {
                return new \WP_Error( 'sk_shopify_body', __( 'Die Antwort des Shops ist kein Shopify-Katalog.', 'sk-core' ) );
            }

            $batch = $data['products'];

            if ( empty( $batch ) ) {
                break;
            }

            $products = array_merge( $products, $batch );

            // Eine nicht volle Seite ist die letzte.
            if ( count( $batch ) < self::PER_PAGE ) {
                break;
            }
        }

        if ( empty( $products ) ) {
            return new \WP_Error( 'sk_shopify_empty', __( 'Der Shop gibt keine Produkte heraus.', 'sk-core' ) );
        }

        return $products;
    }

    /**
     * Rohe Shopify-Produkte in die Form bringen, die der Importer erwartet.
     *
     * Dieselben Schlüssel wie Catalog::build() — Importer, Job, Quota und
     * Variants merken dadurch nicht, aus welcher Quelle ein Artikel stammt.
     *
     * @param array<int,array> $products
     * @return array<int,array>
     */
    public static function build( array $products ): array {
        $items = [];

        foreach ( $products as $product ) {
            if ( ! is_array( $product ) ) {
                continue;
            }

            $name = trim( (string) ( $product['title'] ?? '' ) );

            if ( $name === '' ) {
                continue;
            }

            $variants = self::variants( $product );
            $first    = is_array( $product['variants'] ?? null ) ? reset( $product['variants'] ) : [];
            $sku      = is_array( $first ) ? trim( (string) ( $first['sku'] ?? '' ) ) : '';

            $item = [
                'id'          => (string) ( $product['id'] ?? '' ),
                'sku'         => $sku,
                'name'        => $name,
                'description' => (string) ( $product['body_html'] ?? '' ),
                'short'       => '',
                'price'       => '',
                'categories'  => self::category( $product ),
                'images'      => self::images( $product ),
                'parent'      => '',
                // Der Endpunkt gibt ohnehin nur Veröffentlichtes heraus; die
                // Prüfung bleibt, falls ein Shop das Feld doch einmal leer lässt.
                'draft'       => empty( $product['published_at'] ),
                'variants'    => $variants,
            ];

            if ( empty( $variants ) ) {
                // Ein Produkt ohne echte Auswahl trägt den Preis selbst.
                $item['price'] = is_array( $first ) ? trim( (string) ( $first['price'] ?? '' ) ) : '';
            } else {
                $item['price'] = self::lowest_price( $variants );
                $item['from']  = true;
            }

            $item['key'] = $item['sku'] !== '' ? $item['sku'] : md5( $item['name'] );

            $items[] = $item;
        }

        return $items;
    }

    /**
     * Ausführungen eines Produkts.
     *
     * Shopify legt auch für ein Produkt ohne Auswahl eine Variante an, die
     * dann "Default Title" heisst. Die ist keine Ausführung, sondern das
     * Produkt selbst — würde man sie übernehmen, trüge jedes einzelne
     * Inserat eine sinnlose Ausführung dieses Namens.
     *
     * @return array<int,array{name:string,price:string,sku:string}>
     */
    private static function variants( array $product ): array {
        $raw = is_array( $product['variants'] ?? null ) ? $product['variants'] : [];

        if ( count( $raw ) < 2 ) {
            return [];
        }

        $variants = [];

        foreach ( $raw as $variant ) {
            if ( ! is_array( $variant ) ) {
                continue;
            }

            $label = trim( (string) ( $variant['title'] ?? '' ) );

            if ( $label === '' || strcasecmp( $label, 'Default Title' ) === 0 ) {
                $label = trim( (string) ( $variant['sku'] ?? '' ) );
            }

            if ( $label === '' ) {
                continue;
            }

            $variants[] = [
                'name'  => $label,
                'price' => trim( (string) ( $variant['price'] ?? '' ) ),
                'sku'   => trim( (string) ( $variant['sku'] ?? '' ) ),
            ];
        }

        return $variants;
    }

    /**
     * Bilder als kommagetrennte Liste — die Form, die apply_images() liest.
     */
    private static function images( array $product ): string {
        $urls = [];

        foreach ( (array) ( $product['images'] ?? [] ) as $image ) {
            $src = is_array( $image ) ? trim( (string) ( $image['src'] ?? '' ) ) : '';

            if ( $src !== '' ) {
                $urls[] = $src;
            }
        }

        return implode( ',', $urls );
    }

    /**
     * Was bei Shopify einer Kategorie am nächsten kommt.
     *
     * product_type ist das Feld, mit dem ein Shop sein Sortiment gliedert
     * ("ASIC", "Upgrade Kit"). Fehlt es, dient das erste Schlagwort als
     * Anhaltspunkt — die Zuordnung auf eine SK-Kategorie trifft ohnehin der
     * Händler im Formular.
     */
    private static function category( array $product ): string {
        $type = trim( (string) ( $product['product_type'] ?? '' ) );

        if ( $type !== '' ) {
            return $type;
        }

        $tags = $product['tags'] ?? [];

        if ( is_string( $tags ) ) {
            $tags = array_map( 'trim', explode( ',', $tags ) );
        }

        return is_array( $tags ) && ! empty( $tags ) ? trim( (string) reset( $tags ) ) : '';
    }

    private static function lowest_price( array $variants ): string {
        $prices = [];

        foreach ( $variants as $variant ) {
            $value = Importer::parse_price( (string) $variant['price'] );

            if ( $value !== null && $value > 0 ) {
                $prices[] = $value;
            }
        }

        return empty( $prices ) ? '' : (string) min( $prices );
    }
}
