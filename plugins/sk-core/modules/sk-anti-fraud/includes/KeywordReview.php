<?php

namespace SK\Modules\AntiFraud;

defined( 'ABSPATH' ) || exit;

/**
 * Keyword review — hold listings with risky keywords for manual approval.
 *
 * Ticket resales are a recurring scam vector: the "goods" are intangible,
 * instantly transferable and impossible to verify after payment. Instead of
 * reacting to reports afterwards, listings mentioning tickets go to draft the
 * moment they are published, the vendor is told it is being reviewed, and an
 * admin gets notified.
 *
 * Deliberately independent of the reputation module — it only looks at the
 * listing text, so it works standalone.
 */
class KeywordReview {

    /** Product meta: listing was held for review. */
    const META_FLAGGED = '_sk_kwr_flagged';

    /** Product meta: which keywords matched. */
    const META_MATCHED = '_sk_kwr_matched';

    /** Product meta: reviewed by an admin, don't hold again. */
    const META_APPROVED = '_sk_kwr_approved';

    /** Transient prefix for the vendor facing notice. */
    const NOTICE_TRANSIENT = 'sk_kwr_notice_';

    public function __construct() {
        add_action( 'transition_post_status', [ $this, 'check_on_publish' ], 20, 3 );
        add_action( 'sk_dashboard_content_inside_before', [ $this, 'show_vendor_notice' ] );

        /*
         * Der Hinweis oben erreicht den Anbieter nur, wenn er die
         * Inseratsliste oeffnet — nach dem Veroeffentlichen landet er aber auf
         * der Bearbeiten-Seite, und die feuert diesen Haken nicht. Zusammen mit
         * der Lebensdauer des Transients hiess das: er sieht sein Inserat als
         * Entwurf und erfaehrt nie, warum. Auf Live hat genau das jemand zwei
         * Mal am selben Tag erneut veroeffentlicht.
         *
         * Deshalb hier ein zweiter Hinweis, der nicht an einem Transient
         * haengt, sondern am Zustand des Inserats: er steht so lange da, bis
         * die Pruefung durch ist.
         */
        add_action( 'sk_product_content_inside_area_before', [ $this, 'show_listing_notice' ] );
    }

    /**
     * Default keyword list, used when the setting is empty.
     */
    public static function default_keywords(): array {
        return [ 'ticket', 'tickets', 'eintrittskarte', 'eintrittskarten', 'konzertkarte', 'konzertkarten' ];
    }

    /**
     * Configured keywords, lowercased and deduplicated.
     *
     * @return string[]
     */
    public static function get_keywords(): array {
        $raw = (string) sk_get_option( 'sk_antifraud_keywords', 'sk_antifraud', '' );

        if ( '' === trim( $raw ) ) {
            return self::default_keywords();
        }

        $keywords = array_map(
            static function ( $keyword ) {
                return mb_strtolower( trim( $keyword ) );
            },
            explode( ',', $raw )
        );

        return array_values( array_unique( array_filter( $keywords ) ) );
    }

    // ── Publish check ──────────────────────────────────────────────────────────

    public function check_on_publish( $new_status, $old_status, $post ): void {
        if ( ! $post instanceof \WP_Post || 'product' !== $post->post_type ) {
            return;
        }
        if ( 'publish' !== $new_status || 'publish' === $old_status ) {
            return;
        }
        // Already reviewed — never hold it again.
        if ( get_post_meta( $post->ID, self::META_APPROVED, true ) ) {
            return;
        }

        // An admin publishing the listing counts as the review.
        if ( current_user_can( 'manage_woocommerce' ) ) {
            update_post_meta( $post->ID, self::META_APPROVED, 1 );
            delete_post_meta( $post->ID, self::META_FLAGGED );
            return;
        }

        $matched = self::match_keywords( $post );

        if ( empty( $matched ) ) {
            return;
        }

        $this->hold_for_review( $post, $matched );
    }

    /**
     * Which configured keywords appear in the listing?
     *
     * @return string[]
     */
    public static function match_keywords( \WP_Post $post ): array {
        $haystack = implode( ' ', [
            $post->post_title,
            $post->post_content,
            $post->post_excerpt,
            self::get_term_names( $post->ID, 'product_tag' ),
            self::get_term_names( $post->ID, 'product_cat' ),
        ] );

        $haystack = mb_strtolower( wp_strip_all_tags( $haystack ) );
        $matched  = [];

        foreach ( self::get_keywords() as $keyword ) {
            if ( '' !== $keyword && false !== mb_strpos( $haystack, $keyword ) ) {
                $matched[] = $keyword;
            }
        }

        return $matched;
    }

    private static function get_term_names( int $post_id, string $taxonomy ): string {
        $terms = get_the_terms( $post_id, $taxonomy );

        if ( ! is_array( $terms ) ) {
            return '';
        }

        return implode( ' ', wp_list_pluck( $terms, 'name' ) );
    }

    /**
     * Put the listing back to draft, mark it and notify both sides.
     */
    private function hold_for_review( \WP_Post $post, array $matched ): void {
        // Avoid recursing into our own transition.
        remove_action( 'transition_post_status', [ $this, 'check_on_publish' ], 20 );
        wp_update_post( [ 'ID' => $post->ID, 'post_status' => 'draft' ] );
        add_action( 'transition_post_status', [ $this, 'check_on_publish' ], 20, 3 );

        update_post_meta( $post->ID, self::META_FLAGGED, current_time( 'mysql' ) );
        update_post_meta( $post->ID, self::META_MATCHED, implode( ', ', $matched ) );

        // Persist independently of post and account — both are usually gone by
        // the time anyone looks into a case.
        ReviewLog::record( (int) $post->ID, implode( ', ', $matched ), 'keyword' );

        set_transient(
            self::NOTICE_TRANSIENT . (int) $post->post_author,
            [
                'title'    => $post->post_title,
                'keywords' => implode( ', ', $matched ),
            ],
            5 * MINUTE_IN_SECONDS
        );

        $this->notify_admin( $post, $matched );
    }

    /**
     * Nach der Pruefung freigeben.
     *
     * Die Marke wird vor dem Veroeffentlichen gesetzt, sonst hielte
     * check_on_publish() das Inserat im selben Atemzug wieder zurueck.
     */
    public static function approve( int $product_id ): bool {
        $post = get_post( $product_id );

        if ( ! $post instanceof \WP_Post || 'product' !== $post->post_type ) {
            return false;
        }

        update_post_meta( $product_id, self::META_APPROVED, 1 );
        delete_post_meta( $product_id, self::META_FLAGGED );

        if ( 'publish' !== $post->post_status ) {
            wp_update_post( [ 'ID' => $product_id, 'post_status' => 'publish' ] );
        }

        return true;
    }

    // ── Notifications ──────────────────────────────────────────────────────────

    private function notify_admin( \WP_Post $post, array $matched ): void {
        $to = get_option( 'admin_email' );

        if ( ! $to ) {
            return;
        }

        $vendor    = get_userdata( (int) $post->post_author );
        $site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

        $subject = sprintf(
            /* translators: 1: site name, 2: listing title */
            __( '[%1$s] Inserat zur Prüfung: %2$s', 'sk-core' ),
            $site_name,
            $post->post_title
        );

        $body  = __( 'Ein Inserat wurde automatisch auf Entwurf gesetzt und wartet auf Prüfung.', 'sk-core' ) . "\n\n";
        $body .= sprintf( __( 'Inserat: %s', 'sk-core' ), $post->post_title ) . "\n";
        $body .= sprintf( __( 'Treffer: %s', 'sk-core' ), implode( ', ', $matched ) ) . "\n\n";
        $body .= VendorSummary::text( (int) $post->post_author ) . "\n\n";
        $body .= __( 'Bearbeiten:', 'sk-core' ) . ' ' . admin_url( 'post.php?post=' . $post->ID . '&action=edit' ) . "\n";

        if ( $vendor ) {
            $body .= __( 'Anbieter-Profil:', 'sk-core' ) . ' ' . admin_url( 'user-edit.php?user_id=' . $vendor->ID ) . "\n";
        }

        $body .= "\n" . __( 'Wird das Inserat als Admin veröffentlicht, gilt es als geprüft und wird nicht erneut zurückgehalten.', 'sk-core' ) . "\n";

        wp_mail( $to, $subject, $body );
    }

    /**
     * Hinweis direkt nach dem Veroeffentlichen, auf der Inseratsliste.
     */
    public function show_vendor_notice(): void {
        if ( ! is_user_logged_in() ) {
            return;
        }

        $key    = self::NOTICE_TRANSIENT . get_current_user_id();
        $notice = get_transient( $key );

        if ( ! $notice || ! is_array( $notice ) ) {
            return;
        }

        delete_transient( $key );

        $this->render_notice( (string) $notice['title'] );
    }

    /**
     * Hinweis auf der Bearbeiten-Seite, solange die Pruefung laeuft.
     *
     * Haengt am Zustand des Inserats statt an einem Transient: wer die Seite
     * spaeter wieder oeffnet, sieht denselben Hinweis erneut.
     */
    public function show_listing_notice(): void {
        $post = get_post();

        if ( ! $post instanceof \WP_Post || 'product' !== $post->post_type ) {
            return;
        }

        if ( (int) $post->post_author !== get_current_user_id() ) {
            return;
        }

        if ( ! get_post_meta( $post->ID, self::META_FLAGGED, true ) ) {
            return;
        }

        if ( get_post_meta( $post->ID, self::META_APPROVED, true ) ) {
            return;
        }

        $this->render_notice( (string) $post->post_title );
    }

    /**
     * Die Meldung selbst. Kein eigener Abstand noetig — den bringt .sk-alert mit.
     */
    private function render_notice( string $title ): void {
        ?>
        <div class="sk-alert sk-alert-warning">
            <i class="fas fa-clock"></i>
            <strong><?php esc_html_e( 'Dein Inserat wird geprüft', 'sk-core' ); ?></strong>
            <p>
                <?php
                printf(
                    /* translators: %s: listing title */
                    esc_html__( '„%s“ wurde als Entwurf gespeichert und muss vor der Veröffentlichung von uns freigegeben werden.', 'sk-core' ),
                    esc_html( $title )
                );
                ?>
                <?php esc_html_e( 'Bei Tickets und ähnlichen Angeboten prüfen wir jedes Inserat manuell, weil sie häufig für Betrug genutzt werden. Das dauert in der Regel nicht lange.', 'sk-core' ); ?>
            </p>
            <p>
                <?php esc_html_e( 'Erneutes Veröffentlichen hilft nicht — das Inserat landet dann wieder als Entwurf hier. Warte einfach ab, wir melden uns.', 'sk-core' ); ?>
            </p>
        </div>
        <?php
    }
}
