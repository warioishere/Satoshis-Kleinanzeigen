<?php
if (!defined('ABSPATH')) exit;

function weo_get_option($key, $default = '') {
  $opts = get_option(WEO_OPT, []);
  return isset($opts[$key]) ? $opts[$key] : $default;
}

/**
 * Is the escrow usable at all: switched on, API reachable by configuration,
 * marketplace key present.
 */
function weo_enabled() {
  return weo_get_option('vendor_escrow_enabled', '') === '1'
    && weo_get_option('api_base', '') !== ''
    && weo_get_option('api_key', '') !== ''
    && weo_get_option('escrow_xpub', '') !== '';
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
  return ($code >=200 && $code <300) ? $json : new WP_Error('weo_api', weo_api_error_text($json, $code), ['code'=>$code,'body'=>$json]);
}

function weo_api_get($endpoint) {
  $base = rtrim(weo_get_option('api_base'), '/');
  $key  = weo_get_option('api_key','');
  $headers = $key ? ['x-api-key'=>$key] : [];
  $resp = wp_remote_get("$base$endpoint", ['timeout'=>20,'headers'=>$headers]);
  if (is_wp_error($resp)) return $resp;
  $code = wp_remote_retrieve_response_code($resp);
  $json = json_decode(wp_remote_retrieve_body($resp), true);
  return ($code >=200 && $code <300) ? $json : new WP_Error('weo_api', weo_api_error_text($json, $code), ['code'=>$code,'body'=>$json]);
}

/** Short, user-facing text for an API error response. */
function weo_api_error_text($json, $code) {
  $detail = is_array($json) ? ($json['detail'] ?? '') : '';
  if (is_array($detail)) $detail = wp_json_encode($detail);
  return sprintf('Escrow-API: %s (%d)', $detail !== '' ? $detail : 'Fehler', (int) $code);
}

function weo_sanitize_xpub($x) {
  $x = trim((string) $x);
  return preg_replace('/[^A-Za-z0-9]/','',$x);
}

function weo_sanitize_btc_address($addr) {
  $addr = trim((string) $addr);
  return sanitize_text_field($addr);
}

// ---- Validation helpers ----

/**
 * Normalize any mainnet account key (xpub, ypub, zpub, Ypub, Zpub) to the
 * plain xpub serialization the descriptor uses. Testnet keys are refused.
 */
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
  if (strlen($hex) !== 156) return new WP_Error('weo_xpub','invalid length');
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
  // Checksum-verified where the core helper is available.
  if (class_exists('\SK\Core\BitcoinAddress') && !\SK\Core\BitcoinAddress::is_valid($addr)) return false;
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
  if (strlen($bin) < 5) return false;
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
  wp_enqueue_style('weo-css', WEO_URL.'assets/admin.css', [], SK_ESCROW_VERSION);
  wp_enqueue_script('weo-escrow-signer', plugins_url('assets/js/sk-escrow-signer.js', SK_CORE_FILE), [], (string) @filemtime($bundle), true);
  wp_enqueue_script('weo-escrow-ui', WEO_URL.'assets/sk-escrow-ui.js', ['weo-escrow-signer', 'jquery'], SK_ESCROW_VERSION, true);
  wp_localize_script('weo-escrow-ui', 'weoSigner', [
    'ajaxurl' => admin_url('admin-ajax.php'),
    'nonce'   => wp_create_nonce('weo_escrow'),
    'l10n'    => [
      'pwShort'      => __('Passwort: mindestens 8 Zeichen.', 'sk-core'),
      'pwMismatch'   => __('Die Passwörter stimmen nicht überein.', 'sk-core'),
      'ackMissing'   => __('Bitte bestätige, dass du die 12 Wörter gesichert hast.', 'sk-core'),
      'keySaved'     => __('Schlüssel verschlüsselt gespeichert, xpub eingetragen: %s', 'sk-core'),
      'keyPresent'   => __('Dein Schlüssel für diesen Handel liegt verschlüsselt in diesem Browser.', 'sk-core'),
      'keyMissing'   => __('Kein Schlüssel in diesem Browser: 12 Wörter importieren oder mit der Hardware-Wallet über das PSBT-Feld signieren.', 'sk-core'),
      'verifyOk'     => __('Adresse geprüft: Sie ergibt sich aus dem 2-von-3-Descriptor, und dein Schlüssel ist enthalten.', 'sk-core'),
      'verifyBad'    => __('WARNUNG: Die angezeigte Adresse passt nicht zum Descriptor oder dein Schlüssel fehlt darin. Nicht einzahlen, Support kontaktieren.', 'sk-core'),
      'verifyError'  => __('Descriptor konnte nicht geprüft werden: %s', 'sk-core'),
      'psbtBad'      => __('PSBT konnte nicht gelesen werden: %s', 'sk-core'),
      'summaryTitle' => __('Diese Transaktion zahlt an:', 'sk-core'),
      'fee'          => __('Gebühr: %s sats', 'sk-core'),
      'sats'         => __('sats', 'sk-core'),
      'signed'       => __('Signiert und übermittelt.', 'sk-core'),
      'wrongPw'      => __('Falsches Passwort.', 'sk-core'),
      'signError'    => __('Signieren fehlgeschlagen: %s', 'sk-core'),
      'importOk'     => __('Wörter importiert und verschlüsselt gespeichert.', 'sk-core'),
      'importBad'    => __('Diese Wörter gehören nicht zu diesem Schlüssel.', 'sk-core'),
      'invalidWords' => __('Ungültige Wortliste.', 'sk-core'),
      'addrBad'      => __('Bitte eine gültige Bitcoin-Adresse (bc1…) angeben.', 'sk-core'),
      'xpubMissing'  => __('Bitte zuerst einen Schlüssel erzeugen oder einen xpub eintragen.', 'sk-core'),
      'working'      => __('Bitte warten …', 'sk-core'),
      'netError'     => __('Verbindungsfehler. Bitte erneut versuchen.', 'sk-core'),
      'confirmRelease' => __('Du bestätigst den Erhalt und gibst die Auszahlung an den Verkäufer frei. Das lässt sich nicht rückgängig machen.', 'sk-core'),
      'confirmRefund'  => __('Du erstattest den vollen Betrag abzüglich Netzwerkgebühr an den Käufer.', 'sk-core'),
      'confirmDecline' => __('Anfrage wirklich ablehnen?', 'sk-core'),
      'confirmCancel'  => __('Anfrage wirklich zurückziehen?', 'sk-core'),
      'copied'       => __('Kopiert', 'sk-core'),
    ],
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

/**
 * Sign controls: browser signing with the stored key, or a PSBT text field
 * for a hardware wallet. $kind is "payout" or "refund", it only changes the
 * button label.
 */
function weo_sign_panel_html($label) {
  ob_start();
  ?>
  <div class="weo-sign-panel" hidden>
    <div class="weo-sign-summary"></div>
    <p><label><?php esc_html_e('Passwort', 'sk-core'); ?><br><input type="password" class="weo-sign-pw" autocomplete="current-password"></label></p>
    <p><button type="button" class="button weo-sign-confirm"><?php echo esc_html($label); ?></button></p>
    <details class="weo-sign-manual">
      <summary><?php esc_html_e('Stattdessen mit Hardware-Wallet signieren', 'sk-core'); ?></summary>
      <p><?php esc_html_e('PSBT (Base64) kopieren, in der Wallet signieren und die signierte PSBT hier einfügen.', 'sk-core'); ?></p>
      <textarea class="weo-psbt-source" rows="3" readonly></textarea>
      <textarea class="weo-psbt-signed" rows="3" placeholder="PSBT…"></textarea>
      <p><button type="button" class="button weo-sign-upload"><?php esc_html_e('Signierte PSBT übermitteln', 'sk-core'); ?></button></p>
    </details>
    <p class="weo-sign-status" aria-live="polite"></p>
  </div>
  <?php
  return ob_get_clean();
}

/** Show or import the 12 words of the key in the surrounding context. */
function weo_keybox_html() {
  ob_start();
  ?>
  <div class="weo-keybox">
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
