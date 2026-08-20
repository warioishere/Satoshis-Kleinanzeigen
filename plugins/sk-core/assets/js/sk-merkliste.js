jQuery(document).ready(function($) {

    /**
     * Product IDs on the current user's list, seeded server-side and kept in
     * sync on every toggle. The slider icons have no server-rendered state of
     * their own and read it from here.
     */
    const items = new Set((merklisteAjax.items || []).map(Number));

    /**
     * Show toast notification
     */
    function showToast(message, type) {
        type = type || 'success';

        const iconClass = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';

        const toast = $('<div class="dm-toast ' + type + '">' +
            '<i class="fas ' + iconClass + '"></i>' +
            '<span></span>' +
        '</div>');

        toast.find('span').text(message);

        $('body').append(toast);

        setTimeout(function() {
            toast.fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    }

    /**
     * Apply a product's list state to every control pointing at it — the same
     * product can appear in a slider, in a loop and in a clone of a slide.
     */
    function syncControls(productId, isInList) {
        if (isInList) {
            items.add(Number(productId));
        } else {
            items.delete(Number(productId));
        }

        $('.dm-pin-icon[data-product-id="' + productId + '"]')
            .toggleClass('active', isInList)
            .attr('title', isInList ? merklisteAjax.removeTitle : merklisteAjax.addTitle);

        $('.dm-merkliste-btn[data-product-id="' + productId + '"]').each(function() {
            const $btn = $(this);
            $btn.toggleClass('dm-in-list', isInList);
            $btn.html('<i class="fas fa-thumbtack"></i> ')
                .append(document.createTextNode(isInList ? merklisteAjax.removeTitle : merklisteAjax.addTitle));
        });
    }

    /**
     * Toggle product in merkliste (helper function)
     */
    function toggleProductMerkliste($control, productId) {
        $.ajax({
            url: merklisteAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'dm_toggle_merkliste',
                product_id: productId,
                nonce: merklisteAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    syncControls(productId, response.data.is_in_list);
                    showToast(response.data.message, 'success');
                } else {
                    showToast((response.data && response.data.message) || merklisteAjax.errorText, 'error');
                }
            },
            error: function() {
                showToast(merklisteAjax.errorText, 'error');
            },
            complete: function() {
                $control.removeClass('loading');
            }
        });
    }

    /**
     * Handle pin icon click in product loop and in sliders
     */
    $(document).on('click', '.dm-pin-icon', function(e) {
        e.preventDefault();

        const $icon = $(this);
        const productId = parseInt($icon.data('product-id'), 10);

        if (!merklisteAjax.isLoggedIn) {
            showToast(merklisteAjax.loginRequired, 'error');
            return;
        }

        if ($icon.hasClass('loading')) {
            return;
        }

        if (!productId) {
            showToast(merklisteAjax.errorText, 'error');
            return;
        }

        $icon.addClass('loading');
        toggleProductMerkliste($icon, productId);
    });

    /**
     * Handle button click on single product page
     */
    $(document).on('click', '.dm-merkliste-btn', function(e) {
        e.preventDefault();

        const $button = $(this);
        const productId = parseInt($button.data('product-id'), 10);

        if (!merklisteAjax.isLoggedIn) {
            showToast(merklisteAjax.loginRequired, 'error');
            return;
        }

        if ($button.hasClass('loading')) {
            return;
        }

        if (!productId) {
            showToast(merklisteAjax.errorText, 'error');
            return;
        }

        $button.addClass('loading');
        toggleProductMerkliste($button, productId);
    });

    /**
     * Handle remove from list in dashboard (with AJAX)
     *
     * Without JS the surrounding form posts and the template removes the item.
     */
    $(document).on('click', '.dm-remove-from-list', function(e) {
        e.preventDefault();

        if (!confirm(merklisteAjax.confirmText)) {
            return;
        }

        const $link = $(this);
        const productId = $link.data('product-id');
        const $listItem = $link.closest('.merkliste-item');

        if ($link.hasClass('loading')) {
            return;
        }

        $link.addClass('loading');

        $.ajax({
            url: merklisteAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'dm_remove_from_merkliste',
                product_id: productId,
                nonce: merklisteAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    syncControls(productId, false);

                    // Fade out and remove the list item
                    $listItem.fadeOut(300, function() {
                        $(this).remove();

                        // Check if list is now empty
                        if ($('.merkliste-list .merkliste-item').length === 0) {
                            location.reload();
                        }
                    });

                    showToast(response.data.message, 'success');
                } else {
                    showToast((response.data && response.data.message) || merklisteAjax.errorText, 'error');
                }
            },
            error: function() {
                showToast(merklisteAjax.errorText, 'error');
            },
            complete: function() {
                $link.removeClass('loading');
            }
        });
    });

    /**
     * Add merkliste icon to WCPS slider product images.
     *
     * The slider markup carries the product ID in a class the Merkliste module
     * appends (sk-merk-pid-123); without it the icon would not know which
     * product it stands for and is left out.
     */
    function initWCPSMerklisteIcons() {
        $('.wcps-items-thumb').each(function() {
            const $thumb = $(this);

            if ($thumb.find('.dm-pin-icon-wrapper').length > 0) {
                return;
            }

            const match = /sk-merk-pid-(\d+)/.exec($thumb.attr('class') || '');
            if (!match) {
                return;
            }

            const productId = parseInt(match[1], 10);
            const isInList = items.has(productId);

            const $icon = $('<a href="#" class="dm-pin-icon"><i class="fas fa-thumbtack" aria-hidden="true"></i></a>')
                .attr('data-product-id', productId)
                .toggleClass('dm-pin-icon-disabled', !merklisteAjax.isLoggedIn)
                .toggleClass('active', isInList)
                .attr('title', isInList ? merklisteAjax.removeTitle : merklisteAjax.addTitle);

            $thumb.prepend($('<div class="dm-pin-icon-wrapper dm-pin-icon-wcps"></div>').append($icon));
        });
    }

    initWCPSMerklisteIcons();

    // Splide clones its slides on mount, so slides created after DOM ready need
    // a second pass.
    $(window).on('load', initWCPSMerklisteIcons);
    $(document).on('wcps-items-loaded', initWCPSMerklisteIcons);

});
