<?php
if (! defined('ABSPATH')) exit;  // if direct access
add_action('wp_footer', 'wcps_builder_global_scripts', 999);

function wcps_builder_global_scripts()
{

    global $WCPSBuilderCss;

    $WCPSBuilderCss = str_replace("&quot;", '"', $WCPSBuilderCss);
    $WCPSBuilderCss = str_replace("&gt;", '>', $WCPSBuilderCss);



    wp_register_style('WCPSBuilderCss', false);
    wp_enqueue_style('WCPSBuilderCss');


    wp_add_inline_style('WCPSBuilderCss', $WCPSBuilderCss);
}

function generateLayoutsHTML_wcps($elements, $itemData)
{
    $html = '';
    foreach ($elements as $element) {




        $type = $element['type'];

        if ($type == 'container') {
            $html .= '<div id="element-' . $element['id'] . '">';

            if (!empty($element['children'])) {
                $html .= generateLayoutsHTML_wcps($element['children'], $itemData);
            }
            $html .= '</div>';
        } else {

            //if (isset($element['content'])) {
            $html .= generateLayoutsElementHtml_wcps($element, $itemData);
        }
    }
    return $html;
}



function generateLayoutsElementHtml_wcps($element, $item)
{

    $type = $element['type'];
    $options = isset($element['options']) ? $element['options'] : [];

    //echo "<pre>" . var_export($element, true) . "</pre>";


    //echo '<pre>';
    // echo var_export($item, true);
    //echo '</pre>';

    $content = isset($item["content"]) ?  $item["content"] : "";
    $date = isset($item["date"]) ?  $item["date"] : "";
    $rating = isset($item["rating"]) ?  $item["rating"] : 5;
    $personAvatar = isset($item["personAvatar"]) ?  $item["personAvatar"] : [];
    $personAvatarUrl = isset($personAvatar["srcUrl"]) ?  $personAvatar["srcUrl"] : "";
    $personAvatarId = isset($personAvatar["id"]) ?  $personAvatar["id"] : "";


    $companyLogo = isset($item["companyLogo"]) ?  $item["companyLogo"] : [];
    $companyLogoUrl = isset($companyLogo["srcUrl"]) ?  $companyLogo["srcUrl"] : "";
    $companyLogoId = isset($companyLogo["id"]) ?  $companyLogo["id"] : "";


    $personName = isset($item["personName"]) ?  $item["personName"] : "";
    $jobTitle = isset($item["jobTitle"]) ?  $item["jobTitle"] : "";
    $companyName = isset($item["companyName"]) ?  $item["companyName"] : '';
    $companyWebsite = isset($item["companyWebsite"]) ?  $item["companyWebsite"] : '';


    $iconLibrary = isset($options['library']) ? $options['library'] : '';
    $iconSrcType = isset($options['srcType']) ? $options['srcType'] : '';
    $iconSrc = isset($options['iconSrc']) ? $options['iconSrc'] : '';

    $fontIconHtml = '<span class="' . $iconSrc . '"></span>';


    if ($iconLibrary == 'fontAwesome') {
        wp_enqueue_style('fontawesome-icons');
    } else if ($iconLibrary == 'iconFont') {
        wp_enqueue_style('icofont-icons');
    } else if ($iconLibrary == 'bootstrap') {
        wp_enqueue_style('bootstrap-icons');
    }

    ob_start();

    if ($type == 'content') {
?>
        <div id="element-<?php echo esc_attr($element['id']); ?>">
            <?php echo wp_kses_post(wp_unslash(wp_specialchars_decode($content, ENT_QUOTES))); ?>
        </div>
    <?php
    }
    if ($type == 'personName') {
    ?>
        <div id="element-<?php echo esc_attr($element['id']); ?>">
            <?php echo wp_kses_post($personName); ?>

        </div>
    <?php
    }
    if ($type == 'jobTitle') {
    ?>
        <div id="element-<?php echo esc_attr($element['id']); ?>">
            <?php echo wp_kses_post($jobTitle); ?>

        </div>
    <?php
    }
    if ($type == 'companyName') {
    ?>
        <div id="element-<?php echo esc_attr($element['id']); ?>">
            <?php echo wp_kses_post($companyName); ?>

        </div>
    <?php
    }
    if ($type == 'companyWebsite') {
    ?>
        <a id="element-<?php echo esc_attr($element['id']); ?>">
            <?php echo wp_kses_post($companyWebsite); ?>

        </a>
    <?php
    }






    if ($type == 'rating') {
    ?>
        <div id="element-<?php echo esc_attr($element['id']); ?>">
            <?php //echo wp_kses_post($rating);

            for ($i = 0; $i < 5; $i++) {

                echo wp_kses_post($fontIconHtml);
            }

            ?>

        </div>
    <?php
    }



    if ($type == 'personAvatar') {
    ?>
        <img id="element-<?php echo esc_attr($element['id']); ?>" src="<?php echo esc_url($personAvatarUrl); ?>" alt="">
    <?php
    }

    ?>

<?php


    return ob_get_clean();
}


function wcps_builder_post_query_items($queryArgs, $loopLayouts, $args = [])
{

    $item_class = isset($args['item_class']) ? $args['item_class'] : '';



    $query_args = [];
    $tax_query = [];
    foreach ($queryArgs as $item) {

        $id = isset($item['id']) ? $item['id'] : '';
        $val = isset($item['value']) ? $item['value'] : '';

        if (isset($item['value'])) {
            if ($id == 'postType') {
                $query_args['post_type'] = $val;
            } elseif ($id == 'postStatus') {
                $query_args['post_status'] = $val;
            } elseif ($id == 'order') {
                $query_args['order'] = $val;
            } elseif ($id == 'orderby') {
                $query_args['orderby'] = implode(' ', $val);
            } elseif ($id == 'metaKey') {
                $query_args['meta_key'] = $val;
            } elseif ($id == 'dateQuery') {
                $date_query = [];
                foreach ($val as $arg) {
                    $id = isset($arg['id']) ? $arg['id'] : '';
                    $value = isset($arg['value']) ? $arg['value'] : '';
                    if ($id == 'year' || $id == 'month' || $id == 'week' || $id == 'day' || $id == 'hour' || $id == 'minute' || $id == 'second') {
                        $compare = isset($arg['compare']) ? $arg['compare'] : '';
                        if (!empty($value))
                            $date_query[] = [$id => $value, 'compare' => $compare,];
                    }
                    if ($id == 'inclusive' || $id == 'compare' || $id == 'relation') {
                        if (!empty($value))
                            $date_query[$id] = $value;
                    }
                    if ($id == 'after' || $id == 'before') {
                        $year = isset($arg['year']) ? $arg['year'] : '';
                        $month = isset($arg['month']) ? $arg['month'] : '';
                        $day = isset($arg['day']) ? $arg['day'] : '';
                        if (!empty($year))
                            $date_query[$id]['year'] = $year;
                        if (!empty($month))
                            $date_query[$id]['month'] = $month;
                        if (!empty($day))
                            $date_query[$id]['day'] = $day;
                    }
                }
                $query_args['date_query'] = $date_query;
            } elseif ($id == 'year') {
                $query_args['year'] = $val;
            } elseif ($id == 'monthnum') {
                $query_args['monthnum'] = $val;
            } elseif ($id == 'w') {
                $query_args['w'] = $val;
            } elseif ($id == 'day') {
                $query_args['day'] = $val;
            } elseif ($id == 'hour') {
                $query_args['hour'] = $val;
            } elseif ($id == 'minute') {
                $query_args['minute'] = $val;
            } elseif ($id == 'second') {
                $query_args['second'] = $val;
            } elseif ($id == 'm') {
                $query_args['m'] = $val;
            } elseif ($id == 'author') {
                $query_args['author'] = $val;
            } elseif ($id == 'authorName') {
                $query_args['author_name'] = $val;
            } elseif ($id == 'authorIn') {

                $author_ids = explode(',', $val);
                $author_ids = array_map(function ($a) {
                    return (int) $a;
                }, $author_ids);

                $query_args['author_in'] = !empty($val) ? $author_ids : [];
            } elseif ($id == 'authorNotIn') {
                $query_args['author__not_in'] = !empty($val) ? explode(',', $val) : [];
            } elseif ($id == 'cat') {
                $query_args['cat'] = $val;
            } elseif ($id == 'categoryName') {


                $query_args['category_name'] = $val;
            } elseif ($id == 'categoryAnd') {

                if ($val == '{currentPostCategoryAnd}') {
                    if (is_singular()) {
                        $post_id = get_the_id();
                        $category_ids = wp_get_post_categories($post_id, ['fields' => 'ids']);
                        $query_args['category_and'] = !empty($val) ? explode(',', $val) : [];
                    }
                } else {
                    $query_args['category_and'] = !empty($val) ? explode(',', $val) : [];
                }
            } elseif ($id == 'categoryIn') {

                if ($val == '{currentPostCategoryIn}') {
                    if (is_singular()) {
                        $post_id = get_the_id();
                        $category_ids = wp_get_post_categories($post_id, ['fields' => 'ids']);
                        $query_args['category__in'] = !empty($category_ids) ? $category_ids : [];
                    }
                } else {
                    $query_args['category__in'] = !empty($val) ? explode(',', $val) : [];
                }
            } elseif ($id == 'categoryNotIn') {

                if ($val == '{currentPostCategoryNotIn}') {
                    if (is_singular()) {
                        $post_id = get_the_id();
                        $category_ids = wp_get_post_categories($post_id, ['fields' => 'ids']);
                        $query_args['category__not_in'] = !empty($val) ? explode(',', $val) : [];
                    }
                } else {
                    $query_args['category__not_in'] = !empty($val) ? explode(',', $val) : [];
                }
            } elseif ($id == 'tag') {
                $query_args['tag'] = $val;
            } elseif ($id == 'tagId') {
                $query_args['tag_id'] = $val;
            } elseif ($id == 'tagAnd') {
                $query_args['tag__and'] = !empty($val) ? explode(',', $val) : [];
            } elseif ($id == 'tagIn') {
                $post_id = get_the_id();
                $tag_ids = wp_get_post_tags($post_id, array('fields' => 'ids'));
                $query_args['tag__in'] = !empty($val) ? explode(',', $val) : $tag_ids;
            } elseif ($id == 'tagNotIn') {
                $query_args['tag__not_in'] = !empty($val) ? explode(',', $val) : [];
            } elseif ($id == 'tagSlugAnd') {
                $query_args['tag_slug__and'] = !empty($val) ? explode(',', $val) : [];
            } elseif ($id == 'tagSlugIn') {
                $query_args['tag_slug__in'] = !empty($val) ? explode(',', $val) : [];
            } elseif ($id == 'taxQuery') {
                $query_args['tax_query'] = isset($val[0]) ? $val[0] : $val;
            } elseif ($id == 'p') {
                $query_args['p'] = $val;
            } elseif ($id == 's') {

                //if (!empty($val))
                $query_args['s'] = $val;
            } elseif ($id == 'name') {
                $query_args['name'] = $val;
            } elseif ($id == 'pageId') {
                $query_args['page_id'] = $val;
            } elseif ($id == 'pagename') {
                $query_args['pagename'] = $val;
            } elseif ($id == 'postParent') {
                $query_args['post_parent'] = $val;
            } elseif ($id == 'postParentIn') {
                $query_args['post_parent__in'] = !empty($val) ? explode(',', $val) : [];
            } elseif ($id == 'postParentNotIn') {
                $query_args['post_parent__not_in'] = !empty($val) ? explode(',', $val) : [];
            } elseif ($id == 'postIn') {
                $query_args['post__in'] = !empty($val) ? explode(',', $val) : [];
            } elseif ($id == 'postNotIn') {
                $post_id = get_the_id();
                $query_args['post__not_in'] = !empty($val) ? explode(',', $val) : [$post_id];
            } elseif ($id == 'postNameIn') {
                $query_args['post_name__in'] = !empty($val) ? explode(',', $val) : [];
            } elseif ($id == 'hasPassword') {
                $query_args['has_password'] = $val;
            } elseif ($id == 'postPassword') {
                $query_args['post_password'] = $val;
            } elseif ($id == 'commentCount') {
                $query_args['comment_count'] = $val;
            } elseif ($id == 'nopaging') {
                $query_args['nopaging'] = $val;
            } elseif ($id == 'postsPerPage') {
                $query_args['posts_per_page'] = (int) $val;
            } elseif ($id == 'paged') {
                $query_args['paged'] = $val;
            } elseif ($id == 'offset') {
                $query_args['offset'] = $val;
            } elseif ($id == 'postsPerArchivePage') {
                $query_args['posts_per_archive_page'] = $val;
            } elseif ($id == 'ignoreStickyPosts') {
                $query_args['ignore_sticky_posts'] = $val;
            } elseif ($id == 'metaKey') {
                $query_args['meta_key'] = $val;
            } elseif ($id == 'metaValue') {
                $query_args['meta_value'] = $val;
            } elseif ($id == 'metaValueNum') {
                $query_args['meta_value_num'] = $val;
            } elseif ($id == 'metaCompare') {
                $query_args['meta_compare'] = $val;
            } elseif ($id == 'metaQuery') {
                $query_args['meta_query'] = $val;
            } elseif ($id == 'perm') {
                $query_args['perm'] = $val;
            } elseif ($id == 'postMimeType') {
                $query_args['post_mime_type'] = $val;
            } elseif ($id == 'cacheResults') {
                $query_args['cache_results'] = $val;
            } elseif ($id == 'updatePostMetaCache') {
                $query_args['update_post_meta_cache '] = $val;
            } elseif ($id == 'updatePostTermCache') {
                $query_args['update_post_term_cache'] = $val;
            } elseif ($id == 'wcFeaturedProducts') {

                $tax_query[] = array(
                    'taxonomy' => 'product_visibility',
                    'field' => 'name',
                    'terms' => 'featured',
                    'operator' => 'IN',
                );
            } elseif ($id == 'wcOnSaleProducts') {

                $wc_get_product_ids_on_sale = wc_get_product_ids_on_sale();
                $query_args['post__in'] = $wc_get_product_ids_on_sale;
            } elseif ($id == 'wcOutOfStockProducts') {

                $tax_query[] = array(
                    'taxonomy' => 'product_visibility',
                    'field' => 'name',
                    'terms' => 'outofstock',
                    'operator' => 'NOT IN',
                );
            } elseif ($id == 'wcUpsellsProducts') {

                $tax_query[] = array(
                    'taxonomy' => 'product_visibility',
                    'field' => 'name',
                    'terms' => 'outofstock',
                    'operator' => 'NOT IN',
                );
            } elseif ($id == 'wcCatalogVisibility') {
                $catalog_visibility = $val;
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
        }
    }

    if (get_query_var('paged')) {
        $paged = get_query_var('paged');
    } elseif (get_query_var('page')) {
        $paged = get_query_var('page');
    } else {
        $paged = 1;
    }

    if (!empty($tax_query)) {
        $query_args['tax_query'] = $tax_query;
    }





    $postsHtml = "";
    $html = '';
    $responses = [];
    $posts_query = new WP_Query($query_args);

    $max_num_pages = isset($posts_query->max_num_pages) ? $posts_query->max_num_pages : 0;


    if ($posts_query->have_posts()) :
        $index = 0;
        while ($posts_query->have_posts()) :
            $posts_query->the_post();
            $post_id = get_the_id();

            $term_slugs = wcps_term_slug_list($post_id);


            $postData = get_post($post_id);
            $postsHtml .= "<div class='item loop-item-$index $item_class $term_slugs '>";
            $postsHtml .= renderContentRecursive_wcps($postData, $loopLayouts);
            $postsHtml .= '</div>';
            $index++;
        endwhile;
        $responses['postsHtml'] = $postsHtml;
        $responses['posts_query'] = $posts_query;
        // $responses['max_num_pages'] = isset($posts_query->max_num_pages) ? $posts_query->max_num_pages : 0;;
        wp_reset_postdata();
    endif;




    return $responses;
}






function renderContentRecursive_wcps($postData, array $elements)
{
    $html = '';

    foreach ($elements as $element) {

        // echo '<pre>';
        // echo var_export($element, true);
        // echo '</pre>';
        $id = isset($element['id']) ? $element['id'] : '';
        $type = isset($element['type']) ? $element['type'] : '';
        $children = isset($element['children']) ? $element['children'] : [];


        // if (!empty($children)) {
        //     $html .= "<div id='element-$id' class='$type'>";
        // }

        // Recurse into children if they exist
        if (isset($element['children']) && is_array($element['children'])) {
            //$html .= renderContentRecursive_wcps($postData, $element['children']);
        }

        $html .= apply_filters("wcps_generate_element_html_$type", $html, $postData, $element, $children);



        // if (!empty($children)) {
        //     $html .= '</div>';
        // }

        // End list item

    }




    return $html;
}





// postTitle
function wcps_generate_element_html($html, $postData, $element)
{

    $post_id = isset($element['ID']) ? $element['ID'] : '';
    $type = isset($element['type']) ? $element['type'] : '';
    $id = isset($element['id']) ? $element['id'] : '';

    // echo '<pre>';
    // echo var_export($postData, true);
    // echo '</pre>';

    $html = $element['content'];

    return $html;
}



// postTitle
add_filter('wcps_generate_element_html_postTitle', "wcps_generate_element_html_postTitle", 10, 4);
function wcps_generate_element_html_postTitle($html, $postData, $element, $children)
{

    $type = isset($element['type']) ? $element['type'] : '';
    $id = isset($element['id']) ? $element['id'] : '';
    $options = isset($element['options']) ? $element['options'] : [];

    $linkTo = isset($options['linkTo']) ? $options['linkTo'] : 'postUrl';
    $target = isset($options['target']) ? $options['target'] : '_blank';
    $post_id = isset($postData->ID) ? $postData->ID : '';
    $post_title = isset($postData->post_title) ? $postData->post_title : '';

    $post_url = get_permalink($post_id);


    ob_start();

?>
    <div class="<?php echo esc_attr($type); ?>" id="element-<?php echo esc_attr($id); ?>">
        <?php

        if ($linkTo == 'postUrl') {
        ?>
            <a href="<?php echo esc_url($post_url); ?>"
                target="<?php echo esc_attr($target); ?>"><?php echo wp_kses_post($post_title); ?>
            </a>
        <?php
        } else {
            echo wp_kses_post($post_title);
        }
        ?>
    </div>


<?php
    $html = ob_get_clean();

    return $html;
}

// container
add_filter('wcps_generate_element_html_container', "wcps_generate_element_html_container", 10, 4);
function wcps_generate_element_html_container($html, $postData, $element, $children)
{

    $type = isset($element['type']) ? $element['type'] : '';
    $id = isset($element['id']) ? $element['id'] : '';
    $options = isset($element['options']) ? $element['options'] : [];


    $animateOn = isset($options['animateOn']) ? $options['animateOn'] : [];
    $animateRules = isset($animateOn['rules']) ? $animateOn['rules'] : [];

    if (!empty($animateRules)) {
        wp_enqueue_style('animate');
        wp_enqueue_script('pgpostgrid_builder-js');
    }



    ob_start();

?>
    <div class="<?php echo esc_attr($type); ?>" id="element-<?php echo esc_attr($id); ?>"
        <?php if (!empty($animateRules)): ?> data-animateOn="<?php echo esc_attr(json_encode($animateRules)) ?>" <?php endif; ?>>

        <?php
        echo renderContentRecursive_wcps($postData, $children); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        ?>
    </div>


<?php
    $html = ob_get_clean();

    return $html;
}


// layer
add_filter('wcps_generate_element_html_layer', "wcps_generate_element_html_layer", 10, 4);
function wcps_generate_element_html_layer($html, $postData, $element, $children)
{

    $type = isset($element['type']) ? $element['type'] : '';
    $id = isset($element['id']) ? $element['id'] : '';
    $options = isset($element['options']) ? $element['options'] : [];


    $animateOn = isset($options['animateOn']) ? $options['animateOn'] : [];
    $animateRules = isset($animateOn['rules']) ? $animateOn['rules'] : [];


    $post_id = isset($postData->ID) ? $postData->ID : '';

    if (!empty($animateRules)) {
        wp_enqueue_style('animate');
        wp_enqueue_script('pgpostgrid_builder-js');
    }

    ob_start();


?>
    <div class="<?php echo esc_attr($type); ?>" id="element-<?php echo esc_attr($id); ?>"
        <?php if (!empty($animateRules)): ?> data-animateOn="<?php echo esc_attr(json_encode($animateRules)) ?>" <?php endif; ?>>
        <?php
        echo renderContentRecursive_wcps($postData, $children); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        ?>
    </div>


<?php
    $html = ob_get_clean();

    return $html;
}











// postThumbnail
add_filter('wcps_generate_element_html_postThumbnail', "wcps_generate_element_html_postThumbnail", 10, 4);
function wcps_generate_element_html_postThumbnail($html, $postData, $element, $children)
{

    $post_ID = isset($postData->ID) ? $postData->ID : '';


    $type = isset($element['type']) ? $element['type'] : '';
    $id = isset($element['id']) ? $element['id'] : '';
    $options = isset($element['options']) ? $element['options'] : [];

    $linkTo = isset($options['linkTo']) ? $options['linkTo'] : 'postUrl';
    $target = isset($options['target']) ? $options['target'] : '_blank';

    $lazyLoad = isset($options['lazy']) ? $options['lazy'] : true;
    $lazyLoadSrc = isset($options['lazySrc']) ? $options['lazySrc'] : '';
    $featuredImageLinkTo = isset($options['linkTo']) ? $options['linkTo'] : '';
    $featuredImageLinkToMetaKey = isset($options['linkToMetaKey']) ? $options['linkToMetaKey'] : '';
    $featuredImageAltTextSrc = isset($options['altTextSrc']) ? $options['altTextSrc'] : 'imgAltText';
    $featuredImageTitleTextSrc = isset($options['titleTextSrc']) ? $options['titleTextSrc'] : 'imgTitle';
    $featuredImageAltTextCustom = isset($options['altTextCustom']) ? $options['altTextCustom'] : '';
    $featuredImageAltTextMetaKey = isset($options['altTextMetaKey']) ? $options['altTextMetaKey'] : '';
    $linkTarget = isset($options['linkTarget']) ? $options['linkTarget'] : '_blank';
    $customUrl = isset($options['customUrl']) ? $options['customUrl'] : '';
    $linkAttr = isset($options['linkAttr']) ? $options['linkAttr'] : [];
    $rel = isset($options['rel']) ? $options['rel'] : '';
    $size = isset($options['size']['Desktop']) ? $options['size']['Desktop'] : 'full';




    $post_id = isset($postData->ID) ? $postData->ID : '';
    $post_title = isset($postData->post_title) ? $postData->post_title : '';

    $post_url = get_permalink($post_id);


    $thumb_id = get_post_thumbnail_id($post_ID);
    $image_srcs = wp_get_attachment_image_src($thumb_id, $size);
    $image_src_url = isset($image_srcs[0]) ? $image_srcs[0] : '';
    $image_src_w = isset($image_srcs[1]) ? $image_srcs[1] : '';
    $image_src_h = isset($image_srcs[2]) ? $image_srcs[2] : '';
    $attachment_url = wp_get_attachment_url($thumb_id);
    $attachment_post = get_post($thumb_id);
    $image_srcset = wp_get_attachment_image_srcset($thumb_id);





    $linkUrl = "";
    $author_id = get_post_field('post_author', $post_ID);
    if ($featuredImageLinkTo == 'postUrl') {
        $linkUrl = get_permalink($post_ID);
    } else if ($featuredImageLinkTo == 'customField') {
        $linkUrl = get_post_meta($post_ID, $featuredImageLinkToMetaKey, true);
    } else if ($featuredImageLinkTo == 'authorMeta') {
        $linkUrl = get_user_meta($author_id, $featuredImageLinkToMetaKey, true);
    } else if ($featuredImageLinkTo == 'authorMail') {
        $user = get_user_by('ID', $author_id);
        $linkUrl = $user->user_email;
        $linkUrl = "mailto:$linkUrl";
    } else if ($featuredImageLinkTo == 'authorUrl') {
        $user = get_user_by('ID', $author_id);
        $linkUrl = $user->user_url;
    } else if ($featuredImageLinkTo == 'authorLink') {
        $linkUrl = get_the_author_link($author_id);
    } else if ($featuredImageLinkTo == 'homeUrl') {
        $linkUrl = get_bloginfo('url');
    } else if ($featuredImageLinkTo == 'custom') {
        $linkUrl = $customUrl;
    }
    $altText = '';
    if ($featuredImageAltTextSrc == 'imgAltText') {
        $altText = get_post_meta($thumb_id, '_wp_attachment_image_alt', true);
    } else if ($featuredImageAltTextSrc == 'imgCaption') {
        $altText = $attachment_post->post_excerpt;
    } else if ($featuredImageAltTextSrc == 'imgDescription') {
        $altText = $attachment_post->post_content;
    } else if ($featuredImageAltTextSrc == 'imgTitle') {
        $altText = get_the_title($thumb_id);
    } else if ($featuredImageAltTextSrc == 'imgSlug') {
        $altText = get_post_field('post_name', $post_ID);
    } else if ($featuredImageAltTextSrc == 'postTitle') {
        $altText = get_the_title($post_ID);
    } else if ($featuredImageAltTextSrc == 'excerpt') {
        $altText = get_the_excerpt($post_ID);
    } else if ($featuredImageAltTextSrc == 'postSlug') {
        $altText = get_the_excerpt($post_ID);
    } else if ($featuredImageAltTextSrc == 'customField') {
        $altText = get_post_meta($post_ID, $featuredImageAltTextMetaKey, true);
    } else if ($featuredImageAltTextSrc == 'custom') {
        $altText = $featuredImageAltTextCustom;
    }
    $titleText = '';
    if ($featuredImageTitleTextSrc == 'imgAltText') {
        $titleText = get_post_meta($thumb_id, '_wp_attachment_image_alt', true);
    } else if ($featuredImageTitleTextSrc == 'imgCaption') {
        $titleText = $attachment_post->post_excerpt;
    } else if ($featuredImageTitleTextSrc == 'imgDescription') {
        $titleText = $attachment_post->post_content;
    } else if ($featuredImageTitleTextSrc == 'imgTitle') {
        $titleText = get_the_title($thumb_id);
    } else if ($featuredImageTitleTextSrc == 'imgSlug') {
        $titleText = get_post_field('post_name', $post_ID);
    } else if ($featuredImageTitleTextSrc == 'postTitle') {
        $titleText = get_the_title($post_ID);
    } else if ($featuredImageTitleTextSrc == 'excerpt') {
        $titleText = get_the_excerpt($post_ID);
    } else if ($featuredImageTitleTextSrc == 'postSlug') {
        $titleText = get_the_excerpt($post_ID);
    } else if ($featuredImageTitleTextSrc == 'customField') {
        $titleText = get_post_meta($post_ID, $featuredImageAltTextMetaKey, true);
    } else if ($featuredImageTitleTextSrc == 'custom') {
        $titleText = $featuredImageAltTextCustom;
    }



    if ($lazyLoad == true) {
        $dataSrc = $attachment_url;
        // $lazy_img_src = $lazyLoadSrc;
        $attachment_url = $lazyLoadSrc;
        $lazy = "lazy";
    } else {
        // $attachment_url_img = $attachment_url;
        // $attachment_url = $attachment_url_img;
        $lazy = "eager";
        $dataSrc = "";
    }



    if (empty($image_src_url)) return;



    ob_start();

?>
    <div class="<?php echo esc_attr($type); ?>" id="element-<?php echo esc_attr($id); ?>">

        <?php if (!empty($featuredImageLinkTo)) : ?>
            <a href="<?php echo (!empty($linkUrl)) ? esc_url($linkUrl) : esc_url($post_url); ?>" rel="<?php echo esc_attr($rel); ?>" target="<?php echo esc_attr($linkTarget); ?>" <?php //echo $linkAttrStr; 
                                                                                                                                                                                    ?>>
                <img <?php //echo ($linkAttrStr); 
                        ?> srcset="<?php echo esc_attr($image_srcset); ?>" src="<?php echo esc_url($image_src_url); ?>"
                    <?php if ($lazyLoad == true) : ?> data-src="<?php echo esc_url($dataSrc); ?>" loading="<?php echo esc_attr($lazy) ?>" <?php endif; ?>
                    width="<?php echo esc_attr($image_src_w); ?>" height="<?php echo esc_attr($image_src_h); ?>" alt="<?php echo esc_attr($altText); ?>" title="<?php echo esc_attr($titleText); ?>" />
            </a>
        <?php else : ?>
            <img <?php //echo ($linkAttrStr); 
                    ?> srcset="<?php echo esc_attr($image_srcset); ?>" src="<?php echo esc_url($image_src_url); ?>"
                <?php if ($lazyLoad == true) : ?> data-src="<?php echo esc_url($dataSrc); ?>" loading="<?php echo esc_attr($lazy) ?>" <?php endif; ?>
                width="<?php echo esc_attr($image_src_w); ?>" height="<?php echo esc_attr($image_src_h); ?>" alt="<?php echo esc_attr($altText); ?>" title="<?php echo esc_attr($titleText); ?>" />
        <?php endif; ?>

    </div>


<?php
    $html = ob_get_clean();

    return $html;
}


// postExcerpt
add_filter('wcps_generate_element_html_postExcerpt', "wcps_generate_element_html_postExcerpt", 10, 4);
function wcps_generate_element_html_postExcerpt($html, $postData, $element, $children)
{

    $type = isset($element['type']) ? $element['type'] : '';
    $id = isset($element['id']) ? $element['id'] : '';
    $options = isset($element['options']) ? $element['options'] : [];

    $readmoreLinkTo = isset($options['readmoreLinkTo']) ? $options['readmoreLinkTo'] : 'postUrl';
    $target = isset($options['target']) ? $options['target'] : '_blank';
    $limitBy = isset($options['limitBy']) ? $options['limitBy'] : 'word';
    $limitCount = isset($options['limitCount']) ? $options['limitCount'] : 25;
    $readMoreText = isset($options['readMoreText']) ? $options['readMoreText'] : __('Read More', 'woocommerce-products-slider');


    $post_id = isset($postData->ID) ? $postData->ID : '';
    $post_excerpt = isset($postData->post_excerpt) ? $postData->post_excerpt : '';

    $post_url = get_permalink($post_id);


    ob_start();

?>
    <div class="<?php echo esc_attr($type); ?>" id="element-<?php echo esc_attr($id); ?>">
        <?php


        if ($readmoreLinkTo == 'postUrl') {
        ?>

            <?php

            if (!empty($post_excerpt)) {
            ?>
                <p class="excerptText">
                    <?php echo wp_kses_post($post_excerpt); ?>
                </p>
            <?php
            }

            ?>
            <a href="<?php echo esc_url($post_url); ?>" class="redmore"
                target="<?php echo esc_attr($target); ?>"><?php echo wp_kses_post($readMoreText); ?>
            </a>
        <?php
        } else {
            echo wp_kses_post($post_excerpt);
        }
        ?>
    </div>


<?php
    $html = ob_get_clean();

    return $html;
}

// readMore
add_filter('wcps_generate_element_html_readMore', "wcps_generate_element_html_readMore", 10, 4);
function wcps_generate_element_html_readMore($html, $postData, $element, $children)
{

    $type = isset($element['type']) ? $element['type'] : '';
    $id = isset($element['id']) ? $element['id'] : '';
    $options = isset($element['options']) ? $element['options'] : [];

    $readmoreLinkTo = isset($options['readmoreLinkTo']) ? $options['readmoreLinkTo'] : 'postUrl';
    $target = isset($options['target']) ? $options['target'] : '_blank';
    $readMoreText = isset($options['readMoreText']) ? $options['readMoreText'] : __('Read More', 'woocommerce-products-slider');


    $post_id = isset($postData->ID) ? $postData->ID : '';

    $post_url = get_permalink($post_id);


    ob_start();

?>
    <div class="<?php echo esc_attr($type); ?>" id="element-<?php echo esc_attr($id); ?>">
        <?php


        if ($readmoreLinkTo == 'postUrl') {
        ?>


            <a href="<?php echo esc_url($post_url); ?>" class="redmore"
                target="<?php echo esc_attr($target); ?>"><?php echo wp_kses_post($readMoreText); ?>
            </a>
        <?php
        }
        ?>
    </div>


<?php
    $html = ob_get_clean();

    return $html;
}

// customText
add_filter('wcps_generate_element_html_customText', "wcps_generate_element_html_customText", 10, 4);
function wcps_generate_element_html_customText($html, $postData, $element, $children)
{

    $type = isset($element['type']) ? $element['type'] : '';
    $id = isset($element['id']) ? $element['id'] : '';
    $options = isset($element['options']) ? $element['options'] : [];
    $customTag = isset($options['customTag']) ? $options['customTag'] : 'div';
    $content = isset($options['content']) ? $options['content'] : "";


    $post_id = isset($postData->ID) ? $postData->ID : '';

    $post_url = get_permalink($post_id);


    ob_start();

?>
    <<?php echo tag_escape($customTag) ?> class="<?php echo esc_attr($type); ?>" id="element-<?php echo esc_attr($id); ?>">
        <?php echo wp_kses_post($content); ?>
    </<?php echo tag_escape($customTag) ?>>


<?php
    $html = ob_get_clean();

    return $html;
}



// postDate
add_filter('wcps_generate_element_html_postDate', "wcps_generate_element_html_postDate", 10, 4);
function wcps_generate_element_html_postDate($html, $postData, $element, $children)
{

    $type = isset($element['type']) ? $element['type'] : '';
    $id = isset($element['id']) ? $element['id'] : '';
    $options = isset($element['options']) ? $element['options'] : [];

    $linkTo = isset($options['linkTo']) ? $options['linkTo'] : '';
    $target = isset($options['target']) ? $options['target'] : '_blank';
    $prefixText = isset($options['prefixText']) ? $options['prefixText'] : '';
    $postfixText = isset($options['postfixText']) ? $options['postfixText'] : '';
    $format = !empty($options['format']) ? $options['format'] : 'd-m-Y';
    $post_id = isset($postData->ID) ? $postData->ID : '';
    $post_title = isset($postData->post_title) ? $postData->post_title : '';

    $post_url = get_permalink($post_id);
    $the_post = get_post($post_id);
    $post_date = isset($the_post->post_date) ? $the_post->post_date : '';


    $formatedPostDate = gmdate($format, strtotime($post_date));



    ob_start();

?>
    <div class="<?php echo esc_attr($type); ?>" id="element-<?php echo esc_attr($id); ?>">
        <?php if (!empty($prefixText)): ?>
            <div class="prefix">
                <?php echo wp_kses_post($prefixText); ?>
            </div>
        <?php endif; ?>



        <?php

        if ($linkTo == 'postUrl') {
        ?>
            <a href="<?php echo esc_url($post_url); ?>"
                target="<?php echo esc_attr($target); ?>">
                <?php echo wp_kses_post($formatedPostDate); ?>
            </a>
        <?php
        } else {
            echo wp_kses_post($formatedPostDate);
        }
        ?>

        <?php if (!empty($postfixText)): ?>
            <div class="postfix">
                <?php echo wp_kses_post($postfixText); ?>
            </div>
        <?php endif; ?>
    </div>


<?php
    $html = ob_get_clean();

    return $html;
}

// postAuthor
add_filter('wcps_generate_element_html_postAuthor', "wcps_generate_element_html_postAuthor", 10, 4);
function wcps_generate_element_html_postAuthor($html, $postData, $element, $children)
{

    $type = isset($element['type']) ? $element['type'] : '';
    $id = isset($element['id']) ? $element['id'] : '';
    $options = isset($element['options']) ? $element['options'] : [];

    $linkTo = isset($options['linkTo']) ? $options['linkTo'] : 'postUrl';
    $target = isset($options['target']) ? $options['target'] : '_blank';
    $prefixText = isset($options['prefixText']) ? $options['prefixText'] : '';
    $postfixText = isset($options['postfixText']) ? $options['postfixText'] : '';
    $post_id = isset($postData->ID) ? $postData->ID : '';
    $post_title = isset($postData->post_title) ? $postData->post_title : '';

    $post_url = get_permalink($post_id);
    $the_post = get_post($post_id);
    $post_author_id = isset($the_post->post_author) ? $the_post->post_author : '';


    ob_start();

?>
    <div class="<?php echo esc_attr($type); ?>" id="element-<?php echo esc_attr($id); ?>">
        <?php if (!empty($prefixText)): ?>
            <div class="prefix">
                <?php echo wp_kses_post($prefixText); ?>
            </div>
        <?php endif; ?>



        <?php

        if ($linkTo == 'postUrl') {
        ?>
            <a href="<?php echo esc_url($post_url); ?>"
                target="<?php echo esc_attr($target); ?>">
                <?php echo wp_kses_post(get_the_author_meta('display_name', $post_author_id)) ?>
            </a>
        <?php
        } else {
            echo wp_kses_post(get_the_author_meta('display_name', $post_author_id));;
        }
        ?>

        <?php if (!empty($postfixText)): ?>
            <div class="postfix">
                <?php echo wp_kses_post($postfixText); ?>
            </div>
        <?php endif; ?>
    </div>


<?php
    $html = ob_get_clean();

    return $html;
}

// postAuthorAvatar
add_filter('wcps_generate_element_html_postAuthorAvatar', "wcps_generate_element_html_postAuthorAvatar", 10, 4);
function wcps_generate_element_html_postAuthorAvatar($html, $postData, $element, $children)
{

    $type = isset($element['type']) ? $element['type'] : '';
    $id = isset($element['id']) ? $element['id'] : '';
    $options = isset($element['options']) ? $element['options'] : [];


    $linkTo = isset($options['linkTo']) ? $options['linkTo'] : 'postUrl';
    $target = isset($options['target']) ? $options['target'] : '_blank';
    $prefixText = isset($options['prefixText']) ? $options['prefixText'] : '';
    $postfixText = isset($options['postfixText']) ? $options['postfixText'] : '';
    $post_id = isset($postData->ID) ? $postData->ID : '';
    $post_title = isset($postData->post_title) ? $postData->post_title : '';

    $post_url = get_permalink($post_id);
    $the_post = get_post($post_id);
    $post_author_id = isset($the_post->post_author) ? $the_post->post_author : '';

    $fieldAvatarSize = '48';
    $fieldDefaultAvatar = '';
    $fieldAvatarRating = 'G';

    ob_start();

?>
    <div class="<?php echo esc_attr($type); ?>" id="element-<?php echo esc_attr($id); ?>">
        <?php if (!empty($prefixText)): ?>
            <div class="prefix">
                <?php echo wp_kses_post($prefixText); ?>
            </div>
        <?php endif; ?>



        <?php

        if ($linkTo == 'postUrl') {
        ?>
            <a class="author-link" href="<?php echo esc_url($post_url); ?>"
                target="<?php echo esc_attr($target); ?>">
                <img class="avatar" src="<?php echo esc_url(get_avatar_url($post_author_id, ['size' => $fieldAvatarSize, 'default' => $fieldDefaultAvatar, 'rating' => $fieldAvatarRating]));
                                            ?>" alt=" <?php echo esc_attr(get_the_author_meta('display_name', $post_author_id)) ?> " />
            </a>
        <?php
        } else {
        ?>
            <img class="avatar" src="<?php echo esc_url(get_avatar_url($post_author_id, ['size' => $fieldAvatarSize, 'default' => $fieldDefaultAvatar, 'rating' => $fieldAvatarRating]));
                                        ?>" alt=" <?php echo esc_attr(get_the_author_meta('display_name', $post_author_id)) ?> " />
        <?php
        }
        ?>

        <?php if (!empty($postfixText)): ?>
            <div class="postfix">
                <?php echo wp_kses_post($postfixText); ?>
            </div>
        <?php endif; ?>
    </div>


<?php
    $html = ob_get_clean();

    return $html;
}




// postCategories
add_filter('wcps_generate_element_html_postCategories', "wcps_generate_element_html_postCategories", 10, 4);
function wcps_generate_element_html_postCategories($html, $postData, $element, $children)
{

    $type = isset($element['type']) ? $element['type'] : '';
    $id = isset($element['id']) ? $element['id'] : '';
    $options = isset($element['options']) ? $element['options'] : [];


    $maxCount = isset($options['maxCount']) ? $options['maxCount'] : 1;
    $postCount = isset($options['postCount']) ? $options['postCount'] : false;
    $separator = isset($options['separator']) ? $options['separator'] : ', ';
    $linkTo = isset($options['linkTo']) ? $options['linkTo'] : 'termUrl';
    $target = isset($options['target']) ? $options['target'] : '_blank';
    $prefixText = isset($options['prefixText']) ? $options['prefixText'] : '';
    $postfixText = isset($options['postfixText']) ? $options['postfixText'] : '';
    $post_id = isset($postData->ID) ? $postData->ID : '';
    $post_title = isset($postData->post_title) ? $postData->post_title : '';

    $post_url = get_permalink($post_id);
    $the_post = get_post($post_id);
    $post_author_id = isset($the_post->post_author) ? $the_post->post_author : '';

    $itemsLinkToCustomMeta = '';
    $itemsCustomUrl = '';

    $taxonomy = 'category';
    $terms = get_the_terms($post_id, $taxonomy);
    $termsCount = (is_array($terms)) ? count($terms) : 0;


    ob_start();

?>
    <div class="<?php echo esc_attr($type); ?>" id="element-<?php echo esc_attr($id); ?>">
        <?php if (!empty($prefixText)): ?>
            <div class="prefix">
                <?php echo wp_kses_post($prefixText); ?>
            </div>
        <?php endif; ?>

        <div class="terms-items">

            <?php
            $i = 1;
            if (!empty($terms))
                foreach ($terms as $term) {
                    $term_id = $term->term_id;
                    $term_post_count = $term->count;
                    if ($linkTo == 'postUrl') {
                        $linkUrl = get_permalink($post_id);
                    } else if ($linkTo == 'termUrl') {
                        $linkUrl = get_term_link($term_id);
                    } else if ($linkTo == 'customField') {
                        $linkUrl = get_post_meta($post_id, $itemsLinkToCustomMeta, true);
                    } else if ($linkTo == 'authorUrl') {
                        $author_id = get_post_field('post_author', $post_id);
                        $user = get_user_by('ID', $author_id);
                        $linkUrl = $user->user_url;
                    } else if ($linkTo == 'authorLink') {
                        $author_id = get_post_field('post_author', $post_id);
                        $linkUrl = get_author_posts_url($author_id);
                    } else if ($linkTo == 'homeUrl') {
                        $linkUrl = get_bloginfo('url');
                    } else if ($linkTo == 'customUrl') {
                        $linkUrl = $itemsCustomUrl;
                    }

                    if ($i > $maxCount)
                        break;

            ?>

                <div class="term-item">
                    <?php if (!empty($linkTo)) : ?>
                        <a href="<?php echo esc_url($linkUrl); ?>" target="<?php echo esc_attr($target); ?>" class="term-link">

                            <?php if (!empty($itemsPrefix)) : ?>
                                <span class='prefix'>
                                    <?php echo wp_kses_post($itemsPrefix); ?>
                                </span>
                            <?php endif; ?>
                            <span class='term-title'>
                                <?php echo wp_kses_post($term->name); ?>
                            </span>
                            <?php if ($postCount) : ?>
                                <span class='post-count'>
                                    <?php echo wp_kses_post($term_post_count); ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($itemsPostfix)) : ?>
                                <span class='postfix'>
                                    <?php echo wp_kses_post($itemsPostfix); ?>
                                </span>
                            <?php endif; ?>

                        </a>
                    <?php else : ?>


                        <?php if (!empty($itemsPrefix)) : ?>
                            <span class='prefix'>
                                <?php echo wp_kses_post($itemsPrefix); ?>
                            </span>
                        <?php endif; ?>
                        <span class='term-title'>
                            <?php echo wp_kses_post($term->name); ?>
                        </span>
                        <?php if ($postCount) : ?>
                            <span class='post-count'>
                                <?php echo wp_kses_post($term_post_count); ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($itemsPostfix)) : ?>
                            <span class='postfix'>
                                <?php echo wp_kses_post($itemsPostfix); ?>
                            </span>
                        <?php endif; ?>


                    <?php endif; ?>
                    <?php if ($maxCount > $i) : ?>
                        <?php if (!empty($separatorText)) : ?>
                            <span class='separator'>
                                <?php echo esc_html($separatorText); ?>
                            </span>
                        <?php endif; ?>
                    <?php endif; ?>

                </div>

            <?php
                    $i++;
                }
            ?>


        </div>


        <?php if (!empty($postfixText)): ?>
            <div class="postfix">
                <?php echo wp_kses_post($postfixText); ?>
            </div>
        <?php endif; ?>
    </div>


<?php
    $html = ob_get_clean();

    return $html;
}

// postTags
add_filter('wcps_generate_element_html_postTags', "wcps_generate_element_html_postTags", 10, 4);
function wcps_generate_element_html_postTags($html, $postData, $element, $children)
{

    $type = isset($element['type']) ? $element['type'] : '';
    $id = isset($element['id']) ? $element['id'] : '';
    $options = isset($element['options']) ? $element['options'] : [];


    $maxCount = isset($options['maxCount']) ? $options['maxCount'] : 1;
    $postCount = isset($options['postCount']) ? $options['postCount'] : false;
    $separator = isset($options['separator']) ? $options['separator'] : ', ';
    $linkTo = isset($options['linkTo']) ? $options['linkTo'] : 'termUrl';
    $target = isset($options['target']) ? $options['target'] : '_blank';
    $prefixText = isset($options['prefixText']) ? $options['prefixText'] : '';
    $postfixText = isset($options['postfixText']) ? $options['postfixText'] : '';
    $post_id = isset($postData->ID) ? $postData->ID : '';
    $post_title = isset($postData->post_title) ? $postData->post_title : '';

    $post_url = get_permalink($post_id);
    $the_post = get_post($post_id);
    $post_author_id = isset($the_post->post_author) ? $the_post->post_author : '';

    $itemsLinkToCustomMeta = '';
    $itemsCustomUrl = '';

    $taxonomy = 'post_tag';
    $terms = get_the_terms($post_id, $taxonomy);
    $termsCount = (is_array($terms)) ? count($terms) : 0;


    ob_start();

?>
    <div class="<?php echo esc_attr($type); ?>" id="element-<?php echo esc_attr($id); ?>">
        <?php if (!empty($prefixText)): ?>
            <div class="prefix">
                <?php echo wp_kses_post($prefixText); ?>
            </div>
        <?php endif; ?>

        <div class="terms-items">

            <?php
            $i = 1;
            if (!empty($terms))
                foreach ($terms as $term) {
                    $term_id = $term->term_id;
                    $term_post_count = $term->count;
                    if ($linkTo == 'postUrl') {
                        $linkUrl = get_permalink($post_id);
                    } else if ($linkTo == 'termUrl') {
                        $linkUrl = get_term_link($term_id);
                    } else if ($linkTo == 'customField') {
                        $linkUrl = get_post_meta($post_id, $itemsLinkToCustomMeta, true);
                    } else if ($linkTo == 'authorUrl') {
                        $author_id = get_post_field('post_author', $post_id);
                        $user = get_user_by('ID', $author_id);
                        $linkUrl = $user->user_url;
                    } else if ($linkTo == 'authorLink') {
                        $author_id = get_post_field('post_author', $post_id);
                        $linkUrl = get_author_posts_url($author_id);
                    } else if ($linkTo == 'homeUrl') {
                        $linkUrl = get_bloginfo('url');
                    } else if ($linkTo == 'customUrl') {
                        $linkUrl = $itemsCustomUrl;
                    }

                    if ($i > $maxCount)
                        break;

            ?>

                <div class="item">
                    <?php if (!empty($linkTo)) : ?>
                        <a href="<?php echo esc_url($linkUrl); ?>" target="<?php echo esc_attr($target); ?>" class="term-link">

                            <?php if (!empty($itemsPrefix)) : ?>
                                <span class='prefix'>
                                    <?php echo wp_kses_post($itemsPrefix); ?>
                                </span>
                            <?php endif; ?>
                            <span class='term-title'>
                                <?php echo wp_kses_post($term->name); ?>
                            </span>
                            <?php if ($postCount) : ?>
                                <span class='post-count'>
                                    <?php echo wp_kses_post($term_post_count); ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($itemsPostfix)) : ?>
                                <span class='postfix'>
                                    <?php echo wp_kses_post($itemsPostfix); ?>
                                </span>
                            <?php endif; ?>

                        </a>
                    <?php else : ?>


                        <?php if (!empty($itemsPrefix)) : ?>
                            <span class='prefix'>
                                <?php echo wp_kses_post($itemsPrefix); ?>
                            </span>
                        <?php endif; ?>
                        <span class='term-title'>
                            <?php echo wp_kses_post($term->name); ?>
                        </span>
                        <?php if ($postCount) : ?>
                            <span class='post-count'>
                                <?php echo wp_kses_post($term_post_count); ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($itemsPostfix)) : ?>
                            <span class='postfix'>
                                <?php echo wp_kses_post($itemsPostfix); ?>
                            </span>
                        <?php endif; ?>


                    <?php endif; ?>
                    <?php if ($maxCount > $i) : ?>
                        <?php if (!empty($separatorText)) : ?>
                            <span class='separator'>
                                <?php echo esc_html($separatorText); ?>
                            </span>
                        <?php endif; ?>
                    <?php endif; ?>

                </div>

            <?php
                    $i++;
                }
            ?>


        </div>


        <?php if (!empty($postfixText)): ?>
            <div class="postfix">
                <?php echo wp_kses_post($postfixText); ?>
            </div>
        <?php endif; ?>
    </div>


<?php
    $html = ob_get_clean();

    return $html;
}




// wooPrice
add_filter('wcps_generate_element_html_wooPrice', "wcps_generate_element_html_wooPrice", 10, 4);
function wcps_generate_element_html_wooPrice($html, $postData, $element, $children)
{

    $post_id = isset($postData->ID) ? $postData->ID : '';


    $type = isset($element['type']) ? $element['type'] : '';
    $id = isset($element['id']) ? $element['id'] : '';
    $options = isset($element['options']) ? $element['options'] : [];
    $currencySymbole = isset($options['currencySymbole']) ? $options['currencySymbole'] : '$';
    $currencyPosition = isset($options['currencyPosition']) ? $options['currencyPosition'] : '';
    $separatorText = isset($options['separatorText']) ? $options['separatorText'] : '';
    $prefixText = isset($options['prefixText']) ? $options['prefixText'] : '';
    $postfixText = isset($options['postfixText']) ? $options['postfixText'] : '';
    $post_title = isset($postData->post_title) ? $postData->post_title : '';

    $post_url = get_permalink($post_id);

    global $product;
    $product_type = ($product != null) ? $product->get_type() : '';

    if (function_exists("get_woocommerce_currency_symbol")) {
        $currency_symbol = get_woocommerce_currency_symbol();
    } else {
        $currency_symbol = $currencySymbole;
    }

    ob_start();




?>
    <div class="<?php echo esc_attr($type); ?>" id="element-<?php echo esc_attr($id); ?>">

        <?php if (!empty($prefixText)): ?>
            <div class="prefix">
                <?php echo wp_kses_post($prefixText); ?>
            </div>
        <?php endif; ?>


        <?php if ($product_type == 'simple' || $product_type == 'external') :
            $regular_price = ($product != null) ? $product->get_regular_price() : '';
            $sale_price = ($product != null) ? $product->get_sale_price() : ''; ?>
            <?php if (empty($sale_price)) : ?>
                <span class=' regular'>
                    <span class='currency'><?php echo wp_kses_post($currency_symbol); ?></span><?php echo wp_kses_post($regular_price); ?>
                </span>
            <?php endif; ?>
            <?php if (!empty($sale_price)) : ?>
                <span class='regular'>
                    <span class='currency'><?php echo wp_kses_post($currency_symbol); ?></span><?php echo wp_kses_post($regular_price); ?>
                </span>
                <span class=' sale-price'>
                    <span class='currency'><?php echo wp_kses_post($currency_symbol); ?></span><?php echo wp_kses_post($sale_price); ?>
                </span>
            <?php endif; ?>
        <?php endif;
        if ($product_type == 'variable') :
            $min_price = ($product != null) ? $product->get_variation_price() : '';
            $max_price = ($product != null) ? $product->get_variation_price('max') : '';
        ?>
            <span class='regular'>
                <span class='currency'><?php echo wp_kses_post($currency_symbol); ?></span><?php echo wp_kses_post($min_price); ?>
            </span>
            <span class='regular'>
                <?php echo wp_kses_post($separatorText); ?>
            </span>
            <span class='regular'>
                <span class='currency'><?php echo wp_kses_post($currency_symbol); ?></span><?php echo wp_kses_post($max_price); ?>
            </span>
        <?php endif; ?>
        <?php if ($product_type == 'grouped') :
            $child_prices = array();
            foreach ($product->get_children() as $child_id) {
                $child_prices[] = get_post_meta($child_id, '_price', true);
            }
            $child_prices = array_unique($child_prices);
            $min_price = min($child_prices);
            $max_price = max($child_prices);
        ?>
            <span class='regular'>
                <span class='currency'><?php echo wp_kses_post($currency_symbol); ?></span><?php echo wp_kses_post($min_price); ?>
            </span>
            <span class='regular'>
                <?php echo wp_kses_post($separatorText); ?>
            </span>
            <span class='regular'>
                <span class='currency'><?php echo wp_kses_post($currency_symbol); ?></span><?php echo wp_kses_post($max_price); ?>
            </span>
        <?php endif; ?>

        <?php if (!empty($postfixText)): ?>
            <div class="postfix">
                <?php echo wp_kses_post($postfixText); ?>
            </div>
        <?php endif; ?>
    </div>


<?php
    $html = ob_get_clean();

    return $html;
}

// wooAddToCart
add_filter('wcps_generate_element_html_wooAddToCart', "wcps_generate_element_html_wooAddToCart", 10, 4);
function wcps_generate_element_html_wooAddToCart($html, $postData, $element, $children)
{

    $post_id = isset($postData->ID) ? $postData->ID : '';


    $type = isset($element['type']) ? $element['type'] : '';
    $id = isset($element['id']) ? $element['id'] : '';
    $options = isset($element['options']) ? $element['options'] : [];
    $addToCartText = isset($options['addToCartText']) ? $options['addToCartText'] : __("View Product", 'woocommerce-products-slider');
    $cartBtnRel = isset($options['rel']) ? $options['rel'] : '';

    $quantityEnable = isset($options['quantityEnable']) ? $options['quantityEnable'] : '';
    $iconPosition = isset($options['iconPosition']) ? $options['iconPosition'] : '';
    $icon = isset($options['icon']) ? $options['icon'] : '';

    $iconLibrary = isset($icon['library']) ? $icon['library'] : '';
    $iconSrcType = isset($icon['srcType']) ? $icon['srcType'] : '';
    $iconSrc = isset($icon['iconSrc']) ? $icon['iconSrc'] : '';
    $iconClass = isset($icon['class']) ? $icon['class'] : '';


    $prefixText = isset($options['prefixText']) ? $options['prefixText'] : '';
    $postfixText = isset($options['postfixText']) ? $options['postfixText'] : '';
    $post_title = isset($postData->post_title) ? $postData->post_title : '';
    $cartBtnAjax = isset($cartBtnOptions['ajax']) ? $cartBtnOptions['ajax'] : true;
    $cartBtnText = __("View Product", 'woocommerce-products-slider');

    if ($iconLibrary == 'fontAwesome') {
        wp_enqueue_style('fontawesome-icons');
    } else if ($iconLibrary == 'iconFont') {
        wp_enqueue_style('icofont-icons');
    } else if ($iconLibrary == 'bootstrap') {
        wp_enqueue_style('bootstrap-icons');
    }

    $fontIconHtml = '<span class="' . $iconClass . ' ' . $iconSrc . '"></span>';


    $post_url = get_permalink($post_id);
    $quantityInputQuantity = 1;


    global $product;
    $productSku = ($product == null) ? '' : $product->get_sku();
    $productType = ($product == null) ? '' : $product->get_type();


    if ($productType == 'simple') {
        $cartUrl = ($cartBtnAjax) ? '?add-to-cart=' . esc_attr($post_id) : '?add-to-cart=' . $post_id . '&quantity=' . esc_attr($quantityInputQuantity);
    } else {
        $cartUrl = get_permalink($post_id);
    }

    ob_start();




?>
    <div class="<?php echo esc_attr($type); ?>" id="element-<?php echo esc_attr($id); ?>">

        <?php if (!empty($prefixText)): ?>
            <div class="prefix">
                <?php echo wp_kses_post($prefixText); ?>
            </div>
        <?php endif; ?>

        <?php if ($iconPosition == 'afterPrefix') : ?>
            <?php echo wp_kses_post($fontIconHtml); ?>
        <?php endif; ?>
        <?php
        if ($productType == 'simple') :
        ?>
            <?php if ($quantityEnable) : ?>
                <div class='quantityWrap' data-blockid="<?php echo esc_attr($post_id); ?>">
                    <span class='quantityDecrease'>-</span>
                    <input class='quantityInput' placeholder="1" size="4" type="text" inputmode="numeric"
                        value="<?php echo esc_attr($quantityInputQuantity); ?>" />
                    <span class='quantityIncrease'>+</span>
                </div>
            <?php endif; ?>
            <a class='<?php echo ($cartBtnAjax) ? 'ajax_add_to_cart' : ''; ?> cartBtn'
                data-quantity="<?php echo esc_attr($quantityInputQuantity); ?>" data-product_id="<?php echo esc_attr($post_id); ?>"
                data-product_sku="<?php echo esc_attr($productSku); ?>" aria-label="<?php echo esc_attr($cartBtnRel); ?>"
                aria-describedby="<?php echo esc_attr($cartBtnRel); ?>" rel="<?php echo esc_attr($cartBtnRel); ?>"
                <?php if ($cartBtnAjax):
                    //wp_enqueue_script("wc-add-to-cart");

                ?>
                <?php else: ?>
                href="<?php echo esc_attr($cartUrl); ?>"
                <?php endif; ?>>
                <?php if ($iconPosition == 'beforeCartText') : ?>
                    <?php echo wp_kses_post($fontIconHtml); ?>
                <?php endif; ?>
                <?php echo wp_kses_post($addToCartText); ?>
                <?php if ($iconPosition == 'afterCartText') : ?>
                    <?php echo wp_kses_post($fontIconHtml); ?>
                <?php endif; ?>
            </a>
        <?php else : ?>
            <a class='cartBtn' aria-label="<?php echo esc_attr($cartBtnRel); ?>"
                aria-describedby="<?php echo esc_attr($cartBtnRel); ?>" rel="<?php echo esc_attr($cartBtnRel); ?>"
                href="<?php echo esc_attr($cartUrl); ?>">
                <?php if ($iconPosition == 'beforeCartText') : ?>
                    <?php echo wp_kses_post($fontIconHtml); ?>
                <?php endif; ?>
                <?php echo wp_kses_post($addToCartText); ?>
                <?php if ($iconPosition == 'afterCartText') : ?>
                    <?php echo wp_kses_post($fontIconHtml); ?>
                <?php endif; ?>
            </a>
        <?php endif; ?>
        <?php if ($iconPosition == 'beforePostfix') : ?>
            <?php echo wp_kses_post($fontIconHtml); ?>
        <?php endif; ?>

        <?php if (!empty($postfixText)): ?>
            <div class="postfix">
                <?php echo wp_kses_post($postfixText); ?>
            </div>
        <?php endif; ?>
    </div>


<?php
    $html = ob_get_clean();

    return $html;
}

// wooSaleBadge
add_filter('wcps_generate_element_html_wooSaleBadge', "wcps_generate_element_html_wooSaleBadge", 10, 4);
function wcps_generate_element_html_wooSaleBadge($html, $postData, $element, $children)
{

    $post_id = isset($postData->ID) ? $postData->ID : '';


    $type = isset($element['type']) ? $element['type'] : '';
    $id = isset($element['id']) ? $element['id'] : '';
    $options = isset($element['options']) ? $element['options'] : [];
    $onSaleText = isset($options['onSaleText']) ? $options['onSaleText'] : 'On Sale';

    $iconPosition = isset($options['iconPosition']) ? $options['iconPosition'] : '';
    $icon = isset($options['icon']) ? $options['icon'] : '';

    $iconLibrary = isset($icon['library']) ? $icon['library'] : '';
    $iconSrcType = isset($icon['srcType']) ? $icon['srcType'] : '';
    $iconSrc = isset($icon['iconSrc']) ? $icon['iconSrc'] : '';
    $iconClass = isset($icon['class']) ? $icon['class'] : '';


    $prefixText = isset($options['prefixText']) ? $options['prefixText'] : '';
    $postfixText = isset($options['postfixText']) ? $options['postfixText'] : '';

    if ($iconLibrary == 'fontAwesome') {
        wp_enqueue_style('fontawesome-icons');
    } else if ($iconLibrary == 'iconFont') {
        wp_enqueue_style('icofont-icons');
    } else if ($iconLibrary == 'bootstrap') {
        wp_enqueue_style('bootstrap-icons');
    }

    $fontIconHtml = '<span class="' . $iconClass . ' ' . $iconSrc . '"></span>';


    $post_url = get_permalink($post_id);
    $quantityInputQuantity = 1;


    global $product;
    $productSku = ($product == null) ? '' : $product->get_sku();
    $product_type = ($product == null) ? '' : $product->get_type();

    $onSale = ($product != null) ? $product->is_on_sale() : '';


    if (!$onSale) return;

    ob_start();




?>
    <div class="<?php echo esc_attr($type); ?>" id="element-<?php echo esc_attr($id); ?>">
        <?php if (!empty($prefixText)): ?>
            <div class="prefix">
                <?php echo wp_kses_post($prefixText); ?>
            </div>
        <?php endif; ?>
        <?php if ($iconPosition == 'afterPrefix') : ?>
            <?php echo wp_kses_post($fontIconHtml); ?>
        <?php endif; ?>
        <?php
        if ($product_type != 'variable') :
        ?>
            <span class='on-sale-badge'>
                <?php
                if ($onSale) {
                    echo wp_kses_post($onSaleText);
                }
                ?>
            </span>
        <?php
        endif;
        if ($product_type == 'variable') :
            $onSale = ($product != null) ? $product->is_on_sale() : '';
        ?>
            <span class='on-sale-badge'>
                <?php
                if ($onSale) {
                    echo wp_kses_post($onSaleText);
                }
                ?>
            </span>
        <?php
        endif;
        ?>
        <?php if ($iconPosition == 'beforePostfix') : ?>
            <?php echo wp_kses_post($fontIconHtml); ?>
        <?php endif; ?>
        <?php if (!empty($postfixText)): ?>
            <div class="postfix">
                <?php echo wp_kses_post($postfixText); ?>
            </div>
        <?php endif; ?>
    </div>


<?php
    $html = ob_get_clean();

    return $html;
}

// wooTotalSales
add_filter('wcps_generate_element_html_wooTotalSales', "wcps_generate_element_html_wooTotalSales", 10, 4);
function wcps_generate_element_html_wooTotalSales($html, $postData, $element, $children)
{

    $post_id = isset($postData->ID) ? $postData->ID : '';


    $type = isset($element['type']) ? $element['type'] : '';
    $id = isset($element['id']) ? $element['id'] : '';
    $options = isset($element['options']) ? $element['options'] : [];
    $defaultSaleCount = isset($options['defaultSaleCount']) ? $options['defaultSaleCount'] : 0;

    $iconPosition = isset($options['iconPosition']) ? $options['iconPosition'] : '';
    $icon = isset($options['icon']) ? $options['icon'] : '';

    $iconLibrary = isset($icon['library']) ? $icon['library'] : '';
    $iconSrcType = isset($icon['srcType']) ? $icon['srcType'] : '';
    $iconSrc = isset($icon['iconSrc']) ? $icon['iconSrc'] : '';
    $iconClass = isset($icon['class']) ? $icon['class'] : '';


    $prefixText = isset($options['prefixText']) ? $options['prefixText'] : '';
    $postfixText = isset($options['postfixText']) ? $options['postfixText'] : '';

    if ($iconLibrary == 'fontAwesome') {
        wp_enqueue_style('fontawesome-icons');
    } else if ($iconLibrary == 'iconFont') {
        wp_enqueue_style('icofont-icons');
    } else if ($iconLibrary == 'bootstrap') {
        wp_enqueue_style('bootstrap-icons');
    }

    $fontIconHtml = '<span class="' . $iconClass . ' ' . $iconSrc . '"></span>';


    $post_url = get_permalink($post_id);
    $quantityInputQuantity = 1;


    global $product;
    $productSku = ($product == null) ? '' : $product->get_sku();
    $product_type = ($product == null) ? '' : $product->get_type();

    $productSaleCount = ($product == null) ? '' : $product->get_total_sales();



    ob_start();




?>
    <div class="<?php echo esc_attr($type); ?>" id="element-<?php echo esc_attr($id); ?>">

        <?php if (!empty($prefixText)): ?>
            <div class="prefix">
                <?php echo wp_kses_post($prefixText); ?>
            </div>
        <?php endif; ?>

        <?php if ($iconPosition == 'beforeSaleCount') : ?>
            <?php echo wp_kses_post($fontIconHtml); ?>
        <?php endif; ?>
        <?php echo wp_kses_post($productSaleCount); ?>
        <?php if ($iconPosition == 'afterSaleCount') : ?>
            <?php echo wp_kses_post($fontIconHtml); ?>
        <?php endif; ?>

        <?php if (!empty($postfixText)): ?>
            <div class="postfix">
                <?php echo wp_kses_post($postfixText); ?>
            </div>
        <?php endif; ?>
    </div>


<?php
    $html = ob_get_clean();

    return $html;
}

// wooSKU
add_filter('wcps_generate_element_html_wooSKU', "wcps_generate_element_html_wooSKU", 10, 4);
function wcps_generate_element_html_wooSKU($html, $postData, $element, $children)
{

    $post_id = isset($postData->ID) ? $postData->ID : '';


    $type = isset($element['type']) ? $element['type'] : '';
    $id = isset($element['id']) ? $element['id'] : '';
    $options = isset($element['options']) ? $element['options'] : [];
    $defaultSaleCount = isset($options['defaultSaleCount']) ? $options['defaultSaleCount'] : 0;

    $iconPosition = isset($options['iconPosition']) ? $options['iconPosition'] : '';
    $icon = isset($options['icon']) ? $options['icon'] : '';

    $iconLibrary = isset($icon['library']) ? $icon['library'] : '';
    $iconSrcType = isset($icon['srcType']) ? $icon['srcType'] : '';
    $iconSrc = isset($icon['iconSrc']) ? $icon['iconSrc'] : '';
    $iconClass = isset($icon['class']) ? $icon['class'] : '';


    $prefixText = isset($options['prefixText']) ? $options['prefixText'] : '';
    $postfixText = isset($options['postfixText']) ? $options['postfixText'] : '';

    if ($iconLibrary == 'fontAwesome') {
        wp_enqueue_style('fontawesome-icons');
    } else if ($iconLibrary == 'iconFont') {
        wp_enqueue_style('icofont-icons');
    } else if ($iconLibrary == 'bootstrap') {
        wp_enqueue_style('bootstrap-icons');
    }

    $fontIconHtml = '<span class="' . $iconClass . ' ' . $iconSrc . '"></span>';


    $post_url = get_permalink($post_id);
    $quantityInputQuantity = 1;


    global $product;
    $productSku = ($product == null) ? '' : $product->get_sku();
    $product_type = ($product == null) ? '' : $product->get_type();

    $productSku = ($product == null) ? '' : $product->get_sku();



    ob_start();




?>
    <div class="<?php echo esc_attr($type); ?>" id="element-<?php echo esc_attr($id); ?>">

        <?php if (!empty($prefixText)): ?>
            <div class="prefix">
                <?php echo wp_kses_post($prefixText); ?>
            </div>
        <?php endif; ?>

        <?php if ($iconPosition == 'beforeSaleCount') : ?>
            <?php echo wp_kses_post($fontIconHtml); ?>
        <?php endif; ?>
        <span class='sku'>
            <?php echo wp_kses_post($productSku); ?>
        </span>
        <?php if ($iconPosition == 'afterSaleCount') : ?>
            <?php echo wp_kses_post($fontIconHtml); ?>
        <?php endif; ?>

        <?php if (!empty($postfixText)): ?>
            <div class="postfix">
                <?php echo wp_kses_post($postfixText); ?>
            </div>
        <?php endif; ?>
    </div>


<?php
    $html = ob_get_clean();

    return $html;
}

// wooStockQuantity
add_filter('wcps_generate_element_html_wooStockQuantity', "wcps_generate_element_html_wooStockQuantity", 10, 4);
function wcps_generate_element_html_wooStockQuantity($html, $postData, $element, $children)
{

    $post_id = isset($postData->ID) ? $postData->ID : '';


    $type = isset($element['type']) ? $element['type'] : '';
    $id = isset($element['id']) ? $element['id'] : '';
    $options = isset($element['options']) ? $element['options'] : [];
    $defaultSaleCount = isset($options['defaultSaleCount']) ? $options['defaultSaleCount'] : 0;

    $iconPosition = isset($options['iconPosition']) ? $options['iconPosition'] : '';
    $icon = isset($options['icon']) ? $options['icon'] : '';

    $iconLibrary = isset($icon['library']) ? $icon['library'] : '';
    $iconSrcType = isset($icon['srcType']) ? $icon['srcType'] : '';
    $iconSrc = isset($icon['iconSrc']) ? $icon['iconSrc'] : '';
    $iconClass = isset($icon['class']) ? $icon['class'] : '';


    $prefixText = isset($options['prefixText']) ? $options['prefixText'] : '';
    $postfixText = isset($options['postfixText']) ? $options['postfixText'] : '';

    if ($iconLibrary == 'fontAwesome') {
        wp_enqueue_style('fontawesome-icons');
    } else if ($iconLibrary == 'iconFont') {
        wp_enqueue_style('icofont-icons');
    } else if ($iconLibrary == 'bootstrap') {
        wp_enqueue_style('bootstrap-icons');
    }

    $fontIconHtml = '<span class="' . $iconClass . ' ' . $iconSrc . '"></span>';


    $post_url = get_permalink($post_id);
    $quantityInputQuantity = 1;


    global $product;
    $productSku = ($product == null) ? '' : $product->get_sku();
    $product_type = ($product == null) ? '' : $product->get_type();

    $productStockQuantity = ($product == null) ? '' : $product->get_stock_quantity();



    ob_start();




?>
    <div class="<?php echo esc_attr($type); ?>" id="element-<?php echo esc_attr($id); ?>">

        <?php if (!empty($prefixText)): ?>
            <div class="prefix">
                <?php echo wp_kses_post($prefixText); ?>
            </div>
        <?php endif; ?>

        <?php if ($iconPosition == 'beforeSaleCount') : ?>
            <?php echo wp_kses_post($fontIconHtml); ?>
        <?php endif; ?>
        <span class='stock-quantity'>
            <?php echo wp_kses_post($productStockQuantity); ?>

        </span>
        <?php if ($iconPosition == 'afterSaleCount') : ?>
            <?php echo wp_kses_post($fontIconHtml); ?>
        <?php endif; ?>

        <?php if (!empty($postfixText)): ?>
            <div class="postfix">
                <?php echo wp_kses_post($postfixText); ?>
            </div>
        <?php endif; ?>
    </div>


<?php
    $html = ob_get_clean();

    return $html;
}

// wooInStock
add_filter('wcps_generate_element_html_wooInStock', "wcps_generate_element_html_wooInStock", 10, 4);
function wcps_generate_element_html_wooInStock($html, $postData, $element, $children)
{

    $post_id = isset($postData->ID) ? $postData->ID : '';


    $type = isset($element['type']) ? $element['type'] : '';
    $id = isset($element['id']) ? $element['id'] : '';
    $options = isset($element['options']) ? $element['options'] : [];
    $inStockText = isset($options['inStockText']) ? $options['inStockText'] : 0;
    $outOfStockText = isset($options['outOfStockText']) ? $options['outOfStockText'] : 0;
    $backOrderText = isset($options['backOrderText']) ? $options['backOrderText'] : 0;

    $iconPosition = isset($options['iconPosition']) ? $options['iconPosition'] : '';
    $icon = isset($options['icon']) ? $options['icon'] : '';

    $iconLibrary = isset($icon['library']) ? $icon['library'] : '';
    $iconSrcType = isset($icon['srcType']) ? $icon['srcType'] : '';
    $iconSrc = isset($icon['iconSrc']) ? $icon['iconSrc'] : '';
    $iconClass = isset($icon['class']) ? $icon['class'] : '';


    $prefixText = isset($options['prefixText']) ? $options['prefixText'] : '';
    $postfixText = isset($options['postfixText']) ? $options['postfixText'] : '';

    if ($iconLibrary == 'fontAwesome') {
        wp_enqueue_style('fontawesome-icons');
    } else if ($iconLibrary == 'iconFont') {
        wp_enqueue_style('icofont-icons');
    } else if ($iconLibrary == 'bootstrap') {
        wp_enqueue_style('bootstrap-icons');
    }

    $fontIconHtml = '<span class="' . $iconClass . ' ' . $iconSrc . '"></span>';


    $post_url = get_permalink($post_id);
    $quantityInputQuantity = 1;


    global $product;
    $productSku = ($product == null) ? '' : $product->get_sku();
    $product_type = ($product == null) ? '' : $product->get_type();

    $onStock = ($product != null) ? $product->get_stock_status() : '';
    $manageStock = ($product != null) ? $product->get_manage_stock() : '';



    ob_start();




?>
    <div class="<?php echo esc_attr($type); ?>" id="element-<?php echo esc_attr($id); ?>">

        <?php if (!empty($prefixText)): ?>
            <div class="prefix">
                <?php echo wp_kses_post($prefixText); ?>
            </div>
        <?php endif; ?>

        <?php if ($iconPosition == 'afterPrefix') : ?>
            <?php echo wp_kses_post($fontIconHtml); ?>
        <?php endif; ?>
        <?php
        if ($manageStock) :
            $onStock = ($product != null) ? $product->get_stock_status() : '';
        ?>
            <?php if ($onStock == "instock") : ?>
                <span class='instock'>
                    <?php
                    echo wp_kses_post($inStockText);
                    ?>
                </span>
            <?php endif; ?>
            <?php if ($onStock == "outofstock") : ?>
                <span class='out-of-stock'>
                    <?php
                    echo wp_kses_post($outOfStockText);
                    ?>
                </span>
            <?php endif; ?>
            <?php if ($onStock == "onbackorder") : ?>
                <span class='backorder'>
                    <?php
                    echo wp_kses_post($backOrderText);
                    ?>
                </span>
            <?php endif; ?>
        <?php
        endif;
        if (!$manageStock) :
            $onStock = ($product != null) ? $product->get_stock_status() : '';
        ?>
            <?php if ($onStock == "instock") : ?>
                <span class='instock'>
                    <?php
                    echo wp_kses_post($inStockText);
                    ?>
                </span>
            <?php endif; ?>
            <?php if ($onStock == "outofstock") : ?>
                <span class='out-of-stock'>
                    <?php
                    echo wp_kses_post($outOfStockText);
                    ?>
                </span>
            <?php endif; ?>
            <?php if ($onStock == "onbackorder") : ?>
                <span class='backorder'>
                    <?php
                    echo wp_kses_post($backOrderText);
                    ?>
                </span>
            <?php endif; ?>
        <?php
        endif;
        ?>
        <?php if ($iconPosition == 'beforePostfix') : ?>
            <?php echo wp_kses_post($fontIconHtml); ?>
        <?php endif; ?>


        <?php if (!empty($postfixText)): ?>
            <div class="postfix">
                <?php echo wp_kses_post($postfixText); ?>
            </div>
        <?php endif; ?>
    </div>


<?php
    $html = ob_get_clean();

    return $html;
}

// wooProductRatings
add_filter('wcps_generate_element_html_wooProductRatings', "wcps_generate_element_html_wooProductRatings", 10, 4);
function wcps_generate_element_html_wooProductRatings($html, $postData, $element, $children)
{

    $post_id = isset($postData->ID) ? $postData->ID : '';


    $type = isset($element['type']) ? $element['type'] : '';
    $id = isset($element['id']) ? $element['id'] : '';
    $options = isset($element['options']) ? $element['options'] : [];
    $defaultRating = isset($options['defaultRating']) ? $options['defaultRating'] : 4.5;
    $summaryType = isset($options['summaryType']) ? $options['summaryType'] : '';
    $summaryTypeCustom = isset($options['summaryTypeCustom']) ? $options['summaryTypeCustom'] : '';
    $summaryLinkTo = isset($options['summaryLinkTo']) ? $options['summaryLinkTo'] : '';

    $iconsIdle = isset($options['iconsIdle']) ? $options['iconsIdle'] : [];
    $iconsFilled = isset($options['iconsFilled']) ? $options['iconsFilled'] : [];

    $iconsIdleLibrary = isset($iconsIdle['library']) ? $iconsIdle['library'] : '';
    $iconsIdleSrcType = isset($iconsIdle['srcType']) ? $iconsIdle['srcType'] : '';
    $iconsIdleSrc = isset($iconsIdle['iconSrc']) ? $iconsIdle['iconSrc'] : '';

    $iconsFilledLibrary = isset($iconsFilled['library']) ? $iconsFilled['library'] : '';
    $iconsFilledSrcType = isset($iconsFilled['srcType']) ? $iconsFilled['srcType'] : '';
    $iconsFilledSrc = isset($iconsFilled['iconSrc']) ? $iconsFilled['iconSrc'] : '';


    $prefixText = isset($options['prefixText']) ? $options['prefixText'] : '';
    $postfixText = isset($options['postfixText']) ? $options['postfixText'] : '';

    if ($iconsIdleLibrary == 'fontAwesome') {
        wp_enqueue_style('fontawesome-icons');
    } else if ($iconsIdleLibrary == 'iconFont') {
        wp_enqueue_style('icofont-icons');
    } else if ($iconsIdleLibrary == 'bootstrap') {
        wp_enqueue_style('bootstrap-icons');
    }
    if ($iconsFilledLibrary == 'fontAwesome') {
        wp_enqueue_style('fontawesome-icons');
    } else if ($iconsFilledLibrary == 'iconFont') {
        wp_enqueue_style('icofont-icons');
    } else if ($iconsFilledLibrary == 'bootstrap') {
        wp_enqueue_style('bootstrap-icons');
    }




    $iconsIdleHtml = '<span class="' . $iconsIdleSrc . '"></span>';
    $iconsFilledHtml = '<span class="' . $iconsFilledSrc . '"></span>';


    $post_url = get_permalink($post_id);
    $quantityInputQuantity = 1;



    global $product;
    $productSKu = ($product == null) ? '' : $product->get_sku();
    $productRatingCount = ($product == null) ? '' : $product->get_rating_count();
    $productReviewCount = ($product == null) ? '' : $product->get_review_count();
    $productAverageRating = ($product == null) ? '' : $product->get_average_rating();
    $product_title = ($product == null) ? '' : $product->get_title();
    $summaryVars = array(
        '{rating_count}' => $productRatingCount,
        '{review_count}' => $productReviewCount,
        '{average_rating}' => $productAverageRating,
        '{product_title}' => $product_title,
    );

    $filled_width = (!empty($productAverageRating)) ? $productAverageRating * 20 : 0;


    ob_start();




?>
    <div class="<?php echo esc_attr($type); ?>" id="element-<?php echo esc_attr($id); ?>">

        <?php if (!empty($prefixText)): ?>
            <div class="prefix">
                <?php echo wp_kses_post($prefixText); ?>
            </div>
        <?php endif; ?>

        <div class="icons-wrap">
            <div class="icons-idle">
                <?php echo wp_kses_post($iconsIdleHtml); ?>
                <?php echo wp_kses_post($iconsIdleHtml); ?>
                <?php echo wp_kses_post($iconsIdleHtml); ?>
                <?php echo wp_kses_post($iconsIdleHtml); ?>
                <?php echo wp_kses_post($iconsIdleHtml); ?>
                <div class="icons-filled" style="width:<?php echo esc_attr($filled_width) . '%'; ?>">
                    <?php echo wp_kses_post($iconsFilledHtml); ?>
                    <?php echo wp_kses_post($iconsFilledHtml); ?>
                    <?php echo wp_kses_post($iconsFilledHtml); ?>
                    <?php echo wp_kses_post($iconsFilledHtml); ?>
                    <?php echo wp_kses_post($iconsFilledHtml); ?>
                </div>
            </div>
        </div>
        <?php if (!empty($summarytypeCustom)) : ?>
            <div class="summary">
                <?php
                echo wp_kses_post(strtr($summarytypeCustom, $summaryVars));
                ?>
            </div>
        <?php endif; ?>
        <?php if (empty($summarytypeCustom)) : ?>
            <?php if (!empty($summaryType)) : ?>
                <div class="summary">
                    <?php
                    echo wp_kses_post(strtr($summaryType, $summaryVars));
                    ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (!empty($postfixText)): ?>
            <div class="postfix">
                <?php echo wp_kses_post($postfixText); ?>
            </div>
        <?php endif; ?>
    </div>


<?php
    $html = ob_get_clean();

    return $html;
}





// wooCategories
add_filter('wcps_generate_element_html_wooCategories', "wcps_generate_element_html_wooCategories", 10, 4);
function wcps_generate_element_html_wooCategories($html, $postData, $element, $children)
{

    $type = isset($element['type']) ? $element['type'] : '';
    $id = isset($element['id']) ? $element['id'] : '';
    $options = isset($element['options']) ? $element['options'] : [];


    $maxCount = isset($options['maxCount']) ? $options['maxCount'] : 1;
    $postCount = isset($options['postCount']) ? $options['postCount'] : false;
    $separator = isset($options['separator']) ? $options['separator'] : ', ';
    $linkTo = isset($options['linkTo']) ? $options['linkTo'] : 'termUrl';
    $target = isset($options['target']) ? $options['target'] : '_blank';
    $prefixText = isset($options['prefixText']) ? $options['prefixText'] : '';
    $postfixText = isset($options['postfixText']) ? $options['postfixText'] : '';
    $post_id = isset($postData->ID) ? $postData->ID : '';
    $post_title = isset($postData->post_title) ? $postData->post_title : '';

    $post_url = get_permalink($post_id);
    $the_post = get_post($post_id);
    $post_author_id = isset($the_post->post_author) ? $the_post->post_author : '';

    $itemsLinkToCustomMeta = '';
    $itemsCustomUrl = '';

    $taxonomy = 'product_cat';
    $terms = get_the_terms($post_id, $taxonomy);
    $termsCount = (is_array($terms)) ? count($terms) : 0;


    ob_start();

?>
    <div class="<?php echo esc_attr($type); ?>" id="element-<?php echo esc_attr($id); ?>">
        <?php if (!empty($prefixText)): ?>
            <div class="prefix">
                <?php echo wp_kses_post($prefixText); ?>
            </div>
        <?php endif; ?>

        <div class="terms-items">

            <?php
            $i = 1;
            if (!empty($terms))
                foreach ($terms as $term) {
                    $term_id = $term->term_id;
                    $term_post_count = $term->count;
                    if ($linkTo == 'postUrl') {
                        $linkUrl = get_permalink($post_id);
                    } else if ($linkTo == 'termUrl') {
                        $linkUrl = get_term_link($term_id);
                    } else if ($linkTo == 'customField') {
                        $linkUrl = get_post_meta($post_id, $itemsLinkToCustomMeta, true);
                    } else if ($linkTo == 'authorUrl') {
                        $author_id = get_post_field('post_author', $post_id);
                        $user = get_user_by('ID', $author_id);
                        $linkUrl = $user->user_url;
                    } else if ($linkTo == 'authorLink') {
                        $author_id = get_post_field('post_author', $post_id);
                        $linkUrl = get_author_posts_url($author_id);
                    } else if ($linkTo == 'homeUrl') {
                        $linkUrl = get_bloginfo('url');
                    } else if ($linkTo == 'customUrl') {
                        $linkUrl = $itemsCustomUrl;
                    }

                    if ($i > $maxCount)
                        break;

            ?>

                <div class="term-item">
                    <?php if (!empty($linkTo)) : ?>
                        <a href="<?php echo esc_url($linkUrl); ?>" target="<?php echo esc_attr($target); ?>" class="term-link">

                            <?php if (!empty($itemsPrefix)) : ?>
                                <span class='prefix'>
                                    <?php echo wp_kses_post($itemsPrefix); ?>
                                </span>
                            <?php endif; ?>
                            <span class='term-title'>
                                <?php echo wp_kses_post($term->name); ?>
                            </span>
                            <?php if ($postCount) : ?>
                                <span class='post-count'>
                                    <?php echo wp_kses_post($term_post_count); ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($itemsPostfix)) : ?>
                                <span class='postfix'>
                                    <?php echo wp_kses_post($itemsPostfix); ?>
                                </span>
                            <?php endif; ?>

                        </a>
                    <?php else : ?>


                        <?php if (!empty($itemsPrefix)) : ?>
                            <span class='prefix'>
                                <?php echo wp_kses_post($itemsPrefix); ?>
                            </span>
                        <?php endif; ?>
                        <span class='term-title'>
                            <?php echo wp_kses_post($term->name); ?>
                        </span>
                        <?php if ($postCount) : ?>
                            <span class='post-count'>
                                <?php echo wp_kses_post($term_post_count); ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($itemsPostfix)) : ?>
                            <span class='postfix'>
                                <?php echo wp_kses_post($itemsPostfix); ?>
                            </span>
                        <?php endif; ?>


                    <?php endif; ?>
                    <?php if ($maxCount > $i) : ?>
                        <?php if (!empty($separatorText)) : ?>
                            <span class='separator'>
                                <?php echo esc_html($separatorText); ?>
                            </span>
                        <?php endif; ?>
                    <?php endif; ?>

                </div>

            <?php
                    $i++;
                }
            ?>


        </div>


        <?php if (!empty($postfixText)): ?>
            <div class="postfix">
                <?php echo wp_kses_post($postfixText); ?>
            </div>
        <?php endif; ?>
    </div>


<?php
    $html = ob_get_clean();

    return $html;
}

// wooTags
//add_filter('wcps_generate_element_html_wooTags', "wcps_generate_element_html_wooTags", 10, 4);
function wcps_generate_element_html_wooTags($html, $postData, $element, $children)
{

    $type = isset($element['type']) ? $element['type'] : '';
    $id = isset($element['id']) ? $element['id'] : '';
    $options = isset($element['options']) ? $element['options'] : [];


    $maxCount = isset($options['maxCount']) ? $options['maxCount'] : 1;
    $postCount = isset($options['postCount']) ? $options['postCount'] : false;
    $separator = isset($options['separator']) ? $options['separator'] : ', ';
    $linkTo = isset($options['linkTo']) ? $options['linkTo'] : 'termUrl';
    $target = isset($options['target']) ? $options['target'] : '_blank';
    $prefixText = isset($options['prefixText']) ? $options['prefixText'] : '';
    $postfixText = isset($options['postfixText']) ? $options['postfixText'] : '';
    $post_id = isset($postData->ID) ? $postData->ID : '';
    $post_title = isset($postData->post_title) ? $postData->post_title : '';

    $post_url = get_permalink($post_id);
    $the_post = get_post($post_id);
    $post_author_id = isset($the_post->post_author) ? $the_post->post_author : '';

    $itemsLinkToCustomMeta = '';
    $itemsCustomUrl = '';

    $taxonomy = 'product_tag';
    $terms = get_the_terms($post_id, $taxonomy);
    $termsCount = (is_array($terms)) ? count($terms) : 0;


    ob_start();

?>
    <div class="<?php echo esc_attr($type); ?>" id="element-<?php echo esc_attr($id); ?>">
        <?php if (!empty($prefixText)): ?>
            <div class="prefix">
                <?php echo wp_kses_post($prefixText); ?>
            </div>
        <?php endif; ?>

        <div class="terms-items">

            <?php
            $i = 1;
            if (!empty($terms))
                foreach ($terms as $term) {
                    $term_id = $term->term_id;
                    $term_post_count = $term->count;
                    if ($linkTo == 'postUrl') {
                        $linkUrl = get_permalink($post_id);
                    } else if ($linkTo == 'termUrl') {
                        $linkUrl = get_term_link($term_id);
                    } else if ($linkTo == 'customField') {
                        $linkUrl = get_post_meta($post_id, $itemsLinkToCustomMeta, true);
                    } else if ($linkTo == 'authorUrl') {
                        $author_id = get_post_field('post_author', $post_id);
                        $user = get_user_by('ID', $author_id);
                        $linkUrl = $user->user_url;
                    } else if ($linkTo == 'authorLink') {
                        $author_id = get_post_field('post_author', $post_id);
                        $linkUrl = get_author_posts_url($author_id);
                    } else if ($linkTo == 'homeUrl') {
                        $linkUrl = get_bloginfo('url');
                    } else if ($linkTo == 'customUrl') {
                        $linkUrl = $itemsCustomUrl;
                    }

                    if ($i > $maxCount)
                        break;

            ?>

                <div class="term-item">
                    <?php if (!empty($linkTo)) : ?>
                        <a href="<?php echo esc_url($linkUrl); ?>" target="<?php echo esc_attr($target); ?>" class="term-link">

                            <?php if (!empty($itemsPrefix)) : ?>
                                <span class='prefix'>
                                    <?php echo wp_kses_post($itemsPrefix); ?>
                                </span>
                            <?php endif; ?>
                            <span class='term-title'>
                                <?php echo wp_kses_post($term->name); ?>
                            </span>
                            <?php if ($postCount) : ?>
                                <span class='post-count'>
                                    <?php echo wp_kses_post($term_post_count); ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($itemsPostfix)) : ?>
                                <span class='postfix'>
                                    <?php echo wp_kses_post($itemsPostfix); ?>
                                </span>
                            <?php endif; ?>

                        </a>
                    <?php else : ?>


                        <?php if (!empty($itemsPrefix)) : ?>
                            <span class='prefix'>
                                <?php echo wp_kses_post($itemsPrefix); ?>
                            </span>
                        <?php endif; ?>
                        <span class='term-title'>
                            <?php echo wp_kses_post($term->name); ?>
                        </span>
                        <?php if ($postCount) : ?>
                            <span class='post-count'>
                                <?php echo wp_kses_post($term_post_count); ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($itemsPostfix)) : ?>
                            <span class='postfix'>
                                <?php echo wp_kses_post($itemsPostfix); ?>
                            </span>
                        <?php endif; ?>


                    <?php endif; ?>
                    <?php if ($maxCount > $i) : ?>
                        <?php if (!empty($separatorText)) : ?>
                            <span class='separator'>
                                <?php echo esc_html($separatorText); ?>
                            </span>
                        <?php endif; ?>
                    <?php endif; ?>

                </div>

            <?php
                    $i++;
                }
            ?>


        </div>


        <?php if (!empty($postfixText)): ?>
            <div class="postfix">
                <?php echo wp_kses_post($postfixText); ?>
            </div>
        <?php endif; ?>
    </div>


<?php
    $html = ob_get_clean();

    return $html;
}



function wcps_term_slug_list($post_id)
{
    $term_slug_list = '';
    $post_taxonomies = get_post_taxonomies($post_id);
    foreach ($post_taxonomies as $taxonomy) {
        $term_list[] = wp_get_post_terms($post_id, $taxonomy, array("fields" => "all"));
    }
    if (!empty($term_list)) {
        foreach ($term_list as $term_key => $term) {
            foreach ($term as $term_id => $term) {
                $term_slug_list .= $term->slug . ' ';
            }
        }
    }
    return $term_slug_list;
}
