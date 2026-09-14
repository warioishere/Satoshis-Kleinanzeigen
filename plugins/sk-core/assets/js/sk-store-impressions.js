/**
 * Pictures on the vendor page, enlarged on click.
 *
 * Reuses the overlay the feed already brings along, so both places look
 * the same and there is only one set of styles to maintain.
 */
(function () {
    'use strict';

    function close() {
        var box = document.querySelector('.sk-feed-lightbox');

        if (box) {
            box.remove();
        }
    }

    document.addEventListener('click', function (e) {
        var img = e.target.closest && e.target.closest('.sk-store-impressions img');

        if (img) {
            e.preventDefault();
            close();

            var box = document.createElement('div');
            box.className = 'sk-feed-lightbox';
            // The tile is a crop; the overlay shows the picture whole.
            box.innerHTML = '<img alt=""><button type="button" class="sk-feed-lightbox-close" aria-label="Schliessen">&times;</button>';
            box.querySelector('img').src = img.getAttribute('data-full') || img.currentSrc || img.src;

            document.body.appendChild(box);
            box.querySelector('.sk-feed-lightbox-close').focus();

            return;
        }

        var open = e.target.closest && e.target.closest('.sk-feed-lightbox');

        // The picture itself keeps the overlay open, everything else closes it.
        if (open && ( e.target === open || e.target.classList.contains('sk-feed-lightbox-close') )) {
            close();
        }
    });

    document.addEventListener('keydown', function (e) {
        if ('Escape' === e.key) {
            close();
        }
    });
})();
