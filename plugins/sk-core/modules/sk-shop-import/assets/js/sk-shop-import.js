/**
 * Selection of the listings to import.
 *
 * Keeps the counter and button label in sync with the actual selection —
 * otherwise the button would promise "import 28 listings" even though the
 * package only allows six and the next step would be a rejection.
 */
(function () {
    'use strict';

    var form = document.querySelector('.sk-import-pick');
    if (!form) return;

    var boxes = Array.prototype.slice.call(form.querySelectorAll('input[name="sk_pick[]"]'));
    var counter = document.getElementById('sk-import-count');
    var button = document.getElementById('sk-import-submit');
    var limit = parseInt(form.getAttribute('data-limit') || '0', 10);
    var labelOne = form.getAttribute('data-label-one') || '%d Inserat importieren';
    var labelMany = form.getAttribute('data-label-many') || '%d Inserate importieren';
    var overLabel = form.getAttribute('data-label-over') || '';

    function selected() {
        return boxes.filter(function (b) { return b.checked; }).length;
    }

    function update() {
        var n = selected();

        if (counter) counter.textContent = String(n);

        if (button) {
            var over = limit > 0 && n > limit;
            button.textContent = over && overLabel
                ? overLabel
                : (n === 1 ? labelOne : labelMany).replace('%d', String(n));
            button.disabled = n === 0;
            button.classList.toggle('sk-btn-default', over);
            button.classList.toggle('sk-btn-theme', !over);
        }

        form.classList.toggle('is-over-limit', limit > 0 && n > limit);
    }

    boxes.forEach(function (b) { b.addEventListener('change', update); });

    var all = document.getElementById('sk-import-all');
    if (all) {
        all.addEventListener('change', function () {
            boxes.forEach(function (b) { b.checked = all.checked; });
            update();
        });
    }

    var upTo = document.getElementById('sk-import-uptolimit');
    if (upTo) {
        upTo.addEventListener('click', function (e) {
            e.preventDefault();
            boxes.forEach(function (b, i) { b.checked = limit > 0 && i < limit; });
            update();
        });
    }

    update();

    /*
     * During the import, nothing visibly happens for minutes: the form is
     * submitted, and the server creates listings and fetches images. Without
     * feedback, the seller clicks a second time.
     */
    form.addEventListener('submit', function () {
        var box = document.getElementById('sk-import-progress');
        var text = document.getElementById('sk-import-progress-text');
        var n = selected();

        if (text) {
            text.textContent = n === 1
                ? '1 Inserat wird angelegt.'
                : n + ' Inserate werden angelegt.';
        }

        if (box) box.classList.add('is-visible');
        if (button) button.disabled = true;
    });
}());

/**
 * Modal on the subscription page: what the catalog import can do.
 *
 * A separate function because the block above doesn't even run on the
 * subscription page — there is no selection form there. Clicks are handled
 * via document so the order of script and markup doesn't matter.
 */
(function () {
    'use strict';

    var opener = null;

    function box() {
        return document.getElementById('sk-pack-info');
    }

    function close() {
        var el = box();
        if (!el || !el.classList.contains('is-visible')) return;
        el.classList.remove('is-visible');
        if (opener) {
            opener.focus();
            opener = null;
        }
    }

    document.addEventListener('click', function (e) {
        if (!e.target || !e.target.closest) return;

        var trigger = e.target.closest('[data-sk-pack-info]');
        if (trigger) {
            var el = box();
            if (!el) return;
            e.preventDefault();
            opener = trigger;
            el.classList.add('is-visible');
            var button = el.querySelector('.sk-pack-info__close');
            if (button) button.focus();
            return;
        }

        if (e.target.closest('.sk-pack-info__close') || e.target.closest('.sk-pack-info__backdrop')) {
            e.preventDefault();
            close();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') close();
    });
}());

/**
 * Process the import in batches.
 *
 * The job lives on the server, this block just fetches one batch after
 * another. That's why a closed window survives the import: the page shows
 * the same state on the next visit and continues from there.
 *
 * The server determines the batch size based on measured duration — an
 * item with five images takes a multiple of the time of one without.
 */
(function () {
    'use strict';

    var bar = document.getElementById('sk-import-bar');
    if (!bar) return;

    var fill = document.getElementById('sk-import-bar-fill');
    var text = document.getElementById('sk-import-bar-text');
    var error = document.getElementById('sk-import-bar-error');
    var button = document.getElementById('sk-import-bar-start');

    var total = parseInt(bar.getAttribute('data-total') || '1', 10);
    var running = false;

    function paint(done) {
        var percent = total > 0 ? Math.round((done / total) * 100) : 0;
        if (fill) fill.style.width = percent + '%';
        bar.setAttribute('aria-valuenow', String(percent));
        if (text) {
            text.textContent = done + ' von ' + total + ' Inseraten angelegt';
        }
    }

    function fail(message) {
        running = false;
        if (error) {
            error.textContent = message;
            error.style.display = '';
        }
        if (button) {
            button.disabled = false;
            button.style.display = '';
            button.textContent = 'Erneut versuchen';
        }
    }

    function next() {
        var body = new URLSearchParams();
        body.append('action', 'sk_shop_import_batch');
        body.append('nonce', bar.getAttribute('data-nonce') || '');

        fetch(bar.getAttribute('data-ajaxurl'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || !res.success) {
                    fail((res && res.data && res.data.message) || 'Der Import ist abgebrochen.');
                    return;
                }

                total = res.data.total || total;
                paint(res.data.done);

                if (res.data.fertig) {
                    window.location.href = res.data.weiter;
                    return;
                }

                next();
            })
            .catch(function () {
                fail('Verbindungsfehler. Der Stand ist gespeichert — du kannst fortsetzen.');
            });
    }

    function start() {
        if (running) return;
        running = true;
        if (error) error.style.display = 'none';
        // Hide it while running: the button is only the fallback door, and a
        // disabled button turns purple in this theme.
        if (button) button.style.display = 'none';
        next();
    }

    if (button) button.addEventListener('click', start);

    start();
}());
