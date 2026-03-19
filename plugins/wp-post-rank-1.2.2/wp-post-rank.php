<?php
/*
Plugin Name: WP Post Rank
Description: Numerischer „Rang“ (höher = weiter oben) für Beiträge. Admin-Sortierung, Shortcode [posts_by_rank], und Block-Sortierung (Neueste Beiträge/Query-Loop) via Klasse `order-by-rank`. v1.2.2 erzwingt die Sortierung zusätzlich per pre_get_posts, ohne das HTML/Layout zu ändern.
Version: 1.2.2
Author: yourdevice.ch
License: GPLv2 or later
Text Domain: wp-post-rank
*/

if ( ! defined('ABSPATH') ) { exit; }

class WP_Post_Rank {
    const META_KEY = '_post_rank';

    public function __construct() {
        add_action('init', [$this, 'register_meta']);
        add_action('add_meta_boxes', [$this, 'add_metabox']);
        add_action('save_post_post', [$this, 'save_rank'], 10, 2);

        add_filter('manage_post_posts_columns', [$this, 'add_column']);
        add_action('manage_post_posts_custom_column', [$this, 'render_column'], 10, 2);
        add_filter('manage_edit-post_sortable_columns', [$this, 'make_column_sortable']);
        add_action('pre_get_posts', [$this, 'admin_orderby_rank']);

        add_shortcode('posts_by_rank', [$this, 'shortcode_posts_by_rank']);

        // Block-Filter: setzen zusätzlich ein Kennzeichen, damit wir in pre_get_posts sicher greifen
        add_filter('block_core_latest_posts_query', [$this, 'filter_latest_posts_query'], 1000, 2);
        add_filter('query_loop_block_query_vars',   [$this, 'filter_query_loop_vars'],   1000, 2);

        // Harte Durchsetzung auf SQL-Ebene für Block-Queries mit unserem Kennzeichen
        add_action('pre_get_posts', [$this, 'force_order_for_ranked_blocks'], 1000);
    }

    public function register_meta() {
        register_post_meta('post', self::META_KEY, [
            'type' => 'number',
            'single' => true,
            'show_in_rest' => true,
            'auth_callback' => function() { 
                return current_user_can('edit_posts');
            },
        ]);
    }

    public function add_metabox() {
        add_meta_box(
            'wpr_rank_box',
            __('Rang (Zahl – höher = weiter oben)', 'wp-post-rank'),
            [$this, 'metabox_html'],
            'post',
            'side',
            'default'
        );
    }

    public function metabox_html($post) {
        $value = get_post_meta($post->ID, self::META_KEY, true);
        wp_nonce_field('wpr_save_rank', 'wpr_rank_nonce');
        echo '<p><label for="wpr_rank">'. esc_html__('Rang', 'wp-post-rank') .'</label></p>';
        echo '<input type="number" id="wpr_rank" name="wpr_rank" class="small-text" step="1" min="0" value="'. esc_attr($value) .'" />';
        echo '<p class="description">'. esc_html__('Je höher der Wert, desto weiter oben wird der Beitrag gelistet.', 'wp-post-rank') .'</p>';
    }

    public function save_rank($post_id, $post) {
        if ( ! isset($_POST['wpr_rank_nonce']) || ! wp_verify_nonce($_POST['wpr_rank_nonce'], 'wpr_save_rank') ) return;
        if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
        if ( ! current_user_can('edit_post', $post_id) ) return;

        $rank = isset($_POST['wpr_rank']) ? intval($_POST['wpr_rank']) : '';
        if ($rank === '') {
            delete_post_meta($post_id, self::META_KEY);
        } else {
            update_post_meta($post_id, self::META_KEY, $rank);
        }
    }

    public function add_column($columns) {
        $new = [];
        foreach ($columns as $k=>$v) {
            if ($k === 'date') {
                $new['post_rank'] = __('Rang', 'wp-post-rank');
            }
            $new[$k] = $v;
        }
        if (!isset($new['post_rank'])) {
            $new['post_rank'] = __('Rang', 'wp-post-rank');
        }
        return $new;
    }

    public function render_column($column, $post_id) {
        if ($column === 'post_rank') {
            $rank = get_post_meta($post_id, self::META_KEY, true);
            echo $rank !== '' ? intval($rank) : '—';
        }
    }

    public function make_column_sortable($columns) {
        $columns['post_rank'] = 'post_rank';
        return $columns;
    }

    public function admin_orderby_rank($query) {
        if ( ! is_admin() || ! $query->is_main_query() ) return;
        if ($query->get('post_type') !== 'post') return;

        if ($query->get('orderby') === 'post_rank') {
            $query->set('meta_key', self::META_KEY);
            $query->set('orderby', 'meta_value_num');
            $order = strtoupper($query->get('order')) === 'ASC' ? 'ASC' : 'DESC';
            $query->set('order', $order);
        }
    }

    public function shortcode_posts_by_rank($atts = []) {
        $a = shortcode_atts([
            'posts_per_page' => 10,
        ], $atts, 'posts_by_rank');

        $q = new WP_Query([
            'post_type' => 'post',
            'posts_per_page' => intval($a['posts_per_page']),
            'meta_key' => self::META_KEY,
            'orderby' => ['meta_value_num' => 'DESC', 'date' => 'DESC'],
            'order' => 'DESC',
            'post_status' => 'publish',
            'ignore_sticky_posts' => true,
        ]);

        ob_start();
        if ($q->have_posts()) {
            echo '<ul class="wpr-list">';
            while ($q->have_posts()) { $q->the_post();
                $rank = get_post_meta(get_the_ID(), self::META_KEY, true);
                echo '<li class="wpr-item">';
                echo '<a href="'. esc_url(get_permalink()) .'">'. esc_html(get_the_title()) .'</a>';
                if ($rank !== '') {
                    echo ' <span class="wpr-rank">('. intval($rank) .')</span>';
                }
                echo '</li>';
            }
            echo '</ul>';
            wp_reset_postdata();
        } else {
            echo '<p>'. esc_html__('Keine Beiträge gefunden.', 'wp-post-rank') .'</p>';
        }
        return ob_get_clean();
    }

    /** Block: Neueste Beiträge – Query-Args + Flag */
    public function filter_latest_posts_query($args, $attributes) {
        $class = isset($attributes['className']) ? (string)$attributes['className'] : '';
        if (strpos($class, 'order-by-rank') !== false) {
            $args['meta_key'] = self::META_KEY;
            $args['orderby']  = 'meta_value_num';
            $args['order']    = 'DESC';
            $args['ignore_sticky_posts'] = true;
            $args['wpr_order_by_rank'] = 1; // unser Kennzeichen
        }
        return $args;
    }

    /** Block: Query Loop – Query-Args + Flag */
    public function filter_query_loop_vars($query_args, $block) {
        $attrs = isset($block->attributes) ? $block->attributes : ( (isset($block['attrs'])) ? $block['attrs'] : [] );
        $class = isset($attrs['className']) ? (string)$attrs['className'] : '';
        if (strpos($class, 'order-by-rank') !== false) {
            $query_args['meta_key'] = self::META_KEY;
            $query_args['orderby']  = 'meta_value_num';
            $query_args['order']    = 'DESC';
            $query_args['ignore_sticky_posts'] = true;
            $query_args['wpr_order_by_rank'] = 1; // unser Kennzeichen
        }
        return $query_args;
    }

    /** Erzwinge Sortierung für Block-Queries, die unser Kennzeichen tragen */
    public function force_order_for_ranked_blocks($query) {
        // Nur für WP_Query Instanzen, die unser Flag tragen
        if ( ! $query instanceof WP_Query ) return;
        if ( intval($query->get('wpr_order_by_rank')) !== 1 ) return;

        // Ab hier greifen wir hart ein
        $query->set('meta_key', self::META_KEY);
        $query->set('orderby', 'meta_value_num');
        $query->set('order', 'DESC');
        $query->set('ignore_sticky_posts', true);

        // Fallback: wenn Theme "order" setzt, überschreiben wir erneut
        add_filter('posts_orderby', function($orderby, $wp_query){
            if ( intval($wp_query->get('wpr_order_by_rank')) === 1 ) {
                global $wpdb;
                // meta_value + 0 casten, damit numerisch sortiert wird
                return "{$wpdb->postmeta}.meta_value+0 DESC";
            }
            return $orderby;
        }, 1000, 2);

        // Stellen sicher, dass die Join auf postmeta existiert
        add_filter('posts_join', function($join, $wp_query){
            if ( intval($wp_query->get('wpr_order_by_rank')) === 1 ) {
                global $wpdb;
                if ( strpos($join, $wpdb->postmeta) === false ) {
                    $join .= " LEFT JOIN {$wpdb->postmeta} ON ({$wpdb->posts}.ID = {$wpdb->postmeta}.post_id AND {$wpdb->postmeta}.meta_key = '". esc_sql(self::META_KEY) ."') ";
                }
            }
            return $join;
        }, 1000, 2);

        // Verhindern, dass Meta-Querys anderer Plugins uns stören
        add_filter('posts_groupby', function($groupby, $wp_query){
            if ( intval($wp_query->get('wpr_order_by_rank')) === 1 ) {
                // kein GROUP BY, damit ORDER BY greift
                return '';
            }
            return $groupby;
        }, 1000, 2);
    }
}

new WP_Post_Rank();
