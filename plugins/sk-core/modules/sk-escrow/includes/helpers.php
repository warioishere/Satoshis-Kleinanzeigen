<?php
if (!defined('ABSPATH')) exit;

function weo_get_option($key, $default = '') {
  $opts = get_option(WEO_OPT, []);
  return isset($opts[$key]) ? $opts[$key] : $default;
}

function weo_vendor_escrow_allowed() {
  $enabled = weo_get_option('vendor_escrow_enabled', '1');
  $allowed = ($enabled === '1');
  return apply_filters('weo_vendor_escrow_allowed', $allowed);
}

function weo_admin_treuhand_enabled() {
  $enabled = weo_get_option('vendor_escrow_admin_only', '');
  $allowed = ($enabled === '1');
  return apply_filters('weo_admin_treuhand_enabled', $allowed);
}

function weo_api_post($endpoint, $body = []) {
  $base = rtrim(weo_get_option('api_base'), '/');
  $key  = weo_get_option('api_key','');
  $headers = ['Content-Type'=>'application/json'];
  if ($key) $headers['x-api-key'] = $key;
  $resp = wp_remote_post("$base$endpoint", [
    'headers' => $headers,
    'timeout' => 20,
    'body'    => wp_json_encode($body),
  ]);
  if (is_wp_error($resp)) return $resp;
  $code = wp_remote_retrieve_response_code($resp);
  $json = json_decode(wp_remote_retrieve_body($resp), true);
  return ($code >=200 && $code <300) ? $json : new WP_Error('weo_api', 'API error', ['code'=>$code,'body'=>$json]);
}

function weo_api_get($endpoint) {
  $base = rtrim(weo_get_option('api_base'), '/');
  $key  = weo_get_option('api_key','');
  $headers = $key ? ['x-api-key'=>$key] : [];
  $resp = wp_remote_get("$base$endpoint", ['timeout'=>20,'headers'=>$headers]);
  if (is_wp_error($resp)) return $resp;
  $code = wp_remote_retrieve_response_code($resp);
  $json = json_decode(wp_remote_retrieve_body($resp), true);
  return ($code >=200 && $code <300) ? $json : new WP_Error('weo_api', 'API error', ['code'=>$code,'body'=>$json]);
}

function weo_sanitize_xpub($x) {
  $x = trim($x);
  return preg_replace('/[^A-Za-z0-9]/','',$x);
}

function weo_sanitize_btc_address($addr) {
  $addr = trim($addr);
  return sanitize_text_field($addr);
}

/**
 * Order total in satoshi.
 *
 * The shop runs in SAT, so the total already is the sat amount; the old code
 * multiplied it by 1e8 as if it were BTC and asked the escrow for a deposit
 * a hundred million times too large. Any other currency is refused: the
 * escrow must never be created with a guessed amount.
 *
 * @return int 0 when the amount cannot be determined.
 */
function weo_order_total_sat($order) {
  $total    = floatval($order->get_total());
  $currency = strtoupper((string) $order->get_currency());

  if ($currency === 'SAT' || $currency === 'SATS') {
    $sat = (int) round($total);
  } elseif ($currency === 'BTC') {
    $sat = (int) round($total * 100000000);
  } else {
    return 0;
  }

  return weo_validate_amount($sat) ? $sat : 0;
}

function weo_get_payout_address($user_id) {
  $addr = get_user_meta($user_id, 'weo_payout_address', true);
  if (!$addr) {
    $old = get_user_meta($user_id, 'weo_vendor_payout_address', true);
    if (!$old) $old = get_user_meta($user_id, 'weo_buyer_payout_address', true);
    if ($old) {
      update_user_meta($user_id, 'weo_payout_address', $old);
      $addr = $old;
    }
  }
  return $addr;
}

/**
 * Resolve the payout address for an order's vendor.
 *
 * Determines the vendor from the order (falling back to the product author
 * and caching that on the order), then returns their payout address, or the
 * globally configured fallback.
 *
 * Lived as three byte-identical private copies in WEO_Order, WEO_Admin and
 * WEO_SK before — the WEO_SK one was never even called.
 *
 * @throws Exception When neither a vendor address nor a fallback is set.
 */
function weo_resolve_vendor_payout_address($order_id) {
  $order = wc_get_order($order_id);

  if ($order) {
    $vendor_id = $order->get_meta('_weo_vendor_id');

    if (!$vendor_id) {
      foreach ($order->get_items('line_item') as $item) {
        $pid = $item->get_product_id();
        $vendor_id = get_post_field('post_author', $pid);
        if ($vendor_id) break;
      }
      if ($vendor_id) {
        $order->update_meta_data('_weo_vendor_id', $vendor_id);
        $order->save();
      }
    }

    if ($vendor_id) {
      $payout = weo_get_payout_address($vendor_id);
      if ($payout) return $payout;
    }
  }

  $fallback = get_option('weo_vendor_payout_fallback', '');
  if ($fallback) return $fallback;

  wc_add_notice(__('Keine Fallback-Payout-Adresse konfiguriert.', 'sk-core'), 'error');
  throw new Exception('Fallback vendor payout address missing');
}

/**
 * Make wc_add_notice() usable on admin-post.php.
 *
 * WooCommerce loads its notice functions and starts its session only on
 * frontend requests. admin-post.php counts as admin, so there wc_add_notice()
 * is undefined and every PSBT upload or dispute request ended in a fatal
 * error instead of a redirect with a message.
 */
function weo_ensure_wc_session() {
  if (!function_exists('WC')) return;
  if (!function_exists('wc_add_notice') && defined('WC_ABSPATH')) {
    include_once WC_ABSPATH . 'includes/wc-notice-functions.php';
  }
  if (null === WC()->session && method_exists(WC(), 'initialize_session')) {
    WC()->initialize_session();
  }
}

/**
 * Claim a POSTed escrow action for the current request.
 *
 * Two handlers accept the same form: WEO_SK runs at init, WEO_Order runs
 * again while the order panel renders. On a page where both apply the same
 * POST was processed twice, which fired weo_order_shipped/received twice
 * and therefore sent every notification twice. The first caller gets the
 * action, every later one is told it is already taken.
 *
 * @return bool True if the caller may process it.
 */
function weo_claim_post_action($order_id, $action) {
  static $claimed = [];

  $key = (int) $order_id . '|' . (string) $action;

  if (isset($claimed[$key])) {
    return false;
  }

  $claimed[$key] = true;

  return true;
}

// ---- Validation helpers ----

function weo_normalize_xpub($xpub, $network = 'main') {
  $xpub = weo_sanitize_xpub($xpub);
  if (!$xpub) return new WP_Error('weo_xpub','xpub missing');

  $vers = [
    '0488b21e'=>['net'=>'main','dest'=>'0488b21e'], // xpub
    '049d7cb2'=>['net'=>'main','dest'=>'0488b21e'], // ypub
    '04b24746'=>['net'=>'main','dest'=>'0488b21e'], // zpub
    '0295b43f'=>['net'=>'main','dest'=>'0488b21e'], // Ypub
    '02aa7ed3'=>['net'=>'main','dest'=>'0488b21e'], // Zpub
    '043587cf'=>['net'=>'test','dest'=>'043587cf'], // tpub
    '044a5262'=>['net'=>'test','dest'=>'043587cf'], // upub
    '045f1cf6'=>['net'=>'test','dest'=>'043587cf'], // vpub
    '024289ef'=>['net'=>'test','dest'=>'043587cf'], // Upub
    '02575483'=>['net'=>'test','dest'=>'043587cf'], // Vpub
  ];

  $hex = weo_base58check_decode($xpub);
  if ($hex === false) return new WP_Error('weo_xpub','invalid base58');
  $prefix = substr($hex,0,8);
  $payload = substr($hex,8);
  if (!isset($vers[$prefix])) return new WP_Error('weo_xpub','unknown prefix');
  $info = $vers[$prefix];
  if ($info['net'] !== $network) return new WP_Error('weo_xpub','wrong network');
  $norm_hex = $info['dest'] . $payload;
  return weo_base58check_encode($norm_hex);
}

function weo_validate_btc_address($addr, $network = 'main') {
  $addr = weo_sanitize_btc_address($addr);
  $hrp = $network === 'main' ? 'bc1' : 'tb1';
  if (!preg_match('#^'.preg_quote($hrp,'#').'[0-9ac-hj-np-z]{8,87}$#i', $addr)) return false;
  return true;
}

function weo_validate_amount($sats, $min = 1, $max = 2100000000000000) {
  if (!is_numeric($sats)) return false;
  $sats = intval($sats);
  return ($sats >= $min && $sats <= $max);
}

function weo_sanitize_order_id($id) {
  $id = sanitize_text_field($id);
  return preg_match('/^[A-Za-z0-9_-]{1,32}$/',$id) ? $id : '';
}

// ---- Base58Check helpers ----

function weo_base58check_decode($b58) {
  $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
  $num = '0';
  for ($i=0; $i<strlen($b58); $i++) {
    $p = strpos($alphabet, $b58[$i]);
    if ($p === false) return false;
    $num = bcadd(bcmul($num,'58'), (string)$p);
  }
  $hex = '';
  while (bccomp($num,'0') > 0) {
    $rem = bcmod($num,'16');
    $num = bcdiv($num,'16',0);
    $hex = dechex($rem) . $hex;
  }
  if (strlen($hex)%2) $hex = '0'.$hex;
  $bin = hex2bin($hex);
  $pad = 0;
  for ($i=0; $i<strlen($b58) && $b58[$i]=='1'; $i++) $pad++;
  $bin = str_repeat("\x00", $pad) . $bin;
  $data = substr($bin,0,-4);
  $checksum = substr($bin,-4);
  $hash = substr(hash('sha256', hex2bin(hash('sha256',$data)), true),0,4);
  if ($checksum !== $hash) return false;
  return bin2hex($data);
}

function weo_base58check_encode($hex) {
  $data = hex2bin($hex);
  $checksum = substr(hash('sha256', hex2bin(hash('sha256',$data)), true),0,4);
  $bin = $data . $checksum;
  $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
  $num = '0';
  $bytes = unpack('C*', $bin);
  foreach ($bytes as $b) {
    $num = bcadd(bcmul($num,'256'), (string)$b);
  }
  $res = '';
  while (bccomp($num,'0') > 0) {
    $rem = bcmod($num,'58');
    $num = bcdiv($num,'58',0);
    $res = $alphabet[(int)$rem] . $res;
  }
  foreach ($bytes as $b) {
    if ($b === 0) $res = '1'.$res; else break;
  }
  return $res;
}

// ---- Browser signing ----
//
// assets/js/sk-escrow-signer.js (built from tools/escrow-signer) holds the
// cryptography; modules/sk-escrow/assets/sk-escrow-ui.js wires it to the
// markup below. None of the password or word inputs carry a name attribute,
// so no form ever posts them to the server.

function weo_enqueue_signer() {
  $bundle = SK_CORE_DIR . '/assets/js/sk-escrow-signer.js';
  wp_enqueue_script('weo-escrow-signer', plugins_url('assets/js/sk-escrow-signer.js', SK_CORE_FILE), [], (string) @filemtime($bundle), true);
  wp_enqueue_script('weo-escrow-ui', WEO_URL.'assets/sk-escrow-ui.js', ['weo-escrow-signer'], SK_ESCROW_VERSION, true);
  wp_localize_script('weo-escrow-ui', 'weoSignerL10n', [
    'pwShort'      => __('Passwort: mindestens 8 Zeichen.', 'sk-core'),
    'pwMismatch'   => __('Die Passwörter stimmen nicht überein.', 'sk-core'),
    'ackMissing'   => __('Bitte bestätige, dass du die 12 Wörter gesichert hast.', 'sk-core'),
    'keySaved'     => __('Schlüssel verschlüsselt gespeichert, xpub eingetragen: %s', 'sk-core'),
    'keyPresent'   => __('Dein Schlüssel für diesen Handel liegt verschlüsselt in diesem Browser.', 'sk-core'),
    'keyMissing'   => __('Kein Schlüssel in diesem Browser: 12 Wörter importieren oder mit der Hardware-Wallet über das PSBT-Feld signieren.', 'sk-core'),
    'verifyOk'     => __('Adresse geprüft: Sie ergibt sich aus dem 2-von-3-Descriptor, und dein Schlüssel ist enthalten.', 'sk-core'),
    'verifyBad'    => __('WARNUNG: Die angezeigte Adresse passt nicht zum Descriptor oder dein Schlüssel fehlt darin. Nicht einzahlen, Support kontaktieren.', 'sk-core'),
    'verifyError'  => __('Descriptor konnte nicht geprüft werden: %s', 'sk-core'),
    'noPsbt'       => __('Bitte zuerst die PSBT erstellen.', 'sk-core'),
    'psbtBad'      => __('PSBT konnte nicht gelesen werden: %s', 'sk-core'),
    'summaryTitle' => __('Diese Transaktion zahlt an:', 'sk-core'),
    'fee'          => __('Gebühr: %s sats', 'sk-core'),
    'sats'         => __('sats', 'sk-core'),
    'signed'       => __('Signiert. Jetzt die PSBT hochladen.', 'sk-core'),
    'wrongPw'      => __('Falsches Passwort.', 'sk-core'),
    'signError'    => __('Signieren fehlgeschlagen: %s', 'sk-core'),
    'importOk'     => __('Wörter importiert und verschlüsselt gespeichert.', 'sk-core'),
    'importBad'    => __('Diese Wörter gehören nicht zu diesem Schlüssel.', 'sk-core'),
    'invalidWords' => __('Ungültige Wortliste.', 'sk-core'),
  ]);
}

/** Generate-12-words panel that fills the xpub input with the given id. */
function weo_keygen_html($target_id) {
  ob_start();
  ?>
  <div class="weo-keygen" data-target="#<?php echo esc_attr($target_id); ?>" data-network="main">
    <p>
      <button type="button" class="button weo-keygen-start"><?php esc_html_e('Schlüssel im Browser erzeugen', 'sk-core'); ?></button>
      <span class="description"><?php esc_html_e('oder den xpub deiner Hardware-Wallet oben eintragen.', 'sk-core'); ?></span>
    </p>
    <div class="weo-keygen-panel" hidden>
      <p class="weo-keygen-risk"><?php esc_html_e('Die 12 Wörter werden nur in diesem Browser gespeichert, verschlüsselt mit deinem Passwort. Schreibe sie jetzt auf: Ohne sie kannst du auf einem anderen Gerät nicht signieren. Verlierst du sie, bleibt die Auszahlung über Marktplatz und Gegenpartei möglich. Dieser Code kommt vom Marktplatz; wer dem Server nicht vertraut, signiert stattdessen mit einer Hardware-Wallet.', 'sk-core'); ?></p>
      <ol class="weo-words"></ol>
      <p><label><?php esc_html_e('Passwort (mindestens 8 Zeichen)', 'sk-core'); ?><br><input type="password" class="weo-keygen-pw" autocomplete="new-password"></label></p>
      <p><label><?php esc_html_e('Passwort wiederholen', 'sk-core'); ?><br><input type="password" class="weo-keygen-pw2" autocomplete="new-password"></label></p>
      <p><label><input type="checkbox" class="weo-keygen-ack"> <?php esc_html_e('Ich habe die 12 Wörter aufgeschrieben und sicher verwahrt.', 'sk-core'); ?></label></p>
      <p>
        <button type="button" class="button weo-keygen-save"><?php esc_html_e('Schlüssel verwenden', 'sk-core'); ?></button>
        <button type="button" class="button weo-keygen-cancel"><?php esc_html_e('Abbrechen', 'sk-core'); ?></button>
      </p>
      <p class="weo-keygen-status" aria-live="polite"></p>
    </div>
  </div>
  <?php
  return ob_get_clean();
}

/** "Sign in browser" controls for inside a PSBT upload form. */
function weo_sign_panel_html() {
  ob_start();
  ?>
  <p><button type="button" class="button weo-sign-browser"><?php esc_html_e('Im Browser signieren', 'sk-core'); ?></button></p>
  <div class="weo-sign-panel" hidden>
    <div class="weo-sign-summary"></div>
    <p><label><?php esc_html_e('Passwort', 'sk-core'); ?><br><input type="password" class="weo-sign-pw" autocomplete="current-password"></label></p>
    <p><button type="button" class="button weo-sign-confirm"><?php esc_html_e('Signieren', 'sk-core'); ?></button></p>
    <p class="weo-sign-status" aria-live="polite"></p>
  </div>
  <?php
  return ob_get_clean();
}

/** Show or import the 12 words. Without $xpub the key is taken from the surrounding order context. */
function weo_keybox_html($xpub = '') {
  ob_start();
  ?>
  <div class="weo-keybox"<?php echo $xpub ? ' data-xpub="'.esc_attr($xpub).'" data-network="main"' : ''; ?>>
    <p class="weo-key-state"></p>
    <p>
      <button type="button" class="button weo-words-show"><?php esc_html_e('12 Wörter anzeigen', 'sk-core'); ?></button>
      <button type="button" class="button weo-words-import"><?php esc_html_e('12 Wörter importieren', 'sk-core'); ?></button>
    </p>
    <div class="weo-keybox-panel" hidden>
      <textarea class="weo-keybox-words" rows="2" hidden placeholder="<?php esc_attr_e('12 Wörter, durch Leerzeichen getrennt', 'sk-core'); ?>"></textarea>
      <p><label><?php esc_html_e('Passwort', 'sk-core'); ?><br><input type="password" class="weo-keybox-pw" autocomplete="current-password"></label></p>
      <p><button type="button" class="button weo-keybox-go">OK</button></p>
      <ol class="weo-keybox-list weo-words"></ol>
      <p class="weo-keybox-status" aria-live="polite"></p>
    </div>
  </div>
  <?php
  return ob_get_clean();
}
