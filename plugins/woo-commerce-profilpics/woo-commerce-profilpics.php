<?php
/**
 * Plugin Name: SatVendor Info in Produktkachel
 * Description: Zeigt Avatar + Store-Name über dem Produkttitel innerhalb der Produktkachel.
 * Version: 1.2.1
 * Author: Wario
 */

add_action('woocommerce_shop_loop_item_title', function () {
    global $product;

    $vendor = sk_get_vendor_by_product($product);
    if (!$vendor) return;

    $vendor_id   = $vendor->get_id();
    $store_info  = sk_get_store_info($vendor_id);
    $store_url   = sk_get_store_url($vendor_id);
    $store_name  = esc_html($store_info['store_name'] ?? $vendor->get_shop_name());
    $avatar_url  = get_avatar_url($vendor_id, ['size' => 64]);

    if (empty($avatar_url) || strpos($avatar_url, 'gravatar.com') !== false) {
        $avatar_url = get_stylesheet_directory_uri() . '/assets/default-avatar.jpg';
    }


    echo '<div class="produkt-vendor-info">';
    echo '<img class="vendor-avatar" src="' . esc_url($avatar_url) . '" alt="Avatar">';
    echo '<a class="vendor-name" href="' . esc_url($store_url) . '">@' . $store_name . '</a>';
    echo '</div>';
}, 5); // vor dem Produkttitel selbst

add_action('wp_head', function () {
    echo '<style>
    .produkt-vendor-info {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 10px;
        margin-top: 5px;
        padding-left: 10px;
    }

    .vendor-avatar {
        width: 26px;
        height: 26px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
    }

    .vendor-name {
        color: #ccc;
        font-size: 0.8rem;
        font-weight: 500;
        text-decoration: none;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 140px;
        display: inline-block;
        white-space: nowrap;
    }

    .vendor-name:hover {
        color: #f7931a;
    }
    </style>';
});
