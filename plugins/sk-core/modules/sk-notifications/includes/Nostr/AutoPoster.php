<?php
/**
 * Nostr Auto Poster for WooCommerce.
 *
 * Posts new WooCommerce products to Nostr relays automatically.
 * Originally a standalone plugin by Wario, now part of sk-notifications module.
 *
 * Nostr libs loaded centrally via sk-core/lib/autoload.php.
 */

if (!defined('ABSPATH')) exit;

if (!defined('NAP_OPTION_GROUP')) define('NAP_OPTION_GROUP', 'nap_nostr_settings');
if (!defined('NAP_OPTION_NAME'))  define('NAP_OPTION_NAME',  'nap_nostr_options');
if (!defined('NAP_META_EVENT_ID')) define('NAP_META_EVENT_ID','_nap_nostr_event_id');

use swentel\nostr\Event\Event;
use swentel\nostr\Sign\Sign;
use swentel\nostr\Relay\Relay;
use swentel\nostr\Message\EventMessage;

/**
 * Logging (nur wenn WP_DEBUG true ist)
 */
function nap_log(string $msg): void {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[NAP] ' . $msg);
    }
}

/**
 * Optionen lesen (mit Defaults)
 */
function nap_get_options(): array {
    $defaults = [
        'private_key' => '',
        'relays'      => "wss://relay.nostr.band\nwss://nos.lol",
        'timeout'     => 3, // Sekunden pro Relay (bewusst kurz)
    ];
    $opts = get_option(NAP_OPTION_NAME, []);
    return wp_parse_args($opts, $defaults);
}

/**
 * Privkey auflösen (Konstante > Option > Filter)
 * Rückgabe: string|false
 */
function nap_resolve_private_key() {
    if (defined('NAP_NOSTR_PRIVKEY') && NAP_NOSTR_PRIVKEY) {
        return NAP_NOSTR_PRIVKEY;
    }
    $opts = nap_get_options();
    $key  = trim((string)($opts['private_key'] ?? ''));
    if ($key !== '') return $key;

    // externe Quellen erlauben
    $key = apply_filters('nap_nostr_private_key', '');
    return $key ? $key : false;
}

/**
 * Relays parsen & validieren
 */
function nap_get_relays(): array {
    $opts   = nap_get_options();
    $lines  = preg_split('/\r\n|\r|\n/', (string)$opts['relays']);
    $relays = array_values(array_filter(array_map(function($r) {
        $r = trim($r);
        if ($r === '') return null;
        if (!preg_match('#^wss?://#i', $r)) return null;
        return $r;
    }, $lines)));

    $relays = apply_filters('nap_nostr_relays', $relays);
    return array_unique($relays);
}

/**
 * Minimalschlanker Cleaner:
 * - Entities -> echte Zeichen (ENT_HTML5, UTF-8)
 * - NBSP (0xC2 0xA0) -> normales Leerzeichen
 * - Tags raus
 * - Mehrfachspaces glätten + trim
 */
function nap_clean_text(string $s): string {
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = str_replace("\xC2\xA0", ' ', $s);              // NBSP -> Space
    $s = wp_strip_all_tags($s);                         // Tags entfernen
    $s = trim(preg_replace('/[ \t]+/u', ' ', $s));      // Mehrfachspaces glätten
    return $s;
}

/**
 * Caption bauen (filterbar) – jetzt mit Clean-Schritt gegen &nbsp; &amp; etc.
 */
function nap_build_caption(int $product_id): string {
    // Titel + Inhalt cleanen (Entities raus, NBSP -> Space, Tags weg)
    $raw_title   = get_the_title($product_id);
    $title       = nap_clean_text((string)$raw_title);

    $raw_content = get_post_field('post_content', $product_id);
    $content     = nap_clean_text((string)$raw_content);

    // Kurztext (50 Worte)
    $excerpt = wp_trim_words($content, 50, '...');

    // Permalink
    $link = get_permalink($product_id);

    // Titelbild (Markdown)
    $img = get_the_post_thumbnail_url($product_id, 'full');

    // Preis (wc_price liefert HTML -> erst strip_tags, dann clean)
    if (function_exists('wc_get_product')) {
        $p = wc_get_product($product_id);
        if ($p && $p->get_price() !== '') {
            $price_html = strip_tags(wc_price($p->get_price()));
            $price_text = nap_clean_text($price_html);
            $excerpt   .= "\nPreis: " . $price_text;
        }
    }

    // Bild ganz oben (Markdown) + Text + Link
    $imgPart = $img ? "![{$title}]({$img})\n\n" : '';
    $caption = "{$imgPart}{$title}\n\n{$excerpt}\n\n👉 {$link}";
 
    // --- Hashtag anhängen ---
    $caption .= "\n\n#satoshiskleinanzeigen";

    return apply_filters('nap_nostr_caption', $caption, $product_id);
}

/**
 * SETTINGS-SEITE
 */
add_action('admin_menu', function() {
    add_options_page(
        'Nostr Auto Poster',
        'Nostr Auto Poster',
        'manage_options',
        'nap-nostr-settings',
        'nap_render_settings_page'
    );
});

add_action('admin_init', function() {
    register_setting(NAP_OPTION_GROUP, NAP_OPTION_NAME, [
        'type'              => 'array',
        'sanitize_callback' => 'nap_sanitize_options',
    ]);

    add_settings_section(
        'nap_main',
        'Allgemeine Einstellungen',
        function() {
            echo '<p>Privkey, Relay-Liste und Timeout. Tipp: Privkey besser in <code>wp-config.php</code> via <code>define(\'NAP_NOSTR_PRIVKEY\', \'…\');</code>.</p>';
        },
        'nap-nostr-settings'
    );

    add_settings_field('private_key','Privater Schlüssel (Hex)','nap_field_private_key','nap-nostr-settings','nap_main');
    add_settings_field('relays','Relays (eine URL pro Zeile)','nap_field_relays','nap-nostr-settings','nap_main');
    add_settings_field('timeout','Timeout pro Relay (Sek.)','nap_field_timeout','nap-nostr-settings','nap_main');
});

function nap_sanitize_options($input) {
    $out = nap_get_options();

    if (isset($input['private_key'])) {
        $key = trim((string)$input['private_key']);
        if ($key === '' || preg_match('/^[0-9a-fA-F]{64}$/', $key)) {
            $out['private_key'] = $key;
        }
    }
    if (isset($input['relays'])) {
        $out['relays'] = str_replace("\r\n", "\n", (string)$input['relays']);
    }
    if (isset($input['timeout'])) {
        $t = (int)$input['timeout'];
        if ($t < 1)  $t = 1;
        if ($t > 10) $t = 10; // obere Kappe, damit Requests nicht ewig hängen
        $out['timeout'] = $t;
    }
    return $out;
}

function nap_field_private_key() {
    $opts = nap_get_options();
    $in_cfg = defined('NAP_NOSTR_PRIVKEY') && NAP_NOSTR_PRIVKEY;
    ?>
    <input type="password" name="<?php echo esc_attr(NAP_OPTION_NAME); ?>[private_key]"
           value="<?php echo esc_attr($in_cfg ? '********' : ($opts['private_key'] ?? '')); ?>"
           class="regular-text" placeholder="64-stelliger Hex-Schlüssel" <?php disabled($in_cfg, true); ?> />
    <?php if ($in_cfg): ?>
        <p class="description">Privkey ist per <code>NAP_NOSTR_PRIVKEY</code> in <code>wp-config.php</code> gesetzt.</p>
    <?php else: ?>
        <p class="description">Hinweis: In der DB gespeichert. In <code>wp-config.php</code> ist sicherer.</p>
    <?php endif;
}

function nap_field_relays() {
    $opts   = nap_get_options();
    $relays = (string)$opts['relays'];
    ?>
    <textarea name="<?php echo esc_attr(NAP_OPTION_NAME); ?>[relays]" rows="6" cols="60" class="large-text code"
              placeholder="wss://relay.nostr.band&#10;wss://nos.lol"><?php
        echo esc_textarea($relays);
    ?></textarea>
    <p class="description">Eine URL pro Zeile. Nur <code>wss://</code> oder <code>ws://</code>.</p>
    <?php
}

function nap_field_timeout() {
    $opts = nap_get_options();
    ?>
    <input type="number" min="1" max="10" name="<?php echo esc_attr(NAP_OPTION_NAME); ?>[timeout]"
           value="<?php echo esc_attr((int)$opts['timeout']); ?>" />
    <p class="description">Sekunden pro Relay (1–10). Standard 3.</p>
    <?php
}

function nap_render_settings_page() {
    if (!current_user_can('manage_options')) return;
    ?>
    <div class="wrap">
        <h1>Nostr Auto Poster – Einstellungen</h1>
        <form method="post" action="options.php">
            <?php
                settings_fields(NAP_OPTION_GROUP);
                do_settings_sections('nap-nostr-settings');
                submit_button();
            ?>
        </form>
    </div>
    <?php
}

/**
 * HAUPT-HOOK: auf sk_new_product_added
 * - Fires AFTER all product meta (price, image, categories) is saved
 * - Actual relay sending is deferred to PHP shutdown so the page responds instantly
 * - Nur wenn noch kein Nostr-Event fuer das Produkt existiert (Duplikatschutz)
 */
global $_nap_shutdown_queue;
$_nap_shutdown_queue = [];

add_action('sk_new_product_added', function($post_id, $postdata) {
    global $_nap_shutdown_queue;

    $post = get_post($post_id);
    nap_log(sprintf('sk_new_product_added: id=%d, status=%s', $post_id, $post ? $post->post_status : 'null'));

    if (!$post || $post->post_type !== 'product' || $post->post_status !== 'publish') {
        return;
    }

    // Vendor must have public contact info (fail-closed: skip if check unavailable)
    if ( ! function_exists( 'sk_vendor_has_public_contact' ) || ! sk_vendor_has_public_contact( (int) $post->post_author ) ) {
        nap_log(sprintf('Abbruch #%d: Vendor hat keine öffentlichen Kontaktdaten.', $post_id));
        return;
    }

    // Bereits gesendet?
    if (get_post_meta($post_id, NAP_META_EVENT_ID, true)) {
        nap_log('Abbruch: bereits ein Nostr-Event vorhanden.');
        return;
    }

    $pid = (int) $post_id;
    if ( ! in_array( $pid, $_nap_shutdown_queue, true ) ) {
        $_nap_shutdown_queue[] = $pid;
        nap_log(sprintf('Queued for shutdown send #%d', $pid));
    }
}, 10, 2);

/** Catch draft/pending → publish transitions (WC Admin or manual status change) */
add_action('transition_post_status', function($new_status, $old_status, $post) {
    if ($new_status !== 'publish' || $new_status === $old_status) return;
    if (!$post || $post->post_type !== 'product') return;
    if (get_post_meta($post->ID, NAP_META_EVENT_ID, true)) return;

    // Vendor must have public contact info (fail-closed: skip if check unavailable)
    if ( ! function_exists( 'sk_vendor_has_public_contact' ) || ! sk_vendor_has_public_contact( (int) $post->post_author ) ) {
        nap_log(sprintf('transition_post_status: SKIP #%d — Vendor hat keine öffentlichen Kontaktdaten.', $post->ID));
        return;
    }

    global $_nap_shutdown_queue;
    $pid = (int) $post->ID;
    if ( ! in_array( $pid, $_nap_shutdown_queue, true ) ) {
        $_nap_shutdown_queue[] = $pid;
        nap_log(sprintf('transition_post_status: id=%d (%s → publish), queued for shutdown', $pid, $old_status));
    }
}, 10, 3);

/** Shutdown handler: send Nostr events after response is delivered */
register_shutdown_function(function() {
    global $_nap_shutdown_queue;
    if (empty($_nap_shutdown_queue)) return;

    // Flush response to browser
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }

    if (!class_exists(Event::class)) {
        nap_log('Nostr library (swentel/nostr) nicht gefunden.');
        return;
    }

    $privkey = nap_resolve_private_key();
    if (!$privkey) {
        nap_log('Privater Schluessel nicht gesetzt. Abbruch.');
        return;
    }

    $relays = nap_get_relays();
    if (empty($relays)) {
        nap_log('Keine Relays konfiguriert. Abbruch.');
        return;
    }

    foreach ($_nap_shutdown_queue as $post_id) {
        // Double-check not already sent (race condition guard)
        if (get_post_meta($post_id, NAP_META_EVENT_ID, true)) {
            continue;
        }

        // Queued while it was publish — keyword review or an admin may have
        // pulled it since. Events on relays cannot be taken back, so this check
        // matters more here than anywhere else.
        if (get_post_status($post_id) !== 'publish') {
            nap_log(sprintf('SHUTDOWN SKIP #%d — nicht mehr veroeffentlicht.', $post_id));
            continue;
        }

        try {
            $note = new Event();
            $note->setKind(1);
            $note->setContent(nap_build_caption($post_id));
            foreach ($relays as $r) {
                $note->addTag(['r', $r]);
            }
            $signer = new Sign();
            $signer->signEvent($note, $privkey);
        } catch (\Throwable $e) {
            nap_log('Fehler beim Erstellen/Signieren des Events: ' . $e->getMessage());
            continue;
        }

        // Senden (reduced timeout: 1s per relay as safety net)
        $opts     = nap_get_options();
        $timeout  = min((int)($opts['timeout'] ?? 1), 3);
        if ($timeout < 1) $timeout = 1;

        $eventId  = $note->getId();
        $sent_any = false;

        foreach ($relays as $relayUrl) {
            try {
                $eventMessage = new EventMessage($note);
                $relay        = new Relay($relayUrl);
                if (method_exists($relay, 'setTimeout')) {
                    $relay->setTimeout($timeout);
                }
                $relay->setMessage($eventMessage);
                $response = $relay->send();

                if ($response !== false) {
                    $sent_any = true;
                    nap_log(sprintf('Event %s an Relay %s gesendet.', $eventId, $relayUrl));
                } else {
                    nap_log(sprintf('Relay %s antwortete nicht auf Event %s.', $relayUrl, $eventId));
                }
            } catch (\Throwable $e) {
                nap_log(sprintf('Fehler beim Senden an %s: %s', $relayUrl, $e->getMessage()));
            }
        }

        if ($sent_any) {
            update_post_meta($post_id, NAP_META_EVENT_ID, $eventId);
            update_post_meta($post_id, '_nap_nostr_relays', $relays);
        } else {
            nap_log(sprintf('Kein Relay akzeptierte das Event %s. Meta wird NICHT gesetzt.', $eventId));
        }
    }
});

/**
 * Force-send a single product to Nostr (used by admin metabox).
 *
 * @param int $post_id
 * @return bool
 */
function nap_force_send_product( int $post_id ): bool {
    if (!class_exists(Event::class)) {
        nap_log('Force send: Nostr library nicht gefunden.');
        return false;
    }

    $privkey = nap_resolve_private_key();
    if (!$privkey) {
        nap_log('Force send: Privater Schluessel nicht gesetzt.');
        return false;
    }

    $relays = nap_get_relays();
    if (empty($relays)) {
        nap_log('Force send: Keine Relays konfiguriert.');
        return false;
    }

    try {
        $note = new Event();
        $note->setKind(1);
        $note->setContent(nap_build_caption($post_id));
        foreach ($relays as $r) {
            $note->addTag(['r', $r]);
        }
        $signer = new Sign();
        $signer->signEvent($note, $privkey);
    } catch (\Throwable $e) {
        nap_log('Force send: Fehler beim Erstellen/Signieren: ' . $e->getMessage());
        return false;
    }

    $opts    = nap_get_options();
    $timeout = min((int)($opts['timeout'] ?? 1), 3);
    if ($timeout < 1) $timeout = 1;

    $eventId  = $note->getId();
    $sent_any = false;

    foreach ($relays as $relayUrl) {
        try {
            $eventMessage = new EventMessage($note);
            $relay        = new Relay($relayUrl);
            if (method_exists($relay, 'setTimeout')) {
                $relay->setTimeout($timeout);
            }
            $relay->setMessage($eventMessage);
            $response = $relay->send();

            if ($response !== false) {
                $sent_any = true;
                nap_log(sprintf('Force send: Event %s an Relay %s gesendet.', $eventId, $relayUrl));
            }
        } catch (\Throwable $e) {
            nap_log(sprintf('Force send: Fehler an %s: %s', $relayUrl, $e->getMessage()));
        }
    }

    if ($sent_any) {
        update_post_meta($post_id, NAP_META_EVENT_ID, $eventId);
        update_post_meta($post_id, '_nap_nostr_relays', $relays);
    }

    return $sent_any;
}

// Meta-Box mit "Jetzt an Nostr senden"-Button
add_action('add_meta_boxes', function(){
    add_meta_box('nap_resend_box', 'Nostr', function($post){
        if ($post->post_type !== 'product') return;
        $event_id = get_post_meta($post->ID, NAP_META_EVENT_ID, true);
        $url = wp_nonce_url(
            admin_url('admin-post.php?action=nap_force_resend&post_id='.(int)$post->ID),
            'nap_force_resend_nonce_'.$post->ID
        );
        echo '<p><a href="'.esc_url($url).'" class="button button-primary">Jetzt an Nostr senden</a></p>';
        if ($event_id) {
            echo '<p style="color:#666">Letztes Event: <code style="font-size:11px;">'.esc_html(substr($event_id, 0, 16)).'...</code></p>';
        }
        echo '<p style="color:#666">Sendet sofort (bestehendes Event-Meta wird zurückgesetzt).</p>';
    }, 'product', 'side', 'default');
});

add_action('admin_notices', function(){
    if (!isset($_GET['nap_force_resend'])) return;
    $ok = $_GET['nap_force_resend'] === '1';
    echo '<div class="notice '.($ok?'notice-success':'notice-error').' is-dismissible"><p>'
        .($ok ? 'Nostr: erfolgreich gesendet.' : 'Nostr: Senden fehlgeschlagen (siehe debug.log).')
        .'</p></div>';
});

add_action('admin_post_nap_force_resend', function(){
    nap_log('admin_post_nap_force_resend: ENTER');

    $post_id = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;
    if (!$post_id) wp_die('Ungültige ID.');
    if (!current_user_can('edit_post', $post_id)) wp_die('Keine Berechtigung.');

    $nonce_ok = isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'nap_force_resend_nonce_'.$post_id);
    if (!$nonce_ok) wp_die('Sicherheitscheck fehlgeschlagen.');

    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'product') wp_die('Kein Produkt.');
    if ($post->post_status !== 'publish') wp_die('Produkt ist nicht veröffentlicht.');

    // Reset meta
    delete_post_meta($post_id, NAP_META_EVENT_ID);
    delete_post_meta($post_id, '_nap_nostr_relays');
    delete_post_meta($post_id, '_nap_sent_relays');

    // Send
    $ok = nap_force_send_product($post_id);
    nap_log('admin_post_nap_force_resend: result=' . ($ok ? 'OK' : 'FAIL'));

    $redirect = add_query_arg(['nap_force_resend' => $ok ? '1' : '0'], get_edit_post_link($post_id, ''));
    wp_safe_redirect($redirect);
    exit;
});
