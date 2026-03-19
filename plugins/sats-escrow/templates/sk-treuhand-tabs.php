<?php
if (!defined('ABSPATH')) {
    exit;
}

if (empty($treuhand_data) || !is_array($treuhand_data)) {
    return;
}

$active_tab = $treuhand_data['active_tab'] ?? 'settings';
$tabs       = $treuhand_data['tabs'] ?? [];
?>
<div id="weo-treuhand" class="weo-treuhand" data-active-tab="<?php echo esc_attr($active_tab); ?>">
    <?php if (!empty($tabs)) : ?>
        <nav class="woocommerce-tabs weo-treuhand-tabs" aria-label="<?php esc_attr_e('Treuhand-Bereiche', 'weo'); ?>">
            <ul class="tabs wc-tabs" role="tablist">
                <?php foreach ($tabs as $key => $label) :
                    $is_active = ($active_tab === $key);
                    $tab_url   = add_query_arg('weo_tab', $key);
                    $tab_id    = 'weo-treuhand-tab-'.$key;
                    $panel_id  = 'weo-treuhand-panel-'.$key;
                    ?>
                    <li class="weo-treuhand-tab<?php echo $is_active ? ' active' : ''; ?>" role="presentation">
                        <a
                            id="<?php echo esc_attr($tab_id); ?>"
                            class="nav-tab<?php echo $is_active ? ' nav-tab-active active' : ''; ?>"
                            href="<?php echo esc_url($tab_url); ?>"
                            role="tab"
                            aria-controls="<?php echo esc_attr($panel_id); ?>"
                            aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
                            data-tab-target="<?php echo esc_attr($key); ?>"
                        >
                            <?php echo esc_html($label); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
    <?php endif; ?>

    <?php
    include WEO_DIR.'templates/sk-treuhand-settings.php';
    include WEO_DIR.'templates/sk-treuhand-products.php';
    include WEO_DIR.'templates/sk-treuhand-orders.php';
    ?>
</div>
