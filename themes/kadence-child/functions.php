<?php
/**
 * Enqueue parent and child theme styles for Kadence.
 */
add_action('wp_enqueue_scripts', function () {

    // --- 1) Parent-Theme CSS ---
    wp_enqueue_style(
        'kadence-parent-style',
        get_template_directory_uri() . '/style.css'
    );

    // --- 2) Child-Theme CSS (mit dynamischer Version) ---
    $child_css_path = get_stylesheet_directory() . '/style.css';
    wp_enqueue_style(
        'kadence-child-style',
        get_stylesheet_directory_uri() . '/style.css',
        ['kadence-parent-style'],
        file_exists($child_css_path) ? filemtime($child_css_path) : null
    );

    // --- 3) hide-content.css (direkt im Theme-Root) ---
    $hide_path = get_stylesheet_directory() . '/hide-content.css';
    if (file_exists($hide_path)) {
        wp_enqueue_style(
            'hide-content',
            get_stylesheet_directory_uri() . '/hide-content.css',
            ['kadence-child-style'],
            filemtime($hide_path)
        );
    }

}, 20);

// vendor-card-stretched-link.js moved to sk-core/assets/js/

/**
 * Versionsparameter sicherstellen, falls andere Enqueues greifen.
 * Überschreibt bei Bedarf die ?ver= Version mit dem filemtime()-Wert.
 */
add_filter('style_loader_src', function ($src, $handle) {
    foreach (['style.css', 'hide-content.css'] as $file) {
        $url  = get_stylesheet_directory_uri() . '/' . $file;
        $path = get_stylesheet_directory()   . '/' . $file;

        if (strpos($src, $url) !== false && file_exists($path)) {
            $ver = filemtime($path);
            $src = remove_query_arg('ver', $src);
            $src = add_query_arg('ver', $ver, $src);
        }
    }
    return $src;
}, 10, 2);


// functions.php (Child-Theme)
add_filter('wp_img_tag_add_loading_attr', function ($value, $image, $context) {
    if (strpos($image, 'cdn-icons-png.flaticon.com/512/149/149071.png') !== false) {
        return 'eager'; // statt 'lazy'
    }
    return $value;
}, 10, 3);

// Email SMTP

add_action( 'phpmailer_init', 'my_phpmailer_smtp' );
function my_phpmailer_smtp( $phpmailer ) {
    $phpmailer->isSMTP();
    $phpmailer->Host = SMTP_server;
    $phpmailer->SMTPAuth = SMTP_AUTH;
    $phpmailer->Port = SMTP_PORT;
    $phpmailer->Username = SMTP_username;
    $phpmailer->Password = SMTP_password;
    $phpmailer->SMTPSecure = SMTP_SECURE;
    $phpmailer->From = SMTP_FROM;
    $phpmailer->FromName = SMTP_NAME;
}

// Placeholder emails (satoshi-xxx@*.local) are filtered server-side
// in ContactDetails::is_placeholder_email(). No JS hack needed.

add_action( 'woocommerce_save_account_details', 'save_account_display_name', 12 );
function save_account_display_name( $user_id ) {
    if ( isset( $_POST['account_display_name'] ) ) {
        wp_update_user( array(
            'ID'           => $user_id,
            'display_name' => sanitize_text_field( $_POST['account_display_name'] ),
        ) );
    }
}

// Change Vendor Side Wording

add_action('wp_footer', function () {
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const el = document.querySelector('p.store-count');
        if (el && el.textContent.includes('Shop Insgesamt Anzeigen')) {
            el.textContent = el.textContent.replace('Shop Insgesamt Anzeigen', 'Anbieter insgesamt');
        }
    });
    </script>
    <?php
});

// SK Login shortcode [sk_login] is now in sk-core/modules/sk-auth/module.php.
// Old alias [lnurl_auth_conditional] is also registered there.

// Vorherige Badge-Ausgabe (falls vorhanden) entfernen
remove_action('woocommerce_after_shop_loop_item_title', 'product_category_badges', 6);

// Neue Ausgabe: direkt oberhalb des Preises
add_action('woocommerce_after_shop_loop_item_title', function () {
    if (!function_exists('wc_get_product')) return;
    global $product;
    if (!$product) return;

    $terms = get_the_terms($product->get_id(), 'product_cat');
    if (empty($terms) || is_wp_error($terms)) return;

    // Optional: auf max. 3 Kategorien begrenzen
    $terms = array_slice($terms, 0, 3);

    echo '<div class="product-card-cats product-card-cats--above-price">';
    foreach ($terms as $term) {
        $url = get_term_link($term);
        if (is_wp_error($url)) continue;
        echo '<a class="product-card__cat" href="'.esc_url($url).'">'.esc_html($term->name).'</a>';
    }
    echo '</div>';
}, 9); // kommt direkt vor dem Preis (der ist Priority 10)

// Boost-Icon + zentrierte Zelle (ohne ::after-Text!)
add_action('wp_enqueue_scripts', function () {
  if ( function_exists('sk_is_seller_dashboard') && sk_is_seller_dashboard() ) {

    $handle  = wp_style_is('sk-style','registered') ? 'sk-style' : (wp_styles()->queue[0] ?? 'wp-block-library');
    $icon_default = esc_url_raw( get_stylesheet_directory_uri() . '/assets/icons/boost.svg' );
    $icon_active  = esc_url_raw( get_stylesheet_directory_uri() . '/assets/icons/boost-active.svg' );

    $css = "
      .sk-dashboard .adv_icon_2{
        font-size:0 !important; line-height:1; position:relative;
        width:28px; height:28px; display:inline-block; vertical-align:middle;
        background:url('{$icon_default}') no-repeat center; background-size:contain;
      }
      .sk-dashboard .adv_icon_1{ display:none !important; }
      .sk-dashboard span.sk-product-advertisement.advertised .adv_icon_2,
      .sk-dashboard span.sk-product-advertisement[data-already-advertised=\"advertised\"] .adv_icon_2{
        background:url('{$icon_active}') no-repeat center !important; background-size:contain !important;
      }
      .sk-dashboard td.product-advertisement-td{ text-align:center; }
      .sk-dashboard td.product-advertisement-td .boost-label{
        display:block; margin-top:6px; font-size:13px; font-weight:600; color:#f7931a;
      }
    ";
    wp_add_inline_style($handle, $css);
  }
}, 20);


// category-toggle.js moved to sk-core/assets/js/


// SK Footer Shortcode
add_shortcode( 'sk_footer', function() {
    $version = defined( 'SK_LITE_PLUGIN_VERSION' ) ? SK_LITE_PLUGIN_VERSION : '';
    return '<span class="sk-footer-credit" style="display:block;text-align:center;font-size:13px;color:#8b949e;letter-spacing:0.3px;">Made with <span style="color:#f7931a;">&hearts;</span> by the SK-Team &middot; v' . esc_html( $version ) . '</span>';
});
