<?php

namespace SK\Modules\Sponsors;

defined( 'ABSPATH' ) || exit;

/**
 * Post-Type sk_sponsor — ein Sponsor je Eintrag.
 *
 * Bewusst nicht öffentlich: Ein Sponsor hat keine eigene Seite und kein
 * Archiv, er ist eine Kachel mit Logo, Text und Ziel-URL. Deshalb public =
 * false und publicly_queryable = false, aber show_ui = true, damit der
 * normale WordPress-Editor für Logo und Beschreibung nutzbar bleibt.
 */
class PostType {

    const POST_TYPE = 'sk_sponsor';

    const META_URL      = '_sk_sponsor_url';
    const META_TIER     = '_sk_sponsor_tier';
    const META_STARTS   = '_sk_sponsor_starts';
    const META_EXPIRES  = '_sk_sponsor_expires';
    const META_LEGACY   = '_sk_sponsor_legacy_post_id';

    /** Kontaktadresse für Guthaben-Erinnerungen. */
    const META_EMAIL    = '_sk_sponsor_email';

    /** Geheimer Zugang zur Selbstbedienungsseite (kein Benutzerkonto nötig). */
    const META_TOKEN    = '_sk_sponsor_token';

    /** Monatsrate in Sats — bestimmt den Rang. */
    const META_MONTHLY  = '_sk_sponsor_monthly_sats';

    /** Vorkasse in Sats — bestimmt die Laufzeit. */
    const META_BALANCE  = '_sk_sponsor_balance_sats';

    /**
     * Reihenfolge unter gleicher Monatsrate.
     *
     * Übernimmt die alte Rangzahl aus wp-post-rank, damit die Startseite
     * unverändert aussieht, solange niemand zahlt (dann sind alle Raten 0).
     */
    const META_SORT_HINT = '_sk_sponsor_sort_hint';

    const TIER_TOP      = 'top';
    const TIER_STANDARD = 'standard';

    public function __construct() {
        add_action( 'init', [ $this, 'register' ] );
        add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );
        add_action( 'save_post_' . self::POST_TYPE, [ $this, 'save' ], 10, 2 );

        add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', [ $this, 'columns' ] );
        add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', [ $this, 'column_content' ], 10, 2 );
    }

    public function register(): void {
        register_post_type(
            self::POST_TYPE,
            [
                'labels'             => [
                    'name'               => __( 'Sponsoren', 'sk-core' ),
                    'singular_name'      => __( 'Sponsor', 'sk-core' ),
                    'add_new_item'       => __( 'Neuen Sponsor anlegen', 'sk-core' ),
                    'edit_item'          => __( 'Sponsor bearbeiten', 'sk-core' ),
                    'search_items'       => __( 'Sponsoren durchsuchen', 'sk-core' ),
                    'not_found'          => __( 'Keine Sponsoren gefunden', 'sk-core' ),
                    'not_found_in_trash' => __( 'Keine Sponsoren im Papierkorb', 'sk-core' ),
                    'all_items'          => __( 'Alle Sponsoren', 'sk-core' ),
                ],
                'public'             => false,
                'publicly_queryable' => false,
                'has_archive'        => false,
                'rewrite'            => false,
                'show_ui'            => true,
                // Der Eintrag haengt an der SK-Seite "Sponsoren", nicht im
                // Hauptmenue: PhpDashboard baut das SK-Untermenue selbst auf
                // und wuerde einen CPT-Eintrag ohnehin wieder entfernen.
                'show_in_menu'       => false,
                'show_in_rest'       => false,
                'supports'           => [ 'title', 'editor', 'thumbnail' ],
                'capability_type'    => 'post',
                'map_meta_cap'       => true,
            ]
        );
    }

    public function add_meta_box(): void {
        add_meta_box(
            'sk-sponsor-details',
            __( 'Sponsor-Details', 'sk-core' ),
            [ $this, 'render_meta_box' ],
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    public function render_meta_box( $post ): void {
        $url     = (string) get_post_meta( $post->ID, self::META_URL, true );
        $tier    = get_post_meta( $post->ID, self::META_TIER, true ) ?: self::TIER_STANDARD;
        $email   = (string) get_post_meta( $post->ID, self::META_EMAIL, true );
        $manual  = Backlink::is_manual( (int) $post->ID );
        $monthly = (int) get_post_meta( $post->ID, self::META_MONTHLY, true );
        $balance = (int) get_post_meta( $post->ID, self::META_BALANCE, true );
        $sort    = (string) get_post_meta( $post->ID, self::META_SORT_HINT, true );
        $starts  = (string) get_post_meta( $post->ID, self::META_STARTS, true );
        $expires = (string) get_post_meta( $post->ID, self::META_EXPIRES, true );
        // Beim Anlegen existiert noch kein Slug; dann den Platzhalter zeigen,
        // statt in der Vorlage erneut auf den Post zuzugreifen.
        $slug    = $post->post_name !== '' ? $post->post_name : 'slug';

        wp_nonce_field( 'sk_sponsor_save', 'sk_sponsor_nonce' );

        include SK_SPONSORS_PATH . '/templates/meta-box.php';
    }

    public function save( $post_id, $post ): void {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! isset( $_POST['sk_sponsor_nonce'] ) || ! wp_verify_nonce( $_POST['sk_sponsor_nonce'], 'sk_sponsor_save' ) ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $url = isset( $_POST['sk_sponsor_url'] ) ? esc_url_raw( wp_unslash( $_POST['sk_sponsor_url'] ) ) : '';
        update_post_meta( $post_id, self::META_URL, $url );

        $email = isset( $_POST['sk_sponsor_email'] ) ? sanitize_email( wp_unslash( $_POST['sk_sponsor_email'] ) ) : '';
        update_post_meta( $post_id, self::META_EMAIL, $email );

        update_post_meta( $post_id, Backlink::META_MANUAL, isset( $_POST['sk_sponsor_backlink_manual'] ) ? 1 : 0 );

        // Sorgt dafür, dass jeder Sponsor einen Zugang hat, auch die importierten.
        self::token( $post_id );

        $tier = isset( $_POST['sk_sponsor_tier'] ) && $_POST['sk_sponsor_tier'] === self::TIER_TOP
            ? self::TIER_TOP
            : self::TIER_STANDARD;
        update_post_meta( $post_id, self::META_TIER, $tier );

        update_post_meta( $post_id, self::META_MONTHLY, isset( $_POST['sk_sponsor_monthly'] ) ? absint( $_POST['sk_sponsor_monthly'] ) : 0 );
        update_post_meta( $post_id, self::META_BALANCE, isset( $_POST['sk_sponsor_balance'] ) ? absint( $_POST['sk_sponsor_balance'] ) : 0 );
        update_post_meta( $post_id, self::META_SORT_HINT, isset( $_POST['sk_sponsor_sort_hint'] ) ? absint( $_POST['sk_sponsor_sort_hint'] ) : 0 );

        foreach ( [ self::META_STARTS => 'sk_sponsor_starts', self::META_EXPIRES => 'sk_sponsor_expires' ] as $meta => $field ) {
            $raw = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
            // Nur echte Datumsangaben speichern, sonst leert ein Tippfehler
            // die Laufzeit still und der Sponsor verschwindet von der Seite.
            update_post_meta( $post_id, $meta, preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ? $raw : '' );
        }
    }

    public function columns( $columns ): array {
        $new = [];
        foreach ( $columns as $key => $label ) {
            $new[ $key ] = $label;
            if ( $key === 'title' ) {
                $new['sk_tier']    = __( 'Stufe', 'sk-core' );
                $new['sk_monthly'] = __( 'Monatsrate', 'sk-core' );
                $new['sk_balance'] = __( 'Guthaben', 'sk-core' );
                $new['sk_url']     = __( 'Ziel', 'sk-core' );
                $new['sk_clicks']  = __( 'Klicks (30 Tage)', 'sk-core' );
            }
        }
        return $new;
    }

    public function column_content( $column, $post_id ): void {
        switch ( $column ) {
            case 'sk_tier':
                echo get_post_meta( $post_id, self::META_TIER, true ) === self::TIER_TOP
                    ? esc_html( _x( 'Top', 'Sponsorenstufe', 'sk-core' ) )
                    : esc_html( _x( 'Standard', 'Sponsorenstufe', 'sk-core' ) );
                break;

            case 'sk_monthly':
                $monthly = (int) get_post_meta( $post_id, self::META_MONTHLY, true );
                echo $monthly > 0
                    ? esc_html( number_format_i18n( $monthly ) . ' sats' )
                    : '<span style="color:#646970;">' . esc_html__( 'gratis', 'sk-core' ) . '</span>';
                break;

            case 'sk_balance':
                echo esc_html( number_format_i18n( (int) get_post_meta( $post_id, self::META_BALANCE, true ) ) . ' sats' );
                break;

            case 'sk_url':
                $url = (string) get_post_meta( $post_id, self::META_URL, true );
                echo $url ? '<a href="' . esc_url( $url ) . '" rel="noopener nofollow" target="_blank">' . esc_html( wp_parse_url( $url, PHP_URL_HOST ) ) . '</a>' : '&mdash;';
                break;

            case 'sk_clicks':
                $stats = Stats::for_sponsor( (int) $post_id, gmdate( 'Y-m-d', time() - 30 * DAY_IN_SECONDS ), gmdate( 'Y-m-d' ) );
                printf( '%d (%d)', (int) $stats['clicks'], (int) $stats['unique'] );
                break;
        }
    }

    /**
     * Aktive Sponsoren einer Stufe, beste Gewichtung zuerst.
     *
     * Laufzeit, Stufe und Sortierung werden in PHP ausgewertet statt in einer
     * meta_query. Bei rund zwei Dutzend Sponsoren kostet das nichts, und es
     * vermeidet die Falle, dass ein Eintrag ohne gesetztes Gewicht durch den
     * INNER JOIN von "meta_key" still aus der Liste fliegt.
     *
     * @return \WP_Post[]
     */
    public static function get_active( string $tier = '', int $limit = -1 ): array {
        $query = new \WP_Query(
            [
                'post_type'      => self::POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'no_found_rows'  => true,
                'orderby'        => 'title',
                'order'          => 'ASC',
            ]
        );

        $today   = wp_date( 'Y-m-d' );
        $matches = [];

        foreach ( $query->posts as $post ) {
            if ( $tier !== '' && self::get_tier( $post->ID ) !== $tier ) {
                continue;
            }
            if ( ! self::is_running( $post->ID, $today ) ) {
                continue;
            }
            if ( '' === (string) get_post_meta( $post->ID, self::META_URL, true ) ) {
                continue;
            }
            $matches[] = $post;
        }

        usort( $matches, [ self::class, 'compare_rank' ] );

        return $limit > 0 ? array_slice( $matches, 0, $limit ) : $matches;
    }

    /**
     * Geheimer Token für die Selbstbedienungsseite, bei Bedarf erzeugt.
     *
     * Sponsoren sind Firmen ohne Benutzerkonto. Statt sie zu registrieren,
     * bekommen sie eine unrat­bare Adresse — dieselbe Logik wie bei einem
     * WooCommerce-Zahllink.
     */
    public static function token( int $post_id ): string {
        $token = (string) get_post_meta( $post_id, self::META_TOKEN, true );

        if ( strlen( $token ) < 24 ) {
            $token = wp_generate_password( 32, false, false );
            update_post_meta( $post_id, self::META_TOKEN, $token );
        }

        return $token;
    }

    public static function by_token( string $token ): ?\WP_Post {
        if ( strlen( $token ) < 24 ) {
            return null;
        }

        $found = get_posts(
            [
                'post_type'      => self::POST_TYPE,
                'post_status'    => [ 'publish', 'draft' ],
                'posts_per_page' => 1,
                'meta_key'       => self::META_TOKEN,
                'meta_value'     => $token,
                'no_found_rows'  => true,
            ]
        );

        return $found ? $found[0] : null;
    }

    public static function get_tier( int $post_id ): string {
        return get_post_meta( $post_id, self::META_TIER, true ) === self::TIER_TOP
            ? self::TIER_TOP
            : self::TIER_STANDARD;
    }

    /**
     * Rangfolge: Monatsrate zuerst, dann die alte Rangzahl, dann der Titel.
     *
     * Die Rate entscheidet — nicht das Guthaben. Sonst stuende der oben, der
     * am meisten eingezahlt und am wenigsten pro Monat abgemacht hat, und die
     * guenstigste Strategie waere, die Rate moeglichst klein zu halten.
     */
    public static function compare_rank( \WP_Post $a, \WP_Post $b ): int {
        $ma = (int) get_post_meta( $a->ID, self::META_MONTHLY, true );
        $mb = (int) get_post_meta( $b->ID, self::META_MONTHLY, true );
        if ( $ma !== $mb ) {
            return $mb <=> $ma;
        }

        $sa = (int) get_post_meta( $a->ID, self::META_SORT_HINT, true );
        $sb = (int) get_post_meta( $b->ID, self::META_SORT_HINT, true );
        if ( $sa !== $sb ) {
            return $sb <=> $sa;
        }

        return strcasecmp( $a->post_title, $b->post_title );
    }

    /**
     * Reicht das Guthaben noch fuer diesen Monat?
     *
     * Eine Rate von 0 ist ein Gratisplatz und laeuft nie aus — sonst wuerden
     * beim Einschalten der Abrechnung schlagartig alle Bestandssponsoren von
     * der Seite fliegen, weil deren Guthaben 0 ist.
     */
    public static function has_credit( int $post_id ): bool {
        $monthly = (int) get_post_meta( $post_id, self::META_MONTHLY, true );
        if ( $monthly <= 0 ) {
            return true;
        }

        return (int) get_post_meta( $post_id, self::META_BALANCE, true ) >= $monthly;
    }

    /**
     * Verbleibende volle Monate, oder null bei einem Gratisplatz.
     */
    public static function months_left( int $post_id ): ?int {
        $monthly = (int) get_post_meta( $post_id, self::META_MONTHLY, true );
        if ( $monthly <= 0 ) {
            return null;
        }

        return (int) floor( (int) get_post_meta( $post_id, self::META_BALANCE, true ) / $monthly );
    }

    /**
     * Leere Datumsfelder bedeuten "unbefristet" — nur gesetzte Grenzen greifen.
     */
    public static function is_running( int $post_id, string $today = '' ): bool {
        $today   = $today ?: wp_date( 'Y-m-d' );
        $starts  = (string) get_post_meta( $post_id, self::META_STARTS, true );
        $expires = (string) get_post_meta( $post_id, self::META_EXPIRES, true );

        if ( $starts !== '' && $starts > $today ) {
            return false;
        }
        if ( $expires !== '' && $expires < $today ) {
            return false;
        }

        // Greift erst, wenn die Abrechnung eingeschaltet ist.
        if ( Billing::is_enabled() && ! self::has_credit( $post_id ) ) {
            return false;
        }

        return true;
    }
}
