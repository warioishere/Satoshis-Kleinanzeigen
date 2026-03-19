/**
 * SK Daterangepicker initialization
 */
(function ($) {
    'use strict';

    $(function () {
        if (!$.fn.daterangepicker) return;

        var format = (window.sk && sk.i18n_date_format) ? sk.i18n_date_format : 'YYYY-MM-DD';

        $('.sk-daterangepicker').each(function () {
            var $input = $(this);
            var $startHidden = $input.siblings('input[name="start_date_alt"], input[name="start_date"]').first();
            var $endHidden = $input.siblings('input[name="end_date_alt"], input[name="end_date"]').first();

            $input.daterangepicker({
                autoUpdateInput: false,
                locale: { format: format, cancelLabel: 'Clear' }
            });

            $input.on('apply.daterangepicker', function (ev, picker) {
                $(this).val(picker.startDate.format(format) + ' - ' + picker.endDate.format(format));
                if ($startHidden.length) $startHidden.val(picker.startDate.format('YYYY-MM-DD'));
                if ($endHidden.length) $endHidden.val(picker.endDate.format('YYYY-MM-DD'));
            });

            $input.on('cancel.daterangepicker', function () {
                $(this).val('');
                if ($startHidden.length) $startHidden.val('');
                if ($endHidden.length) $endHidden.val('');
            });
        });
    });
})(jQuery);
