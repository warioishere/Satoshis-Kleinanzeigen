<?php
/**
 * Telegram Notification.
 *
 * Sends new product listings to Telegram, updates on changes,
 * deletes/marks unavailable on unpublish.
 * Originally a standalone plugin by Wario, now part of sk-notifications module.
 */

// =========================
// == Grundeinstellungen  ==
// =========================

if (!defined('TELEGRAM_REPOST_ON_REPUBLISH')) {
    define('TELEGRAM_REPOST_ON_REPUBLISH', false);
}

function tn_clean_text(string $s): string {
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8'); // &amp; &quot; &#8211; …
    $s = str_replace("\xC2\xA0", ' ', $s);                        // NBSP -> Space
    return $s;
}

function tn_string_ends_with(string $haystack, string $needle): bool {
    if (function_exists('str_ends_with')) {
        return str_ends_with($haystack, $needle);
    }

    if ($needle === '') {
        return true;
    }

    return substr($haystack, -strlen($needle)) === $needle;
}

/**
 * Convert AVIF image to JPG for Telegram compatibility
 * Returns the JPG URL (either converted or fallback)
 */
function tn_ensure_telegram_compatible_image($image_url) {
    if (!$image_url || strpos($image_url, '.avif') === false) {
        return $image_url; // Not AVIF, return as-is
    }

    error_log('[TG] tn_ensure_telegram_compatible_image: converting AVIF to JPG');

    // Try to use Imagick first (more reliable)
    if (extension_loaded('imagick')) {
        try {
            // Download the AVIF image
            $response = wp_remote_get($image_url, array('timeout' => 10));
            if (is_wp_error($response)) {
                error_log('[TG] tn_ensure_telegram_compatible_image: failed to download AVIF: ' . $response->get_error_message());
                return $image_url;
            }

            $image_data = wp_remote_retrieve_body($response);
            if (empty($image_data)) {
                error_log('[TG] tn_ensure_telegram_compatible_image: empty response body');
                return $image_url;
            }

            // Convert using Imagick
            $imagick = new Imagick();
            $imagick->readImageBlob($image_data);
            $imagick->setImageFormat('jpeg');
            $imagick->setImageCompressionQuality(85);

            // Create temporary file
            $tmp_dir = wp_upload_dir()['basedir'] . '/tmp-telegram';
            if (!is_dir($tmp_dir)) {
                wp_mkdir_p($tmp_dir);
            }

            $tmp_file = $tmp_dir . '/' . md5($image_url) . '.jpg';
            $imagick->writeImage($tmp_file);
            $imagick->clear();

            // Return temporary file URL
            $upload_dir = wp_upload_dir();
            $tmp_url = $upload_dir['baseurl'] . '/tmp-telegram/' . md5($image_url) . '.jpg';
            error_log('[TG] tn_ensure_telegram_compatible_image: converted to JPG at ' . $tmp_url);
            return $tmp_url;
        } catch (Exception $e) {
            error_log('[TG] tn_ensure_telegram_compatible_image: Imagick conversion failed: ' . $e->getMessage());
        }
    }

    // Fallback: try GD
    if (extension_loaded('gd')) {
        try {
            $response = wp_remote_get($image_url, array('timeout' => 10));
            if (is_wp_error($response)) {
                error_log('[TG] tn_ensure_telegram_compatible_image: GD fallback - failed to download AVIF');
                return $image_url;
            }

            $image_data = wp_remote_retrieve_body($response);
            if (empty($image_data)) {
                error_log('[TG] tn_ensure_telegram_compatible_image: GD fallback - empty response');
                return $image_url;
            }

            // Try to create image from string
            $image = imagecreatefromstring($image_data);
            if ($image === false) {
                error_log('[TG] tn_ensure_telegram_compatible_image: GD failed to parse AVIF');
                return $image_url;
            }

            // Create temporary JPG
            $tmp_dir = wp_upload_dir()['basedir'] . '/tmp-telegram';
            if (!is_dir($tmp_dir)) {
                wp_mkdir_p($tmp_dir);
            }

            $tmp_file = $tmp_dir . '/' . md5($image_url) . '.jpg';
            imagejpeg($image, $tmp_file, 85);
            imagedestroy($image);

            // Return temporary file URL
            $upload_dir = wp_upload_dir();
            $tmp_url = $upload_dir['baseurl'] . '/tmp-telegram/' . md5($image_url) . '.jpg';
            error_log('[TG] tn_ensure_telegram_compatible_image: GD converted to JPG at ' . $tmp_url);
            return $tmp_url;
        } catch (Exception $e) {
            error_log('[TG] tn_ensure_telegram_compatible_image: GD conversion failed: ' . $e->getMessage());
        }
    }

    error_log('[TG] tn_ensure_telegram_compatible_image: no image library available, returning original AVIF');
    return $image_url; // Return original if conversion fails
}

// =====================
// == Admin-Einstellungen
// =====================
function telegram_notification_settings_menu() {
    add_options_page(
        'Telegram Notification Einstellungen',
        'Telegram Notification',
        'manage_options',
        'telegram-notification',
        'telegram_notification_settings_page'
    );
}
add_action('admin_menu', 'telegram_notification_settings_menu');

function telegram_notification_settings_page() {
    ?>
    <div class="wrap">
        <h1>Telegram Notification Einstellungen</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('telegram_notification_settings');
            do_settings_sections('telegram-notification');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

function telegram_notification_register_settings() {
    register_setting('telegram_notification_settings', 'telegram_bot_token');
    register_setting('telegram_notification_settings', 'telegram_chat_id');

    add_settings_section('telegram_notification_section', 'API-Einstellungen', null, 'telegram-notification');

    add_settings_field('telegram_bot_token', 'Bot Token', function () {
        $value = esc_attr(get_option('telegram_bot_token'));
        echo "<input type='text' name='telegram_bot_token' value='$value' class='regular-text' />";
    }, 'telegram-notification', 'telegram_notification_section');

    add_settings_field('telegram_chat_id', 'Chat ID oder Kanalname (@beispiel)', function () {
        $value = esc_attr(get_option('telegram_chat_id'));
        echo "<input type='text' name='telegram_chat_id' value='$value' class='regular-text' />";
    }, 'telegram-notification', 'telegram_notification_section');
}
add_action('admin_init', 'telegram_notification_register_settings');

// =====================
// == Helper / Builder ==
// =====================

/**
 * Escape for Telegram parse_mode "HTML".
 * Only &, <, > must be escaped (in that order) — everything else is allowed raw.
 * Do NOT use esc_html(): it also encodes quotes, which Telegram renders literally.
 */
function tn_tg_html_esc($text) {
    $text = (string) $text;
    $text = str_replace('&', '&amp;', $text);
    $text = str_replace('<', '&lt;', $text);
    $text = str_replace('>', '&gt;', $text);
    return $text;
}

/**
 * Baue Caption & Bilddaten für Telegram.
 * KEINE "Mehr Bilder..."-Zeile und KEIN Pfeil/Permalink im Caption-Text.
 * Der Link hängt am Ende des Beitrags.
 */
function telegram_build_caption_and_media($post_id) {
    error_log('[TG] telegram_build_caption_and_media: ENTER post_id=' . $post_id);
    $title_raw    = get_the_title($post_id);
    $title_clean  = tn_clean_text((string)$title_raw);     // Entities weg + NBSP -> Space
    $title        = tn_tg_html_esc($title_clean);          // HTML-escape, asterisks bleiben literal
    error_log('[TG] telegram_build_caption_and_media: title_raw=' . substr($title_raw, 0, 100) . ' title_clean=' . substr($title_clean, 0, 100));

    // HTML-Entities (z. B. &nbsp; &amp; &quot;) entfernen und NBSP in normale Leerzeichen wandeln
    $full_desc_raw = get_post_field('post_content', $post_id);
    $full_desc_raw = html_entity_decode($full_desc_raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $full_desc_raw = str_replace("\xC2\xA0", ' ', $full_desc_raw); // NBSP -> Space
    $full_desc_raw = wp_strip_all_tags($full_desc_raw);
    $full_desc_raw = str_replace(array("\r\n", "\r"), "\n", $full_desc_raw);
    $full_desc_raw = preg_replace('/\n{3,}/', "\n\n", $full_desc_raw);
    $full_desc_raw = preg_replace('/\A(?:\s*\n)+/', '', $full_desc_raw);
    $full_desc_raw = preg_replace('/(?:\n\s*)+\z/', '', $full_desc_raw);

    // Truncate on the raw text first, then escape — escaping first could split an
    // entity like &amp; mid-sequence and trigger a Telegram HTTP 400.
    $short_desc    = mb_substr($full_desc_raw, 0, 500) . (mb_strlen($full_desc_raw) > 500 ? '...' : '');
    $short_desc    = tn_tg_html_esc($short_desc);

    $vendor_id  = get_post_field('post_author', $post_id);
    $store_info = function_exists('sk_get_store_info') ? sk_get_store_info($vendor_id) : array();
    $user       = get_user_by('id', $vendor_id);

    $price_label = 'Preis:';
    $price_raw   = get_post_meta($post_id, '_regular_price', true);

    if (function_exists('wc_get_product')) {
        $product = wc_get_product($post_id);
        if ($product && is_object($product) && is_a($product, 'WC_Product')) {
            $sale_price    = $product->get_sale_price();
            $regular_price = $product->get_regular_price();

            if ($product->is_on_sale() && $sale_price !== '' && is_numeric($sale_price)) {
                $price_label = 'Reduzierter Preis:';
                $price_raw   = $sale_price;
            } else {
                $price_raw = $regular_price !== '' ? $regular_price : $product->get_price();
            }
        }
    } else {
        $sale_meta = get_post_meta($post_id, '_sale_price', true);
        if ($sale_meta !== '' && is_numeric($sale_meta) && is_numeric($price_raw) && (float)$sale_meta < (float)$price_raw) {
            $price_label = 'Reduzierter Preis:';
            $price_raw   = $sale_meta;
        }
    }

    $price_str = (is_numeric($price_raw)) ? number_format((float)$price_raw, 0, '.', "'") . ' Sats' : 'Preis nicht verfügbar';

    $shipping_note_raw   = get_post_meta($post_id, '_p2p_shipping_note', true);
    $shipping_note_clean = tn_clean_text((string)$shipping_note_raw); // Entities & NBSP weg
    $shipping_note       = tn_tg_html_esc($shipping_note_clean);
    $shipping_str        = !empty($shipping_note) ? "\n\n<b>Versand: $shipping_note</b>" : '';

    $extra_info = '';

    /*
     * Keine Kontaktdaten in den Kanal.
     *
     * Hier standen E-Mail-Adresse, Telefonnummer, Nostr-Schluessel, X-Handle
     * und Telegram-Name im Klartext — dauerhaft und oeffentlich lesbar, damit
     * abgreifbar, und an der Seite vorbei. Wer Kontakt aufnehmen will, findet
     * die Angaben auf dem Inserat, wo sie den Regeln des Anbieters
     * unterliegen und wo der Klick auch gezaehlt wird. Ein Hinweis darauf
     * eruebrigt sich — der Link zum Inserat steht ohnehin darunter.
     */

    // End of feewall check

    $permalink = get_permalink($post_id);
    $caption = "🛒 <b>$title</b>\n\n<b>$price_label $price_str</b>$shipping_str\n\n$short_desc";
    if ( trim( $extra_info ) !== '' ) {
        $caption .= "\n\n$extra_info";
    }
    if ($permalink) {
        /*
         * Beide Wege, nicht einer. Der Grundlink aufs Inserat ist der, den
         * die meisten wollen — sie schauen erst, bevor sie schreiben.
         * Darunter der Direkteinstieg ins Anschreiben: #chat oeffnet auf der
         * Inseratsseite das Chatfenster, als haette der Besucher auf das
         * Symbol geklickt. Als Fragment und nicht als Abfrageparameter,
         * damit die Seite weiterhin aus dem Cache kommt — der Server sieht
         * das # nie.
         */
        $caption .= "\n\n👉 Zum Inserat: " . tn_tg_html_esc($permalink);
        $caption .= "\n💬 Direkt anschreiben: " . tn_tg_html_esc($permalink . '#chat');
    }

    $image_id  = get_post_thumbnail_id($post_id);
    $image_url = $image_id ? wp_get_attachment_url($image_id) : null;

    error_log('[TG] telegram_build_caption_and_media: final caption_length=' . strlen($caption) . ' image_id=' . ($image_id ?: 'null') . ' image_url=' . ($image_url ?: 'null'));

    return array(
        'caption'   => $caption,
        'image_id'  => $image_id,
        'image_url' => $image_url,
    );
}

/** Telegram API Wrapper mit sanftem Retry */
function telegram_api_post($endpoint, $body) {
    $attempts = 0;
    $delay = 0.5; // Sekunden

    // Logging: endpoint und body type
    error_log('[TG] telegram_api_post: endpoint=' . $endpoint);
    error_log('[TG] telegram_api_post: body type=' . gettype($body) . ' size=' . strlen(json_encode($body)));

    do {
        // Log attempt info
        if ($attempts > 0) {
            error_log('[TG] telegram_api_post: retry attempt ' . $attempts . ' after delay ' . $delay . 's');
        }

        $resp = wp_remote_post($endpoint, array(
            'method'  => 'POST',
            'body'    => $body,
            'timeout' => 15,
        ));

        if (is_wp_error($resp)) {
            error_log('[TG] telegram_api_post: WP_Error - ' . $resp->get_error_message());
            $attempts++;
            if ($attempts >= 3) return $resp;
        } else {
            $code = wp_remote_retrieve_response_code($resp);
            $body_resp = wp_remote_retrieve_body($resp);
            $headers = wp_remote_retrieve_headers($resp);

            error_log('[TG] telegram_api_post: response code=' . $code);
            error_log('[TG] telegram_api_post: response headers=' . json_encode($headers->getAll()));
            error_log('[TG] telegram_api_post: response body=' . substr($body_resp, 0, 500));

            if ($code == 429 || ($code >= 500 && $code < 600)) {
                usleep((int)($delay * 1000000));
                $delay *= 2;
                $attempts++;
                continue;
            }
            return $resp;
        }
    } while ($attempts < 3);
    return $resp;
}

// =======================
// == Senden & Editieren ==
// =======================

/** Senden und Metadaten speichern */
function telegram_send_message($post_id) {
    error_log('[TG] telegram_send_message: ENTER post_id=' . $post_id);

    // The send happens on shutdown or via cron, long after it was queued. In
    // between the listing may have been pulled — keyword review drafts flagged
    // listings, an admin may have trashed it. Never announce something that is
    // no longer publicly visible.
    $current_status = get_post_status($post_id);
    if ($current_status !== 'publish') {
        error_log('[TG] telegram_send_message: ABORT #' . $post_id . ' is "' . $current_status . '", not publish');
        return false;
    }

    $bot_token = get_option('telegram_bot_token');
    $chat_id   = get_option('telegram_chat_id');
    if (!$bot_token || !$chat_id) {
        error_log('[TG] telegram_send_message: ABORT missing bot_token or chat_id');
        return false;
    }

    $built     = telegram_build_caption_and_media($post_id);
    $caption   = $built['caption'];
    $image_id  = $built['image_id'];
    $image_url = $built['image_url'];

    if ($image_url) {
        $image_url = tn_ensure_telegram_compatible_image($image_url);
    }

    if ($image_url) {
        $payload  = array(
            'chat_id'      => $chat_id,
            'photo'        => $image_url,
            'caption'      => $caption,
            'parse_mode'   => 'HTML',
        );
        $endpoint = "https://api.telegram.org/bot{$bot_token}/sendPhoto";
    } else {
        $payload  = array(
            'chat_id'      => $chat_id,
            'text'         => $caption,
            'parse_mode'   => 'HTML',
        );
        $endpoint = "https://api.telegram.org/bot{$bot_token}/sendMessage";
    }

    $resp = telegram_api_post($endpoint, $payload);
    if (is_wp_error($resp)) {
        error_log('[Telegram] send error: ' . $resp->get_error_message());
        return false;
    }
    $code = wp_remote_retrieve_response_code($resp);
    $body = json_decode(wp_remote_retrieve_body($resp), true);

    if ($code === 200 && isset($body['ok']) && $body['ok']) {
        $mid = $body['result']['message_id'] ?? null;
        if ($mid) update_post_meta($post_id, '_telegram_message_id', $mid);
        update_post_meta($post_id, '_telegram_message_type', $image_url ? 'photo' : 'text');
        if (isset($body['result']['date'])) {
            update_post_meta($post_id, '_telegram_message_date', (int)$body['result']['date']);
        }
        if ($image_id) update_post_meta($post_id, '_telegram_image_sent', (int)$image_id);
        return true;
    } else {
        error_log('[Telegram] send HTTP error: ' . wp_remote_retrieve_body($resp));
        return false;
    }
}

/** Edit je nach Typ und Änderung */
function telegram_edit_message_for_update($post_id) {
    $bot_token = get_option('telegram_bot_token');
    $chat_id   = get_option('telegram_chat_id');
    $msg_id    = get_post_meta($post_id, '_telegram_message_id', true);
    if (!$bot_token || !$chat_id || !$msg_id) return;

    $built     = telegram_build_caption_and_media($post_id);
    $caption   = $built['caption'];
    $image_id  = $built['image_id'];
    $image_url = $built['image_url'];
    $orig_type   = get_post_meta($post_id, '_telegram_message_type', true); // 'photo'|'text'
    $sent_img_id = (int) get_post_meta($post_id, '_telegram_image_sent', true);

    // Convert AVIF to JPG if needed for Telegram compatibility
    if ($image_url) {
        $image_url = tn_ensure_telegram_compatible_image($image_url);
    }

    if ($orig_type === 'photo') {
        if ($image_url) {
            if ($image_id && $image_id !== $sent_img_id) {
                // Bildwechsel -> editMessageMedia
                $endpoint = "https://api.telegram.org/bot{$bot_token}/editMessageMedia";
                $media = array(
                    'type'       => 'photo',
                    'media'      => $image_url,
                    'caption'    => $caption,
                    'parse_mode' => 'HTML',
                );
                $resp = telegram_api_post($endpoint, array(
                    'chat_id'      => $chat_id,
                    'message_id'   => (int)$msg_id,
                    'media'        => wp_json_encode($media),
                ));
                if (!is_wp_error($resp) && wp_remote_retrieve_response_code($resp) === 200) {
                    update_post_meta($post_id, '_telegram_image_sent', (int)$image_id);
                } else {
                    error_log('[Telegram] editMessageMedia failed: ' . (is_wp_error($resp) ? $resp->get_error_message() : wp_remote_retrieve_body($resp)));
                }
            } else {
                // Nur Caption editieren
                $endpoint = "https://api.telegram.org/bot{$bot_token}/editMessageCaption";
                $resp = telegram_api_post($endpoint, array(
                    'chat_id'      => $chat_id,
                    'message_id'   => (int)$msg_id,
                    'caption'      => $caption,
                    'parse_mode'   => 'HTML',
                ));
                if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) >= 400) {
                    error_log('[Telegram] editMessageCaption failed: ' . (is_wp_error($resp) ? $resp->get_error_message() : wp_remote_retrieve_body($resp)));
                }
            }
        } else {
            // Foto -> Text geht nicht per Edit -> Delete + Re-Send
            if (telegram_delete_message_for_edit($post_id)) {
                if (telegram_send_message($post_id)) {
                    update_post_meta($post_id, '_telegram_message_type', 'text');
                    delete_post_meta($post_id, '_telegram_image_sent');
                    update_post_meta($post_id, '_telegram_sent', 1);
                }
            }
        }
        return;
    }

    if ($orig_type === 'text') {
        if ($image_url) {
            // Text -> Foto -> Delete + Resend
            if (telegram_delete_message_for_edit($post_id)) {
                if (telegram_send_message($post_id)) {
                    update_post_meta($post_id, '_telegram_message_type', 'photo');
                    if ($image_id) update_post_meta($post_id, '_telegram_image_sent', (int)$image_id);
                    update_post_meta($post_id, '_telegram_sent', 1);
                }
            }
        } else {
            // Text-Edit
            $endpoint = "https://api.telegram.org/bot{$bot_token}/editMessageText";
            $resp = telegram_api_post($endpoint, array(
                'chat_id'      => $chat_id,
                'message_id'   => (int)$msg_id,
                'text'         => $caption,
                'parse_mode'   => 'HTML',
            ));
            if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) >= 400) {
                error_log('[Telegram] editMessageText failed: ' . (is_wp_error($resp) ? $resp->get_error_message() : wp_remote_retrieve_body($resp)));
            }
        }
        return;
    }

    // Fallback
    $endpoint = "https://api.telegram.org/bot{$bot_token}/editMessageCaption";
    $resp = telegram_api_post($endpoint, array(
        'chat_id'      => $chat_id,
        'message_id'   => (int)$msg_id,
        'caption'      => $caption,
        'parse_mode'   => 'HTML',
    ));
    if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) >= 400) {
        $endpoint = "https://api.telegram.org/bot{$bot_token}/editMessageText";
        telegram_api_post($endpoint, array(
            'chat_id'      => $chat_id,
            'message_id'   => (int)$msg_id,
            'text'         => $caption,
            'parse_mode'   => 'HTML',
        ));
    }
}

// =============================
// == Löschen (Unpublish/Trash) ==
// =============================

/** Unpublish/Trash/Delete -> deleteMessage, sonst Fallback-Reply */
function telegram_delete_message_unpublish($post_id) {
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'product') return false;

    $bot_token  = get_option('telegram_bot_token');
    $chat_id    = get_option('telegram_chat_id');
    $message_id = get_post_meta($post_id, '_telegram_message_id', true);

    if (empty($bot_token) || empty($chat_id) || empty($message_id)) return false;

    if (get_post_meta($post_id, '_telegram_deleted', true)) return true;

    $endpoint = "https://api.telegram.org/bot{$bot_token}/deleteMessage";
    $payload  = array('chat_id' => $chat_id, 'message_id' => (int)$message_id);
    $resp     = telegram_api_post($endpoint, $payload);

    if (is_wp_error($resp)) {
        error_log('[Telegram] deleteMessage (unpublish) WP_Error: ' . $resp->get_error_message());
        return false;
    }
    $code = wp_remote_retrieve_response_code($resp);
    $body = json_decode(wp_remote_retrieve_body($resp), true);

    if ($code === 200 && isset($body['ok']) && $body['ok'] === true) {
        delete_post_meta($post_id, '_telegram_message_id');
        if (TELEGRAM_REPOST_ON_REPUBLISH) {
            delete_post_meta($post_id, '_telegram_sent');
            delete_post_meta($post_id, '_telegram_image_sent');
        }
        update_post_meta($post_id, '_telegram_deleted', 1);
        return true;
    } else {
        error_log('[Telegram] deleteMessage (unpublish) failed: ' . wp_remote_retrieve_body($resp));
        return false;
    }
}

/** Delete für Edit-Flows (Typwechsel) – Flags bleiben erhalten */
function telegram_delete_message_for_edit($post_id) {
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'product') return false;

    $bot_token  = get_option('telegram_bot_token');
    $chat_id    = get_option('telegram_chat_id');
    $message_id = get_post_meta($post_id, '_telegram_message_id', true);

    if (empty($bot_token) || empty($chat_id) || empty($message_id)) return false;

    $endpoint = "https://api.telegram.org/bot{$bot_token}/deleteMessage";
    $payload  = array('chat_id' => $chat_id, 'message_id' => (int)$message_id);
    $resp     = telegram_api_post($endpoint, $payload);

    if (is_wp_error($resp)) {
        error_log('[Telegram] deleteMessage (edit) WP_Error: ' . $resp->get_error_message());
        return false;
    }
    $code = wp_remote_retrieve_response_code($resp);
    $body = json_decode(wp_remote_retrieve_body($resp), true);

    if ($code === 200 && isset($body['ok']) && $body['ok'] === true) {
        delete_post_meta($post_id, '_telegram_message_id');
        return true;
    } else {
        error_log('[Telegram] deleteMessage (edit) failed: ' . wp_remote_retrieve_body($resp));
        return false;
    }
}

/** Fallback-Reply "nicht mehr verfügbar" */
function telegram_mark_unavailable_fallback($post_id) {
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'product') return false;

    $bot_token  = get_option('telegram_bot_token');
    $chat_id    = get_option('telegram_chat_id');
    $message_id = get_post_meta($post_id, '_telegram_message_id', true);

    if (empty($bot_token) || empty($chat_id) || empty($message_id)) return false;

    if (get_post_meta($post_id, '_telegram_deleted', true)) return true;

    $title_raw   = get_the_title($post_id);
    $title_clean = tn_clean_text((string)$title_raw);
    $title       = tn_tg_html_esc($title_clean);
    $message     = "❌ <b>Das Inserat \"{$title}\" ist nicht mehr verfügbar.</b>";

    $endpoint = "https://api.telegram.org/bot{$bot_token}/sendMessage";
    $resp = telegram_api_post($endpoint, array(
        'chat_id'             => $chat_id,
        'text'                => $message,
        'parse_mode'          => 'HTML',
        'reply_to_message_id' => (int)$message_id,
    ));

    if (is_wp_error($resp)) {
        error_log('[Telegram] fallback sendMessage WP_Error: ' . $resp->get_error_message());
        return false;
    }

    if (wp_remote_retrieve_response_code($resp) >= 400) {
        error_log('[Telegram] fallback sendMessage HTTP Error: ' . wp_remote_retrieve_body($resp));
        return false;
    }

    update_post_meta($post_id, '_telegram_deleted', 1);
    return true;
}

// ========================
// == Hooks / Event-Flow ==
// ========================

/**
 * Telegram notification hooks.
 *
 * Uses sk_new_product_added / sk_product_updated instead of save_post_product
 * so that price, featured image, and all other meta are already saved.
 * Actual sending is deferred to PHP shutdown so the vendor sees the redirect
 * immediately without waiting for the Telegram API call.
 */

/** Queue for shutdown processing */
global $_tn_shutdown_queue;
$_tn_shutdown_queue = [];

/** Erstversand bei neuem Produkt */
add_action('sk_new_product_added', 'telegram_queue_on_new_product', 10, 2);
function telegram_queue_on_new_product($post_id, $postdata) {
    global $_tn_shutdown_queue;
    $post = get_post($post_id);
    error_log("[TG] telegram_queue_on_new_product: id=$post_id status=" . ($post ? $post->post_status : 'null') . " type=" . ($post ? $post->post_type : 'null'));

    if (!$post || $post->post_status !== 'publish' || $post->post_type !== 'product') {
        error_log("[TG] SKIP #$post_id: status/type check failed");
        return;
    }
    if (empty(trim($post->post_title)) || strtolower($post->post_title) === 'auto-draft') {
        error_log("[TG] SKIP #$post_id: title empty or auto-draft");
        return;
    }
    if (get_post_meta($post_id, '_telegram_sent', true)) {
        error_log("[TG] SKIP #$post_id: already sent");
        return;
    }

    $_tn_shutdown_queue[$post_id] = 'new';
    error_log("[TG] QUEUED for shutdown send #$post_id (new product)");
}

/** Catch draft/pending → publish transitions (WC Admin or manual status change) */
add_action('transition_post_status', 'telegram_queue_on_status_publish', 10, 3);
function telegram_queue_on_status_publish($new_status, $old_status, $post) {
    if ($new_status !== 'publish' || $new_status === $old_status) return;
    if (!$post || $post->post_type !== 'product') return;
    if (get_post_meta($post->ID, '_telegram_sent', true)) return;
    if (get_post_meta($post->ID, '_telegram_message_id', true)) return;
    if (empty(trim($post->post_title)) || strtolower($post->post_title) === 'auto-draft') return;

    global $_tn_shutdown_queue;
    $_tn_shutdown_queue[$post->ID] = 'new';
    error_log("[TG] QUEUED for shutdown send #{$post->ID} (status transition: {$old_status} → publish)");
}

/** Bei Produkt-Update: Erstversand nachholen oder Edit senden */
add_action('sk_product_updated', 'telegram_queue_on_product_update', 10, 2);
function telegram_queue_on_product_update($post_id, $postdata) {
    global $_tn_shutdown_queue;
    $post = get_post($post_id);
    error_log("[TG] telegram_queue_on_product_update: id=$post_id status=" . ($post ? $post->post_status : 'null'));

    if (!$post || $post->post_status !== 'publish' || $post->post_type !== 'product') {
        error_log("[TG] SKIP update #$post_id: status/type check failed");
        return;
    }

    $_tn_shutdown_queue[$post_id] = 'update';
    error_log("[TG] QUEUED for shutdown #$post_id (product update)");
}

/** Shutdown handler: send notifications after response is delivered */
register_shutdown_function(function() {
    global $_tn_shutdown_queue;
    if (empty($_tn_shutdown_queue)) return;

    // Flush response to browser so vendor doesn't wait
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }

    foreach ($_tn_shutdown_queue as $post_id => $type) {
        // Queued while it was still publish — it may have been pulled since
        // (keyword review, trash). Drop it instead of scheduling cron retries
        // that could never succeed.
        if (get_post_status($post_id) !== 'publish') {
            error_log("[TG] SHUTDOWN SKIP #$post_id: no longer published");
            delete_post_meta($post_id, '_telegram_job_scheduled');
            continue;
        }

        $was_sent = (bool) get_post_meta($post_id, '_telegram_sent', true);
        $msg_id   = get_post_meta($post_id, '_telegram_message_id', true);

        if (!$was_sent && !$msg_id) {
            // First-time send
            $price    = get_post_meta($post_id, '_regular_price', true);
            $image_id = get_post_thumbnail_id($post_id);
            if (empty($price) || empty($image_id)) {
                // Meta still missing — queue a cron retry
                if (!get_post_meta($post_id, '_telegram_job_scheduled', true)) {
                    wp_schedule_single_event(time() + 90, 'tn_try_send_telegram_event', [(int) $post_id, 1]);
                    update_post_meta($post_id, '_telegram_job_scheduled', 1);
                    error_log("[TG] QUEUED cron retry #$post_id in 90s (price/image still missing)");
                }
                continue;
            }

            error_log("[TG] SHUTDOWN SEND #$post_id");
            $ok = telegram_send_message($post_id);
            error_log("[TG] SHUTDOWN result=" . ($ok ? 'OK' : 'FAIL') . " #$post_id");

            if ($ok) {
                update_post_meta($post_id, '_telegram_sent', 1);
                update_post_meta($post_id, '_telegram_image_sent', (int) $image_id);
                delete_post_meta($post_id, '_telegram_job_scheduled');
            } else {
                if (!get_post_meta($post_id, '_telegram_job_scheduled', true)) {
                    wp_schedule_single_event(time() + 90, 'tn_try_send_telegram_event', [(int) $post_id, 1]);
                    update_post_meta($post_id, '_telegram_job_scheduled', 1);
                    error_log("[TG] QUEUED cron retry #$post_id in 90s");
                }
            }
        } elseif ($was_sent && $msg_id && $type === 'update') {
            // Already sent before — edit the existing Telegram message
            error_log("[TG] SHUTDOWN EDIT #$post_id");
            telegram_edit_message_for_update($post_id);
        }
    }
});

/** Edit bei Updates im Publish-Status (admin-only fallback).
 *  Vendor dashboard edits are handled by the shutdown handler via sk_product_updated
 *  which fires AFTER all meta is saved. This hook only runs for WP Admin edits. */
add_action('save_post_product', 'telegram_maybe_edit_message_on_update', 30, 3);
function telegram_maybe_edit_message_on_update($post_id, $post, $update) {
    if (!$update || $post->post_status !== 'publish' || $post->post_type !== 'product') return;
    // Skip on vendor dashboard — the shutdown handler will edit with correct data
    if (function_exists('sk_is_seller_dashboard') && sk_is_seller_dashboard()) return;
    if (!empty($_POST['sk_update_product'])) return; // fallback check
    $msg_id = get_post_meta($post_id, '_telegram_message_id', true);
    if (!$msg_id) return;
    telegram_edit_message_for_update($post_id);
}

/** Unpublish/Trash/Delete Handler */
function telegram_handle_product_unpublish_or_delete($post_id) {
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'product') return;

    // Boost repost is tracked separately from the first post — remove it too,
    // independent of _telegram_deleted (which only guards the first post).
    telegram_delete_boost_message($post_id);

    if (get_post_meta($post_id, '_telegram_deleted', true)) return;

    $deleted = telegram_delete_message_unpublish($post_id);
    if (!$deleted) {
        telegram_mark_unavailable_fallback($post_id);
    }
}
add_action('wp_trash_post', 'telegram_handle_product_unpublish_or_delete', 10, 1);
add_action('before_delete_post', 'telegram_handle_product_unpublish_or_delete', 10, 1);
add_action('transition_post_status', function($new_status, $old_status, $post){
    if ($post->post_type !== 'product') return;
    // Only delete/mark unavailable on trash/delete, not on draft toggle
    if ($old_status === 'publish' && in_array($new_status, ['trash'], true)) {
        telegram_handle_product_unpublish_or_delete($post->ID);
    }
}, 10, 3);

/** Marker bei Re-Publish nur entfernen (kein Auto-Repost) */
add_action('transition_post_status', function($new_status, $old_status, $post){
    if ($post->post_type !== 'product') return;
    if ($new_status === 'publish') {
        delete_post_meta($post->ID, '_telegram_deleted');
    }
}, 10, 3);

function tn_product_ready_min($post_id){
    $p = get_post($post_id);
    if (!$p || $p->post_type !== 'product' || $p->post_status !== 'publish') return false;
    return true; // einfach gehalten; kannst du später erweitern (Preis etc.)
}

add_action('tn_try_send_telegram_event', function($post_id, $attempt){
    if (!tn_product_ready_min($post_id)) {
        if ($attempt < 5) {
            $delay = 60 * (2 ** ($attempt - 1)); // 60,120,240,480,960s
            wp_schedule_single_event(time()+$delay, 'tn_try_send_telegram_event', [(int)$post_id, $attempt+1]);
            error_log("[TG] RETRY not ready #$post_id attempt=$attempt +{$delay}s");
        } else {
            delete_post_meta($post_id, '_telegram_job_scheduled');
            error_log("[TG] GIVEUP not ready #$post_id");
        }
        return;
    }

    $ok = telegram_send_message($post_id);
    error_log("[TG] CRON SEND ".($ok?'OK':'FAIL')." #$post_id attempt=$attempt");

    if ($ok) {
        update_post_meta($post_id, '_telegram_sent', 1);
        delete_post_meta($post_id, '_telegram_job_scheduled');
    } else if ($attempt < 5) {
        $delay = 120 * (2 ** ($attempt - 1)); // 120,240,480,960,1920s
        wp_schedule_single_event(time()+$delay, 'tn_try_send_telegram_event', [(int)$post_id, $attempt+1]);
        error_log("[TG] CRON RETRY queued #$post_id +{$delay}s");
    } else {
        delete_post_meta($post_id, '_telegram_job_scheduled');
        error_log("[TG] CRON GIVEUP #$post_id");
    }
}, 10, 2);


// B) Generischer save_post (für ALLE Post-Types)
add_action('save_post', function ($post_id, $post, $update) {
    if (empty($post) || !is_object($post)) return;
    error_log("[TG] save_post fired type={$post->post_type} update=" . (int)$update . " id={$post_id}");
}, 10, 3);

// C) Produktspezifisch: save_post_product (genau unser Hook)
add_action('save_post_product', function ($post_id, $post, $update) {
    error_log("[TG] save_post_product fired update=" . (int)$update . " id={$post_id} status={$post->post_status}");
}, 9, 3); // vor deinen Blöcken, damit du sicher was siehst

add_action('save_post', function($post_id, $post, $update){
    if (!is_object($post)) return;
    if ($post->post_type === 'product') {
        error_log("[TG] save_post for PRODUCT fired. update=$update status={$post->post_status} #$post_id");
    }
}, 10, 3);

add_action('woocommerce_update_product', function($product_id){
    error_log("[TG] woocommerce_update_product fired for #$product_id");
}, 10, 1);

add_action('woocommerce_new_product', function($product_id){
    error_log("[TG] woocommerce_new_product fired for #$product_id");
}, 10, 1);

add_action('sk_after_save_product', function($product_id){
    error_log("[TG] sk_after_save_product fired for #$product_id");
}, 10, 1);

// === Admin-Metabox: Telegram neu posten ===
// Admin-Handler: tg_force_resend
// Meta-Box mit "Jetzt an Telegram senden"-Button
add_action('add_meta_boxes', function(){
    add_meta_box('tg_resend_box', 'Telegram', function($post){
        if ($post->post_type !== 'product') return;
        $url = wp_nonce_url(
            admin_url('admin-post.php?action=tg_force_resend&post_id='.(int)$post->ID),
            'tg_force_resend_nonce_'.$post->ID
        );
        echo '<p><a href="'.esc_url($url).'" class="button button-primary">Jetzt an Telegram senden</a></p>';
        echo '<p style="color:#666">Sendet sofort (bestehenden TG-Post löscht er vorher automatisch).</p>';
    }, 'product', 'side', 'default');
});

add_action('admin_notices', function(){
    if (!isset($_GET['tg_force_resend'])) return;
    $ok = $_GET['tg_force_resend'] === '1';
    echo '<div class="notice '.($ok?'notice-success':'notice-error').' is-dismissible"><p>'
        .($ok ? 'Telegram: erfolgreich gesendet.' : 'Telegram: Senden fehlgeschlagen (Retry geplant, siehe debug.log).')
        .'</p></div>';
});

// Admin-Handler: tg_force_resend
add_action('admin_post_tg_force_resend', function(){
    error_log('[TG] admin_post_tg_force_resend: ENTER');

    $post_id = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;
    error_log('[TG] admin_post_tg_force_resend: post_id='.$post_id);

    if (!$post_id) { error_log('[TG] admin: ABORT no post_id'); wp_die('Ungültige ID.'); }

    if (!current_user_can('edit_post', $post_id)) { error_log('[TG] admin: ABORT no capability'); wp_die('Keine Berechtigung.'); }

    // Nonce prüfen
    $nonce_ok = isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'tg_force_resend_nonce_'.$post_id);
    error_log('[TG] admin: nonce_ok=' . ($nonce_ok ? '1' : '0'));
    if (!$nonce_ok) { error_log('[TG] admin: ABORT nonce'); wp_die('Sicherheitscheck fehlgeschlagen.'); }

    $post = get_post($post_id);
    if (!$post) { error_log('[TG] admin: ABORT no post'); wp_die('Kein Post.'); }
    error_log('[TG] admin: post_type='.$post->post_type.' status='.$post->post_status);

    if ($post->post_type !== 'product') { error_log('[TG] admin: ABORT not product'); wp_die('Kein Produkt.'); }
    if ($post->post_status !== 'publish') { error_log('[TG] admin: ABORT not publish'); wp_die('Produkt ist nicht veröffentlicht.'); }

    // Mini-Selftest: Bot-Token/Chat-ID da?
    $bot_token = get_option('telegram_bot_token'); 
    $chat_id   = get_option('telegram_chat_id');
    error_log('[TG] admin: bot_token='.( $bot_token ? 'SET' : 'EMPTY' ).' chat_id='. ( $chat_id ?: 'EMPTY' ));

    // 0) Telegram getMe zur Erreichbarkeit testen (ohne Secrets zu loggen)
    if ($bot_token) {
        $probe = wp_remote_get("https://api.telegram.org/bot{$bot_token}/getMe", ['timeout'=>10]);
        if (is_wp_error($probe)) {
            error_log('[TG] admin: getMe WP_Error: '.$probe->get_error_message());
        } else {
            $pc = wp_remote_retrieve_response_code($probe);
            $pb = wp_remote_retrieve_body($probe);
            error_log('[TG] admin: getMe code='.$pc.' body='.substr($pb,0,200));
        }
    }

    // 1) vorhandenen TG-Post löschen
    $had_msg = get_post_meta($post_id, '_telegram_message_id', true);
    error_log('[TG] admin: had_msg='.($had_msg ?: 'none'));
    if ($had_msg) {
        $del = telegram_delete_message_for_edit($post_id);
        error_log('[TG] admin: delete_message_for_edit result='.($del?'OK':'FAIL'));
    }

    // 2) Flags resetten
    delete_post_meta($post_id, '_telegram_message_id');
    delete_post_meta($post_id, '_telegram_sent');
    delete_post_meta($post_id, '_telegram_image_sent');
    delete_post_meta($post_id, '_telegram_job_scheduled');
    delete_post_meta($post_id, '_telegram_deleted');

    // 3) senden
    error_log("[TG] admin: SEND NOW #$post_id");
    $ok = telegram_send_message($post_id);
    error_log('[TG] admin: SEND result='.($ok?'OK':'FAIL'));

    if ($ok) {
        update_post_meta($post_id, '_telegram_sent', 1);
        if (has_post_thumbnail($post_id)) {
            update_post_meta($post_id, '_telegram_image_sent', (int)get_post_thumbnail_id($post_id));
        }
    } else {
        if (!get_post_meta($post_id, '_telegram_job_scheduled', true)) {
            wp_schedule_single_event(time()+90, 'tn_try_send_telegram_event', [ $post_id, 1 ]);
            update_post_meta($post_id, '_telegram_job_scheduled', 1);
            error_log("[TG] admin: queued retry in 90s #$post_id");
        }
    }

    $redirect = add_query_arg(['tg_force_resend' => $ok ? '1' : '0'], get_edit_post_link($post_id, ''));
    error_log('[TG] admin_post_tg_force_resend: REDIRECT');
    wp_safe_redirect($redirect);
    exit;
});

// =========================
// == Boost / Highlight ==
// =========================
// When a vendor boosts their listing (product-adv module), it is reposted to
// the central Telegram channel — fresh reach, which is the point of a boost.
// Separate message with a boost header and its own meta (_telegram_boost_message_id) —
// does NOT collide with _telegram_message_id, edit/delete of the first post stays intact.

if (!defined('TELEGRAM_BOOST_REPOST_COOLDOWN')) {
    define('TELEGRAM_BOOST_REPOST_COOLDOWN', 3600); // seconds, same boost not reposted more often
}

$GLOBALS['_tn_boost_queue'] = [];

add_action('sk_after_product_advertisement_created', function($insert_id, $data, $args) {
    $product_id = isset($data['product_id']) ? (int) $data['product_id'] : 0;
    if (!$product_id) {
        error_log('[TG] BOOST: no product_id in advertisement data');
        return;
    }
    $GLOBALS['_tn_boost_queue'][$product_id] = true;
    error_log("[TG] BOOST queued #$product_id (advertisement #$insert_id)");
}, 10, 3);

register_shutdown_function(function() {
    if (empty($GLOBALS['_tn_boost_queue'])) return;

    // Flush the response to the browser first — the vendor does not wait for the post.
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }

    foreach (array_keys($GLOBALS['_tn_boost_queue']) as $post_id) {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'product' || $post->post_status !== 'publish') {
            error_log("[TG] BOOST skip #$post_id: not a published product");
            continue;
        }

        $last = (int) get_post_meta($post_id, '_telegram_boost_last', true);
        if ($last && (time() - $last) < TELEGRAM_BOOST_REPOST_COOLDOWN) {
            error_log("[TG] BOOST skip #$post_id: reposted within cooldown");
            continue;
        }

        telegram_send_boost_message($post_id);
    }

    $GLOBALS['_tn_boost_queue'] = [];
});

/** Posts a boosted listing as a new, marked message in the channel. */
function telegram_send_boost_message($post_id) {
    error_log('[TG] telegram_send_boost_message: ENTER post_id=' . $post_id);

    $bot_token = get_option('telegram_bot_token');
    $chat_id   = get_option('telegram_chat_id');
    if (!$bot_token || !$chat_id) {
        error_log('[TG] telegram_send_boost_message: ABORT missing bot_token or chat_id');
        return false;
    }

    $built     = telegram_build_caption_and_media($post_id);
    $caption    = "⭐️ <b>HERVORGEHOBENES INSERAT</b> ⭐️\n\n" . $built['caption'];
    $image_url  = $built['image_url'] ? tn_ensure_telegram_compatible_image($built['image_url']) : null;

    if ($image_url) {
        $endpoint = "https://api.telegram.org/bot{$bot_token}/sendPhoto";
        $payload  = array(
            'chat_id'    => $chat_id,
            'photo'      => $image_url,
            'caption'    => $caption,
            'parse_mode' => 'HTML',
        );
    } else {
        $endpoint = "https://api.telegram.org/bot{$bot_token}/sendMessage";
        $payload  = array(
            'chat_id'    => $chat_id,
            'text'       => $caption,
            'parse_mode' => 'HTML',
        );
    }

    $resp = telegram_api_post($endpoint, $payload);
    if (is_wp_error($resp)) {
        error_log('[TG] boost send error: ' . $resp->get_error_message());
        return false;
    }

    $code = wp_remote_retrieve_response_code($resp);
    $body = json_decode(wp_remote_retrieve_body($resp), true);

    if ($code === 200 && isset($body['ok']) && $body['ok']) {
        $mid = $body['result']['message_id'] ?? null;
        if ($mid) update_post_meta($post_id, '_telegram_boost_message_id', $mid);
        update_post_meta($post_id, '_telegram_boost_last', time());
        error_log('[TG] BOOST sent #' . $post_id . ' mid=' . ($mid ?: '?'));
        return true;
    }

    error_log('[TG] boost send HTTP error: ' . wp_remote_retrieve_body($resp));
    return false;
}

/** Removes the separate boost repost from the channel (own message id). */
function telegram_delete_boost_message($post_id) {
    $bot_token  = get_option('telegram_bot_token');
    $chat_id    = get_option('telegram_chat_id');
    $message_id = get_post_meta($post_id, '_telegram_boost_message_id', true);

    if (empty($bot_token) || empty($chat_id) || empty($message_id)) return false;

    $endpoint = "https://api.telegram.org/bot{$bot_token}/deleteMessage";
    $payload  = array('chat_id' => $chat_id, 'message_id' => (int)$message_id);
    $resp     = telegram_api_post($endpoint, $payload);

    if (is_wp_error($resp)) {
        error_log('[TG] BOOST deleteMessage WP_Error: ' . $resp->get_error_message());
        return false;
    }
    $code = wp_remote_retrieve_response_code($resp);
    $body = json_decode(wp_remote_retrieve_body($resp), true);

    if ($code === 200 && isset($body['ok']) && $body['ok'] === true) {
        delete_post_meta($post_id, '_telegram_boost_message_id');
        delete_post_meta($post_id, '_telegram_boost_last');
        error_log('[TG] BOOST deleted #' . $post_id);
        return true;
    }

    error_log('[TG] BOOST deleteMessage failed: ' . wp_remote_retrieve_body($resp));
    return false;
}
