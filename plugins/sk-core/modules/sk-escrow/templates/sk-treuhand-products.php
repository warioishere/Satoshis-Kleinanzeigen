<?php
if (!defined('ABSPATH')) {
    exit;
}

if (empty($treuhand_data) || !is_array($treuhand_data)) {
    return;
}

$active_tab = $treuhand_data['active_tab'] ?? 'settings';
$is_active  = ($active_tab === 'products');

$products = $treuhand_data['products'] ?? [];

$panel_id = 'weo-treuhand-panel-products';
$tab_id   = 'weo-treuhand-tab-products';
?>
<div
    id="<?php echo esc_attr($panel_id); ?>"
    class="weo-treuhand-panel weo-treuhand-panel--products"
    role="tabpanel"
    aria-labelledby="<?php echo esc_attr($tab_id); ?>"
    aria-hidden="<?php echo $is_active ? 'false' : 'true'; ?>"
    data-tab-panel="products"
    <?php echo $is_active ? '' : ' hidden'; ?>
>
    <?php if (empty($products)) : ?>
        <p><?php esc_html_e('Keine aktiven Escrow-Produkte gefunden.', 'weo'); ?></p>
    <?php else : ?>
        <div class="weo-treuhand-products-list">
            <?php foreach ($products as $product) :
                $stock_label = function_exists('wc_get_stock_status_name') ? wc_get_stock_status_name($product['stock_status']) : $product['stock_status'];
            ?>
                <article class="weo-treuhand-product" aria-labelledby="weo-product-<?php echo intval($product['id']); ?>">
                    <header class="weo-treuhand-product__header">
                        <h3 id="weo-product-<?php echo intval($product['id']); ?>">
                            <a href="<?php echo esc_url($product['permalink']); ?>"><?php echo esc_html($product['name']); ?></a>
                        </h3>
                        <?php if (!empty($product['price_html'])) : ?>
                            <div class="weo-treuhand-product__price"><?php echo wp_kses_post($product['price_html']); ?></div>
                        <?php endif; ?>
                    </header>

                    <ul class="weo-treuhand-product__meta">
                        <li>
                            <strong><?php esc_html_e('Escrow-Status', 'weo'); ?>:</strong>
                            <span class="weo-treuhand-product__status weo-treuhand-product__status--<?php echo $product['escrow_enabled'] ? 'on' : 'off'; ?>">
                                <?php echo $product['escrow_enabled'] ? esc_html__('Aktiv', 'weo') : esc_html__('Inaktiv', 'weo'); ?>
                            </span>
                        </li>
                        <li>
                            <strong><?php esc_html_e('Lagerstatus', 'weo'); ?>:</strong>
                            <?php echo esc_html($stock_label); ?>
                        </li>
                        <li>
                            <strong><?php esc_html_e('Auszahlungsadresse', 'weo'); ?>:</strong>
                            <?php if ($product['payout_address']) : ?>
                                <code><?php echo esc_html($product['payout_address']); ?></code>
                            <?php else : ?>
                                <span><?php esc_html_e('Keine Adresse hinterlegt – bitte in den Einstellungen ergänzen.', 'weo'); ?></span>
                            <?php endif; ?>
                        </li>
                    </ul>

                    <footer class="weo-treuhand-product__actions">
                        <?php if (!empty($product['edit_url'])) : ?>
                            <a class="sk-btn" href="<?php echo esc_url($product['edit_url']); ?>"><?php esc_html_e('Produkt bearbeiten', 'weo'); ?></a>
                        <?php endif; ?>
                        <a class="sk-btn sk-btn-theme" href="<?php echo esc_url($product['permalink']); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Produkt ansehen', 'weo'); ?></a>
                    </footer>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <p class="weo-treuhand-products__hint">
        <?php esc_html_e('Die Escrow-Aktivierung lässt sich im Produkt-Editor unter „Escrow Service für dieses Produkt anbieten“ steuern.', 'weo'); ?>
    </p>
</div>
