/**
 * Feedback shortcode form submit — AJAX POST to admin-ajax.php, inline status.
 * Localized vars from PHP: wpsfFeedback.ajaxurl, wpsfFeedback.i18n.{sending,error}
 */
(function () {
    'use strict';
    document.addEventListener('submit', function (e) {
        var form = e.target.closest('.wpsf-form');
        if (!form) return;
        e.preventDefault();

        var msgEl = form.querySelector('.wpsf-msg');
        var data  = new FormData(form);
        var cfg   = window.wpsfFeedback || { ajaxurl: '/wp-admin/admin-ajax.php', i18n: {} };

        if (msgEl) msgEl.textContent = cfg.i18n.sending || 'Senden...';

        fetch(cfg.ajaxurl, {
            method: 'POST',
            credentials: 'same-origin',
            body: data,
        })
            .then(function (res) { return res.json(); })
            .then(function (json) {
                if (msgEl) msgEl.textContent = json.msg || '';
                if (json.ok) form.reset();
            })
            .catch(function () {
                if (msgEl) msgEl.textContent = cfg.i18n.error || 'Es ist ein Fehler aufgetreten.';
            });
    });
})();
