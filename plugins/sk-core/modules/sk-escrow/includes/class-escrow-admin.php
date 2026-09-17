<?php
if (!defined('ABSPATH')) exit;

use SK\Modules\Escrow\Actions;
use SK\Modules\Escrow\Deadlines;
use SK\Modules\Escrow\Dispute;
use SK\Modules\Escrow\Notify;
use SK\Modules\Escrow\Pool;
use SK\Modules\Escrow\Rows;

/**
 * Operator view: every escrow, and the disputes the marketplace has to
 * resolve. Resolution means building a payout or refund at the API, signing
 * it with the marketplace key in an external wallet (the site never holds
 * that key) and merging the signature with the one the favoured party
 * already gave.
 */
class WEO_Admin {
  public function __construct() {
    add_action('admin_menu', [$this, 'menu']);
  }

  public function menu() {
    add_submenu_page('weo-treuhand', 'Escrows', 'Escrows', 'manage_woocommerce', 'weo-escrows', [$this, 'page']);
    add_submenu_page('weo-treuhand', 'Disputes', 'Disputes', 'manage_woocommerce', 'weo-disputes', [$this, 'disputes_page']);
  }

  private function user_label($id) {
    $u = get_userdata((int) $id);
    return $u ? $u->display_name . ' (#' . (int) $id . ')' : '#' . (int) $id;
  }

  public function page() {
    if (!current_user_can('manage_woocommerce')) wp_die('Nicht erlaubt.');

    if (!empty($_POST['weo_tracking']) && !empty($_POST['hash'])) {
      $this->save_tracking(sanitize_text_field(wp_unslash($_POST['hash'])));
    }

    $rows = Rows::by_status(['requested','pending','confirmed','delivered','disputed','refunded','expired'], 200);

    echo '<div class="wrap"><h1>Escrows</h1>';
    echo '<p>Sendungsstatus: was der Versender auf seiner Seite zur Sendungsnummer zeigt, hier eintragen (§1, §3 des Regelwerks). Der Zustellscan startet das Meldefenster, danach baut der Cron die Auszahlung.</p>';
    echo '<table class="widefat fixed"><thead><tr><th>ID</th><th>Inserat</th><th>Käufer</th><th>Verkäufer</th><th>Betrag</th><th>Status</th><th>Versand</th><th>Treuhand</th></tr></thead><tbody>';
    foreach ($rows as $r) {
      $meta = Rows::meta($r);
      echo '<tr>';
      echo '<td>' . (int) $r->id . '</td>';
      echo '<td>' . esc_html($r->product_id ? get_the_title((int) $r->product_id) : '—') . '</td>';
      echo '<td>' . esc_html($this->user_label($r->buyer_id)) . '</td>';
      echo '<td>' . esc_html($this->user_label($r->vendor_id)) . '</td>';
      echo '<td>' . esc_html(number_format_i18n((int) $r->amount_sats)) . ' sats</td>';
      echo '<td>' . esc_html($r->status) . '</td>';
      echo '<td>' . $this->shipping_cell($r) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
      echo '<td>' . esc_html(($meta['state'] ?? '') . (!empty($meta['address']) ? ' · ' . $meta['address'] : '') . (!empty($meta['settled_txid']) ? ' · tx ' . $meta['settled_txid'] : '')) . '</td>';
      echo '</tr>';
    }
    echo '</tbody></table></div>';
  }

  /** Shipping entry and carrier status of a row, with the form to record the status. */
  private function shipping_cell(object $r): string {
    $ship = Deadlines::shipping($r);
    if (!$ship) {
      return '—';
    }

    $t    = Deadlines::tracking($r);
    $html = esc_html($ship['carrier'] . ' ' . $ship['number']) . '<br><small>versendet ' . esc_html($ship['at']) . '</small>';
    if ($t['state'] !== '') {
      $html .= '<br><small>Status: ' . esc_html($t['state'])
        . ($t['delivered_at'] ? ', zugestellt ' . esc_html(wp_date('d.m.Y', $t['delivered_at'])) : '')
        . ($t['signed'] ? ', mit Unterschrift' : '')
        . ($t['weight_g'] ? ', ' . (int) $t['weight_g'] . ' g' : '')
        . ' (' . esc_html($t['source']) . ')</small>';
    }

    if ($r->status !== 'confirmed') {
      return $html;
    }

    $nonce = wp_create_nonce('weo_admin_' . $r->payment_hash);
    $html .= '<form method="post" style="margin-top:6px;">'
      . '<input type="hidden" name="hash" value="' . esc_attr($r->payment_hash) . '"><input type="hidden" name="weo_nonce" value="' . esc_attr($nonce) . '"><input type="hidden" name="weo_tracking" value="1">'
      . '<select name="state"><option value="in_transit">unterwegs</option><option value="delivered">zugestellt</option><option value="lost">verloren</option></select> '
      . '<input type="date" name="delivered_at" value="' . esc_attr(wp_date('Y-m-d')) . '" style="width:130px;"> '
      . '<label><input type="checkbox" name="signed" value="1"> Unterschrift</label> '
      . '<input type="number" name="weight_g" placeholder="Gramm" min="0" style="width:80px;"> '
      . '<button class="button button-small">Eintragen</button></form>';

    return $html;
  }

  private function save_tracking(string $hash) {
    if (!wp_verify_nonce($_POST['weo_nonce'] ?? '', 'weo_admin_' . $hash)) {
      echo '<div class="notice notice-error"><p>Ungültiger Sicherheits-Token.</p></div>';
      return;
    }
    $row = Rows::get($hash);
    if (!$row || $row->status !== 'confirmed') {
      echo '<div class="notice notice-error"><p>Nur bei einer bezahlten, offenen Treuhand.</p></div>';
      return;
    }
    $state = sanitize_key(wp_unslash($_POST['state'] ?? ''));
    if (!in_array($state, ['in_transit', 'delivered', 'lost'], true)) {
      return;
    }
    $delivered = strtotime(sanitize_text_field(wp_unslash($_POST['delivered_at'] ?? '')) . ' 12:00:00') ?: time();
    Deadlines::record_tracking($hash, $state, $delivered, !empty($_POST['signed']), absint($_POST['weight_g'] ?? 0), 'manual', get_current_user_id());
    echo '<div class="notice notice-success"><p>Sendungsstatus eingetragen.</p></div>';
  }

  public function disputes_page() {
    if (!current_user_can('manage_woocommerce')) wp_die('Nicht erlaubt.');

    if (!empty($_POST['weo_action']) && !empty($_POST['hash'])) {
      $hash = sanitize_text_field(wp_unslash($_POST['hash']));
      if (wp_verify_nonce($_POST['weo_nonce'] ?? '', 'weo_admin_' . $hash)) {
        $row = Rows::get($hash);
        if ($row) {
          $this->handle_action($row, sanitize_key(wp_unslash($_POST['weo_action'])));
        }
      } else {
        echo '<div class="notice notice-error"><p>Ungültiger Sicherheits-Token.</p></div>';
      }
    }

    echo '<div class="wrap"><h1>Disputes</h1>';
    echo '<p>Aufloesen: Auszahlung oder Erstattung bauen, die PSBT mit dem Marktplatz-Schluessel extern signieren (Sparrow o. ae.), signierte PSBT hier einreichen. Die zweite Signatur liefert die beguenstigte Partei ueber ihr Dashboard, danach wird gesendet.</p>';

    foreach (Rows::by_status(['disputed'], 100) as $r) {
      $meta  = Rows::meta($r);
      $all   = Rows::all_meta($r);
      $nonce = wp_create_nonce('weo_admin_' . $r->payment_hash);
      echo '<div class="card" style="max-width:900px;padding:12px;margin-top:12px;">';
      echo '<h2>#' . (int) $r->id . ' · ' . esc_html($r->product_id ? get_the_title((int) $r->product_id) : '—') . ' · ' . esc_html(number_format_i18n((int) $r->amount_sats)) . ' sats</h2>';
      echo '<p>Käufer: ' . esc_html($this->user_label($r->buyer_id)) . ' · Verkäufer: ' . esc_html($this->user_label($r->vendor_id)) . '</p>';
      echo '<p>Grund: ' . esc_html((string) ($all['dispute_reason'] ?? '')) . ' · gemeldet ' . esc_html((string) ($all['dispute_at'] ?? '')) . '</p>';
      echo '<p>Treuhand: ' . esc_html((string) ($meta['address'] ?? '')) . ' · API-Status ' . esc_html((string) ($meta['state'] ?? '')) . ' · eingezahlt ' . esc_html(number_format_i18n((int) ($meta['funded_sat'] ?? 0))) . ' sats</p>';
      echo '<p>Auszahlungsadresse Verkäufer: <code>' . esc_html((string) ($meta['payout_address'] ?? '')) . '</code><br>Erstattungsadresse Käufer: <code>' . esc_html((string) ($meta['refund_address'] ?? '')) . '</code></p>';
      if (!empty($meta['psbt_type'])) {
        echo '<p>Offene Transaktion: <strong>' . esc_html($meta['psbt_type']) . '</strong>, signiert von: ' . esc_html(implode(', ', (array) ($meta['signed'] ?? [])) ?: '—') . '</p>';
        echo '<p><label>PSBT (unsigniert, Base64)</label><br><textarea rows="4" style="width:100%;" readonly>' . esc_textarea((string) $meta['psbt']) . '</textarea></p>';
      }

      $fee = (int) ($meta['fee_sat'] ?? 0);
      echo '<p>Servicegebühr in der Treuhand: ' . esc_html(number_format_i18n($fee)) . ' sats · Regelwerk ' . esc_html((string) ($meta['rules_version'] ?? '–')) . '</p>';
      echo $this->dispute_block($r, $nonce); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

      foreach ([
        'payout'     => 'Auszahlung an Verkäufer bauen (Gebühr an Marktplatz)',
        'refund_fee' => 'Erstattung an Käufer bauen (Gebühr bleibt beim Marktplatz)',
        'refund'     => 'Volle Erstattung an Käufer bauen (nicht versendet, §3)',
      ] as $type => $label) {
        echo '<form method="post" style="display:inline;margin-right:6px;">';
        echo '<input type="hidden" name="hash" value="' . esc_attr($r->payment_hash) . '"><input type="hidden" name="weo_nonce" value="' . esc_attr($nonce) . '"><input type="hidden" name="weo_action" value="build_' . $type . '">';
        echo '<button class="button">' . esc_html($label) . '</button></form>';
      }

      echo '<form method="post" style="margin-top:8px;">';
      echo '<input type="hidden" name="hash" value="' . esc_attr($r->payment_hash) . '"><input type="hidden" name="weo_nonce" value="' . esc_attr($nonce) . '"><input type="hidden" name="weo_action" value="partial">';
      echo '<label>Mit Marktplatz-Schlüssel signierte PSBT (Base64)</label><br><textarea name="psbt" rows="4" style="width:100%;"></textarea><br>';
      echo '<button class="button button-primary">Signatur einreichen</button></form>';

      echo '<form method="post" style="margin-top:8px;">';
      echo '<input type="hidden" name="hash" value="' . esc_attr($r->payment_hash) . '"><input type="hidden" name="weo_nonce" value="' . esc_attr($nonce) . '"><input type="hidden" name="weo_action" value="close">';
      echo '<button class="button">Dispute schliessen (zurück zu „bezahlt“)</button></form>';
      echo '</div>';
    }

    echo '<h2 style="margin-top:24px;">Kulanzanträge (§7)</h2>';
    echo '<p>Fonds: ' . esc_html(number_format_i18n(Pool::balance())) . ' sats. Auszahlung von Hand aus der Plattform-Wallet an die angegebene Adresse, dann hier als bezahlt eintragen; das bucht den Betrag vom Fonds ab.</p>';
    $claims = Dispute::pending_claims();
    if (!$claims) {
      echo '<p>Keine offenen Anträge.</p>';
    }
    foreach ($claims as $r) {
      $c     = Dispute::get($r)['claim'];
      $nonce = wp_create_nonce('weo_admin_' . $r->payment_hash);
      echo '<div class="card" style="max-width:900px;padding:12px;margin-top:12px;">';
      echo '<p>#' . (int) $r->id . ' · ' . esc_html($r->product_id ? get_the_title((int) $r->product_id) : '—') . ' · Antrag von ' . esc_html($c['by'] === 'buyer' ? 'Käufer ' : 'Verkäufer ') . esc_html($this->user_label((int) $c['user_id']))
        . ' · <strong>' . esc_html(number_format_i18n((int) $c['amount'])) . ' sats</strong> an <code>' . esc_html((string) $c['pay_to']) . '</code> · gestellt ' . esc_html(wp_date('d.m.Y', (int) $c['at'])) . '</p>';
      foreach (['claim_paid' => ['button-primary', 'Als bezahlt eintragen'], 'claim_rejected' => ['', 'Ablehnen']] as $action => [$class, $label]) {
        echo '<form method="post" style="display:inline;margin-right:6px;"><input type="hidden" name="hash" value="' . esc_attr($r->payment_hash) . '"><input type="hidden" name="weo_nonce" value="' . esc_attr($nonce) . '"><input type="hidden" name="weo_action" value="' . esc_attr($action) . '">'
          . '<input type="text" name="note" placeholder="' . ($action === 'claim_paid' ? 'Zahlungsreferenz' : 'Grund') . '" style="width:220px;"> <button class="button ' . esc_attr($class) . '">' . esc_html($label) . '</button></form>';
      }
      echo '</div>';
    }

    echo '</div>';
  }

  /** The case, the facts, the decision and the buttons to obtain or confirm it. */
  private function dispute_block(object $r, string $nonce): string {
    $d    = Dispute::get($r);
    $kind = (string) ($d['kind'] ?? '');
    $html = '<div style="border:1px solid #ccd0d4;padding:8px;margin:8px 0;">';
    $html .= '<p><strong>Fall:</strong> ' . esc_html($kind !== '' ? ($kind === 'not_received' ? 'nicht erhalten (§4)' : 'nicht wie beschrieben (§5)') : 'Fristablauf oder Meldung ohne Art') . '</p>';
    $html .= '<p><strong>Angaben (keine Tatsachen):</strong> Käufer: ' . esc_html((string) ($d['statements']['buyer'] ?? '–')) . ' · Verkäufer: ' . esc_html((string) ($d['statements']['seller'] ?? '–')) . '</p>';
    $html .= '<details><summary>Tatsachen, wie das Modell sie bekommt</summary><pre style="white-space:pre-wrap;font-size:11px;">' . esc_html(wp_json_encode(Dispute::facts($r), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) . '</pre></details>';

    if ($kind === 'not_as_described') {
      $rs = $d['return_shipping'] ?? null;
      $rt = $d['return_tracking'] ?? [];
      $html .= '<p><strong>Rücksendung:</strong> ' . ($rs ? esc_html($rs['carrier'] . ' ' . $rs['number'] . ', eingetragen ' . wp_date('d.m.Y', (int) $rs['at'])) : 'noch keine');
      if ($rt) {
        $html .= ' · Status ' . esc_html((string) ($rt['state'] ?? '')) . (!empty($rt['delivered_at']) ? ', zugestellt ' . esc_html(wp_date('d.m.Y', (int) $rt['delivered_at'])) : '') . (!empty($rt['weight_g']) ? ', ' . (int) $rt['weight_g'] . ' g' : '');
      }
      $html .= '</p>';
      if ($rs && empty($d['decision'])) {
        $html .= '<form method="post" style="margin:4px 0;"><input type="hidden" name="hash" value="' . esc_attr($r->payment_hash) . '"><input type="hidden" name="weo_nonce" value="' . esc_attr($nonce) . '"><input type="hidden" name="weo_action" value="return_tracking">'
          . 'Rücksendung laut Versender: <select name="state"><option value="in_transit">unterwegs</option><option value="delivered">zugestellt</option><option value="refused">Annahme verweigert</option><option value="lost">verloren</option></select> '
          . '<input type="date" name="delivered_at" value="' . esc_attr(wp_date('Y-m-d')) . '" style="width:130px;"> <input type="number" name="weight_g" placeholder="Gramm" min="0" style="width:80px;"> '
          . '<button class="button button-small">Eintragen</button></form>';
      }
    }

    $c = $d['decision'] ?? null;
    if ($c) {
      $html .= '<p><strong>Entscheidung (' . esc_html((string) ($c['model'] ?? '')) . ', ' . esc_html(wp_date('d.m.Y H:i', (int) ($c['decided_at'] ?? 0))) . '):</strong> ' . esc_html(Dispute::outcome_text($c))
        . ' · Transaktion <code>' . esc_html((string) $c['transaction']) . '</code> · Vorfall für: ' . esc_html((string) $c['incident_for'])
        . ' · Regeln: ' . esc_html(implode(', ', (array) $c['rules_applied'])) . '<br>' . esc_html((string) $c['reasoning'])
        . (!empty($c['flags']) ? '<br><em>Hinweise: ' . esc_html(implode('; ', (array) $c['flags'])) . '</em>' : '')
        . '<br>Kulanzantrag möglich: Käufer ' . (!empty($c['pool_claim_eligible']['buyer']) ? 'ja' : 'nein') . ', Verkäufer ' . (!empty($c['pool_claim_eligible']['seller']) ? 'ja' : 'nein') . '</p>';
      if (empty($d['confirmed'])) {
        $html .= '<form method="post" style="display:inline;"><input type="hidden" name="hash" value="' . esc_attr($r->payment_hash) . '"><input type="hidden" name="weo_nonce" value="' . esc_attr($nonce) . '"><input type="hidden" name="weo_action" value="confirm_decision">'
          . '<button class="button button-primary">Entscheidung bestätigen und Transaktion bauen</button></form> ';
      } else {
        $html .= '<p>Bestätigt am ' . esc_html(wp_date('d.m.Y H:i', (int) $d['confirmed']['at'])) . ' (' . esc_html((string) $d['confirmed']['type']) . '). Abweichen unten nur mit Begründung im Chat.</p>';
      }
    } else {
      $html .= '<p>Noch keine Entscheidung' . (!empty($d['decide_error']) ? ' · letzter Fehler: ' . esc_html((string) $d['decide_error']) : '') . ' · Versuche: ' . (int) ($d['decide_attempts'] ?? 0) . '</p>';
      $html .= '<form method="post" style="display:inline;"><input type="hidden" name="hash" value="' . esc_attr($r->payment_hash) . '"><input type="hidden" name="weo_nonce" value="' . esc_attr($nonce) . '"><input type="hidden" name="weo_action" value="decide">'
        . '<button class="button">Entscheidung nach dem Regelwerk einholen</button></form>';
    }

    return $html . '</div>';
  }

  private function handle_action(object $row, string $action) {
    if ($action === 'claim_paid' || $action === 'claim_rejected') {
      $error = Dispute::claim_settle($row, get_current_user_id(), $action === 'claim_paid', sanitize_text_field(wp_unslash($_POST['note'] ?? '')));
      echo '<div class="notice notice-' . ($error === '' ? 'success' : 'error') . '"><p>' . esc_html($error === '' ? 'Antrag erledigt.' : $error) . '</p></div>';
      return;
    }

    $meta = Rows::meta($row);
    if (empty($meta['order_id'])) {
      echo '<div class="notice notice-error"><p>Kein API-Auftrag zu dieser Zeile.</p></div>';
      return;
    }

    if ($action === 'decide') {
      Rows::save_meta($row->payment_hash, ['dispute' => array_merge(Dispute::get($row), ['decide_attempts' => 0])]);
      $ok = Dispute::decide(Rows::get($row->payment_hash));
      echo '<div class="notice notice-' . ($ok ? 'success' : 'error') . '"><p>' . ($ok ? 'Entscheidung liegt vor.' : esc_html('Keine Entscheidung: ' . (string) (Dispute::get(Rows::get($row->payment_hash))['decide_error'] ?? ''))) . '</p></div>';
      return;
    }

    if ($action === 'confirm_decision') {
      $error = Dispute::confirm($row, get_current_user_id());
      echo '<div class="notice notice-' . ($error === '' ? 'success' : 'error') . '"><p>' . esc_html($error === '' ? 'Bestätigt. PSBT gebaut, jetzt extern signieren und unten einreichen.' : $error) . '</p></div>';
      return;
    }

    if ($action === 'return_tracking') {
      $state = sanitize_key(wp_unslash($_POST['state'] ?? ''));
      if (!in_array($state, ['in_transit', 'delivered', 'refused', 'lost'], true)) {
        return;
      }
      $delivered = strtotime(sanitize_text_field(wp_unslash($_POST['delivered_at'] ?? '')) . ' 12:00:00') ?: time();
      Dispute::record_return_tracking($row->payment_hash, $state, $delivered, absint($_POST['weight_g'] ?? 0), 'manual', get_current_user_id());
      Dispute::tick(Rows::get($row->payment_hash));
      echo '<div class="notice notice-success"><p>Rücksendungsstatus eingetragen.</p></div>';
      return;
    }

    if ($action === 'close') {
      if (Rows::set_status($row->payment_hash, 'disputed', 'confirmed')) {
        Rows::save_meta($row->payment_hash, ['psbt_type' => '', 'psbt' => '', 'signed' => []]);
        Notify::chat($row, get_current_user_id(), __('Der Marktplatz hat den Dispute geschlossen. Käufer und Verkäufer können den Handel normal abschliessen.', 'sk-core'));
        echo '<div class="notice notice-success"><p>Dispute geschlossen.</p></div>';
      }
      return;
    }

    if (strpos($action, 'build_') === 0) {
      $type = substr($action, 6);
      if (!in_array($type, Actions::TYPES, true)) {
        return;
      }
      $res = Actions::build($row, $type);
      if (is_wp_error($res) || empty($res['psbt'])) {
        echo '<div class="notice notice-error"><p>' . esc_html(is_wp_error($res) ? $res->get_error_message() : 'PSBT konnte nicht erstellt werden.') . '</p></div>';
        return;
      }
      Rows::save_meta($row->payment_hash, ['psbt_type' => $type, 'psbt' => (string) $res['psbt'], 'signed' => []]);
      Notify::chat($row, get_current_user_id(), $type === 'payout'
        ? __('Der Marktplatz hat entschieden: Auszahlung an den Verkäufer. Der Verkäufer signiert unter „Verkäufe“, der Marktplatz zeichnet gegen.', 'sk-core')
        : __('Der Marktplatz hat entschieden: Erstattung an den Käufer. Der Käufer signiert unter „Käufe“, der Marktplatz zeichnet gegen.', 'sk-core'));
      echo '<div class="notice notice-success"><p>PSBT gebaut. Jetzt extern signieren und unten einreichen; die Gegenpartei signiert im Dashboard.</p></div>';
      return;
    }

    if ($action === 'partial') {
      $partial = trim((string) wp_unslash($_POST['psbt'] ?? ''));
      if ($partial === '' || base64_decode($partial, true) === false) {
        echo '<div class="notice notice-error"><p>Ungültige PSBT.</p></div>';
        return;
      }
      if (empty($meta['psbt_type'])) {
        echo '<div class="notice notice-error"><p>Zuerst Auszahlung oder Erstattung bauen.</p></div>';
        return;
      }
      $merge = weo_api_post('/psbt/merge', ['order_id' => $meta['order_id'], 'partials' => [$partial]]);
      if (is_wp_error($merge) || empty($merge['psbt'])) {
        echo '<div class="notice notice-error"><p>' . esc_html(is_wp_error($merge) ? $merge->get_error_message() : 'Zusammenführen fehlgeschlagen.') . '</p></div>';
        return;
      }
      $dec   = weo_api_post('/psbt/decode', ['psbt' => $merge['psbt']]);
      $count = is_wp_error($dec) ? 0 : (int) ($dec['sign_count'] ?? 0);
      $signed = array_values(array_unique(array_merge((array) ($meta['signed'] ?? []), ['escrow'])));
      Rows::save_meta($row->payment_hash, ['signed' => $signed, 'sign_count' => $count]);

      if ($count < 2) {
        echo '<div class="notice notice-success"><p>Marktplatz-Signatur gespeichert (' . (int) $count . '/2). Die begünstigte Partei muss noch im Dashboard signieren.</p></div>';
        return;
      }

      $txid = Actions::settle(Rows::get($row->payment_hash), $merge['psbt'], (string) $meta['psbt_type']);
      if (is_wp_error($txid)) {
        echo '<div class="notice notice-error"><p>' . esc_html($txid->get_error_message()) . '</p></div>';
        return;
      }
      echo '<div class="notice notice-success"><p>Transaktion gesendet: ' . esc_html($txid) . '</p></div>';
    }
  }
}
