<?php
/**
 * Kadence Child — Enqueue parent and child theme styles.
 */
add_action('wp_enqueue_scripts', function () {

    // Parent-Theme CSS
    wp_enqueue_style(
        'kadence-parent-style',
        get_template_directory_uri() . '/style.css'
    );

    // Child-Theme CSS (cache-busted via filemtime)
    $child_css_path = get_stylesheet_directory() . '/style.css';
    wp_enqueue_style(
        'kadence-child-style',
        get_stylesheet_directory_uri() . '/style.css',
        ['kadence-parent-style'],
        file_exists($child_css_path) ? filemtime($child_css_path) : null
    );

}, 20);

/**
 * Cache-bust child theme CSS when re-enqueued by other code.
 */
add_filter('style_loader_src', function ($src, $handle) {
    $file = 'style.css';
    $url  = get_stylesheet_directory_uri() . '/' . $file;
    $path = get_stylesheet_directory()     . '/' . $file;

    if (strpos($src, $url) !== false && file_exists($path)) {
        $ver = filemtime($path);
        $src = remove_query_arg('ver', $src);
        $src = add_query_arg('ver', $ver, $src);
    }

    return $src;
}, 10, 2);

/**
 * Eager-load specific external avatar to avoid layout shift.
 */
add_filter('wp_img_tag_add_loading_attr', function ($value, $image, $context) {
    if (strpos($image, 'cdn-icons-png.flaticon.com/512/149/149071.png') !== false) {
        return 'eager';
    }
    return $value;
}, 10, 3);
