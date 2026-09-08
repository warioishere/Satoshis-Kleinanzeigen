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

if (!defined('NAP_OPTION_NAME'))  define('NAP_OPTION_NAME',  'nap_nostr_options');
if (!defined('NAP_META_EVENT_ID')) define('NAP_META_EVENT_ID','_nap_nostr_event_id');

use swentel\nostr\Event\Event;
use SK\Modules\Auth\RelayPublisher;

/**
 * Logging (only when WP_DEBUG is true)
 */
function nap_log(string $msg): void {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[NAP] ' . $msg);
    }
}

/**
 * Send a signed event to the relays and log per relay.
 *
 * @return bool True if at least one relay accepted it.
 */
/**
 * A kind 1 note with one r tag per relay, signed with the marketplace key.
 *
 * @throws \RuntimeException When the key is unusable.
 */
function nap_sign_note(string $caption, array $relays, string $privkey): Event {
    $tags = array_map(static fn($r) => ['r', $r], array_values($relays));

    return \SK\Core\Nostr\Events::to_object(\SK\Core\Nostr\Events::sign(1, $caption, $tags, $privkey));
}

function nap_publish(Event $note, array $relays, string $prefix = ''): bool {
    if (!class_exists(RelayPublisher::class)) {
        nap_log($prefix . 'RelayPublisher (sk_auth) not loaded.');
        return false;
    }

    $result = RelayPublisher::publish($note, $relays);

    foreach ($result['accepted'] as $url) {
        nap_log(sprintf('%sEvent %s sent to relay %s.', $prefix, $note->getId(), $url));
    }
    foreach ($result['rejected'] as $url => $reason) {
        nap_log(sprintf('%sRelay %s rejected event %s: %s', $prefix, $url, $note->getId(), $reason));
    }

    return !empty($result['accepted']);
}

/**
 * Read options (with defaults). Only the private key is still read from
 * here; relays and the switch live in the Nostr section of the SK settings.
 */
function nap_get_options(): array {
    $defaults = [
        'private_key' => '',
    ];
    $opts = get_option(NAP_OPTION_NAME, []);
    return wp_parse_args(is_array($opts) ? $opts : [], $defaults);
}

/**
 * Resolve the private key (constant > option > filter).
 * Returns: string|false
 */
function nap_resolve_private_key() {
    if (defined('NAP_NOSTR_PRIVKEY') && NAP_NOSTR_PRIVKEY) {
        return NAP_NOSTR_PRIVKEY;
    }
    $opts = nap_get_options();
    $key  = trim((string)($opts['private_key'] ?? ''));
    if ($key !== '') return $key;

    // allow external sources
    $key = apply_filters('nap_nostr_private_key', '');
    return $key ? $key : false;
}

/**
 * Relays: the one list from the Nostr section of the SK settings.
 */
function nap_get_relays(): array {
    $relays = class_exists('SK\Modules\Auth\NostrIdentity')
        ? \SK\Modules\Auth\NostrIdentity::get_relays()
        : [];

    $relays = apply_filters('nap_nostr_relays', $relays);
    return array_values(array_unique($relays));
}

/**
 * Minimal, lightweight cleaner:
 * - Entities -> real characters (ENT_HTML5, UTF-8)
 * - NBSP (0xC2 0xA0) -> normal space
 * - Strip tags
 * - Collapse repeated spaces + trim
 */
function nap_clean_text(string $s): string {
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = str_replace("\xC2\xA0", ' ', $s);              // NBSP -> Space
    $s = wp_strip_all_tags($s);                         // strip tags
    $s = trim(preg_replace('/[ \t]+/u', ' ', $s));      // collapse repeated spaces
    return $s;
}

/**
 * Build the caption (filterable) – now with a clean step against &nbsp; &amp; etc.
 */
function nap_build_caption(int $product_id): string {
    // Clean title + content (strip entities, NBSP -> space, strip tags)
    $raw_title   = get_the_title($product_id);
    $title       = nap_clean_text((string)$raw_title);

    $raw_content = get_post_field('post_content', $product_id);
    $content     = nap_clean_text((string)$raw_content);

    // Short text (50 words)
    $excerpt = wp_trim_words($content, 50, '...');

    // Permalink
    $link = get_permalink($product_id);

    // Featured image
    $img = get_the_post_thumbnail_url($product_id, 'full');

    // Price (wc_price returns HTML -> strip_tags first, then clean)
    if (function_exists('wc_get_product')) {
        $p = wc_get_product($product_id);
        if ($p && $p->get_price() !== '') {
            $price_html = strip_tags(wc_price($p->get_price()));
            $price_text = nap_clean_text($price_html);
            $excerpt   .= "\nPreis: " . $price_text;
        }
    }

    /*
     * Image as a bare URL, not as Markdown.
     *
     * A note (kind 1) is plain text; Markdown isn't part of any spec.
     * Clients recognize an image URL on their own and display it — with
     * "![Title](url)" they did exactly that with the part in parentheses
     * and left "![Title](" and ")" behind as garbled characters.
     *
     * On its own line, so the URL ends cleanly and doesn't run into the
     * title after it.
     */
    $imgPart = $img ? "{$img}\n\n" : '';
    $caption = "{$imgPart}{$title}\n\n{$excerpt}\n\n👉 {$link}";
 
    // --- Append hashtag ---
    $caption .= "\n\n#satoshiskleinanzeigen";

    return apply_filters('nap_nostr_caption', $caption, $product_id);
}

/*
 * Settings live in the Nostr section of the SK settings (sk-auth,
 * NostrSettings): relays, the private key when it is not in wp-config.php,
 * and the on/off switch.
 */

/**
 * MAIN HOOK: on sk_new_product_added
 * - Fires AFTER all product meta (price, image, categories) is saved
 * - Actual relay sending is deferred to PHP shutdown so the page responds instantly
 * - Only when no Nostr event exists yet for the product (duplicate protection)
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

    // Already sent?
    if (get_post_meta($post_id, NAP_META_EVENT_ID, true)) {
        nap_log('Aborting: a Nostr event already exists.');
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
        nap_log('Nostr library (swentel/nostr) not found.');
        return;
    }

    $privkey = nap_resolve_private_key();
    if (!$privkey) {
        nap_log('Private key not set. Aborting.');
        return;
    }

    $relays = nap_get_relays();
    if (empty($relays)) {
        nap_log('No relays configured. Aborting.');
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
            nap_log(sprintf('SHUTDOWN SKIP #%d — no longer published.', $post_id));
            continue;
        }

        try {
            $note = nap_sign_note(nap_build_caption($post_id), $relays, $privkey);
        } catch (\Throwable $e) {
            nap_log('Error creating/signing the event: ' . $e->getMessage());
            continue;
        }

        $eventId  = $note->getId();
        $sent_any = nap_publish($note, $relays);

        if ($sent_any) {
            update_post_meta($post_id, NAP_META_EVENT_ID, $eventId);
            update_post_meta($post_id, '_nap_nostr_relays', $relays);
        } else {
            nap_log(sprintf('No relay accepted the event %s. Meta is NOT set.', $eventId));
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
        nap_log('Force send: Nostr library not found.');
        return false;
    }

    $privkey = nap_resolve_private_key();
    if (!$privkey) {
        nap_log('Force send: private key not set.');
        return false;
    }

    $relays = nap_get_relays();
    if (empty($relays)) {
        nap_log('Force send: no relays configured.');
        return false;
    }

    try {
        $note = nap_sign_note(nap_build_caption($post_id), $relays, $privkey);
    } catch (\Throwable $e) {
        nap_log('Force send: error creating/signing: ' . $e->getMessage());
        return false;
    }

    $eventId  = $note->getId();
    $sent_any = nap_publish($note, $relays, 'Force send: ');

    if ($sent_any) {
        update_post_meta($post_id, NAP_META_EVENT_ID, $eventId);
        update_post_meta($post_id, '_nap_nostr_relays', $relays);
    }

    return $sent_any;
}

// Meta box with a "Post to Nostr now" button
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
