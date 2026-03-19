/**
 * SK Global Utilities
 * - Delete confirm prompt (sweetalert)
 * - Bulk delete prompt
 * - Select-all checkbox toggle
 * - Tooltip init
 */
(function ($) {
    'use strict';

    // Delete confirm with sweetalert, then redirect
    window.sk_show_delete_prompt = function (e, url) {
        e.preventDefault();
        if (typeof sk_sweetalert === 'undefined') {
            if (confirm(sk.delete_confirm || 'Are you sure?')) {
                window.location = url;
            }
            return;
        }
        sk_sweetalert(sk.delete_confirm || 'Are you sure?', {
            action: 'confirm',
            icon: 'warning'
        }).then(function (val) {
            if (val.isConfirmed || val === true) {
                window.location = url;
            }
        });
    };

    // Backwards compat alias
    

    // Bulk delete: confirm + submit form
    window.sk_bulk_delete_prompt = function (e, form) {
        e.preventDefault();
        if (typeof sk_sweetalert === 'undefined') {
            if (confirm(sk.delete_confirm || 'Are you sure?')) {
                form.submit();
            }
            return;
        }
        sk_sweetalert(sk.delete_confirm || 'Are you sure?', {
            action: 'confirm',
            icon: 'warning'
        }).then(function (val) {
            if (val.isConfirmed || val === true) {
                form.submit();
            }
        });
    };

    

    // Select-all checkbox toggle
    $(document).on('change', '#cb-select-all', function () {
        var checked = $(this).is(':checked');
        $('.cb-select-items').prop('checked', checked);
    });

    // Tooltip init (tipTip)
    $(function () {
        $('.tips, .help_tip').each(function () {
            var $el = $(this);
            if ($.fn.tipTip && $el.data('tip')) {
                $el.tipTip({ content: $el.data('tip'), defaultPosition: 'top' });
            }
        });
    });
})(jQuery);
