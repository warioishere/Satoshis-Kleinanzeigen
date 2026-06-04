(function ($) {
    'use strict';

    if (!window.skProductVotes) return;
    var cfg = window.skProductVotes;

    $(document).on('click', '.sk-pv-widget .sk-pv-btn:not(:disabled)', function (e) {
        e.preventDefault();
        var $btn    = $(this);
        var $widget = $btn.closest('.sk-pv-widget');
        var product_id = $widget.data('product-id');
        var value      = parseInt($btn.data('value'), 10);

        $widget.find('.sk-pv-btn').prop('disabled', true);

        $.post(cfg.ajaxurl, {
            action: 'sk_product_vote',
            nonce: cfg.nonce,
            product_id: product_id,
            value: value
        }).done(function (res) {
            if (!res || !res.success) {
                alert((res && res.data && res.data.message) ? res.data.message : cfg.i18n.error);
                return;
            }
            var data = res.data;

            $widget.find('.sk-pv-btn').removeClass('sk-pv-active');
            if (data.user_vote === 1)  $widget.find('.sk-pv-hot').addClass('sk-pv-active');
            if (data.user_vote === -1) $widget.find('.sk-pv-cold').addClass('sk-pv-active');

            if (data.show) {
                $widget.find('.sk-pv-too-few').remove();
                var $hot  = $widget.find('.sk-pv-hot');
                var $cold = $widget.find('.sk-pv-cold');
                if (!$hot.find('[data-role=hot]').length)  $hot.append('<span class="sk-pv-count" data-role="hot">' + data.hot + '</span>');
                else $hot.find('[data-role=hot]').text(data.hot);
                if (!$cold.find('[data-role=cold]').length) $cold.append('<span class="sk-pv-count" data-role="cold">' + data.cold + '</span>');
                else $cold.find('[data-role=cold]').text(data.cold);
            } else {
                // Vote zurückgenommen, Total wieder 0 → Badges entfernen
                $widget.find('.sk-pv-count').remove();
            }
        }).fail(function (xhr) {
            var msg = cfg.i18n.error;
            try {
                var body = JSON.parse(xhr.responseText);
                if (body && body.data && body.data.message) msg = body.data.message;
            } catch (e) {}
            alert(msg);
        }).always(function () {
            $widget.find('.sk-pv-btn').prop('disabled', false);
        });
    });

})(jQuery);
