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

add_action('wp_enqueue_scripts', function () {

    // Nur auf Einzel-Produktseiten laden
    if ( is_product() ) {
        $path = get_stylesheet_directory() . '/js/vendor-card-stretched-link.js';
        wp_enqueue_script(
            'vendor-card-stretched-link',
            get_stylesheet_directory_uri() . '/js/vendor-card-stretched-link.js',
            [],                                   // keine Abhängigkeiten
            file_exists($path) ? filemtime($path) : null,  // Cache-Bust über Dateidatum
            true                                  // im Footer laden
        );
    }

}, 20);

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

// DOKAN

// In dein Child-Theme: functions.php
add_action('wp_footer', function () {
    ?>
    <script>
    document.addEventListener("DOMContentLoaded", function () {
      document.querySelectorAll(".sk-store-email").forEach(function (el) {
        const link = el.querySelector("a[href^='mailto:']");
        if (link) {
          const email = link.getAttribute("href").replace("mailto:", "").toLowerCase();
          if (email.startsWith("satoshi-")) {
            el.remove();
          }
        }
      });
    });
    </script>
    <?php
});

// Add Change Displayname to Dokan Dashboard

add_action( 'woocommerce_edit_account_form', 'add_display_name_to_account_form' );
function add_display_name_to_account_form() {
    $user = wp_get_current_user();
    ?>
    <p class="form-row form-row-wide">
        <label for="account_display_name"><?php esc_html_e( 'Anzeigename', 'woocommerce' ); ?>&nbsp;<span class="required">*</span></label>
        <input type="text" class="woocommerce-Input woocommerce-Input--text input-text"
               name="account_display_name" id="account_display_name"
               value="<?php echo esc_attr( $user->display_name ); ?>" />
        <span><em><?php esc_html_e( 'Dies ist ist rein intern und wird nicht öffentlich angezeigt.', 'woocommerce' ); ?></em></span>
    </p>
    <?php
}

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

// SK Login — tabbed login with Lightning, Bitcoin, Nostr
add_shortcode('sk_login', function () {
    if ( is_user_logged_in() ) {
        $user = wp_get_current_user();
        return '<p style="text-align:center;">Hallo <strong>' . esc_html($user->display_name) . '</strong>, du bist bereits eingeloggt.</p>';
    }

    ob_start();
    ?>
    <div class="sk-login-tabs">
        <div class="sk-login-tab-buttons">
            <button class="sk-login-tab-btn active" data-tab="lightning">⚡ Lightning</button>
            <button class="sk-login-tab-btn" data-tab="bitcoin"><span style="color:#f7931a;">₿</span> Bitcoin</button>
            <button class="sk-login-tab-btn" data-tab="nostr">🔑 Nostr</button>
        </div>
        <div class="sk-login-tab-content">
            <div class="sk-login-panel active" id="sk-login-lightning">
                <?php echo do_shortcode('[lnurl_auth]'); ?>
                <div id="lnurl-copy-button-container"></div>
            </div>
            <div class="sk-login-panel" id="sk-login-bitcoin">
                <?php echo do_shortcode('[btc_login]'); ?>
            </div>
            <div class="sk-login-panel" id="sk-login-nostr">
                <?php echo do_shortcode('[nostr_login_box]'); ?>
            </div>
        </div>
    </div>
    <style>
    .sk-login-tabs { max-width: 480px; margin: 0 auto; }
    .sk-login-tab-buttons {
        display: flex; gap: 0; border-bottom: 2px solid #2b3240;
        margin-bottom: 1.5rem;
    }
    .sk-login-tab-btn {
        flex: 1; padding: 12px 16px; border: none; background: #181e27;
        color: #8b949e; font-size: 15px; font-weight: 600; cursor: pointer;
        border-bottom: 3px solid transparent; transition: all 0.2s;
    }
    .sk-login-tab-btn:first-child { border-radius: 8px 0 0 0; }
    .sk-login-tab-btn:last-child { border-radius: 0 8px 0 0; }
    .sk-login-tab-btn:hover { color: #e2e8f0; background: #1f2733; }
    .sk-login-tab-btn.active {
        color: #f7931a; border-bottom-color: #f7931a; background: #1f2733;
    }
    .sk-login-panel { display: none; text-align: center; padding: 1rem 0; }
    .sk-login-panel.active { display: block; }
    @media (max-width: 480px) {
        .sk-login-tab-btn { font-size: 13px; padding: 10px 8px; }
    }
    </style>
    <script>
    (function() {
        var btns = document.querySelectorAll('.sk-login-tab-btn');
        var panels = document.querySelectorAll('.sk-login-panel');
        btns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                btns.forEach(function(b) { b.classList.remove('active'); });
                panels.forEach(function(p) { p.classList.remove('active'); });
                btn.classList.add('active');
                document.getElementById('sk-login-' + btn.dataset.tab).classList.add('active');
            });
        });
        /* LNURL copy button */
        var retries = 0, maxRetries = 100;
        var iv = setInterval(function() {
            var link = document.querySelector('.lnurl-auth-permalink a[href^="lightning:"]');
            var container = document.getElementById('lnurl-copy-button-container');
            if (link && container && !document.getElementById('lnurl-copy-button')) {
                var lnurl = link.getAttribute('href').replace(/^lightning:/, '').trim();
                if (lnurl) {
                    var btn = document.createElement('button');
                    btn.id = 'lnurl-copy-button';
                    btn.textContent = 'LNURL kopieren';
                    btn.className = 'sk-btn';
                    btn.style.marginTop = '1em';
                    btn.onclick = function() {
                        navigator.clipboard.writeText(lnurl).then(function() {
                            btn.textContent = 'Kopiert!';
                            setTimeout(function() { btn.textContent = 'LNURL kopieren'; }, 2000);
                        });
                    };
                    container.appendChild(btn);
                }
                clearInterval(iv);
            }
            if (++retries >= maxRetries) clearInterval(iv);
        }, 100);
    })();
    </script>
    <?php
    return ob_get_clean();
});

// Keep old shortcode as alias
add_shortcode('lnurl_auth_conditional', function () {
    return do_shortcode('[sk_login]');
});

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


// Category Switcher – robustes Enqueue + Debug
add_action('wp_enqueue_scripts', function () {
    // Pfade bauen
    $file_rel = '/js/category-toggle.js';
    $file_abs = get_stylesheet_directory() . $file_rel;        // /wp-content/themes/kadence-child/js/category-toggle.js
    $file_uri = get_stylesheet_directory_uri() . $file_rel;    // https://.../wp-content/themes/kadence-child/js/category-toggle.js

    // Existenz prüfen (loggt in error_log, falls nicht gefunden)
    if ( ! file_exists($file_abs) ) {
        error_log('category-toggle.js NICHT gefunden: ' . $file_abs);
        return;
    }

    // Version = mtime -> Cache Buster
    $ver = @filemtime($file_abs) ?: null;

    // WICHTIG: erstmal im HEAD laden (letzter Parameter = false), damit wir es im Network sicher sehen
    wp_enqueue_script(
        'category-toggle',
        $file_uri,
        array(),      // keine Deps
        $ver,
        false         // jetzt HEAD (zum Debug), später wieder true für Footer
    );

    // Mini-Debug in die Konsole
}, 99);


// SK Footer Shortcode
add_shortcode( 'sk_footer', function() {
    $version = defined( 'SK_LITE_PLUGIN_VERSION' ) ? SK_LITE_PLUGIN_VERSION : '';
    return '<span class="sk-footer-credit" style="display:block;text-align:center;font-size:13px;color:#8b949e;letter-spacing:0.3px;">Made with <span style="color:#f7931a;">&hearts;</span> by the SK-Team &middot; v' . esc_html( $version ) . '</span>';
});
