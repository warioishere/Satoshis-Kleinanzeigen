/**
 * Product-edit-page JS extras:
 *  - Remove Yoast "brand" select (we don't expose brands to vendors)
 *  - Reorder sidebar: P2P shipping + Sats converter above the description
 *  - Add a hint below the map picker
 *  - Sats converter: Fiat → Sats via Kraken API
 */

/* ── Yoast brand field removal ─────────────────────────────────────────── */
(function () {
    'use strict';

    var observers = [];

    function disconnectObserver(observer) {
        observer.disconnect();
        for (var i = observers.length - 1; i >= 0; i--) {
            if (observers[i] === observer) observers.splice(i, 1);
        }
    }

    function scheduleCleanup(observer, timeout) {
        var stopAt = performance.now() + timeout;
        (function loop() {
            if (observers.indexOf(observer) === -1) return;
            if (performance.now() > stopAt) { disconnectObserver(observer); return; }
            requestAnimationFrame(loop);
        })();
    }

    function findBrandSelect(scope) {
        if (!scope) return null;
        if (scope.nodeType === 1 && scope.matches && scope.matches('#product_brand, #product_brand_edit')) return scope;
        if (scope.querySelector) return scope.querySelector('#product_brand, #product_brand_edit');
        return null;
    }

    function removeBrandField(scope) {
        var root = scope || document;
        var removed = false;
        var select = findBrandSelect(root);
        while (select) {
            var group   = select.closest ? select.closest('.sk-form-group') : null;
            var select2 = select.nextElementSibling;
            if (select2 && select2.classList && select2.classList.contains('select2')) select2.remove();
            if (group) { group.remove(); } else { select.style.display = 'none'; }
            if (select.id) {
                var label = root.querySelector ? root.querySelector('label[for="' + select.id + '"]') : null;
                if (!label) label = document.querySelector('label[for="' + select.id + '"]');
                if (label) label.remove();
            }
            removed = true;
            select = findBrandSelect(root);
        }
        return removed;
    }

    function observeTarget(selector) {
        var target = document.querySelector(selector);
        if (!target) return;
        removeBrandField(target);
        var observer = new MutationObserver(function (mutations) {
            var updated = false;
            for (var i = 0; i < mutations.length; i++) {
                var nodes = mutations[i].addedNodes || [];
                for (var j = 0; j < nodes.length; j++) {
                    var node = nodes[j];
                    if (node.nodeType !== 1) continue;
                    updated = removeBrandField(node) || updated;
                }
            }
            if (updated) { disconnectObserver(observer); removeBrandField(document); }
        });
        observers.push(observer);
        observer.observe(target, { childList: true, subtree: true });
        scheduleCleanup(observer, 1500);
    }

    function init() {
        while (observers.length) disconnectObserver(observers.pop());
        removeBrandField(document);
        observeTarget('#sk-product-edit-form');
        observeTarget('.product-edit-new-container');
    }

    document.addEventListener('DOMContentLoaded', init);
})();

/* ── Reorder: P2P shipping + Sats converter above short description ────── */
document.addEventListener('DOMContentLoaded', function () {
    var converterBox = document.querySelector('[data-togglehandler="sats_converter_box"]');
    var shippingBox  = document.querySelector('[data-togglehandler="p2p_shipping_box"]');
    converterBox = converterBox && converterBox.closest('.sk-edit-row');
    shippingBox  = shippingBox  && shippingBox.closest('.sk-edit-row');

    var excerptLabel = document.querySelector('label[for="post_excerpt"]');
    if (!excerptLabel) return;
    var target = excerptLabel.closest('.sk-product-short-description');
    if (!target || !target.parentElement) return;

    if (shippingBox)  target.parentElement.insertBefore(shippingBox,  target);
    if (converterBox) target.parentElement.insertBefore(converterBox, target);
});

/* ── Map hint paragraph ────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', function () {
    var label = document.querySelector('label[for="setting_map"]');
    if (!label) return;
    var targetDiv = label.nextElementSibling;
    if (!targetDiv) return;
    var hint = document.createElement('p');
    hint.innerText = 'Klicke auf die Lupe oben auf der Karte und gib deine Adresse ein, um zu zeigen, wo du dich befindest. Das hilft anderen, eine mögliche Abholung besser einschätzen zu können. Du kannst natürlich auch nur deinen groben Standort angeben, wie z.B. nur Köln.';
    hint.style.marginTop = '10px';
    hint.style.fontSize  = '14px';
    hint.style.color     = '#ccc';
    targetDiv.appendChild(hint);
});

/* ── Sats Converter (Fiat → Sats via Kraken public ticker) ─────────────── */
(function () {
    'use strict';

    function moveConverterBox(root) {
        var box = document.getElementById('sats-converter-box');
        if (!box) return;
        var scope = root || document;
        var shippingEl = scope.querySelector('[data-togglehandler="p2p_shipping_box"]');
        var targetRow  = shippingEl && shippingEl.closest ? shippingEl.closest('.sk-edit-row') : null;
        if (!targetRow || !targetRow.parentNode) return;
        if (box.nextElementSibling !== targetRow) {
            targetRow.parentNode.insertBefore(box, targetRow);
        }
    }

    async function convertFiatToSats() {
        var fiatInput = document.getElementById('fiat_to_sats');
        var currSel   = document.getElementById('fiat_currency');
        var resultBox = document.getElementById('sats_result');
        if (!fiatInput || !currSel || !resultBox) return;

        var fiat = parseFloat(fiatInput.value);
        var currency = (currSel.value || 'eur').toUpperCase();
        if (!fiat || fiat <= 0) {
            resultBox.textContent = 'Bitte einen Betrag eingeben.';
            return;
        }

        try {
            var pair = 'XBT' + currency;
            var res  = await fetch('https://api.kraken.com/0/public/Ticker?pair=' + pair);
            var data = await res.json();
            if (!data.result || Object.keys(data.result).length === 0) {
                throw new Error('Kein Kurs gefunden');
            }
            var key   = Object.keys(data.result)[0];
            var price = parseFloat(data.result[key].c[0]);
            if (!price || price <= 0) throw new Error('Ungültiger Kurs');

            var sats = Math.round((fiat / price) * 100000000);
            resultBox.textContent = '≈ ' + sats.toLocaleString() + ' Sats';

            var satsInput = document.querySelector('input[name="regular_price"]');
            if (satsInput) satsInput.value = sats;
        } catch (e) {
            resultBox.textContent = 'Fehler beim Abrufen des Wechselkurses.';
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        moveConverterBox(document);
        var btn = document.getElementById('convert_to_sats');
        if (btn) btn.addEventListener('click', convertFiatToSats);
        var watch = document.querySelector('.sk-dashboard-wrap') || document;
        new MutationObserver(function () { moveConverterBox(document); })
            .observe(watch, { childList: true, subtree: true });
    });
})();
