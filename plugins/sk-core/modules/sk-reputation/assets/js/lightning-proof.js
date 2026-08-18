/**
 * Public Lightning proof page: copy buttons, bolt11 expand/collapse and the
 * fiat equivalent next to each sats amount.
 */
(function() {
    /* ── Kopieren ── */
    document.querySelectorAll('.skl-copy-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var text = this.getAttribute('data-copy');
            if (navigator.clipboard && text) {
                navigator.clipboard.writeText(text);
                var icon = this.querySelector('i');
                if (icon) icon.className = 'fas fa-check';
                var el = this;
                setTimeout(function() {
                    var i = el.querySelector('i');
                    if (i) i.className = 'fas fa-copy';
                }, 2000);
            }
        });
    });

    /* ── bolt11 aufklappen/zuklappen ── */
    document.querySelectorAll('.skl-bolt11-proof').forEach(function(el) {
        el.addEventListener('click', function() {
            var expanded = this.getAttribute('data-expanded') === 'true';
            this.style.maxHeight = expanded ? '40px' : 'none';
            this.setAttribute('data-expanded', expanded ? 'false' : 'true');
        });
    });

    /* ── Fiat-Gegenwert anzeigen ── */
    var lang = (navigator.language || (navigator.languages && navigator.languages[0]) || '').toLowerCase();
    var currency = (lang === 'de-ch' || lang === 'fr-ch' || lang === 'it-ch' || lang === 'rm-ch') ? 'CHF' : 'EUR';

    fetch('https://blockchain.info/ticker')
        .then(function(r) { return r.json(); })
        .then(function(prices) {
            var rate = prices && prices[currency] && prices[currency].last;
            if (!rate) return;

            document.querySelectorAll('.skl-proof-entry').forEach(function(entry) {
                var satsEl = entry.querySelector('[style*="color:#f7931a"][style*="font-size:16px"]');
                if (!satsEl) return;
                var text = satsEl.textContent.replace(/[^0-9]/g, '');
                var sats = parseInt(text, 10);
                if (isNaN(sats)) return;

                var fiat = (sats * rate / 100000000).toFixed(2);
                var formatted = Number(fiat).toLocaleString('de-DE', { minimumFractionDigits: 2 });
                satsEl.insertAdjacentHTML('afterend',
                    '<span style="margin-left:8px;font-size:13px;font-weight:400;color:#5a6a7e;">≈ ' + formatted + ' ' + currency + '</span>'
                );
            });
        })
        .catch(function() {});
})();
