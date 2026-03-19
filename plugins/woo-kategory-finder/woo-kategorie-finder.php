<?php
/*
Plugin Name: Woo Kategorie Finder (AJAX)
Description: AJAX-Suchfeld für WooCommerce-Produktkategorien per Shortcode [woo_kategorie_finder].
Version: 1.1
Author: Wario
*/

add_shortcode('woo_kategorie_finder', 'wkf_render_search_box');

function wkf_render_search_box() {
    ob_start();
    ?>
    <div class="wkf-autocomplete-wrapper">
        <input type="text" id="wkf-cat-search" placeholder="Kategorie suchen...">
        <ul id="wkf-results"></ul>
    </div>
    <?php
    return ob_get_clean();
}

add_action('wp_enqueue_scripts', 'wkf_enqueue_scripts');

function wkf_enqueue_scripts() {
    wp_enqueue_script('wkf-ajax-script', plugin_dir_url(__FILE__) . 'woo-kategorie-finder.js', ['jquery'], null, true);
    wp_localize_script('wkf-ajax-script', 'wkf_ajax_object', [
        'ajax_url' => admin_url('admin-ajax.php'),
    ]);

    wp_enqueue_style('wkf-style', plugin_dir_url(__FILE__) . 'woo-kategorie-finder.css');
}

add_action('wp_ajax_wkf_search_categories', 'wkf_search_categories');
add_action('wp_ajax_nopriv_wkf_search_categories', 'wkf_search_categories');

function wkf_search_categories() {
    $term = isset($_POST['term']) ? sanitize_text_field($_POST['term']) : '';
    $results = [];

    if ($term !== '') {
        $terms = get_terms([
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'name__like' => $term
        ]);

        if (!empty($terms) && !is_wp_error($terms)) {
            foreach ($terms as $t) {
                $results[] = [
                    'name' => $t->name,
                    'link' => get_term_link($t)
                ];
            }
        }
    }

    wp_send_json($results);
}

