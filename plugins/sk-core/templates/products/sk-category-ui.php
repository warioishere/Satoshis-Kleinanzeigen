<!-- The Modal -->
<div id="sk-product-category-modal" class="sk-product-category-modal">

    <!-- Modal content -->
    <div class="sk-product-category-modal-content">
        <div class="sk-product-category-modal-header">
            <div class="sk-product-category-title">
                <span class="sk-single-title"><?php esc_html_e( 'Add new category', 'sk-core' ); ?></span>
                <span class="sk-single-des"><?php esc_html_e( 'Please choose the right category for this product', 'sk-core' ); ?></span>
            </div>
            <div class="sk-product-category-close">
                <span class="close" id="sk-category-close-modal">&times;</span>
            </div>
        </div>
        <div class="sk-product-category-modal-body">
            <div class="sk-category-search-container">
                <div class="sk-cat-search-box">
                    <span class="sk-cat-search-icon"><i class="fas fa-search"></i></span>
                    <input maxlength="100" id="sk-single-cat-search-input" class="sk-cat-search-input" type="text" placeholder="<?php esc_attr_e( 'Search category', 'sk-core' ); ?>">
                    <span class="sk-cat-search-text-limit">
                        <span id="sk-cat-search-text-limit">
                            <?php echo esc_html( number_format_i18n( 0 ) ); ?>
                        </span>
                        /<?php echo esc_html( number_format_i18n( 100 ) ); ?>
                    </span>
                </div>
                <div id="sk-cat-search-res" class="sk-cat-search-res sk-hide">
                    <ul id="sk-cat-search-res-ul" class="sk-cat-search-res-ul"></ul>
                </div>
            </div>
            <div class="sk-single-categories-container">
                <span class="sk-single-categories-left sk-single-categories-arrow sk-hide">
                    <span class="sk-single-categories-left-box">
                        <span><i class="fas fa-chevron-left"></i></span>
                    </span>
                </span>
                <div class="sk-single-categories" id="sk-single-categories"></div>
                <span class="sk-single-categories-right sk-single-categories-arrow sk-hide">
                    <span class="sk-single-categories-right-box">
                        <span><i class="fas fa-chevron-right"></i></span>
                    </span>
                </span>
            </div>

        </div>
        <div class="sk-product-category-modal-footer">
            <div class="sk-selected-category-label-container">
                <span class="sk-selected-category-label"><?php esc_html_e( 'Selected: ', 'sk-core' ); ?></span>
                <span class="sk-selected-category-span" id="sk-selected-category-span">
                    <span class="sk-selected-category-product"><?php esc_html_e( 'No category', 'sk-core' ); ?></span>
                </span>
            </div>
            <div class="sk-product-category-button-container">
                <button class="sk-single-cat-select-btn sk-btn sk-btn-default sk-btn-theme" id="sk-single-cat-select-btn" type='button'><?php esc_html_e( 'Done', 'sk-core' ); ?></button>
            </div>
        </div>
    </div>

</div>
