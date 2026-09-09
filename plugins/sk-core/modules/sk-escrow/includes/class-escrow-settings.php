<?php
if (!defined('ABSPATH')) exit;

class WEO_Settings {
  public function __construct() {
    add_action('admin_menu', [$this, 'menu']);
  }

  public function sanitize($opts) {
    $clean = [];
    $clean['api_base']   = esc_url_raw($opts['api_base'] ?? '');
    $clean['escrow_xpub']= weo_sanitize_xpub($opts['escrow_xpub'] ?? '');
    $clean['min_conf']   = max(0, intval($opts['min_conf'] ?? 1));
    $clean['api_key']    = sanitize_text_field($opts['api_key'] ?? '');
    $clean['hmac_secret']= sanitize_text_field($opts['hmac_secret'] ?? '');
    $clean['timeout_days']= max(1, intval($opts['timeout_days'] ?? 7));
    $clean['vendor_escrow_enabled'] = !empty($opts['vendor_escrow_enabled']) ? '1' : '';
    $clean['vendor_escrow_admin_only'] = !empty($opts['vendor_escrow_admin_only']) ? '1' : '';
    return $clean;
  }

  public function menu() {
    add_menu_page('Treuhand', 'Treuhand', 'manage_woocommerce', 'weo-treuhand', [$this,'render'], 'dashicons-lock', 56);
  }

  public function sanitize_fallback_address($addr) {
    $addr = weo_sanitize_btc_address($addr);
    if (!$addr) add_settings_error('weo_vendor_payout_fallback','required',__('Bitte eine gültige Fallback-Adresse angeben.','sk-core'));
    return $addr;
  }

  /**
   * Landing page of the Treuhand menu. The settings form that used to live
   * here moved to SK Admin → Settings → Treuhand; the operational pages
   * (Escrows, Disputes) are registered by WEO_Admin as submenus.
   */
  public function render() {
    $settings_url = admin_url('admin.php?page=sk&tab=settings&section=' . \SK\Modules\Escrow\EscrowSettings::SECTION);
    ?>
    <div class="wrap">
      <h1><?php esc_html_e('Treuhand', 'sk-core'); ?></h1>
      <p><?php esc_html_e('Nicht-verwahrende On-Chain-Treuhand: Käufer, Verkäufer und Marktplatz halten je einen Schlüssel, ausgezahlt wird mit zwei von drei Signaturen.', 'sk-core'); ?></p>
      <p>
        <a href="<?php echo esc_url($settings_url); ?>" class="button button-primary"><?php esc_html_e('Einstellungen öffnen', 'sk-core'); ?></a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=weo-escrows')); ?>" class="button"><?php esc_html_e('Escrows', 'sk-core'); ?></a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=weo-disputes')); ?>" class="button"><?php esc_html_e('Disputes', 'sk-core'); ?></a>
      </p>
    </div>
    <?php
  }
}
