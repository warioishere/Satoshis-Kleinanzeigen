/**
 * SK Store Listing Filter Toggle
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Toggle filter form visibility
        $('.sk-store-list-filter-button').on('click', function(e) {
            e.preventDefault();
            $('#sk-store-listing-filter-form-wrap').slideToggle(300, function() {
                if ($(this).is(':visible')) {
                    $(this).find('input[type="text"]').first().focus();
                }
            });
        });
    });
})(jQuery);
