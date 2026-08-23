/**
 * Spenden-Modal nach dem Loeschen eines Inserats.
 * Schliesst ueber "Jetzt nicht", Klick auf den Hintergrund oder Escape.
 */
(function () {
    'use strict';

    var modal = document.getElementById('sk-donate-modal');
    if (!modal) return;

    function close() {
        modal.style.display = 'none';
    }

    var dismiss = modal.querySelector('.sk-donate-modal-close');
    if (dismiss) dismiss.addEventListener('click', close);

    var backdrop = modal.querySelector('.sk-donate-modal-backdrop');
    if (backdrop) backdrop.addEventListener('click', close);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') close();
    });
})();
