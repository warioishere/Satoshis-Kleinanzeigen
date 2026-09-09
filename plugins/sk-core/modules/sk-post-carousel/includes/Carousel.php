<?php

namespace SK\Modules\PostCarousel;

defined( 'ABSPATH' ) || exit;

/**
 * Post Image Carousel — [post_image_carousel] shortcode over featured
 * images, plus an optional per-post external link (only applied in the
 * "sponsoren" category) that overrides the permalink everywhere.
 *
 * Absorbed from the standalone `wp-post-image-carousel` plugin; behavior
 * unchanged.
 */
class Carousel {

    const META_KEY = '_wppic_image_link'; // External link per post.
    const CAT_SLUG = 'sponsoren';          // Category in which the link is active.

    public function __construct() {
        add_shortcode( 'post_image_carousel', [ $this, 'shortcode' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_responsive_assets' ], 99 );

        add_action( 'add_meta_boxes', [ $this, 'add_link_metabox' ] );
        add_action( 'save_post', [ $this, 'save_link_metabox' ] );

        add_filter( 'post_link', [ $this, 'maybe_external_permalink' ], 10, 3 );
        add_filter( 'the_permalink', [ $this, 'maybe_external_permalink_tp' ], 10, 2 );
        add_filter( 'post_type_link', [ $this, 'maybe_external_pt_link' ], 10, 4 );
    }

    /* ====================== Assets ====================== */

    public function register_assets() {
        wp_register_style( 'wppic-style', SK_POST_CAROUSEL_URL . '/assets/css/carousel.css', [], SK_POST_CAROUSEL_VERSION );
        wp_register_script( 'wppic-script', SK_POST_CAROUSEL_URL . '/assets/js/carousel.js', [], SK_POST_CAROUSEL_VERSION, true );
    }

    /** Unconditional (not gated on the shortcode being present), like the original plugin. */
    public function enqueue_responsive_assets() {
        wp_enqueue_style( 'wppis-responsive-carousel', SK_POST_CAROUSEL_URL . '/assets/css/responsive-carousel.css', [], SK_POST_CAROUSEL_VERSION );
        wp_enqueue_script( 'wppis-responsive-carousel-js', SK_POST_CAROUSEL_URL . '/assets/js/responsive-carousel.js', [], SK_POST_CAROUSEL_VERSION, true );
    }

    /* ====================== Settings ====================== */

    private function options(): array {
        return [
            'posts'      => sk_get_option( 'posts', Settings::SECTION, '8' ),
            'categories' => sk_get_option( 'categories', Settings::SECTION, '' ),
            'gap'        => sk_get_option( 'gap', Settings::SECTION, '15' ),
            'h_height'   => sk_get_option( 'h_height', Settings::SECTION, '200' ),
            'v_width'    => sk_get_option( 'v_width', Settings::SECTION, '200' ),
            'direction'  => sk_get_option( 'direction', Settings::SECTION, 'horizontal' ),
            'arrows'     => sk_get_option( 'arrows', Settings::SECTION, 'off' ) === 'on' ? 'true' : 'false',
        ];
    }

    /* ====================== Metabox ====================== */

    public function add_link_metabox() {
        add_meta_box(
            'wppic_image_link',
            __( 'Bild-Link für Carousel', 'sk-core' ),
            [ $this, 'render_link_metabox' ],
            [ 'post' ],
            'side',
            'default'
        );
    }

    public function render_link_metabox( $post ) {
        $val = get_post_meta( $post->ID, self::META_KEY, true );
        wp_nonce_field( 'wppic_image_link_save', 'wppic_image_link_nonce' );
        ?>
        <p>
            <label for="wppic_image_link_input" style="display:block;margin-bottom:6px;">
                <?php esc_html_e( 'Externer Link (nur wirksam in Kategorie „sponsoren"):', 'sk-core' ); ?>
            </label>
            <input type="url" id="wppic_image_link_input" name="wppic_image_link_input"
                   class="widefat" placeholder="https://…" value="<?php echo esc_attr( $val ); ?>" />
        </p>
        <p class="description">
            <?php esc_html_e( 'Ist ein Link gesetzt und der Beitrag in „sponsoren", verlinken Titel/Bild & Carousel auf diese URL.', 'sk-core' ); ?>
        </p>
        <?php
    }

    public function save_link_metabox( $post_id ) {
        if ( ! isset( $_POST['wppic_image_link_nonce'] ) || ! wp_verify_nonce( $_POST['wppic_image_link_nonce'], 'wppic_image_link_save' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $val = isset( $_POST['wppic_image_link_input'] ) ? trim( $_POST['wppic_image_link_input'] ) : '';
        if ( '' === $val ) {
            delete_post_meta( $post_id, self::META_KEY );
            return;
        }

        $url = esc_url_raw( $val, [ 'http', 'https' ] );
        if ( $url ) {
            update_post_meta( $post_id, self::META_KEY, $url );
        }
    }

    /* ====================== Helper ====================== */

    private function external_link_if_applicable( $post ) {
        $post = get_post( $post );
        if ( ! $post || $post->post_type !== 'post' ) {
            return false;
        }

        if ( ! has_category( self::CAT_SLUG, $post ) ) {
            return false;
        }

        $url = get_post_meta( $post->ID, self::META_KEY, true );
        return $url ? esc_url( $url ) : false;
    }

    /* ===== Permalink filters: replace the permalink with the external link (sponsoren only) ===== */

    public function maybe_external_permalink( $permalink, $post, $leavename ) {
        if ( is_admin() ) {
            return $permalink;
        }
        $url = $this->external_link_if_applicable( $post );
        return $url ?: $permalink;
    }

    public function maybe_external_permalink_tp( $permalink, $post = null ) {
        if ( is_admin() ) {
            return $permalink;
        }
        $url = $this->external_link_if_applicable( $post ?: get_post() );
        return $url ?: $permalink;
    }

    public function maybe_external_pt_link( $permalink, $post, $leavename, $sample ) {
        if ( is_admin() ) {
            return $permalink;
        }
        $url = $this->external_link_if_applicable( $post );
        return $url ?: $permalink;
    }

    /* ====================== Shortcode ====================== */

    public function shortcode( $atts = [] ) {
        $opts     = $this->options();
        $defaults = [
            'posts'      => $opts['posts'],
            'categories' => $opts['categories'],
            'gap'        => $opts['gap'],
            'h_height'   => $opts['h_height'],
            'v_width'    => $opts['v_width'],
            'direction'  => $opts['direction'],
            'arrows'     => $opts['arrows'],
        ];
        $a = shortcode_atts( $defaults, $atts, 'post_image_carousel' );

        $direction = ( isset( $atts['direction'] ) && strtolower( $atts['direction'] ) === 'vertical' ) ? 'vertical' : $a['direction'];
        if ( $direction !== 'vertical' ) {
            $direction = 'horizontal';
        }

        $arrows = isset( $atts['arrows'] ) ? strtolower( $atts['arrows'] ) : $a['arrows'];
        $arrows = ( $arrows === 'true' || $arrows === '1' || $arrows === 1 ) ? 'true' : 'false';

        $args = [
            'post_type'           => 'post',
            'post_status'         => 'publish',
            'posts_per_page'      => max( 1, intval( $a['posts'] ) ),
            'ignore_sticky_posts' => true,
            'meta_query'          => [ [ 'key' => '_thumbnail_id', 'compare' => 'EXISTS' ] ],
        ];
        $cats_raw = trim( (string) $a['categories'] );
        if ( $cats_raw !== '' ) {
            $parts = array_filter( array_map( 'trim', explode( ',', $cats_raw ) ) );
            $ids   = [];
            $slugs = [];
            foreach ( $parts as $p ) {
                if ( ctype_digit( $p ) ) {
                    $ids[] = intval( $p );
                } else {
                    $slugs[] = sanitize_title( $p );
                }
            }
            if ( ! empty( $ids ) ) {
                $args['cat'] = implode( ',', array_map( 'intval', $ids ) );
            }
            if ( ! empty( $slugs ) ) {
                $args['category_name'] = implode( ',', array_map( 'sanitize_title', $slugs ) );
            }
        }

        $q = new \WP_Query( $args );
        if ( ! $q->have_posts() ) {
            return '<div class="wppic-empty">' . esc_html__( 'Keine Beiträge mit Bild gefunden.', 'sk-core' ) . '</div>';
        }

        wp_enqueue_style( 'wppic-style' );
        wp_enqueue_script( 'wppic-script' );

        $uid = 'wppic-' . wp_generate_uuid4();
        $gap = max( 0, intval( $a['gap'] ) );
        $h   = max( 50, intval( $a['h_height'] ) );
        $v   = max( 50, intval( $a['v_width'] ) );

        ob_start();
        ?>
        <div id="<?php echo esc_attr( $uid ); ?>"
             class="wppis-slider <?php echo esc_attr( $direction ); ?>"
             data-direction="<?php echo esc_attr( $direction ); ?>"
             data-gap="<?php echo esc_attr( $gap ); ?>"
             data-h="<?php echo esc_attr( $h ); ?>"
             data-v="<?php echo esc_attr( $v ); ?>"
             data-arrows="<?php echo esc_attr( $arrows ); ?>">
          <div class="wppis-track">
            <?php
            while ( $q->have_posts() ) :
                $q->the_post();
                $custom = $this->external_link_if_applicable( get_the_ID() );
                $href   = $custom ? $custom : get_permalink();
                ?>
                <div class="wppis-slide">
                  <a href="<?php echo esc_url( $href ); ?>" class="wppis-link" aria-label="<?php echo esc_attr( get_the_title() ); ?>">
                    <figure class="wppis-figure">
                      <?php echo get_the_post_thumbnail( get_the_ID(), 'large', [ 'loading' => 'lazy', 'decoding' => 'async' ] ); ?>
                    </figure>
                  </a>
                </div>
            <?php endwhile; wp_reset_postdata(); ?>
          </div>
          <button class="wppis-arrow prev" aria-label="<?php esc_attr_e( 'Zurück', 'sk-core' ); ?>">&lsaquo;</button>
          <button class="wppis-arrow next" aria-label="<?php esc_attr_e( 'Weiter', 'sk-core' ); ?>">&rsaquo;</button>
        </div>
        <?php
        return ob_get_clean();
    }
}
