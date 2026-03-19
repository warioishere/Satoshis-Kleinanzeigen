/**
 * SK Helper — Date/time format conversion, SweetAlert wrapper, utilities
 */
(function (document, window, $) {
    'use strict';

    /* ── Date Format (PHP → jQuery UI datepicker) ── */

    window.sk_get_i18n_date_format = function (convert) {
        if (convert === false) return sk_helper.i18n_date_format;
        var map = { d: 'dd', D: 'D', j: 'd', l: 'DD', F: 'MM', m: 'mm', M: 'M', n: 'm', o: 'yy', Y: 'yy', y: 'y' };
        var result = '';
        for (var i = 0; i < sk_helper.i18n_date_format.length; i++) {
            var c = sk_helper.i18n_date_format[i];
            result += (c in map) ? map[c] : c;
        }
        return result;
    };

    /* ── Time Format (PHP → Moment.js) ── */

    window.sk_get_i18n_time_format = function (convert) {
        if (convert === false) return sk_helper.i18n_time_format;
        var map = {
            N: 'E', S: 'o', w: 'e', z: 'DDD', W: 'W', F: 'MMMM', m: 'MM', M: 'MMM', n: 'M',
            o: 'YYYY', Y: 'YYYY', y: 'YY', a: 'a', A: 'A', g: 'h', G: 'H', h: 'hh', H: 'HH',
            i: 'mm', s: 'ss', u: 'SSS', e: 'zz', U: 'X'
        };
        var result = '';
        for (var i = 0; i < sk_helper.i18n_time_format.length; i++) {
            if (sk_helper.i18n_time_format[i] === '\\') {
                result += sk_helper.i18n_time_format[i];
                i++;
                result += sk_helper.i18n_time_format[i];
            } else {
                var c = sk_helper.i18n_time_format[i];
                result += (c in map) ? map[c] : c;
            }
        }
        return result;
    };

    window.sk_get_i18n_time_format_for_moment_js = function (fmt) {
        fmt = fmt || sk_get_i18n_time_format();
        return fmt.replace(/\\(.)/g, '[$1]');
    };

    /* ── Format Time String ── */

    window.sk_get_formatted_time = function (value, format, timeFormat) {
        timeFormat = timeFormat || sk_get_i18n_time_format();
        if (!format || !format.length) return '';

        var d = moment(value, timeFormat).toDate();
        var pad = function (n) { return n < 10 ? '0' + n : '' + n; };
        var h = d.getHours();
        var m = d.getMinutes();
        var s = d.getSeconds();
        var h12 = h % 12 || 12;

        var replacements = {
            hh: pad(h12), h: h12, HH: pad(h), H: h, g: h12,
            MM: pad(m), M: m, mm: pad(m), m: m, i: pad(m),
            ss: pad(s), s: s, A: h >= 12 ? 'PM' : 'AM', a: h >= 12 ? 'pm' : 'am'
        };

        var get = function (obj, key) { return obj[key] ? obj[key] : key; };
        var result = '', token = '';
        for (var i = 0; i < format.length; i++) {
            var c = format[i];
            if (c === '\\') {
                if (token) { result += get(replacements, token); token = ''; }
                i++;
                result += format[i];
            } else if (!token) {
                token = c;
            } else if (token !== c) {
                result += get(replacements, token);
                token = c;
            } else {
                token += c;
            }
        }
        if (token) result += get(replacements, token);
        return result;
    };

    /* ── DateRange Picker Format (PHP → Moment.js) ── */

    window.sk_get_daterange_picker_format = function (phpFormat) {
        phpFormat = phpFormat || sk_helper.i18n_date_format;
        var map = {
            d: 'D', D: 'DD', j: 'D', l: 'DD', F: 'MMMM', m: 'MM', M: 'MM', n: 'M',
            o: 'YYYY', Y: 'YYYY', y: 'YY', g: 'h', G: 'H', h: 'hh', H: 'HH', i: 'mm', s: 'ss'
        };
        var result = '';
        for (var i = 0; i < phpFormat.length; i++) {
            var c = phpFormat[i];
            result += (c in map) ? map[c] : c;
        }
        return result;
    };

    /* ── SweetAlert Wrapper ── */

    window.sk_sweetalert = async function (text, opts) {
        opts = opts || {};
        var config = Object.assign({
            text: text,
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#dc3545'
        }, sk_helper.sweetalert_local || {}, opts);

        var action = config.action;
        delete config.action;

        if (action === 'confirm' || action === 'prompt') {
            return await Swal.fire(config);
        }
        delete config.showCancelButton;
        Swal.fire(config);
    };

    /* ── Google reCaptcha v3 ── */

    window.sk_execute_recaptcha = function (selector, action) {
        return new Promise(function (resolve) {
            if (typeof sk_google_recaptcha === 'undefined') { resolve(); return; }
            var sitekey = sk_google_recaptcha.recaptcha_sitekey;
            var input = document.querySelector(selector);
            if (!sitekey) { resolve(); return; }
            grecaptcha.ready(function () {
                grecaptcha.execute(sitekey, { action: action }).then(function (token) {
                    input.value = token;
                    resolve();
                });
            });
        });
    };

    /* ── AJAX Error Handler ── */

    window.sk_handle_ajax_error = function (err) {
        if (err.responseJSON && err.responseJSON.message) return err.responseJSON.message;
        if (err.responseJSON && err.responseJSON.data && err.responseJSON.data.message) return err.responseJSON.data.message;
        return err.responseText || '';
    };

    /* ── Phone Number Input Sanitizer ── */

    window.sk_sanitize_phone_number = function (e) {
        var allowed = ['Backspace', 'Tab', 'Enter', 'Escape', 'ArrowLeft', 'ArrowRight'];
        var chars = ['(', ')', '.', '-', '_', '+'];
        if (allowed.indexOf(e.key) !== -1) return;
        if (chars.indexOf(e.key) !== -1) return;
        if (e.key === 'a' && e.ctrlKey) return;
        if (e.shiftKey && !isNaN(Number(e.key))) { e.preventDefault(); return; }
        if (isNaN(Number(e.key))) e.preventDefault();
    };

    /* ── Copy to Clipboard ── */

    var copySvg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">' +
        '<path d="M8 4V16C8 17.1046 8.89543 18 10 18L18 18C19.1046 18 20 17.1046 20 16V7.24162C20 6.7034 19.7831 6.18789 19.3982 5.81161L16.0829 2.56999C15.7092 2.2046 15.2074 2 14.6847 2H10C8.89543 2 8 2.89543 8 4Z" stroke="#000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>' +
        '<path d="M16 18V20C16 21.1046 15.1046 22 14 22H6C4.89543 22 4 21.1046 4 20V9C4 7.89543 4.89543 7 6 7H8" stroke="#000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';

    var checkSvg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">' +
        '<path d="M4.89163 13.2687L9.16582 17.5427L18.7085 8" stroke="#000" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';

    $(document).ready(function () {
        var $btns = $('.sk-copy-to-clipboard');
        $btns.css('cursor', 'pointer').html(copySvg);

        $btns.on('click', function () {
            var $el = $(this);
            var text = $el.data('copy') || '';
            var textarea = document.createElement('textarea');
            document.body.appendChild(textarea);
            textarea.value = text;
            textarea.select();
            textarea.setSelectionRange(0, 99999);
            var ok = document.execCommand('copy');
            document.body.removeChild(textarea);
            if (ok) {
                $el.html(checkSvg);
                setTimeout(function () { $el.html(copySvg); }, 1000);
            }
        });
    });

})(document, window, jQuery);
