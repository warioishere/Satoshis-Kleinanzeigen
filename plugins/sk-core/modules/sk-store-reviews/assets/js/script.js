/**
 * SK Store Reviews — Review popup, rating, submit
 *
 * Uses document-level delegation + lazy modal init so the buttons still work
 * after AJAX tab switches (sk-store-tabs.js injects the reviews tab DOM on
 * demand — at script-load time the wrapper/modal elements don't exist yet).
 */
(function ($) {
    'use strict';

    var ajaxAction = 'sk_store_rating_ajax_handler';

    function getModal() {
        var $el = $('.sk-store-review-iziModal');
        if (!$el.length) return null;
        if (!$el.data('iziModal')) {
            $el.iziModal({
                width: 690,
                closeButton: true,
                appendTo: 'body',
                title: '',
                headerColor: sk.modal_header_color
            });
        }
        return $el;
    }

    function initRating() {
        var $form = $('form.sk-form-container');
        var rating = $form.data('rating');
        var rtl = $form.data('rtl');
        $('#sk-seller-rating').rateYo({
            rating: rating || 1,
            starWidth: '20px',
            fullStar: true,
            minValue: 1,
            rtl: rtl || false
        });
    }

    function wrapContent(html) {
        return '<div class="sk-seller-rating-add-wrapper sk-izimodal-wraper">' +
            '<div id="ds-error-msg"></div>' +
            '<div class="sk-izimodal-close-btn">' +
            '<button data-iziModal-close class="icon-close"><i class="fa fa-times" aria-hidden="true"></i></button>' +
            '</div>' + html + '</div>';
    }

    // Add review popup
    $(document).on('click', '.sk-review-wrapper button.add-review-btn', function () {
        var $m = getModal();
        if (!$m) return;
        var storeId = $(this).data('store_id');
        $m.iziModal('startLoading');
        $.post(sk.ajaxurl, {
            action: ajaxAction,
            data: 'review_form',
            store_id: storeId
        }, function (res) {
            if (res.success == 1) {
                $m.iziModal('setContent', wrapContent(res.data).trim());
                $m.iziModal('open');
                initRating();
            }
            $m.iziModal('stopLoading');
        });
    });

    // Edit review popup
    $(document).on('click', '.sk-review-wrapper button.edit-review-btn', function () {
        var $m = getModal();
        if (!$m) return;
        var $btn = $(this);
        $m.iziModal('startLoading');
        $.post(sk.ajaxurl, {
            action: ajaxAction,
            data: 'edit_review_form',
            store_id: $btn.data('store_id'),
            post_id: $btn.data('post_id')
        }, function (res) {
            if (res.success == 1) {
                $m.iziModal('setContent', wrapContent(res.data).trim());
                $m.iziModal('open');
                initRating();
            }
            $m.iziModal('stopLoading');
        });
    });

    // Submit review
    $(document).on('submit', '#sk-add-review-form', function (e) {
        e.preventDefault();
        var $form = $(this);
        var $error = $('#ds-error-msg');
        var $m = getModal();
        if (!$m) return;

        $m.iziModal('startLoading');
        $.post(sk.ajaxurl, {
            action: ajaxAction,
            data: 'submit_review',
            store_id: $('button.add-review-btn').data('store_id'),
            rating: $('#sk-seller-rating').rateYo('rating'),
            form_data: $form.serialize()
        }, function (res) {
            if (res.success == 1) {
                var msg = '<div class="sk-seller-rating-add-wrapper sk-izimodal-wraper sk-alert sk-alert-success">' + res.msg + '</div>';
                $m.iziModal('setContent', msg.trim());
                location.reload();
            } else if (res.success == 0) {
                $error.removeClass('sk-hide').html(res.msg).addClass('sk-alert sk-alert-danger');
            }
            $m.iziModal('stopLoading');
        });
    });

})(jQuery);
