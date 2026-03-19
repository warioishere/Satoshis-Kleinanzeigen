/**
 * SK Subscription — Pack selector, cancel/activate confirm dialogs
 */
(function ($) {
    'use strict';

    var $packWrapper = $('.dps-pack-wrappper');

    function showDetails() {
        var val = $('select#sk-subscription-pack').val();
        $('.dps-pack').hide();
        $('.dps-pack-' + val).show();
    }

    // Pack selector
    $packWrapper.on('change', 'select#sk-subscription-pack', showDetails);
    showDetails();

    // Cancel subscription confirm
    $('.seller_subs_info input[name="dps_submit"]').on('click', function (e) {
        e.preventDefault();
        if ($('input[name="dps_cancel_subscription"]').val()) {
            sk_sweetalert(skSubscription.cancel_string, { action: 'confirm', icon: 'warning' }).then(function (result) {
                if (result.isConfirmed) $('#dps_submit_form').submit();
            });
        }
    });

    // Activate subscription confirm
    $('.seller_subs_info input[name="dps_submit"]').on('click', function (e) {
        e.preventDefault();
        if (!$('input[name="dps_cancel_subscription"]').val()) {
            sk_sweetalert(skSubscription.activate_string, { action: 'confirm', icon: 'warning' }).then(function (result) {
                if (result.isConfirmed) $('#dps_submit_form').submit();
            });
        }
    });

})(jQuery);
