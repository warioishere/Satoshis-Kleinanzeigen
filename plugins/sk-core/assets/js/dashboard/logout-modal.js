/**
 * Logout Modal — open on .sk-logout-trigger click, close on cancel/backdrop/Escape.
 */
(function () {
    'use strict';

    var modal = document.getElementById('sk-logout-modal');
    if (!modal) return;

    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('.sk-logout-trigger');
        if (trigger) {
            e.preventDefault();
            modal.style.display = 'flex';
        }
    });

    var cancel = modal.querySelector('.sk-logout-cancel');
    if (cancel) cancel.addEventListener('click', function () { modal.style.display = 'none'; });

    var backdrop = modal.querySelector('.sk-logout-modal-backdrop');
    if (backdrop) backdrop.addEventListener('click', function () { modal.style.display = 'none'; });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.style.display === 'flex') {
            modal.style.display = 'none';
        }
    });
})();
