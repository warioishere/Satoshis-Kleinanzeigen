<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!is_user_logged_in()) {
    echo '<p>Bitte <a href="/mein-konto/">einloggen</a>, um deine Merkliste zu sehen.</p>';
    return;
}

$user_id = get_current_user_id();

// Handle delete action (POST only — a link that deletes is fired by browser prefetch)
if (isset($_POST['delete_product']) && is_numeric($_POST['delete_product'])) {
    $delete_id = (int) $_POST['delete_product'];
    $nonce_ok = isset($_POST['_dm_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_dm_nonce'])), 'dm_del_' . $delete_id);

    if ($nonce_ok) {
        dm_remove_from_merkliste($delete_id, $user_id);
        echo '<div class="sk-alert sk-alert-success">Produkt von Merkliste entfernt.</div>';
    }
}

do_action('sk_dashboard_wrap_start');
?>

<div class="sk-dashboard-wrap">
    <?php
        /**
         * Hook: sk_dashboard_content_before
         *
         */
        do_action('sk_dashboard_content_before');
    ?>

    <div class="sk-dashboard-content sk-dashboard-content--merkliste">
        <?php
            /**
             * Hook: sk_dashboard_content_inside_before
             *
             */
            do_action('sk_dashboard_content_inside_before');
        ?>

        <div class="sk-review-page-header">
            <h2><i class="fas fa-thumbtack"></i> Merkliste</h2>
        </div>

        <div class="merkliste-dashboard-wrapper">
            <div class="merkliste-dashboard-inner">

                <?php
                // Resolve first: rows can point at products that are gone or no longer
                // published, and those must not count towards a non-empty list either.
                $merkliste_products = [];
                foreach (dm_get_merkliste_products($user_id) as $item) {
                    $product = wc_get_product($item->product_id);
                    if ($product && 'publish' === $product->get_status()) {
                        $merkliste_products[] = $product;
                    }
                }

                if (!empty($merkliste_products)) :
                ?>
                    <ul class="merkliste-list">
                        <?php
                        foreach ($merkliste_products as $product) :
                            $product_id = $product->get_id();

                            $vendor = sk_get_vendor_by_product($product);
                            $vendor_name = $vendor ? $vendor->get_shop_name() : __('Unbekannter Anbieter', 'sk-core');
                            $vendor_url = $vendor ? sk_get_store_url($vendor->get_id()) : '#';

                            $product_title = $product->get_name();
                            $product_url = get_permalink($product_id);
                            $product_price = $product->get_price_html();
                            $product_image = $product->get_image('thumbnail');

                        ?>
                            <li class="merkliste-item" data-product-id="<?php echo esc_attr($product_id); ?>">
                                <div class="merkliste-item-image">
                                    <a href="<?php echo esc_url($product_url); ?>" target="_blank" rel="noopener">
                                        <?php echo $product_image; ?>
                                    </a>
                                </div>
                                <div class="merkliste-item-details">
                                    <div class="merkliste-item-head">
                                        <strong class="merkliste-title">
                                            <a href="<?php echo esc_url($product_url); ?>" target="_blank" rel="noopener">
                                                <?php echo esc_html($product_title); ?>
                                            </a>
                                        </strong>
                                    </div>
                                    <div class="merkliste-vendor">
                                        <i class="fas fa-store"></i>
                                        <a href="<?php echo esc_url($vendor_url); ?>" target="_blank" rel="noopener">
                                            <?php echo esc_html($vendor_name); ?>
                                        </a>
                                    </div>
                                    <div class="merkliste-price">
                                        <?php echo $product_price; ?>
                                    </div>
                                </div>
                                <div class="merkliste-actions">
                                    <a class="btn btn-sm btn-primary" href="<?php echo esc_url($product_url); ?>" target="_blank" rel="noopener">
                                        <i class="fas fa-eye"></i> Ansehen
                                    </a>
                                    <form class="merkliste-remove-form" method="post" action="<?php echo esc_url(remove_query_arg(['delete_product', '_dm_nonce'])); ?>">
                                        <?php wp_nonce_field('dm_del_' . $product_id, '_dm_nonce'); ?>
                                        <input type="hidden" name="delete_product" value="<?php echo esc_attr($product_id); ?>">
                                        <button type="submit" class="btn btn-sm btn-danger dm-remove-from-list"
                                                data-product-id="<?php echo esc_attr($product_id); ?>">
                                            <i class="fas fa-trash"></i> Entfernen
                                        </button>
                                    </form>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <div class="merkliste-empty">
                        <i class="fas fa-thumbtack"></i>
                        <p>Deine Merkliste ist noch leer.</p>
                        <p>Wenn du beim Stöbern durch die Produkte interessante Artikel findest, kannst du sie mit dem Pin-Icon zur Merkliste hinzufügen.</p>
                        <a href="<?php echo esc_url(home_url('/shop')); ?>" class="btn btn-primary">
                            <i class="fas fa-shopping-bag"></i> Produkte entdecken
                        </a>
                    </div>
                <?php endif; ?>

            </div>
        </div>

        <?php
            /**
             * Hook: sk_dashboard_content_inside_after
             *
             */
            do_action('sk_dashboard_content_inside_after');
        ?>
    </div>

    <?php
        /**
         * Hook: sk_dashboard_content_after
         *
         */
        do_action('sk_dashboard_content_after');
    ?>
</div>

<?php do_action('sk_dashboard_wrap_end');
