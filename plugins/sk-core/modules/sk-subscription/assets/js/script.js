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

    // Term buttons on a package card: pick the sibling pack the buy button
    // leads to, and show its price.
    $(document).on('click', '.pack_term', function () {
        var $btn  = $(this);
        var $card = $btn.closest('.product_pack_item');

        $card.find('.pack_term').removeClass('is-active');
        $btn.addClass('is-active');
        $card.find('.dps-amount').text($btn.data('price'));
        $card.find('.pack_days').text($btn.data('days'));

        var url = $btn.data('url');
        if (url) $card.find('.buy_product_pack').attr('href', url);
    });

    // Last look before the payment page: what was picked, and what it
    // contains. Filled from the card, so the list can never drift from it.
    $(document).on('click', '.buy_product_pack', function (e) {
        var $link = $(this);
        var href  = $link.attr('href') || '';
        var $box  = $('#sk-pack-confirm');

        // "Your pack" points at the package page, not at a purchase.
        if (!$box.length || href.indexOf('add-to-cart=') === -1) return;

        e.preventDefault();

        var $card = $link.closest('.product_pack_item');
        var $term = $card.find('.pack_term.is-active');

        $box.find('[data-role=title]').text($card.find('h2').first().text().trim());
        $box.find('[data-role=price]').text($card.find('.dps-amount').text().split('≈')[0].trim());
        $box.find('[data-role=term]').text(
            $term.length ? $term.contents().first().text().trim() : $card.find('.pack_days').text().trim() + ' ' + skSubscription.days_string
        );
        $box.find('[data-role=go]').attr('href', href);

        var facts = [];
        var limit = $card.find('.pack_limit').text().trim();
        var days  = $card.find('.pack_days').text().trim();

        if (limit || days) {
            facts.push({
                icon: 'fa-list',
                text: [limit && limit + ' ' + skSubscription.listings_string, days && days + ' ' + skSubscription.days_string]
                    .filter(Boolean).join(' · ')
            });
        }

        $card.find('.pack_short_desc p').each(function () {
            var text = $(this).text().replace(/\s+/g, ' ').trim();
            if (text) facts.push({ icon: 'fa-circle-info', text: text });
        });
        $card.find('.pack_features li').each(function () {
            var $li = $(this);
            facts.push({ icon: ($li.find('i').attr('class') || '').replace('fas ', ''), text: $li.text().trim() });
        });

        $box.find('[data-role=facts]').html(facts.map(function (f) {
            return '<li><i class="fas ' + f.icon + '"></i><span>' + $('<div>').text(f.text).html() + '</span></li>';
        }).join(''));

        $box.addClass('is-visible');
        $box.find('.sk-pack-info__close').trigger('focus');
    });

    $(document).on('click', '#sk-pack-confirm .sk-pack-info__close, #sk-pack-confirm .sk-pack-info__backdrop', function (e) {
        e.preventDefault();
        $('#sk-pack-confirm').removeClass('is-visible');
    });

})(jQuery);
