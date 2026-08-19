/**
 * SK Review Management — Approve/Spam/Trash/Delete reviews via AJAX
 */
(function ($) {
    'use strict';

    var ajaxurl = (window.sk && sk.ajaxurl) ;
    var nonce = (window.sk && sk.nonce) ;

    // Single review action (approve/spam/trash/delete)
    $(document).on('click', '.sk-cmt-action', function (e) {
        e.preventDefault();
        var $link = $(this);
        var $card = $link.closest('.sk-review-card, tr');

        var data = {
            action: 'sk_comment_status',
            nonce: nonce,
            comment_id: $link.data('comment_id'),
            comment_status: $link.data('cmt_status'),
            post_type: $link.data('post_type')
        };

        $card.css('opacity', '0.5');

        $.post(ajaxurl, data, function (res) {
            if (res.success) {
                // Update status counts in filter tabs. Template uses multi-class
                // selectors (.sk-review-count.pending etc.), not dashed variants.
                // Toggle is-zero so CSS can hide the badge at 0 without removing it.
                if (res.data) {
                    var updateCount = function (sel, val) {
                        if (val === undefined) return;
                        var $el = $(sel);
                        if (!$el.length) return;
                        $el.text(val).toggleClass('is-zero', parseInt(val, 10) === 0);
                    };
                    // 'approved' badge is the generic .sk-review-count (no status class).
                    updateCount('.sk-review-filter-tab:first-child .sk-review-count', res.data.approved);
                    updateCount('.sk-review-count.pending', res.data.pending);
                    updateCount('.sk-review-count.spam',    res.data.spam);
                    updateCount('.sk-review-count.trash',   res.data.trash);
                }

                // If deleted or moved to different status view, remove card
                var currentPage = $link.data('curr_page');
                var newStatus = $link.data('cmt_status');

                if (newStatus === 'delete' || currentPage !== newStatus) {
                    $card.fadeOut(300, function () { $(this).remove(); });
                } else {
                    // Update the card HTML if returned
                    if (res.data && res.data.content) {
                        $card.replaceWith(res.data.content);
                    } else {
                        $card.css('opacity', '1');
                    }
                }
            } else {
                $card.css('opacity', '1');
            }
        }).fail(function () {
            $card.css('opacity', '1');
        });
    });

    // Permanent deletion cannot be undone — ask before submitting the bulk form.
    $(document).on('submit', '#sk_comments-form', function (e) {
        if ($(this).find('.sk-reviews-bulk-select').val() !== 'delete') return;

        var count = $('.sk-check-col:checked').length;
        if (!count) return;

        if (!confirm(count + ' Rezension(en) werden dauerhaft gelöscht. Das lässt sich nicht rückgängig machen. Fortfahren?')) {
            e.preventDefault();
        }
    });

    // Bulk action: select all checkboxes
    $(document).on('change', '.sk-check-all', function () {
        $('.sk-check-col').prop('checked', $(this).is(':checked'));
    });

    // Untick "select all" as soon as a single row is unticked again.
    $(document).on('change', '.sk-check-col', function () {
        var $boxes = $('.sk-check-col');
        $('.sk-check-all').prop('checked', $boxes.length > 0 && $boxes.length === $boxes.filter(':checked').length);
    });

})(jQuery);
