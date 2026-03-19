/**
 * SK Pro Admin — Module toggle + shipment accordion (vanilla JS)
 */
(function () {
    'use strict';

    // Module toggle
    document.addEventListener('change', function (e) {
        var input = e.target;
        if (!input.classList.contains('sk-toggle-module')) return;

        var li = input.closest('li');
        var card = input.closest('.plugin-card');
        if (!li || !card) return;

        var type = input.checked ? 'activate' : 'deactivate';
        var label = input.checked ? (window.sk_admin && sk_admin.activating || 'Activating...') : (window.sk_admin && sk_admin.deactivating || 'Deactivating...');

        // Show overlay
        var overlay = document.createElement('div');
        overlay.className = 'sk-module-overlay';
        overlay.textContent = label;
        overlay.style.cssText = 'position:absolute;inset:0;background:rgba(34,34,34,.7);color:#fff;display:flex;align-items:center;justify-content:center;font-size:16px;z-index:10;border-radius:4px;';
        card.style.position = 'relative';
        card.appendChild(overlay);

        fetch(sk_admin.ajaxurl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=sk-toggle-module&type=' + type + '&module=' + encodeURIComponent(li.dataset.module) + '&nonce=' + sk_admin.nonce,
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); }).then(function (data) {
            overlay.textContent = data.data || (type === 'activate' ? 'Activated' : 'Deactivated');
            setTimeout(function () { overlay.remove(); }, 1000);
        }).catch(function () {
            overlay.textContent = 'Error';
            setTimeout(function () { overlay.remove(); }, 1500);
        });
    });

    // Shipment accordion
    document.addEventListener('click', function (e) {
        var toggle = e.target.closest('.shipment-item-details-tab-toggle');
        if (toggle) {
            e.preventDefault();
            var id = toggle.dataset.shipment_id;
            document.querySelectorAll('.shipment_body_' + id + ',.shipment_footer_' + id + ',.shipment_notes_area_' + id).forEach(function (el) {
                el.style.display = el.style.display === 'none' ? '' : 'none';
            });
            var span = toggle.querySelector('span');
            if (span) {
                span.classList.toggle('dashicons-arrow-down-alt2');
                span.classList.toggle('dashicons-arrow-up-alt2');
            }
            return;
        }

        var notesToggle = e.target.closest('.shipment-notes-details-tab-toggle');
        if (notesToggle) {
            e.preventDefault();
            var nid = notesToggle.dataset.shipment_id;
            var area = document.querySelector('.shipment-list-notes-inner-area' + nid);
            if (area) area.style.display = area.style.display === 'none' ? '' : 'none';
            var s = notesToggle.querySelector('span');
            if (s) {
                s.classList.toggle('dashicons-arrow-down-alt2');
                s.classList.toggle('dashicons-arrow-up-alt2');
            }
        }
    });
})();
