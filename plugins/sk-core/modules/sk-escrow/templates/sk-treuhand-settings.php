<?php
if (!defined('ABSPATH')) {
    exit;
}

if (empty($treuhand_data) || !is_array($treuhand_data)) {
    return;
}

$active_tab = $treuhand_data['active_tab'] ?? 'settings';
$is_active  = ($active_tab === 'settings');

$settings  = $treuhand_data['settings'] ?? [];
$context   = $treuhand_data['context'] ?? '';
$available = $treuhand_data['available'] ?? false;

$xpub          = $settings['xpub'] ?? '';
$payout        = $settings['payout'] ?? '';
$escrow_enable = $settings['escrow_enabled'] ?? '';

$panel_id = 'weo-treuhand-panel-settings';
$tab_id   = 'weo-treuhand-tab-settings';
?>
<div
    id="<?php echo esc_attr($panel_id); ?>"
    class="weo-treuhand-panel weo-treuhand-panel--settings"
    role="tabpanel"
    aria-labelledby="<?php echo esc_attr($tab_id); ?>"
    aria-hidden="<?php echo $is_active ? 'false' : 'true'; ?>"
    data-tab-panel="settings"
    <?php echo $is_active ? '' : ' hidden'; ?>
>
    <?php if ($context === 'admin') : ?>
        <div class="sk-alert sk-alert-info">
            <p><?php esc_html_e('Der Treuhand-Service ist derzeit nur für Administratoren sichtbar.', 'weo'); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!$available && $context !== 'admin') : ?>
        <div class="sk-alert sk-alert-warning">
            <p><?php esc_html_e('Der Marktplatz hat den Treuhand-Service für Verkäufer deaktiviert.', 'weo'); ?></p>
        </div>
    <?php else : ?>
    <form method="post" class="sk-form weo-treuhand-form">
        <?php wp_nonce_field('weo_sk_xpub'); ?>
        <div class="sk-form-group">
            <label for="weo_vendor_xpub" class="sk-form-label"><?php esc_html_e('Vendor xpub', 'weo'); ?></label>
            <input type="text" class="sk-form-control" name="weo_vendor_xpub" id="weo_vendor_xpub" value="<?php echo esc_attr($xpub); ?>">
        </div>

        <div class="sk-form-group">
            <label for="weo_payout_address" class="sk-form-label"><?php esc_html_e('Payout-/Refund-Adresse', 'weo'); ?></label>
            <input type="text" class="sk-form-control" name="weo_payout_address" id="weo_payout_address" value="<?php echo esc_attr($payout); ?>">
            <p class="help-block"><?php esc_html_e('Diese Adresse wird für Auszahlungen und Rückerstattungen verwendet.', 'weo'); ?></p>
        </div>

        <div class="sk-form-group weo-treuhand-toggle">
            <label for="weo_vendor_escrow_enabled" class="sk-form-label">
                <input type="checkbox" name="weo_vendor_escrow_enabled" id="weo_vendor_escrow_enabled" value="1" <?php checked($escrow_enable, '1'); ?>>
                <?php esc_html_e('Escrow-Service aktiv', 'weo'); ?>
            </label>
        </div>

        <div class="sk-form-group">
            <button type="submit" class="sk-btn sk-btn-theme" <?php disabled(!$available && $context !== 'admin'); ?>>
                <?php esc_html_e('Speichern', 'weo'); ?>
            </button>
        </div>
    </form>
    <?php endif; ?>
</div>
