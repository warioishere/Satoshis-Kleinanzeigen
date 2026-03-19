<?php
/*
Plugin Name: WP Post Image Carousel (Simple)
Description: Karussell für Beitrags-Bilder (Featured Images). Optionaler Custom-Link pro Beitrag, der – nur in der Kategorie "sponsoren" – statt des Permalinks genutzt wird. Gilt auch global für Titel-/Bildlinks.
Version: 2.2.0
Author: wario
License: GPLv2 or later
Text Domain: wp-post-image-carousel
*/

if ( ! defined('ABSPATH') ) { exit; }

class WPPIC_Plugin {
    const OPT_KEY  = 'wppic_settings';
    const META_KEY = '_wppic_image_link';        // Metafeld für externen Link
    const CAT_SLUG = 'sponsoren';                // Kategorie, in der der Link aktiv wird

    public function __construct(){
        add_shortcode('post_image_carousel', [$this, 'shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);

        // Metabox für Bild-Link
        add_action('add_meta_boxes', [$this, 'add_link_metabox']);
        add_action('save_post',      [$this, 'save_link_metabox']);

        // Globale Permalink-Überschreibung (Titel-/Bildlinks etc.)
        add_filter('post_link',       [$this,'maybe_external_permalink'], 10, 3); // klassische Posts
        add_filter('the_permalink',   [$this,'maybe_external_permalink_tp'], 10, 2); // Block-Themes / Loops
        add_filter('post_type_link',  [$this,'maybe_external_pt_link'], 10, 4); // Absicherung
    }

    /* ====================== Assets ====================== */
    public function register_assets(){
        $v = '2.2.0';
        wp_register_style('wppic-style', plugins_url('assets/css/carousel.css', __FILE__), [], $v);
        wp_register_script('wppic-script', plugins_url('assets/js/carousel.js', __FILE__), [], $v, true);
    }

    /* ====================== Settings ====================== */
    public function add_settings_page(){
        add_options_page(
            __('Post Image Carousel', 'wp-post-image-carousel'),
            __('Post Image Carousel', 'wp-post-image-carousel'),
            'manage_options',
            'wppic',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings(){
        register_setting(self::OPT_KEY, self::OPT_KEY, ['sanitize_callback'=>[$this,'sanitize']]);

        add_settings_section('wppic_main', __('Allgemein', 'wp-post-image-carousel'), function(){
            echo '<p>'. esc_html__('Standardwerte. Der Shortcode [post_image_carousel] nutzt diese. Attribute überschreiben die Settings.', 'wp-post-image-carousel') .'</p>';
        }, self::OPT_KEY);

        $fields = [
            'posts'     => ['Anzahl Beiträge', 'number'],
            'categories'=> ['Kategorien (Slugs oder IDs, komma-getrennt)', 'text'],
            'gap'       => ['Abstand zwischen Bildern (px, horizontal)', 'number'],
            'h_height'  => ['Höhe der Bilder (horizontal, px)', 'number'],
            'v_width'   => ['Breite der Bilder (vertikal, px)', 'number'],
            'direction' => ['Standard-Richtung (horizontal|vertical)', 'text'],
            'arrows'    => ['Pfeile anzeigen', 'checkbox'],
        ];
        foreach($fields as $key=>$meta){
            add_settings_field($key, esc_html__($meta[0],'wp-post-image-carousel'), function() use($key,$meta){
                $opts = get_option(WPPIC_Plugin::OPT_KEY, []);
                $val = isset($opts[$key]) ? $opts[$key] : '';
                $type = $meta[1];
                $name = WPPIC_Plugin::OPT_KEY."[$key]";
                if ($type==='checkbox'){
                    printf('<label><input type="checkbox" name="%s" value="1" %s /> %s</label>',
                        esc_attr($name), checked($val, '1', false), esc_html__('aktivieren', 'wp-post-image-carousel'));
                } else {
                    $attrs = $type==='number' ? 'min="0" step="1"' : '';
                    printf('<input type="%s" name="%s" value="%s" class="regular-text" %s />',
                        esc_attr($type), esc_attr($name), esc_attr($val), $attrs);
                }
                if ($key==='categories') echo '<p class="description">'. esc_html__('Beispiel: news,12,events (Slugs oder IDs).', 'wp-post-image-carousel') .'</p>';
            }, self::OPT_KEY, 'wppic_main');
        }
    }

    public function sanitize($in){
        $out = [];
        $out['posts']      = isset($in['posts']) ? max(1, intval($in['posts'])) : 8;
        $out['categories'] = isset($in['categories']) ? sanitize_text_field($in['categories']) : '';
        $out['gap']        = isset($in['gap']) ? max(0, intval($in['gap'])) : 15;
        $out['h_height']   = isset($in['h_height']) ? max(50, intval($in['h_height'])) : 200;
        $out['v_width']    = isset($in['v_width']) ? max(50, intval($in['v_width'])) : 200;
        $dir = isset($in['direction']) ? strtolower(trim($in['direction'])) : 'horizontal';
        $out['direction']  = ($dir==='vertical') ? 'vertical' : 'horizontal';
        $out['arrows']     = !empty($in['arrows']) ? '1' : '0';
        return $out;
    }

    public function render_settings_page(){
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Post Image Carousel – Einstellungen', 'wp-post-image-carousel'); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields(self::OPT_KEY); do_settings_sections(self::OPT_KEY); submit_button(); ?>
            </form>
            <hr/>
            <p><strong><?php esc_html_e('Shortcode:', 'wp-post-image-carousel'); ?></strong>
               <code>[post_image_carousel]</code>,
               <code>[post_image_carousel direction="vertical"]</code>,
               <code>[post_image_carousel posts="12" categories="news,12" arrows="false"]</code>
            </p>
        </div>
        <?php
    }

    /* ====================== Metabox ====================== */
    public function add_link_metabox(){
        add_meta_box(
            'wppic_image_link',
            __('Bild-Link für Carousel', 'wp-post-image-carousel'),
            [$this, 'render_link_metabox'],
            ['post'],
            'side',
            'default'
        );
    }

    public function render_link_metabox($post){
        $val = get_post_meta($post->ID, self::META_KEY, true);
        wp_nonce_field('wppic_image_link_save', 'wppic_image_link_nonce');
        ?>
        <p>
            <label for="wppic_image_link_input" style="display:block;margin-bottom:6px;">
                <?php esc_html_e('Externer Link (nur wirksam in Kategorie „sponsoren“):', 'wp-post-image-carousel'); ?>
            </label>
            <input type="url" id="wppic_image_link_input" name="wppic_image_link_input"
                   class="widefat" placeholder="https://…" value="<?php echo esc_attr($val); ?>" />
        </p>
        <p class="description">
            <?php esc_html_e('Ist ein Link gesetzt und der Beitrag in „sponsoren“, verlinken Titel/Bild & Carousel auf diese URL.', 'wp-post-image-carousel'); ?>
        </p>
        <?php
    }

    public function save_link_metabox($post_id){
        if ( ! isset($_POST['wppic_image_link_nonce']) || ! wp_verify_nonce($_POST['wppic_image_link_nonce'], 'wppic_image_link_save') ) return;
        if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
        if ( ! current_user_can('edit_post', $post_id) ) return;

        $val = isset($_POST['wppic_image_link_input']) ? trim($_POST['wppic_image_link_input']) : '';
        if ($val === '') { delete_post_meta($post_id, self::META_KEY); return; }

        $url = esc_url_raw($val, ['http','https']);
        if ($url) update_post_meta($post_id, self::META_KEY, $url);
    }

    /* ====================== Helper ====================== */
    private function external_link_if_applicable($post){
        $post = get_post($post);
        if ( ! $post || $post->post_type !== 'post') return false;

        // Nur in Kategorie "sponsoren"
        if ( ! has_category(self::CAT_SLUG, $post) ) return false;

        $url = get_post_meta($post->ID, self::META_KEY, true);
        return $url ? esc_url($url) : false;
    }

    /* ===== Permalink-Filter: ersetzt Permalink durch externen Link (nur sponsoren) ===== */
    public function maybe_external_permalink($permalink, $post, $leavename){
        if (is_admin()) return $permalink;
        $url = $this->external_link_if_applicable($post);
        return $url ?: $permalink;
    }
    // the_permalink (Block-Themes geben $post häufig mit)
    public function maybe_external_permalink_tp($permalink, $post = null){
        if (is_admin()) return $permalink;
        $url = $this->external_link_if_applicable($post ?: get_post());
        return $url ?: $permalink;
    }
    // Absicherung über post_type_link
    public function maybe_external_pt_link($permalink, $post, $leavename, $sample){
        if (is_admin()) return $permalink;
        $url = $this->external_link_if_applicable($post);
        return $url ?: $permalink;
    }

    /* ====================== Shortcode ====================== */
    public function shortcode($atts=[]){
        $opts = get_option(self::OPT_KEY, []);
        $defaults = [
            'posts'      => isset($opts['posts']) ? $opts['posts'] : 8,
            'categories' => isset($opts['categories']) ? $opts['categories'] : '',
            'gap'        => isset($opts['gap']) ? $opts['gap'] : 15,
            'h_height'   => isset($opts['h_height']) ? $opts['h_height'] : 200,
            'v_width'    => isset($opts['v_width']) ? $opts['v_width'] : 200,
            'direction'  => isset($opts['direction']) ? $opts['direction'] : 'horizontal',
            'arrows'     => isset($opts['arrows']) && $opts['arrows'] === '1' ? 'true' : 'false',
        ];
        $a = shortcode_atts($defaults, $atts, 'post_image_carousel');

        $direction = (isset($atts['direction']) && strtolower($atts['direction'])==='vertical') ? 'vertical' : $a['direction'];
        if ($direction!=='vertical') $direction='horizontal';

        $arrows = isset($atts['arrows']) ? strtolower($atts['arrows']) : $a['arrows'];
        $arrows = ($arrows === 'true' || $arrows === '1' || $arrows === 1) ? 'true' : 'false';

        $args = [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => max(1, intval($a['posts'])),
            'ignore_sticky_posts' => true,
            'meta_query'     => [[ 'key'=>'_thumbnail_id', 'compare'=>'EXISTS' ]]
        ];
        $cats_raw = trim((string)$a['categories']);
        if ($cats_raw!==''){
            $parts = array_filter(array_map('trim', explode(',', $cats_raw)));
            $ids=[]; $slugs=[];
            foreach($parts as $p){ if (ctype_digit($p)) $ids[]=intval($p); else $slugs[]=sanitize_title($p); }
            if (!empty($ids))   $args['cat'] = implode(',', array_map('intval',$ids));
            if (!empty($slugs)) $args['category_name'] = implode(',', array_map('sanitize_title',$slugs));
        }

        $q = new WP_Query($args);
        if (!$q->have_posts()) return '<div class="wppic-empty">'.esc_html__('Keine Beiträge mit Bild gefunden.','wp-post-image-carousel').'</div>';

        wp_enqueue_style('wppic-style');
        wp_enqueue_script('wppic-script');

        $uid = 'wppic-'.wp_generate_uuid4();
        $gap = max(0, intval($a['gap']));
        $h = max(50, intval($a['h_height']));
        $v = max(50, intval($a['v_width']));

        ob_start(); ?>
        <div id="<?php echo esc_attr($uid); ?>"
             class="wppis-slider <?php echo esc_attr($direction); ?>"
             data-direction="<?php echo esc_attr($direction); ?>"
             data-gap="<?php echo esc_attr($gap); ?>"
             data-h="<?php echo esc_attr($h); ?>"
             data-v="<?php echo esc_attr($v); ?>"
             data-arrows="<?php echo esc_attr($arrows); ?>">
          <div class="wppis-track">
            <?php while($q->have_posts()): $q->the_post();
                // Custom-Link NUR, wenn der Beitrag in "sponsoren" ist
                $custom = $this->external_link_if_applicable(get_the_ID());
                $href   = $custom ? $custom : get_permalink();
                ?>
                <div class="wppis-slide">
                  <a href="<?php echo esc_url($href); ?>" class="wppis-link" aria-label="<?php echo esc_attr(get_the_title()); ?>">
                    <figure class="wppis-figure">
                      <?php echo get_the_post_thumbnail(get_the_ID(), 'large', ['loading'=>'lazy','decoding'=>'async']); ?>
                    </figure>
                  </a>
                </div>
            <?php endwhile; wp_reset_postdata(); ?>
          </div>
          <button class="wppis-arrow prev" aria-label="<?php esc_attr_e('Zurück','wp-post-image-carousel'); ?>">&lsaquo;</button>
          <button class="wppis-arrow next" aria-label="<?php esc_attr_e('Weiter','wp-post-image-carousel'); ?>">&rsaquo;</button>
        </div>
        <?php
        return ob_get_clean();
    }
}

new WPPIC_Plugin();

/* Zusätzliche responsive Assets (falls vorhanden) */
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'wppis-responsive-carousel',
        plugin_dir_url(__FILE__) . 'assets/css/responsive-carousel.css',
        array(),
        '1.0'
    );
    wp_enqueue_script(
        'wppis-responsive-carousel-js',
        plugin_dir_url(__FILE__) . 'assets/js/responsive-carousel.js',
        array(),
        '1.0',
        true
    );
}, 99);


