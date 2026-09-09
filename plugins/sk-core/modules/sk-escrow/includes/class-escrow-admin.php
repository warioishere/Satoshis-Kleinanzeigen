<?php
if (!defined('ABSPATH')) exit;

use SK\Modules\Escrow\Actions;
use SK\Modules\Escrow\Notify;
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

    $rows = Rows::by_status(['requested','pending','confirmed','delivered','disputed','refunded','expired'], 200);

    echo '<div class="wrap"><h1>Escrows</h1>';
    echo '<table class="widefat fixed"><thead><tr><th>ID</th><th>Inserat</th><th>Käufer</th><th>Verkäufer</th><th>Betrag</th><th>Status</th><th>Treuhand</th></tr></thead><tbody>';
    foreach ($rows as $r) {
      $meta = Rows::meta($r);
      echo '<tr>';
      echo '<td>' . (int) $r->id . '</td>';
      echo '<td>' . esc_html($r->product_id ? get_the_title((int) $r->product_id) : '—') . '</td>';
      echo '<td>' . esc_html($this->user_label($r->buyer_id)) . '</td>';
      echo '<td>' . esc_html($this->user_label($r->vendor_id)) . '</td>';
      echo '<td>' . esc_html(number_format_i18n((int) $r->amount_sats)) . ' sats</td>';
      echo '<td>' . esc_html($r->status) . '</td>';
      echo '<td>' . esc_html(($meta['state'] ?? '') . (!empty($meta['address']) ? ' · ' . $meta['address'] : '') . (!empty($meta['settled_txid']) ? ' · tx ' . $meta['settled_txid'] : '')) . '</td>';
      echo '</tr>';
    }
    echo '</tbody></table></div>';
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

      foreach (['payout' => 'Auszahlung an Verkäufer bauen', 'refund' => 'Erstattung an Käufer bauen'] as $type => $label) {
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

    echo '</div>';
  }

  private function handle_action(object $row, string $action) {
    $meta = Rows::meta($row);
    if (empty($meta['order_id'])) {
      echo '<div class="notice notice-error"><p>Kein API-Auftrag zu dieser Zeile.</p></div>';
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

    if ($action === 'build_payout' || $action === 'build_refund') {
      $type = $action === 'build_payout' ? 'payout' : 'refund';
      if ($type === 'refund') {
        $res = weo_api_post('/psbt/build_refund', ['order_id' => $meta['order_id'], 'address' => $meta['refund_address'], 'rbf' => true, 'target_conf' => 3]);
      } else {
        $res = weo_api_post('/psbt/build', ['order_id' => $meta['order_id'], 'outputs' => [$meta['payout_address'] => (int) $row->amount_sats], 'rbf' => true, 'target_conf' => 3]);
      }
      if (is_wp_error($res) || empty($res['psbt'])) {
        echo '<div class="notice notice-error"><p>' . esc_html(is_wp_error($res) ? $res->get_error_message() : 'PSBT konnte nicht erstellt werden.') . '</p></div>';
        return;
      }
      Rows::save_meta($row->payment_hash, ['psbt_type' => $type, 'psbt' => (string) $res['psbt'], 'signed' => []]);
      Notify::chat($row, get_current_user_id(), $type === 'refund'
        ? __('Der Marktplatz hat entschieden: Erstattung an den Käufer. Der Käufer signiert unter „Käufe“, der Marktplatz zeichnet gegen.', 'sk-core')
        : __('Der Marktplatz hat entschieden: Auszahlung an den Verkäufer. Der Verkäufer signiert unter „Verkäufe“, der Marktplatz zeichnet gegen.', 'sk-core'));
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
