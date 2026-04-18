<div id="sk-secondary" class="sk-store-sidebar" role="complementary">
    <?php if ( sk_get_option( 'enable_theme_store_sidebar', 'sk_appearance', 'off' ) === 'off' ) { ?>

        <div class="sk-widget-area widget-collapse">
            <?php do_action( 'sk_sidebar_store_before', $store_user->data, $store_info ); ?>
            <?php
            if ( ! dynamic_sidebar( 'sidebar-store' ) ) {
                $args = [
                    'before_widget' => '<aside class="widget sk-store-widget %s">',
                    'after_widget'  => '</aside>',
                    'before_title'  => '<h3 class="widget-title">',
                    'after_title'   => '</h3>',
                ];

                sk_store_category_widget();

                if ( ! empty( $map_location ) ) {
                    sk_store_location_widget();
                }

                sk_store_contact_widget();
            }
            ?>

            <?php do_action( 'sk_sidebar_store_after', $store_user->data, $store_info ); ?>
        </div>

    <?php } else { ?>
        <?php get_sidebar( 'store' ); ?>
    <?php } ?>

</div><!-- #secondary .widget-area -->
