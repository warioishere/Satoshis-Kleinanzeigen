<?php
if (!defined('ABSPATH')) exit;  // if direct access

add_action('wcps_builder_viewFilterable', 'wcps_builder_viewFilterable', 5, 2);

function wcps_builder_viewFilterable($post_id, $PostGridData)
{

    global $WCPSBuilderCss;


    $globalOptions = isset($PostGridData["globalOptions"]) ? $PostGridData["globalOptions"] : [];
    $lazyLoad = isset($globalOptions["lazyLoad"]) ? $globalOptions["lazyLoad"] : false;
    $itemSource = isset($globalOptions["itemSource"]) ? $globalOptions["itemSource"] : "topToBottom";




    $items = isset($PostGridData["items"]) ? $PostGridData["items"] : [];
    $itemQueryArgs = isset($PostGridData["itemQueryArgs"]) ? $PostGridData["itemQueryArgs"] : [];








    $reponsiveCss = isset($PostGridData["reponsiveCss"]) ? $PostGridData["reponsiveCss"] : "";


    $PostGridBuilderCss .= $reponsiveCss;

    //var_dump($globalOptions);

    $loopLayout = isset($PostGridData["loopLayout"]) ? $PostGridData["loopLayout"] : [];

    $loopLayouts = $loopLayout[0]['children'];
    $wrapper = isset($PostGridData["wrapper"]) ? $PostGridData["wrapper"] : [];
    $wrapperOptions = isset($wrapper["options"]) ? $wrapper["options"] : [];
    $wrapperTag = !empty($wrapperOptions["tag"]) ? $wrapperOptions["tag"] : "div";
    $wrapperClass = isset($wrapperOptions["class"]) ? $wrapperOptions["class"] : "";


    $filterableFilters = isset($globalOptions['filters']) ? $globalOptions['filters'] : [];
    $filterableShowSort = isset($globalOptions['showSort']) ? $globalOptions['showSort'] : 'no';
    $filterToggle = isset($globalOptions['filterToggle']) ? $globalOptions['filterToggle'] : 'no';
    $filterableShowRandom = isset($globalOptions['showRandom']) ? $globalOptions['showRandom'] : 'no';
    $filterableShowAll = isset($globalOptions['showAll']) ? $globalOptions['showAll'] : 'yes';
    $filterableShowClear = isset($globalOptions['showClear']) ? $globalOptions['showClear'] : 'no';
    $filterablePerPage = isset($globalOptions['perPage']) ? $globalOptions['perPage'] : 6;
    $logicWithinGroup = isset($globalOptions['logicWithinGroup']) ? $globalOptions['logicWithinGroup'] : 'or';
    $logicBetweenGroups = isset($globalOptions['logicBetweenGroups']) ? $globalOptions['logicBetweenGroups'] : 'and';
    $multifilter = isset($globalOptions['multifilter']) ? (bool) $globalOptions['multifilter'] : true;
    $activeFilter = isset($attributes['activeFilter']) ? $attributes['activeFilter'] : [];
    $activeFilterOptions = isset($activeFilter['options']) ? $activeFilter['options'] : [];
    $activeFilterSlug = !empty($activeFilterOptions['slug']) ? $activeFilterOptions['slug'] : 'all';

    //var_dump($filterableShowAll);

    if ($itemSource == "posts") {
        $itemsQueryResponse = wcps_builder_post_query_items($itemQueryArgs, $loopLayouts);

        //var_dump($itemsQueryResponse);

        $postsHtml = isset($itemsQueryResponse['postsHtml']) ? $itemsQueryResponse['postsHtml'] : '';
        $posts_query = isset($itemsQueryResponse['posts_query']) ? $itemsQueryResponse['posts_query'] : [];
    }
    // if ($itemSource == "terms") {
    //     $items = wcps_terms_query_item($itemQueryArgs);
    // }
    // if ($itemSource == "easyAccordion") {
    //     $items = wcps_easy_accordion_query_item($itemQueryArgs);
    // }

    $blockId = "wcps-" . $post_id;

    //echo "<pre>" . var_export($loopLayouts, true) . "</pre>";


    $PostGridDataAttr = [
        "id" => $blockId,
        "lazyLoad" => $lazyLoad,
    ];

    $postGridArgs = [
        'blockId' => $post_id,
        'lazyLoad' => ['enable' => $lazyLoad],
        'activeFilter' => ['slug' => $activeFilterSlug],
        'perPage' => $filterablePerPage,
        'logicWithinGroup' => $logicWithinGroup,
        'logicBetweenGroups' => $logicBetweenGroups,
        'multifilter' => $multifilter,
    ];

    wp_enqueue_style('font-awesome-5');
    wp_enqueue_script('pgpostgrid_mixitup');
    wp_enqueue_script('pgpostgrid_mixitup_multifilter');
    wp_enqueue_script('pgpostgrid_mixitup_pagination');
    wp_enqueue_script('wcps-filterable-front');

?>
    <div id="<?php echo esc_attr($blockId); ?>" class="" data-accordionBuilder=<?php echo esc_attr(json_encode($PostGridDataAttr)) ?> role="tablist" style="<?php echo ($lazyLoad) ? "display: none;" : ""; ?>">
        <div
            class="<?php echo esc_attr($blockId); ?> ComboBlocksFilterableGridNav ComboBlocksFilterableGridNav-<?php echo esc_attr($post_id); ?>"
            data-postgridargs="<?php echo esc_attr(wp_json_encode($postGridArgs)); ?>">

            <div class="filterable-group-wrap">
                <?php
                $groupLogic = '';
                if (!empty($filterableFilters)) {
                ?>
                    <div class="filterable-group" data-filter-group data-logic="OR">
                        <?php if ($filterableShowAll == 'yes') : ?>
                            <span class="pg-filter pg-filter-<?php echo esc_attr($post_id); ?>" data-filter="all">
                                <?php echo 'All'; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php
                }
                if (!empty($filterableFilters)) {
                    $groupCount = 0;
                    foreach ($filterableFilters as $filterGroup) {
                        $groupTitle = isset($filterGroup['groupTitle']) ? $filterGroup['groupTitle'] : '';
                        $groupType = isset($filterGroup['type']) ? $filterGroup['type'] : '';
                        $groupLogic = isset($filterGroup['logic']) ? $filterGroup['logic'] : '';
                        $groupshowPostCount = isset($filterGroup['showPostCount']) ? $filterGroup['showPostCount'] : '';
                        $groupitems = isset($filterGroup['items']) ? $filterGroup['items'] : [];
                        if (!empty($groupitems)) {
                    ?>
                            <div class="filterable-group" data-filter-group data-logic="<?php echo esc_attr($groupLogic); ?>">
                                <span class="filterable-group-title">
                                    <?php echo esc_html($groupTitle); ?>
                                </span>
                                <?php if ($groupCount == 0 && count($filterableFilters) == 1) : ?>
                                    <?php if ($filterableShowAll == 'yes') : ?>
                                        <span class="pg-filter pg-filter-<?php echo esc_attr($post_id); ?>" data-filter="all">
                                            <?php echo 'All'; ?>
                                        </span>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php
                                if (!empty($groupitems))
                                    foreach ($groupitems as $item) {
                                        $itemId = isset($item['id']) ? $item['id'] : '';
                                        $itemTitle = isset($item['title']) ? $item['title'] : '';
                                        $itemTitleToSlug = str_replace(' ', '-', strtolower($itemTitle));
                                        $itemSlug = !empty($item['slug']) ? $item['slug'] : $itemTitleToSlug;
                                        $itemCount = isset($item['count']) ? $item['count'] : '';
                                ?>
                                    <span class="pg-filter pg-filter-<?php echo esc_attr($post_id); ?>" <?php if ($filterToggle == 'yes') : ?>
                                        data-toggle="<?php echo '.' . esc_attr($itemSlug); ?>" <?php else : ?>
                                        data-filter="<?php echo '.' . esc_attr($itemSlug); ?>" <?php endif; ?>>
                                        <?php echo esc_html($itemTitle) ?>
                                        <?php echo ($groupshowPostCount == 'yes') ? '(' . esc_html($itemCount) . ')' : '' ?>
                                    </span>
                                <?php
                                    }
                                ?>
                            </div>
                <?php
                        }
                        $groupCount++;
                    }
                }
                ?>
                <div class="filterable-group" data-filter-group data-logic="<?php echo esc_attr($groupLogic); ?>">
                    <?php if ($filterableShowSort == 'yes') : ?>
                        <span class="pg-filter pg-filter-<?php echo esc_attr($post_id); ?>" data-sort="order:asc">
                            <?php echo esc_html__('ASC', 'woocommerce-products-slider'); ?>
                        </span>
                        <span class="pg-filter pg-filter-<?php echo esc_attr($post_id); ?>" data-sort="order:desc">
                            <?php echo esc_html__('DESC', 'woocommerce-products-slider'); ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($filterableShowRandom == 'yes') : ?>
                        <span class="pg-filter pg-filter-<?php echo esc_attr($post_id); ?>" data-sort="random">
                            <?php echo esc_html__('Random', 'woocommerce-products-slider'); ?>
                        </span>
                    <?php endif; ?>
                    <?php if (count($filterableFilters) > 1 && $filterableShowClear == 'yes') : ?>
                        <button class="pg-filter" type="reset">
                            <?php echo esc_html__('Clear', 'woocommerce-products-slider'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>


        <div class="items">
            <?php

            echo wp_kses_post($postsHtml);

            ?>
        </div>

        <div id="pagination-<?php echo esc_attr($blockId); ?>" class="<?php echo esc_attr($blockId); ?>  pagination ComboBlocksPostGrid-pagination  pager-list mixitup-page-list pager-list-<?php echo esc_attr($post_id); ?>" data-postqueryargs="<?php //echo esc_attr(json_encode($blockArgs)); 
                                                                                                                                                                                                                                                    ?>">

        </div>
    </div>

<?php
}
