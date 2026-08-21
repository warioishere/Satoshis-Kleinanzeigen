<?php
/**
 * Merkliste dashboard.
 *
 * Variables come from Merkliste::dashboard_view_data(), registered as
 * 'template_args' in the module config and run before this file is included.
 *
 * @var bool                                      $logged_in
 * @var array<int,array{type:string,text:string}> $notices
 * @var WC_Product[]                              $products
 */

if (!defined('ABSPATH')) {
    exit;
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

        <?php if (!$logged_in) : ?>
            <p>Bitte <a href="/mein-konto/">einloggen</a>, um deine Merkliste zu sehen.</p>
        <?php else : ?>

        <div class="sk-review-page-header">
            <h2><i class="fas fa-thumbtack"></i> Merkliste</h2>
        </div>

        <div class="merkliste-dashboard-wrapper">
            <div class="merkliste-dashboard-inner">

                <?php foreach ($notices as $notice) : ?>
                    <div class="sk-alert sk-alert-<?php echo esc_attr($notice['type']); ?>"><?php echo esc_html($notice['text']); ?></div>
                <?php endforeach; ?>

                <?php if (!empty($products)) : ?>
                    <ul class="merkliste-list">
                        <?php
                        foreach ($products as $product) :
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

        <?php endif; ?>

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
