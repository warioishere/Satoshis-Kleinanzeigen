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
                // Update status counts in filter tabs
                if (res.data) {
                    if (res.data.approved !== undefined) {
                        $('.sk-review-count-approved').text(res.data.approved);
                    }
                    if (res.data.pending !== undefined) {
                        $('.sk-review-count-pending').text(res.data.pending);
                    }
                    if (res.data.spam !== undefined) {
                        $('.sk-review-count-spam').text(res.data.spam);
                    }
                    if (res.data.trash !== undefined) {
                        $('.sk-review-count-trash').text(res.data.trash);
                    }
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

    // Bulk action: select all checkboxes
    $(document).on('change', '#cb-select-all-reviews', function () {
        $('.cb-select-review').prop('checked', $(this).is(':checked'));
    });

})(jQuery);
