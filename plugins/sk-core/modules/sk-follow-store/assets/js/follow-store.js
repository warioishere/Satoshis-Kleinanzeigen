/**
 * SK Follow Store — Toggle follow/unfollow vendor
 */
(function ($) {
    'use strict';

    function toggleFollow($btn, vendorId, nonce) {
        $btn.toggleClass('sk-follow-store-button-working');

        $.ajax({
            url: sk.ajaxurl,
            method: 'post',
            dataType: 'json',
            data: {
                action: 'sk_follow_store_toggle_status',
                _nonce: nonce || skFollowStore._nonce,
                vendor_id: vendorId
            }
        }).fail(function (xhr) {
            // A 500 or a dropped connection has no responseJSON; reading it blindly
            // threw a TypeError and the user saw nothing at all.
            var data = xhr.responseJSON && xhr.responseJSON.data;
            var err = Array.isArray(data) ? data[data.length - 1] : data;
            sk_sweetalert((err && err.message) || skFollowStore.error_text, { icon: 'error' });
        }).always(function () {
            $btn.toggleClass('sk-follow-store-button-working');
        }).done(function (res) {
            if (res.data && res.data.status) {
                if (res.data.status === 'following') {
                    $btn.attr('data-status', 'following')
                        .children('.sk-follow-store-button-label-current')
                        .html(skFollowStore.button_labels.following);
                } else {
                    $btn.attr('data-status', '')
                        .children('.sk-follow-store-button-label-current')
                        .html(skFollowStore.button_labels.follow);
                }
            }
            $('body').trigger('sk:follow_store:changed_follow_status', {
                vendor_id: vendorId, button: $btn, status: res.data.status
            });
        });
    }

    function getCurrentStatus(vendorId) {
        $.ajax({
            url: sk.ajaxurl,
            method: 'get',
            dataType: 'json',
            data: { action: 'sk_follow_store_get_current_status', vendor_id: vendorId }
        }).done(function (res) {
            $('body').trigger('sk:follow_store:current_status', {
                vendor_id: vendorId,
                is_following: res.data.is_following,
                nonce: res.data.nonce
            });
        });
    }

    $('body').on('click', '.sk-follow-store-button', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var vendorId = parseInt($btn.data('vendor-id'));

        if (!parseInt($btn.data('is-logged-in'))) {
            // .one() and a namespace: every click used to stack another set of
            // handlers on body, so after two clicks the toggle ran twice and the
            // follow was undone right after it happened.
            $('body').off('.skFollowLogin');

            $('body').on('sk:login_form_popup:fetching_form.skFollowLogin sk:login_form_popup:fetched_form.skFollowLogin', function () {
                $btn.toggleClass('sk-follow-store-button-working');
            });
            $('body').one('sk:login_form_popup:logged_in.skFollowLogin', function () {
                getCurrentStatus(vendorId);
            });
            $('body').one('sk:follow_store:current_status.skFollowLogin', function (ev, data) {
                data.is_following ? window.location.reload() : toggleFollow($btn, vendorId, data.nonce);
            });
            $('body').one('sk:follow_store:changed_follow_status.skFollowLogin', function () {
                window.location.reload();
            });
            $('body').trigger('sk:login_form_popup:show');
            return;
        }

        toggleFollow($btn, vendorId);
    });

})(jQuery);
