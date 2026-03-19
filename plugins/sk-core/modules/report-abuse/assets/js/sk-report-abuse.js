/**
 */
(function () {
    'use strict';

    if (!window.skReportAbuse) return;

    var cfg = window.skReportAbuse;
    var ajaxurl = (window.sk && sk.ajaxurl) ;
    var modal = null;

    // Click on report button
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.sk-report-abuse-button');
        if (!btn) return;
        e.preventDefault();

        // If not logged in and login required
        if (!cfg.is_user_logged_in && cfg.reported_by_logged_in_users_only === 'on') {
            if (window.jQuery && jQuery.fn.iziModal && jQuery('#sk-login-form-popup').length) {
                jQuery('#sk-login-form-popup').iziModal('open');
            } else {
                alert('Bitte einloggen um ein Produkt zu melden.');
            }
            return;
        }

        loadForm();
    });

    function loadForm() {
        fetch(ajaxurl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=sk_report_abuse_get_form&_ajax_nonce=' + cfg.nonce,
            credentials: 'same-origin'
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (!res.success) return;
            showModal(res.data.title, res.data.html);
        });
    }

    function showModal(title, html) {
        // Remove old modal
        var old = document.getElementById('sk-report-abuse-modal');
        if (old) old.remove();

        var overlay = document.createElement('div');
        overlay.id = 'sk-report-abuse-modal';
        overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:99999;display:flex;align-items:center;justify-content:center;';

        var box = document.createElement('div');
        box.style.cssText = 'background:#1f2933;color:#e2e8f0;border-radius:12px;padding:24px;max-width:480px;width:90%;max-height:80vh;overflow-y:auto;position:relative;';

        var header = '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">' +
            '<h3 style="margin:0;color:#f7931a;">' + title + '</h3>' +
            '<button id="sk-report-abuse-close" style="background:none;border:none;color:#8b949e;font-size:24px;cursor:pointer;">&times;</button></div>';

        box.innerHTML = header + html;
        overlay.appendChild(box);
        document.body.appendChild(overlay);
        modal = overlay;

        // Close button
        document.getElementById('sk-report-abuse-close').addEventListener('click', closeModal);
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) closeModal();
        });

        // Handle form submit
        var form = box.querySelector('form');
        if (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                submitForm(form);
            });
        }
    }

    function closeModal() {
        if (modal) { modal.remove(); modal = null; }
    }

    function submitForm(form) {
        var submitBtn = form.querySelector('#sk-report-abuse-form-submit-btn');
        var workingBtn = form.querySelector('#sk-report-abuse-form-working-btn');
        var errorEl = form.querySelector('.sk-popup-error');

        if (submitBtn) submitBtn.style.display = 'none';
        if (workingBtn) workingBtn.classList.remove('sk-hide');
        if (errorEl) errorEl.textContent = '';

        var formData = new FormData(form);
        var data = {};
        formData.forEach(function (v, k) { data[k] = v; });
        data.product_id = cfg.product_id;

        fetch(ajaxurl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=sk_report_abuse_submit_form&_ajax_nonce=' + cfg.nonce + '&form_data=' + encodeURIComponent(JSON.stringify(data)),
            credentials: 'same-origin'
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.success) {
                var box = modal.querySelector('div > div') || modal.firstElementChild;
                box.innerHTML = '<div style="text-align:center;padding:40px 20px;">' +
                    '<i class="fas fa-check-circle" style="font-size:48px;color:#16a34a;margin-bottom:16px;display:block;"></i>' +
                    '<p>' + (res.data.message || 'Danke für deine Meldung.') + '</p></div>';
                setTimeout(closeModal, 3000);
            } else {
                if (errorEl) errorEl.textContent = res.data.message || 'Fehler beim Senden.';
                if (submitBtn) submitBtn.style.display = '';
                if (workingBtn) workingBtn.classList.add('sk-hide');
            }
        })
        .catch(function () {
            if (errorEl) errorEl.textContent = 'Netzwerkfehler.';
            if (submitBtn) submitBtn.style.display = '';
            if (workingBtn) workingBtn.classList.add('sk-hide');
        });
    }
})();
