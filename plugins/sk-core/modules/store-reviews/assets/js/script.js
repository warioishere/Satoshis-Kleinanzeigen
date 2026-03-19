/**
 * SK Store Reviews — Review popup, rating, submit
 */
(function ($) {
    'use strict';

    var $wrapper = $('.sk-review-wrapper');
    var ajaxAction = 'sk_store_rating_ajax_handler';
    var $modal = $('.sk-store-review-iziModal').iziModal({
        width: 690,
        closeButton: true,
        appendTo: 'body',
        title: '',
        headerColor: sk.modal_header_color
    });

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

    // Show add review popup
    $wrapper.on('click', 'button.add-review-btn', function () {
        $modal.iziModal('startLoading');
        $.post(sk.ajaxurl, {
            action: ajaxAction,
            data: 'review_form',
            store_id: $('button.add-review-btn').data('store_id')
        }, function (res) {
            if (res.success == 1) {
                $modal.iziModal('setContent', wrapContent(res.data).trim());
                $modal.iziModal('open');
                initRating();
            }
            $modal.iziModal('stopLoading');
        });
    });

    // Show edit review popup
    $wrapper.on('click', 'button.edit-review-btn', function () {
        $modal.iziModal('startLoading');
        $.post(sk.ajaxurl, {
            action: ajaxAction,
            data: 'edit_review_form',
            store_id: $('button.edit-review-btn').data('store_id'),
            post_id: $('button.edit-review-btn').data('post_id')
        }, function (res) {
            if (res.success == 1) {
                $modal.iziModal('setContent', wrapContent(res.data).trim());
                $modal.iziModal('open');
                initRating();
            }
            $modal.iziModal('stopLoading');
        });
    });

    // Submit review
    $('body').on('submit', '#sk-add-review-form', function (e) {
        e.preventDefault();
        var $form = $(this);
        var $error = $('#ds-error-msg');

        $modal.iziModal('startLoading');
        $.post(sk.ajaxurl, {
            action: ajaxAction,
            data: 'submit_review',
            store_id: $('button.add-review-btn').data('store_id'),
            rating: $('#sk-seller-rating').rateYo('rating'),
            form_data: $form.serialize()
        }, function (res) {
            if (res.success == 1) {
                var msg = '<div class="sk-seller-rating-add-wrapper sk-izimodal-wraper sk-alert sk-alert-success">' + res.msg + '</div>';
                $modal.iziModal('setContent', msg.trim());
                location.reload();
            } else if (res.success == 0) {
                $error.removeClass('sk-hide').html(res.msg).addClass('sk-alert sk-alert-danger');
            }
            $modal.iziModal('stopLoading');
        });
    });

})(jQuery);
