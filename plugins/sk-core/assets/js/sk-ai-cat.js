(function ($) {
    'use strict';

    var cfg       = window.skAiCat || {};
    var debounceT = null;
    var lastTitle = '';
    var pending   = false;
    var suggestion = null; // { term_id, term_name, path }

    // ── DOM refs (resolved after document ready) ─────────────────────────────
    var $titleInput, $descInput, $box, $boxText, $applyBtn, $dismissBtn, $spinner;

    function init() {
        // new-product uses id="post-title", edit-product uses id="post_title"
        $titleInput  = $('#post-title, #post_title').first();
        $descInput   = $('textarea[name="post_content"], #post_content');


        if (!$titleInput.length) {
            return;
        }

        // Inject suggestion box after the category container
        var $catContainer = $('.sk-select-product-category-container').first().closest('.sk-form-group, .form-group').last();
        if (!$catContainer.length) {
            $catContainer = $('.sk-select-product-category-container').first().parent();
        }

        $('<div id="skai-box" class="sk-edit-row">'
            + '<div class="sk-section-content skai-content">'
            + '<span class="skai-spinner" id="skai-spinner" style="display:none"></span>'
            + '<span class="skai-label" id="skai-label">' + cfg.strings.loading + '</span>'
            + '<strong id="skai-cat-name"></strong>'
            + '<button type="button" id="skai-apply">' + cfg.strings.apply + '</button>'
            + '<button type="button" id="skai-dismiss" title="' + cfg.strings.dismiss + '">✕</button>'
            + '</div>'
        + '</div>').insertAfter($catContainer);

        $box       = $('#skai-box');
        $spinner   = $('#skai-spinner');
        $boxText   = $('#skai-label');
        $applyBtn  = $('#skai-apply');
        $dismissBtn = $('#skai-dismiss');

        $applyBtn.hide();

        // ── Event listeners ───────────────────────────────────────────────────
        // Only trigger when user leaves the title field (blur), not on every keystroke.
        $titleInput.on('blur', maybeRequest);
        $descInput.on('blur', maybeRequest);

        $applyBtn.on('click', applyCategory);
        $dismissBtn.on('click', dismissBox);
    }

    // ── Debounce ──────────────────────────────────────────────────────────────
    function debounceRequest() {
        clearTimeout(debounceT);
        debounceT = setTimeout(maybeRequest, 1200);
    }

    function hasRealCategory() {
        var uncatId = parseInt(cfg.uncategorizedId, 10);
        return $('.sk_chosen_product_cat').filter(function () {
            var v = parseInt($(this).val(), 10);
            return v > 0 && v !== uncatId;
        }).length > 0;
    }

    function maybeRequest() {
        var title = $titleInput.val().trim();
        if (title.length < 4 || title === lastTitle) return;
        lastTitle = title;
        requestSuggestion(title);
    }

    // ── API call ──────────────────────────────────────────────────────────────
    function requestSuggestion(title) {
        if (pending) return;
        pending = true;

        showLoading();

        var desc = $descInput.val ? $descInput.val().substring(0, 400) : '';

        $.ajax({
            url:  cfg.ajaxurl,
            type: 'POST',
            data: {
                action:      'skai_suggest',
                nonce:       cfg.nonce,
                title:       title,
                description: desc
            },
            success: function (res) {
                if (res.success && res.data && res.data.term_id) {
                    suggestion = res.data;
                    showSuggestion(res.data);
                    if (cfg.autoApply) {
                        applyCategory();
                    }
                } else {
                    hideBox();
                }
            },
            error: function () {
                hideBox();
            },
            complete: function () {
                pending = false;
            }
        });
    }

    // ── UI helpers ────────────────────────────────────────────────────────────
    function showLoading() {
        $spinner.show();
        $boxText.text(cfg.strings.loading);
        $('#skai-cat-name').text('');
        $applyBtn.hide();
        $box.addClass('visible');
    }

    function showSuggestion(data) {
        $spinner.hide();
        $boxText.text(cfg.strings.suggestion + ' ');
        $('#skai-cat-name').text(data.path || data.term_name);
        $applyBtn.show();
        $box.addClass('visible');
    }

    function hideBox() {
        $spinner.hide();
        $box.removeClass('visible');
        suggestion = null;
    }

    function dismissBox() {
        hideBox();
        lastTitle = ''; // allow re-triggering after dismiss
    }

    // ── Apply category to form ────────────────────────────────────────────────
    function applyCategory() {
        if (!suggestion) return;

        var termId   = suggestion.term_id;
        var termName = suggestion.path || suggestion.term_name;

        // Find or create the hidden input inside the category container
        var $container = $('.sk-select-product-category-container').first();
        var $input     = $container.find('.sk_chosen_product_cat');

        if ($input.length) {
            // Update existing input
            $input
                .val(termId)
                .attr('class', 'sk_chosen_product_cat sk_chosen_product_cat_' + termId);
        } else {
            // Create new input
            $('<input type="hidden" name="chosen_product_cat[]">')
                .attr('data-field-name', 'chosen_product_cat')
                .attr('class', 'sk_chosen_product_cat sk_chosen_product_cat_' + termId)
                .val(termId)
                .appendTo($container);
        }

        // Update the display label
        var $title = $('#sk_product_cat_res');
        if ($title.length) {
            $title.text(termName);
        }

        // Mark container as active (category selected)
        $container.attr('data-activate', 'yes');
        $container.closest('.sk-select-product-category-container').attr('data-activate', 'yes');

        // Trigger WP hooks for any listeners (e.g. validation state)
        if (window.wp && window.wp.hooks) {
            wp.hooks.doAction('sk_selected_multistep_category', termId);
        }

        // Update UI
        $applyBtn.hide();
        $boxText.text('');
        $('#skai-cat-name').text('');
        $spinner.hide();
        $box.removeClass('visible');

        // Brief confirmation flash
        $container.find('.sk-select-product-category').css('border-color', '#16a34a');
        setTimeout(function () {
            $container.find('.sk-select-product-category').css('border-color', '');
        }, 1500);
    }

    // ── Boot ──────────────────────────────────────────────────────────────────
    $(document).ready(init);

})(jQuery);
