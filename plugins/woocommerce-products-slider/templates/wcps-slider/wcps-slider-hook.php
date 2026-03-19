<?php
if (!defined('ABSPATH')) exit;  // if direct access

add_action('wcps_slider_main', 'wcps_slider_main_ribbon', 10);
function wcps_slider_main_ribbon($args)
{
    $wcps_id = isset($args['wcps_id']) ? (int) $args['wcps_id'] : 0;
    $wcps_options = get_post_meta($wcps_id, 'wcps_options', true);
    $slider_ribbon = isset($wcps_options['ribbon']) ? $wcps_options['ribbon'] : array();

    $ribbon_name = isset($slider_ribbon['ribbon_name']) ? $slider_ribbon['ribbon_name'] : '';
    $ribbon_custom = isset($slider_ribbon['ribbon_custom']) ? $slider_ribbon['ribbon_custom'] : '';
    $ribbon_position = isset($slider_ribbon['position']) ? $slider_ribbon['position'] : '';

    $ribbon_text = isset($slider_ribbon['text']) ? $slider_ribbon['text'] : '';
    $ribbon_background_img = isset($slider_ribbon['background_img']) ? $slider_ribbon['background_img'] : '';
    $ribbon_background_color = isset($slider_ribbon['background_color']) ? $slider_ribbon['background_color'] : '';
    $ribbon_text_color = isset($slider_ribbon['text_color']) ? $slider_ribbon['text_color'] : '';
    $ribbon_width = isset($slider_ribbon['width']) ? $slider_ribbon['width'] : '';
    $ribbon_height = isset($slider_ribbon['height']) ? $slider_ribbon['height'] : '';
    $ribbon_position = isset($slider_ribbon['position']) ? $slider_ribbon['position'] : '';



    if ($ribbon_name == 'none') {
        $ribbon_url = '';
    } elseif ($ribbon_name == 'custom') {
        $ribbon_url = $ribbon_custom;
    } else {
        $ribbon_url = wcps_plugin_url . 'assets/front/images/ribbons/' . $ribbon_name . '.png';
    }



    $ribbon_url = apply_filters('wcps_ribbon_img', $ribbon_url);

    //var_dump($slider_ribbon);

    if (!empty($ribbon_url)) :
?>
        <div class="wcps-ribbon <?php echo esc_attr($ribbon_position); ?>"><?php echo wp_kses_post($ribbon_text); ?></div>

        <style>
            <?php echo '.wcps-container-' . esc_attr($wcps_id) . ' .wcps-ribbon'; ?> {
                background-color: <?php echo esc_attr($ribbon_background_color); ?>;
                background-image: url("<?php echo esc_url($ribbon_background_img); ?>");
                color: <?php echo esc_attr($ribbon_text_color); ?>;
                width: <?php echo esc_attr($ribbon_width); ?>;
                height: <?php echo esc_attr($ribbon_height); ?>;
                text-align: center;
                text-transform: uppercase;
                background-repeat: no-repeat;
                background-size: 100%;
            }
        </style>
    <?php
    endif;
}



add_action('wcps_slider_main', 'wcps_slider_main_items', 20);

function wcps_slider_main_items($args)
{


    $wcps_id = isset($args['wcps_id']) ? (int) $args['wcps_id'] : 0;
    $wcps_options = get_post_meta($wcps_id, 'wcps_options', true);
    $slider_for = isset($wcps_options['slider_for']) ? $wcps_options['slider_for'] : 'products';
    if ($slider_for != 'products') return;

    $item_layout_id = isset($wcps_options['item_layout_id']) ? $wcps_options['item_layout_id'] : wcps_first_wcps_layout();



    $developer_options = isset($wcps_options['developer_options']) ? $wcps_options['developer_options'] : array();

    $query = isset($wcps_options['query']) ? $wcps_options['query'] : array();



    $posts_per_page = isset($query['posts_per_page']) ? $query['posts_per_page'] : 10;
    $query_order = isset($query['order']) ? $query['order'] : 'DESC';
    $query_orderby = isset($query['orderby']) ? $query['orderby'] : array();
    $query_ordberby_meta_key = isset($query['ordberby_meta_key']) ? $query['ordberby_meta_key'] : '';

    $hide_out_of_stock = isset($query['hide_out_of_stock']) ? $query['hide_out_of_stock'] : 'no_check';
    $product_featured = isset($query['product_featured']) ? $query['product_featured'] : 'no_check';
    $taxonomies = !empty($query['taxonomies']) ? $query['taxonomies'] : array();
    $taxonomy_relation = !empty($query['taxonomy_relation']) ? $query['taxonomy_relation'] : 'OR';


    $on_sale = isset($query['on_sale']) ? $query['on_sale'] : 'no';
    $catalog_visibility = isset($query['catalog_visibility']) ? $query['catalog_visibility'] : '';
    $product_ids = isset($query['product_ids']) ? $query['product_ids'] : '';
    $query_only = isset($query['query_only']) ? $query['query_only'] : 'no_check';


    $wcps_options = get_post_meta($wcps_id, 'wcps_options', true);

    $container = isset($wcps_options['container']) ? $wcps_options['container'] : array();
    $container_background_img_url = isset($container['background_img_url']) ? $container['background_img_url'] : '';
    $container_background_color = isset($container['background_color']) ? $container['background_color'] : '';
    $container_padding = isset($container['padding']) ? $container['padding'] : '';
    $container_margin = isset($container['margin']) ? $container['margin'] : '';

    $item_style = isset($wcps_options['item_style']) ? $wcps_options['item_style'] : array();
    $item_height = isset($wcps_options['item_height']) ? $wcps_options['item_height'] : array();

    $item_height_large = isset($item_height['large']) ? $item_height['large'] : '';
    $item_height_medium = isset($item_height['medium']) ? $item_height['medium'] : '';
    $item_height_small = isset($item_height['small']) ? $item_height['small'] : '';



    $item_padding = isset($item_style['padding']) ? $item_style['padding'] : '';
    //    $item_margin = isset($item_style['margin']) ? $item_style['margin'] : '10px';
    $item_background_color = isset($item_style['background_color']) ? $item_style['background_color'] : '';
    $item_text_align = isset($item_style['text_align']) ? $item_style['text_align'] : '';

    $slider_option = isset($wcps_options['slider']) ? $wcps_options['slider'] : array();

    $slider_column_large = isset($slider_option['column_large']) ? $slider_option['column_large'] : 3;
    $slider_column_medium = isset($slider_option['column_medium']) ? $slider_option['column_medium'] : 2;
    $slider_column_small = isset($slider_option['column_small']) ? $slider_option['column_small'] : 1;

    $slider_slideby_large = isset($slider_option['slideby_large']) ? $slider_option['slideby_large'] : 3;
    $slider_slideby_medium = isset($slider_option['slideby_medium']) ? $slider_option['slideby_medium'] : 2;
    $slider_slideby_small = isset($slider_option['slideby_small']) ? $slider_option['slideby_small'] : true;



    $slider_slide_speed = isset($slider_option['slide_speed']) ? $slider_option['slide_speed'] : 1000;
    //    $slider_pagination_speed = isset($slider_option['pagination_speed']) ? $slider_option['pagination_speed'] : 1200;
    $gutter = isset($slider_option['gutter']) ? $slider_option['gutter'] : 20;

    $slider_auto_play = isset($slider_option['auto_play']) ? $slider_option['auto_play'] : true;
    $auto_play_speed = !empty($slider_option['auto_play_speed']) ? $slider_option['auto_play_speed'] : 1000;
    $auto_play_timeout = !empty($slider_option['auto_play_timeout']) ? $slider_option['auto_play_timeout'] : 1200;

    //$auto_play_timeout = ($auto_play_speed >= $auto_play_timeout) ? $auto_play_speed + 1000 : $auto_play_timeout;

    $slider_rewind = isset($slider_option['rewind']) ? $slider_option['rewind'] : false;
    $slider_loop = isset($slider_option['loop']) ? $slider_option['loop'] : true;
    $slider_center = isset($slider_option['center']) ? (bool)$slider_option['center'] : true;
    $slider_stop_on_hover = isset($slider_option['stop_on_hover']) ? $slider_option['stop_on_hover'] : true;
    $slider_navigation = isset($slider_option['navigation']) ?  $slider_option['navigation'] : true;
    //$slider_navigation = ($slider_navigation) ? 'true' : 'false';


    $navigation_position = isset($slider_option['navigation_position']) ? $slider_option['navigation_position'] : '';
    $navigation_background_color = isset($slider_option['navigation_background_color']) ? $slider_option['navigation_background_color'] : '';
    $navigation_color = isset($slider_option['navigation_color']) ? $slider_option['navigation_color'] : '';
    $navigation_style = isset($slider_option['navigation_style']) ? $slider_option['navigation_style'] : 'flat';

    $dots_background_color = isset($slider_option['dots_background_color']) ? $slider_option['dots_background_color'] : '';
    $dots_active_background_color = isset($slider_option['dots_active_background_color']) ? $slider_option['dots_active_background_color'] : '';

    $slider_pagination = isset($slider_option['pagination']) ? $slider_option['pagination'] : true;
    $slider_pagination_count = isset($slider_option['pagination_count']) ? $slider_option['pagination_count'] : 0;
    $slider_rtl = isset($slider_option['rtl']) ? (bool)$slider_option['rtl'] : false;
    $slider_lazy_load = isset($slider_option['lazy_load']) ?  $slider_option['lazy_load'] : true;
    $slider_mouse_drag = isset($slider_option['mouse_drag']) ? $slider_option['mouse_drag'] : true;
    $slider_touch_drag = isset($slider_option['touch_drag']) ? $slider_option['touch_drag'] : true;

    $item_layout_id = isset($wcps_options['item_layout_id']) ? $wcps_options['item_layout_id'] : '';
    $layout_elements_data = get_post_meta($item_layout_id, 'layout_elements_data', true);
    $args['layout_id'] = $item_layout_id;


    $wcps_settings = get_option('wcps_settings');
    $font_aw_version = isset($wcps_settings['font_aw_version']) ? $wcps_settings['font_aw_version'] : 'none';

    if ($font_aw_version == 'v_5') {
        $navigation_text_prev = '<i class="fas fa-chevron-left"></i>';
        $navigation_text_next = '<i class="fas fa-chevron-right"></i>';
    } elseif ($font_aw_version == 'v_4') {
        $navigation_text_prev = '<i class="fa fa-chevron-left"></i>';
        $navigation_text_next = '<i class="fa fa-chevron-right"></i>';
    } else {
        $navigation_text_prev = '<i class="fas fa-chevron-left"></i>';
        $navigation_text_next = '<i class="fas fa-chevron-right"></i>';
    }


    $navigation_text_prev = !empty($slider_option['navigation_text']['prev']) ? $slider_option['navigation_text']['prev'] : $navigation_text_prev;
    $navigation_text_next = !empty($slider_option['navigation_text']['next']) ? $slider_option['navigation_text']['next'] : $navigation_text_next;









    //if(empty($post_id)) return;
    $query_args = array();

    $tax_query = array();
    $query_orderby_new = array();

    $query_args['post_type'] = 'product';


    if (!empty($query_orderby))
        foreach ($query_orderby as $elementIndex => $argData) {
            $arg_order = isset($argData['arg_order']) ? $argData['arg_order'] : '';
            if (!empty($arg_order))
                $query_orderby_new[$elementIndex]  = $arg_order;
        }


    if (!empty($query_orderby_new))
        $query_args['orderby'] = $query_orderby_new;

    if (!empty($query_ordberby_meta_key))
        $query_args['meta_key']         = $query_ordberby_meta_key;

    $query_args['order']              = $query_order;
    $query_args['posts_per_page']     = $posts_per_page;

    foreach ($taxonomies as $taxonomy => $taxonomyData) {

        $terms = !empty($taxonomyData['terms']) ? $taxonomyData['terms'] : array();
        $terms_relation = !empty($taxonomyData['terms_relation']) ? $taxonomyData['terms_relation'] : 'OR';

        if (!empty($terms)) {
            $tax_query[] = array(
                'taxonomy' => $taxonomy,
                'field'    => 'term_id',
                'terms'    => $terms,
                'operator'    => $terms_relation,
            );
        }
    }





    if ($hide_out_of_stock == 'yes') {
        $tax_query[] = array(
            'taxonomy' => 'product_visibility',
            'field' => 'name',
            'terms' => 'outofstock',
            'operator' => 'NOT IN',
        );
    }


    if ($product_featured == 'no') {
        $tax_query[] = array(
            'taxonomy' => 'product_visibility',
            'field' => 'name',
            'terms' => 'featured',
            'operator' => 'NOT IN',
        );
    }



    if ($on_sale == 'no') {
        $wc_get_product_ids_on_sale = wc_get_product_ids_on_sale();
        $query_args['post__not_in'] = $wc_get_product_ids_on_sale;
    }

    if (!empty($catalog_visibility)) {

        if ($catalog_visibility == 'hidden') {
            $tax_query[] = array(
                'taxonomy' => 'product_visibility',
                'field'    => 'name',
                'terms'    => 'exclude-from-catalog',
                'operator' => 'IN',
            );
        }

        if ($catalog_visibility == 'visible') {
            $tax_query[] = array(
                'taxonomy' => 'product_visibility',
                'field'    => 'name',
                'terms'    => 'exclude-from-catalog',
                'operator' => 'NOT IN',
            );
        }
        if ($catalog_visibility == 'catalog') {
            $tax_query[] = array(
                'taxonomy' => 'product_visibility',
                'field'    => 'name',
                'terms'    => 'exclude-from-catalog',
                'operator' => 'NOT IN',
            );
        }
        if ($catalog_visibility == 'search') {
            $tax_query[] = array(
                'taxonomy' => 'product_visibility',
                'field'    => 'name',
                'terms'    => 'exclude-from-search',
                'operator' => 'IN',
            );
        }
    }



    if (!empty($product_ids)) {

        $product_ids = array_map('intval', explode(',', $product_ids));
        $query_args['post__in'] = $product_ids;
    }


    if ($query_only == 'on_sale') {
        $wc_get_product_ids_on_sale = wc_get_product_ids_on_sale();
        $query_args['post__in'] = $wc_get_product_ids_on_sale;
    } elseif ($query_only == 'featured') {

        $tax_query[] = array(
            'taxonomy' => 'product_visibility',
            'field' => 'name',
            'terms' => 'featured',
            'operator' => 'IN',
        );
    } elseif ($query_only == 'in_stock') {

        $tax_query[] = array(
            'taxonomy' => 'product_visibility',
            'field' => 'name',
            'terms' => 'outofstock',
            'operator' => 'NOT IN',
        );
    }






    if (!empty($tax_query))
        $query_args['tax_query'] = array_merge(array('relation' => $taxonomy_relation), $tax_query);


    //echo var_export('<pre>' . var_export($tax_query, true) . '</pre>');

    $query_args = apply_filters('wcps_slider_query_args', $query_args, $args);

    if (in_array('query_args', $developer_options)) {
        echo 'query_args: ############';
        echo '<pre>' . esc_html(var_export($query_args, true)) . '</pre>';
    }



    $wcps_query = new WP_Query($query_args);

    if (in_array('found_posts', $developer_options)) {

        echo 'found_posts: ############';
        echo '<pre>' . esc_html(var_export(((int) $wcps_query->found_posts), true)) . '</pre>';
    }


    if ($slider_rtl == 1) {
        $slider_rtl = 'rtl';
    } elseif ($slider_rtl == 0) {
        $slider_rtl = 'ltr';
    } elseif ($slider_rtl == 'ttb') {
        $slider_rtl = 'ttb';
    }


    $sliderOptions = [
        "type" => $slider_loop ? "loop" : "slide",

        "clones" => 2,
        "perPage" => (int) $slider_column_large,
        "perMove" => (int)$slider_slideby_large,
        "arrows" => (bool)$slider_navigation,
        "pagination" => (bool)$slider_pagination,
        //"paginationDirection" => $slider_rtl ? 'rtl' : 'ltr',
        "autoplay" => (bool)$slider_auto_play,
        "interval" => $auto_play_timeout,
        "lazyLoad" => (bool)$slider_lazy_load,
        "pauseOnHover" => (bool)$slider_stop_on_hover,
        "pauseOnFocus" => (bool)$slider_stop_on_hover,
        "autoHeight" => false,
        // "rewind" => (bool)$slider_rewind,
        "speed" => (int)$auto_play_speed,
        //"rewindSpeed" => $auto_play_speed,
        // "rewindByDrag" => true,
        "drag" => $slider_mouse_drag,
        "direction" => $slider_rtl,
        "gap" => (int)$gutter,
        "breakpoints" => [
            "1200" => [
                "perPage" => (int) $slider_column_large,
                "perMove" => (int) $slider_slideby_large,

            ],
            "768" => [
                "perPage" => (int) $slider_column_medium,
                "perMove" => (int) $slider_slideby_medium,

            ],
            "576" => [
                "perPage" => (int) $slider_column_small,
                "perMove" => (int) $slider_slideby_small,

            ],
        ],

        //"focus" => $slider_center ? 'center' : 99999,
    ];
    $prevIconHtml = wp_specialchars_decode($navigation_text_prev, ENT_QUOTES);
    $prevIconPosition = 'before';
    $nextIconPosition = 'after';
    $nextIconHtml = wp_specialchars_decode($navigation_text_next, ENT_QUOTES);


    if ($wcps_query->have_posts()) :

        $wcps_items_class = apply_filters('wcps_items_wrapper_class', ' wcps-items ', $args);

        do_action('wcps_slider_before_items', $wcps_query, $args);

    ?>




        <div id="wcps-<?php echo esc_attr($wcps_id); ?>" class="<?php echo esc_attr($wcps_items_class); ?> splide" data-splide="<?php echo esc_attr(json_encode($sliderOptions)) ?>">


            <div class="splide__arrows <?php echo esc_attr($navigation_position); ?> <?php echo esc_attr($navigation_style); ?>">
                <div class='prev splide__arrow splide__arrow--prev'>

                    <?php if ($prevIconPosition == 'before') : ?>
                        <span class='icon'>
                            <?php echo wp_kses_post($prevIconHtml); ?>
                        </span>
                    <?php endif; ?>

                    <?php if (!empty($prevText)) : ?>
                        <span>
                            <?php echo esc_attr($prevText); ?>
                        </span>
                    <?php endif; ?>



                    <?php if ($prevIconPosition == 'after') : ?>
                        <span class='icon'>
                            <?php echo wp_kses_post($prevIconHtml); ?>
                        </span>
                    <?php endif; ?>

                </div>
                <div class='next splide__arrow splide__arrow--next'>



                    <?php if ($nextIconPosition == 'before') : ?>
                        <span class='icon'>
                            <?php echo wp_kses_post($nextIconHtml); ?>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($nextText)) : ?>
                        <span>
                            <?php echo esc_attr($nextText); ?>
                        </span>
                    <?php endif; ?>



                    <?php if ($nextIconPosition == 'after') : ?>
                        <span class='icon'>
                            <?php echo wp_kses_post($nextIconHtml); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>




            <div class="splide__track">
                <ul class="splide__list">
                    <?php

                    $loop_count = 1;
                    while ($wcps_query->have_posts()) : $wcps_query->the_post();

                        $product_id = get_the_id();
                        $args['product_id'] = $product_id;
                        $args['loop_count'] = $loop_count;



                        do_action('wcps_slider_item', $args);

                        $loop_count++;
                    endwhile;

                    wp_reset_postdata();
                    ?>
                </ul>
            </div>
            <ul class="splide__pagination "></ul>

        </div>

        <?php


        do_action('wcps_slider_after_items', $wcps_query, $args);

        ?>

    <?php
    else :
        do_action('wcps_slider_no_item');
    endif;
}




add_action('wcps_slider_item', 'wcps_slider_item', 10);

function wcps_slider_item($args)
{

    $wcps_id = isset($args['wcps_id']) ? $args['wcps_id'] : '';

    $wcps_options = get_post_meta($wcps_id, 'wcps_options', true);


    $defaultLayout_data = array(
        0 =>
        array(
            'wrapper_start' =>
            array(
                'wrapper_id' => '',
                'wrapper_class' => 'layer-media',
                'css_idle' => '',
                'margin' => '',
            ),
        ),
        1 =>
        array(
            'thumbnail' =>
            array(
                'thumb_size' => 'full',
                'thumb_height' =>
                array(
                    'large' => '1000px',
                    'medium' => '',
                    'small' => '',
                ),
                'default_thumb_src' => wcps_plugin_url . 'assets/front/images/no-thumb.png',
                'margin' => '',
                'link_to' => 'none',
            ),
        ),
        2 =>
        array(
            'wrapper_end' =>
            array(
                'wrapper_id' => '',
            ),
        ),
        3 =>
        array(
            'wrapper_start' =>
            array(
                'wrapper_id' => '',
                'wrapper_class' => 'layer-content',
                'css_idle' => '',
                'margin' => '',
            ),
        ),
        5 =>
        array(
            'post_title' =>
            array(
                'color' => '#000000',
                'font_size' => '16px',
                'font_family' => '',
                'margin' => '10px 0',
                'text_align' => 'left',
                'link_to' => 'product_link',
            ),
        ),

        8 =>
        array(
            'product_price' =>
            array(
                'price_type' => 'full',
                'wrapper_html' => '',
                'color' => '#000000',
                'margin' => '10px 0',
                'font_size' => '',
                'text_align' => 'left',
            ),
        ),
        10 =>
        array(
            'add_to_cart' =>
            array(
                'background_color' => '#1e73be',
                'color' => '#ffffff',
                'show_quantity' => 'no',
                'margin' => '',
            ),
        ),
        12 =>
        array(
            'wrapper_end' =>
            array(
                'wrapper_id' => '',
            ),
        ),
    );







    $item_layout_id = isset($wcps_options['item_layout_id']) ? $wcps_options['item_layout_id'] : wcps_first_wcps_layout();
    $layout_elements_data = get_post_meta($item_layout_id, 'layout_elements_data', true);

    $wcps_item_class = apply_filters('wcps_slider_item_class', 'item splide__slide ', $args);

    $layout_elements_data = !empty($layout_elements_data) ? $layout_elements_data : $defaultLayout_data;



    ?>




    <div class="<?php echo esc_attr($wcps_item_class); ?> ">
        <div class="elements-wrapper layout-<?php echo esc_attr($item_layout_id); ?>">
            <?php
            if (!empty($layout_elements_data))
                foreach ($layout_elements_data as $elementGroupIndex => $elementGroupData) {

                    if (!empty($elementGroupData))
                        foreach ($elementGroupData as $elementIndex => $elementData) {

                            $args['elementData'] = $elementData;
                            $args['element_index'] = $elementGroupIndex;



                            do_action('wcps_layout_element_' . $elementIndex, $args);
                        }
                }
            ?>
        </div>
    </div>
    <?php

}








//add_action('wcps_slider_main', 'wcps_slider_main_items_orders', 20);

function wcps_slider_main_items_orders($args)
{


    $wcps_id = isset($args['wcps_id']) ? (int) $args['wcps_id'] : 0;
    $wcps_options = get_post_meta($wcps_id, 'wcps_options', true);
    $slider_for = isset($wcps_options['slider_for']) ? $wcps_options['slider_for'] : 'products';
    if ($slider_for != 'orders') return;

    $item_layout_id = isset($wcps_options['item_layout_id']) ? $wcps_options['item_layout_id'] : wcps_first_wcps_layout();



    $developer_options = isset($wcps_options['developer_options']) ? $wcps_options['developer_options'] : array();

    $query_orders = isset($wcps_options['query_orders']) ? $wcps_options['query_orders'] : array();

    $posts_per_page = isset($query_orders['posts_per_page']) ? $query_orders['posts_per_page'] : 10;
    $query_order = isset($query_orders['order']) ? $query_orders['order'] : 'DESC';
    $query_orderby = isset($query_orders['orderby']) ? $query_orders['orderby'] : 'date';
    $product_ids = isset($query_orders['post_ids']) ? $query_orders['post_ids'] : '';

    //if(empty($post_id)) return;
    $query_args = array();

    $query_args['post_type'] = 'shop_order';
    $query_args['post_status'] = 'any';


    $query_args['orderby'] = $query_orderby;

    $query_args['order']              = $query_order;
    $query_args['posts_per_page']     = $posts_per_page;




    if (!empty($product_ids)) {

        $product_ids = array_map('intval', explode(',', $product_ids));
        $query_args['post__in'] = $product_ids;
    }


    $query_args = apply_filters('wcps_slider_query_args', $query_args, $args);

    if (in_array('query_args', $developer_options)) {
        echo 'query_args: ############';
        echo '<pre>' . esc_html(var_export($query_args, true)) . '</pre>';
    }



    //echo '<pre>'.var_export($query_args, true).'</pre>';
    $wcps_query = new WP_Query($query_args);

    if (in_array('found_posts', $developer_options)) {

        echo 'found_posts: ############';
        echo '<pre>' . esc_html(var_export(((int) $wcps_query->found_posts), true)) . '</pre>';
    }



    if ($wcps_query->have_posts()) :

        $wcps_items_class = apply_filters('wcps_items_wrapper_class', 'wcps-items  ', $args);

        do_action('wcps_slider_before_items', $wcps_query, $args);

    ?>
        <div id="wcps-<?php echo esc_attr($wcps_id); ?>" class="<?php echo esc_attr($wcps_items_class); ?> splide">
            <?php

            $loop_count = 1;
            while ($wcps_query->have_posts()) : $wcps_query->the_post();

                $product_id = get_the_id();
                $args['post_id'] = $product_id;
                $args['loop_count'] = $loop_count;



                do_action('wcps_slider_item_order', $args);

                $loop_count++;
            endwhile;

            wp_reset_postdata();
            ?>
        </div>

        <?php


        do_action('wcps_slider_after_items', $wcps_query, $args);

        ?>

    <?php
    else :
        do_action('wcps_slider_no_item');
    endif;
}



//add_action('wcps_slider_item_order', 'wcps_slider_item_order', 10);

function wcps_slider_item_order($args)
{

    $first_term_id = (int) wcps_get_first_category_id();

    $wcps_id = isset($args['wcps_id']) ? $args['wcps_id'] : '';
    $post_id = isset($args['post_id']) ? $args['post_id'] : $first_term_id;

    $wcps_options = get_post_meta($wcps_id, 'wcps_options', true);
    $item_layout_id = isset($wcps_options['item_layout_id']) ? $wcps_options['item_layout_id'] : wcps_first_wcps_layout();
    $layout_elements_data = get_post_meta($item_layout_id, 'layout_elements_data', true);

    $wcps_item_class = apply_filters('wcps_slider_item_class', 'item splide__slide ', $args);

    ?>
    <div class="<?php echo esc_attr($wcps_item_class); ?>">
        <div class="elements-wrapper layout-<?php echo esc_attr($item_layout_id); ?>">
            <?php
            if (!empty($layout_elements_data))
                foreach ($layout_elements_data as $elementGroupIndex => $elementGroupData) {

                    if (!empty($elementGroupData))
                        foreach ($elementGroupData as $elementIndex => $elementData) {

                            $args['elementData'] = $elementData;
                            $args['element_index'] = $elementGroupIndex;


                            do_action('wcps_layout_element_' . $elementIndex, $args);
                        }
                }
            ?>
        </div>
    </div>
    <?php

}






add_action('wcps_slider_main', 'wcps_slider_main_items_dokan_vendors', 20);

function wcps_slider_main_items_dokan_vendors($args)
{

    $wcps_id = isset($args['wcps_id']) ? (int) $args['wcps_id'] : 0;


    $wcps_options = get_post_meta($wcps_id, 'wcps_options', true);

    $container = isset($wcps_options['container']) ? $wcps_options['container'] : array();
    $container_background_img_url = isset($container['background_img_url']) ? $container['background_img_url'] : '';
    $container_background_color = isset($container['background_color']) ? $container['background_color'] : '';
    $container_padding = isset($container['padding']) ? $container['padding'] : '';
    $container_margin = isset($container['margin']) ? $container['margin'] : '';

    $item_style = isset($wcps_options['item_style']) ? $wcps_options['item_style'] : array();
    $item_height = isset($wcps_options['item_height']) ? $wcps_options['item_height'] : array();

    $item_height_large = isset($item_height['large']) ? $item_height['large'] : '';
    $item_height_medium = isset($item_height['medium']) ? $item_height['medium'] : '';
    $item_height_small = isset($item_height['small']) ? $item_height['small'] : '';



    $item_padding = isset($item_style['padding']) ? $item_style['padding'] : '';
    //    $item_margin = isset($item_style['margin']) ? $item_style['margin'] : '10px';
    $item_background_color = isset($item_style['background_color']) ? $item_style['background_color'] : '';
    $item_text_align = isset($item_style['text_align']) ? $item_style['text_align'] : '';

    $slider_option = isset($wcps_options['slider']) ? $wcps_options['slider'] : array();

    $slider_column_large = isset($slider_option['column_large']) ? $slider_option['column_large'] : 3;
    $slider_column_medium = isset($slider_option['column_medium']) ? $slider_option['column_medium'] : 2;
    $slider_column_small = isset($slider_option['column_small']) ? $slider_option['column_small'] : 1;

    $slider_slideby_large = isset($slider_option['slideby_large']) ? $slider_option['slideby_large'] : 3;
    $slider_slideby_medium = isset($slider_option['slideby_medium']) ? $slider_option['slideby_medium'] : 2;
    $slider_slideby_small = isset($slider_option['slideby_small']) ? $slider_option['slideby_small'] : true;



    $slider_slide_speed = isset($slider_option['slide_speed']) ? $slider_option['slide_speed'] : 1000;
    //    $slider_pagination_speed = isset($slider_option['pagination_speed']) ? $slider_option['pagination_speed'] : 1200;
    $gutter = isset($slider_option['gutter']) ? $slider_option['gutter'] : 20;

    $slider_auto_play = isset($slider_option['auto_play']) ? $slider_option['auto_play'] : true;
    $auto_play_speed = !empty($slider_option['auto_play_speed']) ? $slider_option['auto_play_speed'] : 1000;
    $auto_play_timeout = !empty($slider_option['auto_play_timeout']) ? $slider_option['auto_play_timeout'] : 1200;

    //$auto_play_timeout = ($auto_play_speed >= $auto_play_timeout) ? $auto_play_speed + 1000 : $auto_play_timeout;

    $slider_rewind = isset($slider_option['rewind']) ? $slider_option['rewind'] : false;
    $slider_loop = isset($slider_option['loop']) ? $slider_option['loop'] : true;
    $slider_center = isset($slider_option['center']) ? (bool)$slider_option['center'] : true;
    $slider_stop_on_hover = isset($slider_option['stop_on_hover']) ? $slider_option['stop_on_hover'] : true;
    $slider_navigation = isset($slider_option['navigation']) ?  $slider_option['navigation'] : true;
    //$slider_navigation = ($slider_navigation) ? 'true' : 'false';


    $navigation_position = isset($slider_option['navigation_position']) ? $slider_option['navigation_position'] : '';
    $navigation_background_color = isset($slider_option['navigation_background_color']) ? $slider_option['navigation_background_color'] : '';
    $navigation_color = isset($slider_option['navigation_color']) ? $slider_option['navigation_color'] : '';
    $navigation_style = isset($slider_option['navigation_style']) ? $slider_option['navigation_style'] : 'flat';

    $dots_background_color = isset($slider_option['dots_background_color']) ? $slider_option['dots_background_color'] : '';
    $dots_active_background_color = isset($slider_option['dots_active_background_color']) ? $slider_option['dots_active_background_color'] : '';

    $slider_pagination = isset($slider_option['pagination']) ? $slider_option['pagination'] : true;
    $slider_pagination_count = isset($slider_option['pagination_count']) ? $slider_option['pagination_count'] : 0;
    $slider_rtl = isset($slider_option['rtl']) ? (bool)$slider_option['rtl'] : false;
    $slider_lazy_load = isset($slider_option['lazy_load']) ?  $slider_option['lazy_load'] : true;
    $slider_mouse_drag = isset($slider_option['mouse_drag']) ? $slider_option['mouse_drag'] : true;
    $slider_touch_drag = isset($slider_option['touch_drag']) ? $slider_option['touch_drag'] : true;

    $item_layout_id = isset($wcps_options['item_layout_id']) ? $wcps_options['item_layout_id'] : '';
    $layout_elements_data = get_post_meta($item_layout_id, 'layout_elements_data', true);
    $args['layout_id'] = $item_layout_id;


    $wcps_settings = get_option('wcps_settings');
    $font_aw_version = isset($wcps_settings['font_aw_version']) ? $wcps_settings['font_aw_version'] : 'none';

    if ($font_aw_version == 'v_5') {
        $navigation_text_prev = '<i class="fas fa-chevron-left"></i>';
        $navigation_text_next = '<i class="fas fa-chevron-right"></i>';
    } elseif ($font_aw_version == 'v_4') {
        $navigation_text_prev = '<i class="fa fa-chevron-left"></i>';
        $navigation_text_next = '<i class="fa fa-chevron-right"></i>';
    } else {
        $navigation_text_prev = '<i class="fas fa-chevron-left"></i>';
        $navigation_text_next = '<i class="fas fa-chevron-right"></i>';
    }


    $navigation_text_prev = !empty($slider_option['navigation_text']['prev']) ? $slider_option['navigation_text']['prev'] : $navigation_text_prev;
    $navigation_text_next = !empty($slider_option['navigation_text']['next']) ? $slider_option['navigation_text']['next'] : $navigation_text_next;


    $sliderOptions = [
        "type" => $slider_loop ? "loop" : "slide",

        "clones" => 2,
        "perPage" => (int) $slider_column_large,
        "perMove" => (int)$slider_slideby_large,
        "arrows" => (bool)$slider_navigation,
        "pagination" => (bool)$slider_pagination,
        //"paginationDirection" => $slider_rtl ? 'rtl' : 'ltr',
        "autoplay" => (bool)$slider_auto_play,
        "interval" => $auto_play_timeout,
        "lazyLoad" => (bool)$slider_lazy_load,
        "pauseOnHover" => (bool)$slider_stop_on_hover,
        "pauseOnFocus" => (bool)$slider_stop_on_hover,
        "autoHeight" => false,
        // "rewind" => (bool)$slider_rewind,
        "speed" => (int)$auto_play_speed,
        //"rewindSpeed" => $auto_play_speed,
        // "rewindByDrag" => true,
        "drag" => $slider_mouse_drag,
        "direction" => $slider_rtl,
        "gap" => (int)$gutter,
        "breakpoints" => [
            "1200" => [
                "perPage" => (int) $slider_column_large,
                "perMove" => (int) $slider_slideby_large,

            ],
            "768" => [
                "perPage" => (int) $slider_column_medium,
                "perMove" => (int) $slider_slideby_medium,

            ],
            "576" => [
                "perPage" => (int) $slider_column_small,
                "perMove" => (int) $slider_slideby_small,

            ],
        ],

        //"focus" => $slider_center ? 'center' : 99999,
    ];

    $prevIconHtml = wp_specialchars_decode($navigation_text_prev, ENT_QUOTES);
    $prevIconPosition = 'before';
    $nextIconPosition = 'after';
    $nextIconHtml = wp_specialchars_decode($navigation_text_next, ENT_QUOTES);











    $wcps_id = isset($args['wcps_id']) ? (int) $args['wcps_id'] : 0;
    $wcps_options = get_post_meta($wcps_id, 'wcps_options', true);
    $slider_for = isset($wcps_options['slider_for']) ? $wcps_options['slider_for'] : 'products';
    if ($slider_for != 'dokan_vendors') return;

    $item_layout_id = isset($wcps_options['item_layout_id']) ? $wcps_options['item_layout_id'] : wcps_first_wcps_layout();



    $developer_options = isset($wcps_options['developer_options']) ? $wcps_options['developer_options'] : array();

    $dokan_vendors_query = isset($wcps_options['dokan_vendors_query']) ? $wcps_options['dokan_vendors_query'] : array();

    $posts_per_page = isset($dokan_vendors_query['posts_per_page']) ? $dokan_vendors_query['posts_per_page'] : 10;
    $query_order = isset($dokan_vendors_query['order']) ? $dokan_vendors_query['order'] : 'DESC';
    $query_orderby = isset($dokan_vendors_query['orderby']) ? $dokan_vendors_query['orderby'] : 'date';
    $product_ids = isset($dokan_vendors_query['post_ids']) ? $dokan_vendors_query['post_ids'] : '';

    //if(empty($post_id)) return;
    $query_args = array();

    //$query_args['role__in'] 	= array('shop_vendor');
    $query_args['orderby'] = $query_orderby;
    $query_args['order']              = $query_order;
    $query_args['number']     = $posts_per_page;








    $query_args = apply_filters('wcps_slider_query_dokan_vendors_args', $query_args, $args);




    $authors = get_users($query_args);
    //$authors = $wp_user_query->get_results();


    if (!empty($authors)) :

        $wcps_items_class = apply_filters('wcps_items_wrapper_class', 'wcps-items  ', $args);


    ?>
        <div id="wcps-<?php echo esc_attr($wcps_id); ?>" class="<?php echo esc_attr($wcps_items_class); ?> splide" data-splide="<?php echo esc_attr(json_encode($sliderOptions)) ?>">


            <div class="splide__arrows <?php echo esc_attr($navigation_position); ?> <?php echo esc_attr($navigation_style); ?>">
                <div class='prev splide__arrow splide__arrow--prev'>

                    <?php if ($prevIconPosition == 'before') : ?>
                        <span class='icon'>
                            <?php echo wp_kses_post($prevIconHtml); ?>
                        </span>
                    <?php endif; ?>

                    <?php if (!empty($prevText)) : ?>
                        <span>
                            <?php echo esc_attr($prevText); ?>
                        </span>
                    <?php endif; ?>



                    <?php if ($prevIconPosition == 'after') : ?>
                        <span class='icon'>
                            <?php echo wp_kses_post($prevIconHtml); ?>
                        </span>
                    <?php endif; ?>

                </div>
                <div class='next splide__arrow splide__arrow--next'>



                    <?php if ($nextIconPosition == 'before') : ?>
                        <span class='icon'>
                            <?php echo wp_kses_post($nextIconHtml); ?>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($nextText)) : ?>
                        <span>
                            <?php echo esc_attr($nextText); ?>
                        </span>
                    <?php endif; ?>



                    <?php if ($nextIconPosition == 'after') : ?>
                        <span class='icon'>
                            <?php echo wp_kses_post($nextIconHtml); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>


            <div class="splide__track">
                <ul class="splide__list">
                    <?php

                    $loop_count = 1;
                    foreach ($authors as $author) {

                        $args['user_id'] = $author->ID;
                        $args['loop_count'] = $loop_count;

                        do_action('wcps_slider_item_dokan_vendor', $args);

                        $loop_count++;
                    }
                    ?>
                </ul>
            </div>
            <ul class="splide__pagination "></ul>


        </div>

    <?php
    else :
        do_action('wcps_slider_no_item');
    endif;
}



add_action('wcps_slider_item_dokan_vendor', 'wcps_slider_item_dokan_vendors', 10);

function wcps_slider_item_dokan_vendors($args)
{

    $first_user_id = (int) wcps_get_first_dokan_vendor_id();

    $wcps_id = isset($args['wcps_id']) ? $args['wcps_id'] : $first_user_id;
    $user_id = isset($args['user_id']) ? $args['user_id'] : '';

    $wcps_options = get_post_meta($wcps_id, 'wcps_options', true);
    $item_layout_id = isset($wcps_options['item_layout_id']) ? $wcps_options['item_layout_id'] : wcps_first_wcps_layout();
    $layout_elements_data = get_post_meta($item_layout_id, 'layout_elements_data', true);

    $wcps_item_class = apply_filters('wcps_slider_item_class', 'item splide__slide ', $args);


    ?>
    <div class="<?php echo esc_attr($wcps_item_class); ?>">
        <div class="elements-wrapper layout-<?php echo esc_attr($item_layout_id); ?>">
            <?php
            if (!empty($layout_elements_data))
                foreach ($layout_elements_data as $elementGroupIndex => $elementGroupData) {

                    if (!empty($elementGroupData))
                        foreach ($elementGroupData as $elementIndex => $elementData) {

                            $args['elementData'] = $elementData;
                            $args['element_index'] = $elementGroupIndex;

                            do_action('wcps_layout_element_' . $elementIndex, $args);
                        }
                }
            ?>
        </div>
    </div>
    <?php

}












add_action('wcps_slider_main', 'wcps_slider_main_items_categories', 20);

function wcps_slider_main_items_categories($args)
{


    $wcps_id = isset($args['wcps_id']) ? (int) $args['wcps_id'] : 0;
    $wcps_options = get_post_meta($wcps_id, 'wcps_options', true);
    $slider_for = isset($wcps_options['slider_for']) ? $wcps_options['slider_for'] : 'products';
    if ($slider_for != 'categories') return;

    $item_layout_id = isset($wcps_options['item_layout_id']) ? $wcps_options['item_layout_id'] : wcps_first_wcps_layout();

    $query = !empty($wcps_options['query_categories']) ? $wcps_options['query_categories'] : array();
    $taxonomies = !empty($query['taxonomies']) ? $query['taxonomies'] : array();


    $slider_option = isset($wcps_options['slider']) ? $wcps_options['slider'] : array();

    $slider_column_large = isset($slider_option['column_large']) ? $slider_option['column_large'] : 3;
    $slider_column_medium = isset($slider_option['column_medium']) ? $slider_option['column_medium'] : 2;
    $slider_column_small = isset($slider_option['column_small']) ? $slider_option['column_small'] : 1;

    $slider_slideby_large = isset($slider_option['slideby_large']) ? $slider_option['slideby_large'] : 3;
    $slider_slideby_medium = isset($slider_option['slideby_medium']) ? $slider_option['slideby_medium'] : 2;
    $slider_slideby_small = isset($slider_option['slideby_small']) ? $slider_option['slideby_small'] : true;



    $slider_slide_speed = isset($slider_option['slide_speed']) ? $slider_option['slide_speed'] : 1000;
    //    $slider_pagination_speed = isset($slider_option['pagination_speed']) ? $slider_option['pagination_speed'] : 1200;
    $gutter = isset($slider_option['gutter']) ? $slider_option['gutter'] : 20;

    $slider_auto_play = isset($slider_option['auto_play']) ? $slider_option['auto_play'] : true;
    $auto_play_speed = !empty($slider_option['auto_play_speed']) ? $slider_option['auto_play_speed'] : 1000;
    $auto_play_timeout = !empty($slider_option['auto_play_timeout']) ? $slider_option['auto_play_timeout'] : 1200;

    //$auto_play_timeout = ($auto_play_speed >= $auto_play_timeout) ? $auto_play_speed + 1000 : $auto_play_timeout;

    $slider_rewind = isset($slider_option['rewind']) ? $slider_option['rewind'] : false;
    $slider_loop = isset($slider_option['loop']) ? $slider_option['loop'] : true;
    $slider_center = isset($slider_option['center']) ? (bool)$slider_option['center'] : true;
    $slider_stop_on_hover = isset($slider_option['stop_on_hover']) ? $slider_option['stop_on_hover'] : true;
    $slider_navigation = isset($slider_option['navigation']) ?  $slider_option['navigation'] : true;
    //$slider_navigation = ($slider_navigation) ? 'true' : 'false';

    $navigation_position = isset($slider_option['navigation_position']) ? $slider_option['navigation_position'] : '';
    $navigation_background_color = isset($slider_option['navigation_background_color']) ? $slider_option['navigation_background_color'] : '';
    $navigation_color = isset($slider_option['navigation_color']) ? $slider_option['navigation_color'] : '';
    $navigation_style = isset($slider_option['navigation_style']) ? $slider_option['navigation_style'] : 'flat';

    $dots_background_color = isset($slider_option['dots_background_color']) ? $slider_option['dots_background_color'] : '';
    $dots_active_background_color = isset($slider_option['dots_active_background_color']) ? $slider_option['dots_active_background_color'] : '';

    $slider_pagination = isset($slider_option['pagination']) ? $slider_option['pagination'] : true;
    $slider_pagination_count = isset($slider_option['pagination_count']) ? $slider_option['pagination_count'] : 0;
    $slider_rtl = isset($slider_option['rtl']) ? (bool)$slider_option['rtl'] : false;
    $slider_lazy_load = isset($slider_option['lazy_load']) ?  $slider_option['lazy_load'] : true;
    $slider_mouse_drag = isset($slider_option['mouse_drag']) ? $slider_option['mouse_drag'] : true;
    $slider_touch_drag = isset($slider_option['touch_drag']) ? $slider_option['touch_drag'] : true;


    $wcps_settings = get_option('wcps_settings');
    $font_aw_version = isset($wcps_settings['font_aw_version']) ? $wcps_settings['font_aw_version'] : 'none';

    if ($font_aw_version == 'v_5') {
        $navigation_text_prev = '<i class="fas fa-chevron-left"></i>';
        $navigation_text_next = '<i class="fas fa-chevron-right"></i>';
    } elseif ($font_aw_version == 'v_4') {
        $navigation_text_prev = '<i class="fa fa-chevron-left"></i>';
        $navigation_text_next = '<i class="fa fa-chevron-right"></i>';
    } else {
        $navigation_text_prev = '<i class="fas fa-chevron-left"></i>';
        $navigation_text_next = '<i class="fas fa-chevron-right"></i>';
    }


    $navigation_text_prev = !empty($slider_option['navigation_text']['prev']) ? $slider_option['navigation_text']['prev'] : $navigation_text_prev;
    $navigation_text_next = !empty($slider_option['navigation_text']['next']) ? $slider_option['navigation_text']['next'] : $navigation_text_next;



    $sliderOptions = [
        "type" => $slider_loop ? "loop" : "slide",

        "clones" => 2,
        "perPage" => (int) $slider_column_large,
        "perMove" => (int)$slider_slideby_large,
        "arrows" => (bool)$slider_navigation,
        "pagination" => (bool)$slider_pagination,
        //"paginationDirection" => $slider_rtl ? 'rtl' : 'ltr',
        "autoplay" => (bool)$slider_auto_play,
        "interval" => $auto_play_timeout,
        "lazyLoad" => (bool)$slider_lazy_load,
        "pauseOnHover" => (bool)$slider_stop_on_hover,
        "pauseOnFocus" => (bool)$slider_stop_on_hover,
        "autoHeight" => false,
        // "rewind" => (bool)$slider_rewind,
        "speed" => (int)$auto_play_speed,
        //"rewindSpeed" => $auto_play_speed,
        // "rewindByDrag" => true,
        "drag" => $slider_mouse_drag,
        "direction" => $slider_rtl,
        "gap" => (int)$gutter,
        "breakpoints" => [
            "1200" => [
                "perPage" => (int) $slider_column_large,
                "perMove" => (int) $slider_slideby_large,

            ],
            "768" => [
                "perPage" => (int) $slider_column_medium,
                "perMove" => (int) $slider_slideby_medium,

            ],
            "576" => [
                "perPage" => (int) $slider_column_small,
                "perMove" => (int) $slider_slideby_small,

            ],
        ],

        //"focus" => $slider_center ? 'center' : 99999,
    ];
    $prevIconHtml = wp_specialchars_decode($navigation_text_prev, ENT_QUOTES);
    $prevIconPosition = 'before';
    $nextIconPosition = 'after';
    $nextIconHtml = wp_specialchars_decode($navigation_text_next, ENT_QUOTES);


    $terms_list = array();
    $loop_count = 0;

    if (!empty($taxonomies) && is_array($taxonomies)) :

        $wcps_items_class = apply_filters('wcps_items_wrapper_class', ' wcps-items ', $args);

    ?>
        <div id="wcps-<?php echo esc_attr($wcps_id); ?>" class="<?php echo esc_attr($wcps_items_class); ?> splide" data-splide="<?php echo esc_attr(json_encode($sliderOptions)) ?>">


            <div class="splide__arrows <?php echo esc_attr($navigation_position); ?> <?php echo esc_attr($navigation_style); ?>">
                <div class='prev splide__arrow splide__arrow--prev'>

                    <?php if ($prevIconPosition == 'before') : ?>
                        <span class='icon'>
                            <?php echo wp_kses_post($prevIconHtml); ?>
                        </span>
                    <?php endif; ?>

                    <?php if (!empty($prevText)) : ?>
                        <span>
                            <?php echo esc_attr($prevText); ?>
                        </span>
                    <?php endif; ?>



                    <?php if ($prevIconPosition == 'after') : ?>
                        <span class='icon'>
                            <?php echo wp_kses_post($prevIconHtml); ?>
                        </span>
                    <?php endif; ?>

                </div>
                <div class='next splide__arrow splide__arrow--next'>



                    <?php if ($nextIconPosition == 'before') : ?>
                        <span class='icon'>
                            <?php echo wp_kses_post($nextIconHtml); ?>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($nextText)) : ?>
                        <span>
                            <?php echo esc_attr($nextText); ?>
                        </span>
                    <?php endif; ?>



                    <?php if ($nextIconPosition == 'after') : ?>
                        <span class='icon'>
                            <?php echo wp_kses_post($nextIconHtml); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>




            <div class="splide__track">
                <ul class="splide__list">
                    <?php

                    foreach ($taxonomies as $taxonomy) {
                        $terms = isset($taxonomy['terms']) ? $taxonomy['terms'] : array();
                        foreach ($terms as $terms_id) {
                            //$terms_list[] =  $terms_id;

                            $args['term_id'] = $terms_id;
                            $args['loop_count'] = $loop_count;

                            do_action('wcps_slider_item_term', $args);

                            $loop_count++;
                        }
                    }

                    ?>
                </ul>
            </div>
            <ul class="splide__pagination "></ul>








        </div>
    <?php

    else :
        do_action('wcps_slider_no_item');
    endif;
}



add_action('wcps_slider_item_term', 'wcps_slider_item_term', 10);

function wcps_slider_item_term($args)
{

    $first_term_id = (int) wcps_get_first_category_id();

    $wcps_id = isset($args['wcps_id']) ? $args['wcps_id'] : '';
    $term_id = isset($args['term_id']) ? $args['term_id'] : $first_term_id;

    $wcps_options = get_post_meta($wcps_id, 'wcps_options', true);
    $item_layout_id = isset($wcps_options['item_layout_id']) ? $wcps_options['item_layout_id'] : wcps_first_wcps_layout();
    $layout_elements_data = get_post_meta($item_layout_id, 'layout_elements_data', true);

    $wcps_item_class = apply_filters('wcps_slider_item_class', 'item splide__slide', $args);

    ?>
    <div class="<?php echo esc_attr($wcps_item_class); ?>">
        <div class="elements-wrapper layout-<?php echo esc_attr($item_layout_id); ?>">
            <?php
            if (!empty($layout_elements_data))
                foreach ($layout_elements_data as $elementGroupIndex => $elementGroupData) {

                    if (!empty($elementGroupData))
                        foreach ($elementGroupData as $elementIndex => $elementData) {

                            $args['elementData'] = $elementData;
                            $args['element_index'] = $elementGroupIndex;


                            do_action('wcps_layout_element_' . $elementIndex, $args);
                        }
                }
            ?>
        </div>
    </div>
<?php

}




//add_filter('wcps_slider_main', 'wcps_slider_main_scripts_slick', 90);

function wcps_slider_main_scripts_slick($args)
{

    $wcps_id = isset($args['wcps_id']) ? $args['wcps_id'] : '';

    $wcps_options = get_post_meta($wcps_id, 'wcps_options', true);

    $container = isset($wcps_options['container']) ? $wcps_options['container'] : array();
    $container_background_img_url = isset($container['background_img_url']) ? $container['background_img_url'] : '';
    $container_background_color = isset($container['background_color']) ? $container['background_color'] : '';
    $container_padding = isset($container['padding']) ? $container['padding'] : '';
    $container_margin = isset($container['margin']) ? $container['margin'] : '';

    $item_style = isset($wcps_options['item_style']) ? $wcps_options['item_style'] : array();
    $item_height = isset($wcps_options['item_height']) ? $wcps_options['item_height'] : array();

    $item_height_large = isset($item_height['large']) ? $item_height['large'] : '';
    $item_height_medium = isset($item_height['medium']) ? $item_height['medium'] : '';
    $item_height_small = isset($item_height['small']) ? $item_height['small'] : '';



    $item_padding = isset($item_style['padding']) ? $item_style['padding'] : '';
    //    $item_margin = isset($item_style['margin']) ? $item_style['margin'] : '10px';
    $item_background_color = isset($item_style['background_color']) ? $item_style['background_color'] : '';
    $item_text_align = isset($item_style['text_align']) ? $item_style['text_align'] : '';

    $slider_option = isset($wcps_options['slider']) ? $wcps_options['slider'] : array();

    $slider_column_large = isset($slider_option['column_large']) ? $slider_option['column_large'] : 3;
    $slider_column_medium = isset($slider_option['column_medium']) ? $slider_option['column_medium'] : 2;
    $slider_column_small = isset($slider_option['column_small']) ? $slider_option['column_small'] : 1;

    $slider_slideby_large = isset($slider_option['slideby_large']) ? $slider_option['slideby_large'] : 3;
    $slider_slideby_medium = isset($slider_option['slideby_medium']) ? $slider_option['slideby_medium'] : 2;
    $slider_slideby_small = isset($slider_option['slideby_small']) ? $slider_option['slideby_small'] : true;



    $slider_slide_speed = isset($slider_option['slide_speed']) ? $slider_option['slide_speed'] : 1000;
    //    $slider_pagination_speed = isset($slider_option['pagination_speed']) ? $slider_option['pagination_speed'] : 1200;
    $gutter = isset($slider_option['gutter']) ? $slider_option['gutter'] : 10;

    $slider_auto_play = isset($slider_option['auto_play']) ? $slider_option['auto_play'] : true;
    $auto_play_speed = !empty($slider_option['auto_play_speed']) ? $slider_option['auto_play_speed'] : 1000;
    $auto_play_timeout = !empty($slider_option['auto_play_timeout']) ? $slider_option['auto_play_timeout'] : 1200;

    $auto_play_timeout = ($auto_play_speed >= $auto_play_timeout) ? $auto_play_speed + 1000 : $auto_play_timeout;

    $slider_rewind = isset($slider_option['rewind']) ? $slider_option['rewind'] : true;
    $slider_loop = isset($slider_option['loop']) ? $slider_option['loop'] : true;
    $slider_center = isset($slider_option['center']) ? $slider_option['center'] : true;
    $slider_stop_on_hover = isset($slider_option['stop_on_hover']) ? $slider_option['stop_on_hover'] : true;
    $slider_navigation = isset($slider_option['navigation']) ?  $slider_option['navigation'] : true;
    //$slider_navigation = ($slider_navigation) ? 'true' : 'false';


    $navigation_position = isset($slider_option['navigation_position']) ? $slider_option['navigation_position'] : '';
    $navigation_background_color = isset($slider_option['navigation_background_color']) ? $slider_option['navigation_background_color'] : '';
    $navigation_color = isset($slider_option['navigation_color']) ? $slider_option['navigation_color'] : '';
    $navigation_style = isset($slider_option['navigation_style']) ? $slider_option['navigation_style'] : 'flat';

    $dots_background_color = isset($slider_option['dots_background_color']) ? $slider_option['dots_background_color'] : '';
    $dots_active_background_color = isset($slider_option['dots_active_background_color']) ? $slider_option['dots_active_background_color'] : '';

    $slider_pagination = isset($slider_option['pagination']) ? $slider_option['pagination'] : true;
    $slider_pagination_count = isset($slider_option['pagination_count']) ? $slider_option['pagination_count'] : 0;
    $slider_rtl = isset($slider_option['rtl']) ? $slider_option['rtl'] : false;
    $slider_lazy_load = isset($slider_option['lazy_load']) ?  $slider_option['lazy_load'] : true;
    $slider_mouse_drag = isset($slider_option['mouse_drag']) ? $slider_option['mouse_drag'] : true;
    $slider_touch_drag = isset($slider_option['touch_drag']) ? $slider_option['touch_drag'] : true;

    $item_layout_id = isset($wcps_options['item_layout_id']) ? $wcps_options['item_layout_id'] : '';
    $layout_elements_data = get_post_meta($item_layout_id, 'layout_elements_data', true);
    $args['layout_id'] = $item_layout_id;


    $wcps_settings = get_option('wcps_settings');
    $font_aw_version = isset($wcps_settings['font_aw_version']) ? $wcps_settings['font_aw_version'] : 'none';

    if ($font_aw_version == 'v_5') {
        $navigation_text_prev = '<i class="fas fa-chevron-left"></i>';
        $navigation_text_next = '<i class="fas fa-chevron-right"></i>';
    } elseif ($font_aw_version == 'v_4') {
        $navigation_text_prev = '<i class="fa fa-chevron-left"></i>';
        $navigation_text_next = '<i class="fa fa-chevron-right"></i>';
    } else {
        $navigation_text_prev = '<i class="fas fa-chevron-left"></i>';
        $navigation_text_next = '<i class="fas fa-chevron-right"></i>';
    }


    $navigation_text_prev = !empty($slider_option['navigation_text']['prev']) ? $slider_option['navigation_text']['prev'] : $navigation_text_prev;
    $navigation_text_next = !empty($slider_option['navigation_text']['next']) ? $slider_option['navigation_text']['next'] : $navigation_text_next;




?>


    <script>
        jQuery(document).ready(function($) {

            $('#wcps-<?php echo esc_attr($wcps_id); ?>').slick({
                adaptiveHeight: true,
                slidesToShow: <?php echo esc_attr($slider_column_large); ?>,
                slidesToScroll: <?php echo esc_attr($slider_slideby_large); ?>,
                responsive: [{
                        breakpoint: 1200,
                        settings: {
                            slidesToShow: <?php echo esc_attr($slider_column_large); ?>,
                            slidesToScroll: <?php echo esc_attr($slider_slideby_large); ?>,
                            nav: <?php echo esc_attr($slider_navigation); ?>,
                        }
                    },
                    {
                        breakpoint: 768,
                        settings: {
                            slidesToShow: <?php echo esc_attr($slider_column_medium); ?>,
                            slidesToScroll: <?php echo esc_attr($slider_slideby_medium); ?>,
                            nav: <?php echo esc_attr($slider_navigation); ?>,
                        }
                    },
                    {
                        breakpoint: 576,
                        settings: {
                            slidesToShow: <?php echo esc_attr($slider_column_small); ?>,
                            slidesToScroll: <?php echo esc_attr($slider_slideby_small); ?>,
                            nav: <?php echo esc_attr($slider_navigation); ?>,
                            touchMove: <?php echo esc_attr($slider_touch_drag); ?>,
                            swipeToSlide: <?php echo esc_attr($slider_touch_drag); ?>,


                        }
                    }
                ],
                arrows: <?php echo esc_attr($slider_navigation); ?>,
                appendArrows: '.controlsWrap-<?php echo esc_attr($wcps_id); ?>',
                prevArrow: `<div class=" prev "><?php echo wp_kses_post(wp_specialchars_decode($navigation_text_prev, ENT_QUOTES)); ?></div>`,
                nextArrow: `<div class="next"><?php echo wp_kses_post(wp_specialchars_decode($navigation_text_next, ENT_QUOTES)); ?></div>`,
                speed: <?php echo esc_attr($auto_play_speed); ?>,
                autoplay: <?php echo esc_attr($slider_auto_play); ?>,
                autoplaySpeed: <?php echo esc_attr($auto_play_timeout); ?>,
                dots: <?php echo esc_attr($slider_pagination); ?>,
                centerMode: <?php echo esc_attr($slider_center); ?>,
                draggable: <?php echo esc_attr($slider_mouse_drag); ?>,
                touchMove: <?php echo esc_attr($slider_touch_drag); ?>,
                swipe: <?php echo esc_attr($slider_touch_drag); ?>,
                lazyLoad: <?php echo ($slider_lazy_load) ?  "'ondemand'" : "''"; ?>,
                rtl: <?php echo esc_attr($slider_rtl); ?>,
            });




            $('#wcps-container-<?php echo esc_js($wcps_id) . ' .controlsWrap'; ?> ').addClass('<?php echo esc_js($navigation_position); ?> <?php echo esc_js($navigation_style); ?>');

            $(document).on('change', '#wcps-<?php echo esc_attr($wcps_id) . ' .wcps-items-cart .quantity'; ?> ', function() {
                quantity = $(this).val();
                $(this).next().attr('data-quantity', quantity);
            })


        });
    </script>

<?php

}











add_filter('wcps_slider_main', 'wcps_slider_main_style', 90);

function wcps_slider_main_style($args)
{

    $wcps_id = isset($args['wcps_id']) ? $args['wcps_id'] : '';

    $wcps_options = get_post_meta($wcps_id, 'wcps_options', true);

    $container = isset($wcps_options['container']) ? $wcps_options['container'] : array();
    $container_background_img_url = isset($container['background_img_url']) ? $container['background_img_url'] : '';
    $container_background_color = isset($container['background_color']) ? $container['background_color'] : '';
    $container_padding = isset($container['padding']) ? $container['padding'] : '';
    $container_margin = isset($container['margin']) ? $container['margin'] : '';

    $item_style = isset($wcps_options['item_style']) ? $wcps_options['item_style'] : array();
    $item_height = isset($wcps_options['item_height']) ? $wcps_options['item_height'] : array();

    $item_height_large = isset($item_height['large']) ? $item_height['large'] : '';
    $item_height_medium = isset($item_height['medium']) ? $item_height['medium'] : '';
    $item_height_small = isset($item_height['small']) ? $item_height['small'] : '';



    $item_padding = isset($item_style['padding']) ? $item_style['padding'] : '';
    $item_margin = isset($item_style['margin']) ? $item_style['margin'] : '10px';
    $item_background_color = isset($item_style['background_color']) ? $item_style['background_color'] : '';
    $item_text_align = isset($item_style['text_align']) ? $item_style['text_align'] : '';

    $slider_option = isset($wcps_options['slider']) ? $wcps_options['slider'] : array();

    $slider_column_large = isset($slider_option['column_large']) ? $slider_option['column_large'] : 3;
    $slider_column_medium = isset($slider_option['column_medium']) ? $slider_option['column_medium'] : 2;
    $slider_column_small = isset($slider_option['column_small']) ? $slider_option['column_small'] : true;

    $slider_slideby_large = isset($slider_option['slideby_large']) ? $slider_option['slideby_large'] : 3;
    $slider_slideby_medium = isset($slider_option['slideby_medium']) ? $slider_option['slideby_medium'] : 2;
    $slider_slideby_small = isset($slider_option['slideby_small']) ? $slider_option['slideby_small'] : true;



    //    $slider_slide_speed = isset($slider_option['slide_speed']) ? $slider_option['slide_speed'] : 1000;
    $slider_pagination_speed = isset($slider_option['pagination_speed']) ? $slider_option['pagination_speed'] : 1200;

    $slider_auto_play = isset($slider_option['auto_play']) ? $slider_option['auto_play'] : true;
    $auto_play_speed = !empty($slider_option['auto_play_speed']) ? $slider_option['auto_play_speed'] : 1000;
    $auto_play_timeout = !empty($slider_option['auto_play_timeout']) ? $slider_option['auto_play_timeout'] : 1200;

    //$auto_play_timeout = ($auto_play_speed >= $auto_play_timeout) ? $auto_play_speed + 1000 : $auto_play_timeout;

    $slider_rewind = !empty($slider_option['rewind']) ? $slider_option['rewind'] : true;
    $slider_loop = !empty($slider_option['loop']) ? $slider_option['loop'] : true;
    $slider_center = !empty($slider_option['center']) ? $slider_option['center'] : true;
    $slider_stop_on_hover = isset($slider_option['stop_on_hover']) ? $slider_option['stop_on_hover'] : true;
    $slider_navigation = isset($slider_option['navigation']) ? $slider_option['navigation'] : true;
    $navigation_position = isset($slider_option['navigation_position']) ? $slider_option['navigation_position'] : '';
    $navigation_background_color = !empty($slider_option['navigation_background_color']) ? $slider_option['navigation_background_color'] : '#1e6fcc';
    $navigation_color = !empty($slider_option['navigation_color']) ? $slider_option['navigation_color'] : '#ffffff';
    $navigation_style = isset($slider_option['navigation_style']) ? $slider_option['navigation_style'] : 'flat';

    $dots_background_color = isset($slider_option['dots_background_color']) ? $slider_option['dots_background_color'] : '';
    $dots_active_background_color = !empty($slider_option['dots_active_background_color']) ? $slider_option['dots_active_background_color'] : '#1e6fcc';

    $slider_pagination = isset($slider_option['pagination']) ? $slider_option['pagination'] : true;
    $slider_pagination_count = isset($slider_option['pagination_count']) ? $slider_option['pagination_count'] : 0;
    $slider_rtl = !empty($slider_option['rtl']) ? $slider_option['rtl'] : 0;
    $slider_lazy_load = isset($slider_option['lazy_load']) ? $slider_option['lazy_load'] : true;
    $slider_mouse_drag = isset($slider_option['mouse_drag']) ? $slider_option['mouse_drag'] : true;
    $slider_touch_drag = isset($slider_option['touch_drag']) ? $slider_option['touch_drag'] : true;




    $defaultLayout_data = array(
        0 =>
        array(
            'wrapper_start' =>
            array(
                'wrapper_id' => '',
                'wrapper_class' => 'layer-media',
                'css_idle' => '',
                'margin' => '',
            ),
        ),
        1 =>
        array(
            'thumbnail' =>
            array(
                'thumb_size' => 'full',
                'thumb_height' =>
                array(
                    'large' => '1000px',
                    'medium' => '',
                    'small' => '',
                ),
                'default_thumb_src' => wcps_plugin_url . 'assets/front/images/no-thumb.png',
                'margin' => '',
                'link_to' => 'none',
            ),
        ),
        2 =>
        array(
            'wrapper_end' =>
            array(
                'wrapper_id' => '',
            ),
        ),
        3 =>
        array(
            'wrapper_start' =>
            array(
                'wrapper_id' => '',
                'wrapper_class' => 'layer-content',
                'css_idle' => '',
                'margin' => '',
            ),
        ),
        5 =>
        array(
            'post_title' =>
            array(
                'color' => '#000000',
                'font_size' => '16px',
                'font_family' => '',
                'margin' => '10px 0',
                'text_align' => 'left',
                'link_to' => 'product_link',
            ),
        ),

        8 =>
        array(
            'product_price' =>
            array(
                'price_type' => 'full',
                'wrapper_html' => '',
                'color' => '#000000',
                'margin' => '10px 0',
                'font_size' => '',
                'text_align' => 'left',
            ),
        ),
        10 =>
        array(
            'add_to_cart' =>
            array(
                'background_color' => '#1e73be',
                'color' => '#ffffff',
                'show_quantity' => 'no',
                'margin' => '',
                'padding' => '12px 30px',
            ),
        ),
        12 =>
        array(
            'wrapper_end' =>
            array(
                'wrapper_id' => '',
            ),
        ),
    );











    $item_layout_id = isset($wcps_options['item_layout_id']) ? $wcps_options['item_layout_id'] : wcps_first_wcps_layout();
    $layout_elements_data = get_post_meta($item_layout_id, 'layout_elements_data', true);
    $layout_elements_data = !empty($layout_elements_data) ? $layout_elements_data : $defaultLayout_data;

    $args['layout_id'] = $item_layout_id;
















    $wcps_settings = get_option('wcps_settings');
    $font_aw_version = isset($wcps_settings['font_aw_version']) ? $wcps_settings['font_aw_version'] : 'none';

    if ($font_aw_version == 'v_5') {
        $navigation_text_prev = '<i class="fas fa-chevron-left"></i>';
        $navigation_text_next = '<i class="fas fa-chevron-right"></i>';
    } elseif ($font_aw_version == 'v_4') {
        $navigation_text_prev = '<i class="fa fa-chevron-left"></i>';
        $navigation_text_next = '<i class="fa fa-chevron-right"></i>';
    } else {
        $navigation_text_prev = '<i class="fas fa-chevron-left"></i>';
        $navigation_text_next = '<i class="fas fa-chevron-right"></i>';
    }


    $navigation_text_prev = !empty($slider_option['navigation_text']['prev']) ? $slider_option['navigation_text']['prev'] : $navigation_text_prev;
    $navigation_text_next = !empty($slider_option['navigation_text']['next']) ? $slider_option['navigation_text']['next'] : $navigation_text_next;

    //var_dump($slider_navigation);

?>

    <style>
        <?php echo '.wcps-container-' . esc_attr($wcps_id); ?> {
            <?php if (!empty($container_padding)) : ?>padding: <?php echo esc_attr($container_padding); ?>;
            <?php endif; ?><?php if (!empty($container_margin)) : ?>margin: <?php echo esc_attr($container_margin); ?>;
            <?php endif; ?><?php if (!empty($container_background_color)) : ?>background-color: <?php echo esc_attr($container_background_color); ?>;
            <?php endif; ?><?php if (!empty($container_background_img_url)) : ?>background-image: url(<?php echo esc_attr($container_background_img_url); ?>) repeat scroll 0 0;
            <?php endif; ?>position: relative;
            overflow: hidden;
        }

        <?php echo esc_attr('.wcps-container-' . $wcps_id) . ' .elements-wrapper img'; ?> {
            max-width: 100%;
            height: auto;
        }

        <?php echo esc_attr('.wcps-container-' . $wcps_id) . ' .wcps-ribbon.topright'; ?> {
            position: absolute;
            right: -25px;
            top: 15px;
            box-shadow: 0 2px 4px -1px rgb(51, 51, 51);
            transform: rotate(45deg);
            z-index: 10;
        }

        <?php echo esc_attr('.wcps-container-' . $wcps_id) . ' .wcps-ribbon.topleft'; ?> {
            position: absolute;
            left: -25px;
            top: 15px;
            box-shadow: 0 2px 4px -1px rgb(51, 51, 51);
            transform: rotate(-45deg);
            z-index: 10;
        }

        <?php echo esc_attr('.wcps-container-' . $wcps_id) . ' .wcps-ribbon.bottomleft'; ?> {
            position: absolute;
            left: -25px;
            bottom: 10px;
            box-shadow: 0 2px 4px -1px rgb(51, 51, 51);
            transform: rotate(45deg);
            z-index: 10;
        }

        <?php echo esc_attr('.wcps-container-' . $wcps_id) . ' .wcps-ribbon.bottomright'; ?> {
            position: absolute;
            right: -24px;
            bottom: 10px;
            box-shadow: 0 2px 4px -1px rgb(51, 51, 51);
            transform: rotate(-45deg);
            z-index: 10;
        }

        <?php echo esc_attr('.wcps-container-' . $wcps_id) . ' .wcps-ribbon.none'; ?> {
            display: none;
        }

        <?php echo esc_attr('.wcps-container-' . $wcps_id) . ' .slick-slide'; ?> {
            <?php if (!empty($item_padding)) : ?>padding: <?php echo esc_attr($item_padding); ?>;
            <?php endif; ?><?php if (!empty($item_margin)) : ?>margin: <?php echo esc_attr($item_margin); ?>;
            <?php endif; ?><?php if (!empty($item_background_color)) : ?>background: <?php echo esc_attr($item_background_color); ?>;
            <?php endif; ?><?php if (!empty($item_text_align)) : ?>text-align: <?php echo esc_attr($item_text_align); ?>;
            <?php endif; ?>
        }

        @media only screen and (min-width: 0px) and (max-width: 767px) {
            <?php echo esc_attr('.wcps-container-' . $wcps_id) . ' .item'; ?> {
                <?php if (!empty($item_height_small)) : ?>height: <?php echo esc_attr($item_height_small); ?>;
                <?php endif; ?>
            }
        }

        @media only screen and (min-width: 768px) and (max-width: 1023px) {
            <?php echo esc_attr('.wcps-container-' . $wcps_id) . ' .item'; ?> {
                <?php if (!empty($item_height_medium)) : ?>height: <?php echo esc_attr($item_height_medium); ?>;
                <?php endif; ?>
            }
        }

        @media only screen and (min-width: 1024px) {
            <?php echo esc_attr('.wcps-container-' . $wcps_id) . ' .item '; ?> {
                <?php if (!empty($item_height_large)) : ?>height: <?php echo esc_attr($item_height_large); ?>;
                <?php endif; ?>
            }
        }

        <?php echo esc_attr('.wcps-container-' . $wcps_id) . ' .wcps-items'; ?> {}

        <?php echo esc_attr('.wcps-container-' . $wcps_id) . ' .on-sale img'; ?> {
            width: 30px;
            height: auto;
            box-shadow: none;
            display: inline-block;
            vertical-align: middle;
        }

        <?php echo esc_attr('.wcps-container-' . $wcps_id) . ' .on-sale.topright'; ?> {
            position: absolute;
            right: 20px;
            top: 15px;
            z-index: 10;
        }

        <?php echo esc_attr('.wcps-container-' . $wcps_id) . ' .on-sale.topleft'; ?> {
            position: absolute;
            left: 20px;
            top: 15px;
            z-index: 10;
        }

        <?php echo esc_attr('.wcps-container-' . $wcps_id) . ' .on-sale.bottomleft'; ?> {
            position: absolute;
            left: 20px;
            bottom: 10px;
            z-index: 10;
        }

        <?php echo esc_attr('.wcps-container-' . $wcps_id) . ' .on-sale.bottomright'; ?> {
            position: absolute;
            right: 20px;
            bottom: 10px;
            z-index: 10;
        }

        <?php echo esc_attr('.wcps-container-' . $wcps_id) . ' .featured-mark img'; ?> {
            width: 30px;
            height: auto;
            box-shadow: none;
            display: inline-block;
            vertical-align: middle;
        }

        <?php echo esc_attr('.wcps-container-' . $wcps_id) . ' .featured-mark.topright'; ?> {
            position: absolute;
            right: 20px;
            top: 15px;
            z-index: 10;
        }

        <?php echo esc_attr('.wcps-container-' . $wcps_id) . ' .featured-mark.topleft'; ?> {
            position: absolute;
            left: 20px;
            top: 15px;
            z-index: 10;
        }

        <?php echo esc_attr('.wcps-container-' . $wcps_id) . ' .featured-mark.bottomleft'; ?> {
            position: absolute;
            left: 20px;
            bottom: 10px;
            z-index: 10;
        }

        <?php echo esc_attr('.wcps-container-' . $wcps_id) . ' .featured-mark.bottomright'; ?> {
            position: absolute;
            right: 20px;
            bottom: 10px;
            z-index: 10;
        }

        <?php echo esc_attr('.wcps-container-' . $wcps_id) . ' .splide__pagination '; ?> {
            text-align: center;
            width: 100%;
            margin: 30px 0 0;
        }

        <?php echo esc_attr('.wcps-container-' . $wcps_id) . ' .splide__pagination li'; ?> {
            display: inline-block;
            list-style: none;
            margin: 0;
        }

        <?php echo esc_attr('.wcps-container-' . $wcps_id) . ' .splide__pagination button'; ?> {
            <?php if (!empty($dots_background_color)) : ?>background: <?php echo esc_attr($dots_background_color); ?>;
            <?php endif; ?>border-radius: 20px;
            display: inline-block;
            height: 15px;
            margin: 5px 7px;
            width: 15px;
            outline: none;
            font-size: 0;
            line-height: 0;
        }

        <?php echo esc_attr('.wcps-container-' . $wcps_id) . ' .splide__pagination button.is-active '; ?>,
        <?php echo esc_attr('.wcps-container-' . $wcps_id) . ' .splide__pagination button.is-active:hover '; ?> {
            <?php if (!empty($dots_active_background_color)) : ?>background: <?php echo esc_attr($dots_active_background_color); ?>;
            <?php endif; ?>
        }

        <?php echo esc_attr('.wcps-container-' . $wcps_id) . ' .splide__arrows div'; ?> {
            <?php if (!empty($navigation_background_color)) : ?>background: <?php echo esc_attr($navigation_background_color); ?>;
            <?php endif; ?><?php if (!empty($navigation_color)) : ?>color: <?php echo esc_attr($navigation_color); ?>;
            <?php endif; ?>margin: 0 5px;
            outline: nonepadding: 5px 20px;
            cursor: pointer;
            display: inline-block;
        }

        <?php echo esc_attr('.wcps-container-' . $wcps_id) . ' .splide__arrows.topright'; ?> {
            text-align: right;
            margin-bottom: 15px;
        }

        <?php echo esc_attr('.wcps-container-' . $wcps_id) . ' .splide__arrows.topleft'; ?> {
            text-align: left;
            margin-bottom: 15px;
        }

        <?php echo esc_attr('.wcps-container-' . $wcps_id) . ' .splide__arrows.bottomleft '; ?> {
            position: absolute;
            left: 15px;
            bottom: 15px;
        }

        <?php echo esc_attr('.wcps-container-' . $wcps_id) . '  .splide__arrows.bottomright'; ?> {
            position: absolute;
            right: 15px;
            bottom: 15px;
        }

        <?php echo esc_attr('.wcps-container-' . $wcps_id) . ' .splide__arrows.middle-fixed'; ?> {
            position: absolute;
            top: 50%;
            transform: translate(0, -50%);
            width: 100%;
            z-index: 99;
        }

        <?php echo esc_attr('.wcps-container-' . $wcps_id) . ' .splide__arrows.middle-fixed .next'; ?> {
            float: right;
        }

        <?php echo esc_attr('.wcps-container-' . $wcps_id) . ' .splide__arrows.middle-fixed .-prev'; ?> {
            float: left;
        }

        <?php echo esc_attr('.wcps-container-' . $wcps_id) . ' .splide__arrows.middle'; ?> {
            position: absolute;
            top: 50%;
            transform: translate(0, -50%);
            width: 100%;
            z-index: 99;
        }

        <?php echo esc_attr('.wcps-container-' . $wcps_id) . ' .splide__arrows.middle .next '; ?> {
            float: right;
            right: -20%;
            position: absolute;
            transition: all ease 1s 0s;
        }

        <?php echo esc_attr('.wcps-container-' . $wcps_id) . ':hover .splide__arrows.middle .next'; ?> {
            right: 0;
        }

        <?php echo esc_attr('.wcps-container-' . $wcps_id) . ' .splide__arrows.middle .prev'; ?> {
            left: -20%;
            position: absolute;
            transition: all ease 1s 0s;
        }

        <?php echo esc_attr('.wcps-container-' . $wcps_id) . ':hover .splide__arrows.middle .prev'; ?> {
            left: 0;
            position: absolute;
        }

        <?php echo esc_attr('.wcps-container-' . $wcps_id) . ' .splide__arrows.flat div'; ?> {
            padding: 5px 20px;
            border-radius: 0;
        }

        <?php echo esc_attr('.wcps-container-' . $wcps_id) . ' .splide__arrows.border div'; ?> {
            padding: 5px 20px;
            border: 2px solid #777;
        }

        <?php echo esc_attr('.wcps-container-' . $wcps_id) . ' .splide__arrows.semi-round div'; ?> {
            padding: 5px 20px;
            border-radius: 8px;
        }

        <?php echo esc_attr('.wcps-container-' . $wcps_id) . ' .splide__arrows.round div'; ?> {
            border-radius: 50px;
        }

        <?php echo esc_attr('.wcps-container-' . $wcps_id) . ' .quantity'; ?> {
            width: 45px;
        }

        <?php

        $custom_css = isset($wcps_options['custom_css']) ? $wcps_options['custom_css'] : '';
        echo esc_attr(str_replace('__ID__', $wcps_id, $custom_css));

        $custom_scripts = get_post_meta($item_layout_id, 'custom_scripts', true);
        $layout_custom_css = isset($custom_scripts['custom_css']) ? $custom_scripts['custom_css'] : '';

        echo esc_attr(str_replace('__ID__', 'layout-' . $item_layout_id, $layout_custom_css));

        ?>
    </style>
<?php
    if (!empty($layout_elements_data))
        foreach ($layout_elements_data as $elementGroupIndex => $elementGroupData) {

            if (!empty($elementGroupData))
                foreach ($elementGroupData as $elementIndex => $elementData) {
                    $args['elementData'] = $elementData;
                    $args['element_index'] = $elementGroupIndex;
                    do_action('wcps_layout_element_css_' . $elementIndex, $args);
                }
        }
}




add_filter('wcps_slider_main', 'wcps_slider_main_enqueue_scripts', 99);

function wcps_slider_main_enqueue_scripts($args)
{

    $wcps_settings = get_option('wcps_settings');

    $font_aw_version = !empty($wcps_settings['font_aw_version']) ? $wcps_settings['font_aw_version'] : 'v_5';



    // wp_enqueue_script('slick');
    wp_enqueue_script('splide.min');

    //wp_enqueue_style('slick');
    wp_enqueue_style('splide_core');
    wp_enqueue_script('wcps_script');


    if ($font_aw_version == 'v_5') {
        wp_enqueue_style('font-awesome-5');
    } elseif ($font_aw_version == 'v_4') {
        wp_enqueue_style('font-awesome-4');
    }
}
