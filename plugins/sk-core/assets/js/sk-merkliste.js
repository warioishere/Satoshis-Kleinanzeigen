jQuery(document).ready(function($) {

    /**
     * Show toast notification
     */
    function showToast(message, type) {
        type = type || 'success';

        const iconClass = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';

        const toast = $('<div class="dm-toast ' + type + '">' +
            '<i class="fas ' + iconClass + '"></i>' +
            '<span>' + message + '</span>' +
        '</div>');

        $('body').append(toast);

        setTimeout(function() {
            toast.fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    }

    /**
     * Handle pin icon click in product loop
     */
    $(document).on('click', '.dm-pin-icon', function(e) {
        e.preventDefault();

        const $icon = $(this);
        let productId = $icon.data('product-id');

        // Check if user is logged in
        if (!merklisteAjax.isLoggedIn) {
            showToast(merklisteAjax.loginRequired, 'error');
            return;
        }

        if ($icon.hasClass('loading')) {
            return;
        }

        $icon.addClass('loading');

        // If we don't have product ID, fetch it from URL first
        if (!productId || productId === 0) {
            const productUrl = $icon.data('product-url');
            if (!productUrl) {
                showToast('Product URL not found', 'error');
                $icon.removeClass('loading');
                return;
            }

            // Fetch product ID from URL
            $.ajax({
                url: merklisteAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'dm_get_product_id_from_url',
                    product_url: productUrl,
                    nonce: merklisteAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        productId = response.data.product_id;
                        // Now toggle merkliste with the product ID
                        toggleProductMerkliste($icon, productId);
                    } else {
                        showToast('Product ID konnte nicht ermittelt werden', 'error');
                        $icon.removeClass('loading');
                    }
                },
                error: function() {
                    showToast('Fehler beim Abrufen der Produkt-ID', 'error');
                    $icon.removeClass('loading');
                }
            });
        } else {
            // Already have product ID, toggle directly
            toggleProductMerkliste($icon, productId);
        }
    });

    /**
     * Toggle product in merkliste (helper function)
     */
    function toggleProductMerkliste($icon, productId) {
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
                    const isInList = response.data.is_in_list;

                    // Toggle active class only
                    if (isInList) {
                        $icon.addClass('active');
                        $icon.attr('title', 'Von Merkliste entfernen');
                    } else {
                        $icon.removeClass('active');
                        $icon.attr('title', 'Zur Merkliste hinzufügen');
                    }

                    showToast(response.data.message, 'success');
                } else {
                    showToast(response.data.message || merklisteAjax.errorText, 'error');
                }
            },
            error: function() {
                showToast(merklisteAjax.errorText, 'error');
            },
            complete: function() {
                $icon.removeClass('loading');
            }
        });
    }

    /**
     * Handle button click on single product page
     */
    $(document).on('click', '.dm-merkliste-btn', function(e) {
        e.preventDefault();

        const $button = $(this);
        const productId = $button.data('product-id');

        // Check if user is logged in
        if (!merklisteAjax.isLoggedIn) {
            showToast(merklisteAjax.loginRequired, 'error');
            return;
        }

        if ($button.hasClass('loading')) {
            return;
        }

        $button.addClass('loading');

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
                    const isInList = response.data.is_in_list;

                    // Toggle button state
                    if (isInList) {
                        $button.addClass('dm-in-list');
                        $button.html('<i class="fas fa-thumbtack"></i> Von Merkliste entfernen');
                    } else {
                        $button.removeClass('dm-in-list');
                        $button.html('<i class="fas fa-thumbtack"></i> Zur Merkliste hinzufügen');
                    }

                    showToast(response.data.message, 'success');
                } else {
                    showToast(response.data.message || merklisteAjax.errorText, 'error');
                }
            },
            error: function() {
                showToast(merklisteAjax.errorText, 'error');
            },
            complete: function() {
                $button.removeClass('loading');
            }
        });
    });

    /**
     * Handle remove from list in dashboard (with AJAX)
     */
    $(document).on('click', '.dm-remove-from-list', function(e) {
        e.preventDefault();

        if (!confirm('Wirklich von der Merkliste entfernen?')) {
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
                    showToast(response.data.message || merklisteAjax.errorText, 'error');
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
     * Update all pin icons on page load based on current merkliste state
     */
    function updatePinIcons() {
        $('.dm-pin-icon').each(function() {
            const $icon = $(this);
            const productId = $icon.data('product-id');

            $.ajax({
                url: merklisteAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'dm_check_merkliste_status',
                    product_id: productId,
                    nonce: merklisteAjax.nonce
                },
                success: function(response) {
                    if (response.success && response.data.is_in_list) {
                        $icon.addClass('active');
                        $icon.attr('title', 'Von Merkliste entfernen');
                    }
                }
            });
        });
    }

    // Initialize pin icons on page load
    if ($('.dm-pin-icon').length > 0) {
        // Icons are already rendered with correct state from PHP
        // No need to check via AJAX unless you want to ensure sync
    }

    /**
     * Add merkliste icon to WCPS slider product images
     */
    function initWCPSMerklisteIcons() {
        // Find all WCPS product image containers
        $('.wcps-items-thumb').each(function() {
            const $thumb = $(this);

            // Skip if icon already exists
            if ($thumb.find('.dm-pin-icon-wrapper').length > 0) {
                return;
            }

            // Get product URL from the link
            const productUrl = $thumb.find('a').attr('href');
            if (!productUrl) return;

            // Create icon HTML
            const disabledClass = !merklisteAjax.isLoggedIn ? 'dm-pin-icon-disabled' : '';
            const iconHTML = '<div class="dm-pin-icon-wrapper dm-pin-icon-wcps">' +
                '<a href="#" class="dm-pin-icon ' + disabledClass + '" data-product-url="' + productUrl + '" title="Zur Merkliste hinzufügen">' +
                '<i class="fas fa-thumbtack" aria-hidden="true"></i>' +
                '</a>' +
                '</div>';

            // Insert icon
            $thumb.prepend(iconHTML);
        });
    }

    // Initialize on page load
    $(document).ready(function() {
        initWCPSMerklisteIcons();
    });

    // Also reinitialize after AJAX or dynamic content loading
    $(document).on('wcps-items-loaded', function() {
        initWCPSMerklisteIcons();
    });

});
