/**
 * Donation modal after deleting a listing.
 *
 * All clicks go through document, not through references to the modal:
 * the script is output in the footer before the modal, so a getElementById
 * at load time would return null and the handler would be silently dead.
 *
 * Payment goes through the same BTCPay dialog as subscriptions and boosts.
 * If it fails, payUrl leads to the normal payment page — never a dead click.
 */
(function () {
    'use strict';

    var busy = false;

    function modal() {
        return document.getElementById('sk-donate-modal');
    }

    function close() {
        var el = modal();
        if (el) el.classList.remove('is-visible');
    }

    /**
     * Fade in after the page has settled. Without the delay, the modal
     * appears at the same moment as the finished dashboard and looks
     * like a flash.
     */
    function reveal() {
        var el = modal();
        if (!el) return;
        requestAnimationFrame(function () {
            requestAnimationFrame(function () { el.classList.add('is-visible'); });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { setTimeout(reveal, 550); });
    } else {
        setTimeout(reveal, 550);
    }

    function openBtcpay(data) {
        if (!window.btcpay || !data.invoiceId) {
            if (data.payUrl) window.location.href = data.payUrl;
            return;
        }
        close();
        window.btcpay.setApiUrlPrefix(data.btcpayUrl);
        window.btcpay.showInvoice(data.invoiceId);
        window.btcpay.onModalReceiveMessage(function (event) {
            if (!event || typeof event.data !== 'object') return;
            var status = (event.data.status || '').toLowerCase();
            if (['complete', 'paid', 'processing', 'settled'].indexOf(status) !== -1) {
                window.location.href = data.orderCompleteLink || window.location.pathname;
            }
        });
    }

    function donate(sats, button) {
        if (busy || !window.skDonate) return;
        busy = true;
        if (button) button.disabled = true;

        var body = new URLSearchParams();
        body.append('action', window.skDonate.action);
        body.append('nonce', window.skDonate.nonce);
        body.append('sats', sats);

        fetch(window.skDonate.ajaxurl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                busy = false;
                if (button) button.disabled = false;
                if (res && res.success) {
                    openBtcpay(res.data || {});
                } else {
                    var msg = (res && res.data && res.data.message) ? res.data.message : 'Fehler beim Zahlungsvorgang.';
                    window.alert(msg);
                }
            })
            .catch(function () {
                busy = false;
                if (button) button.disabled = false;
                window.alert('Verbindungsfehler. Bitte versuche es erneut.');
            });
    }

    document.addEventListener('click', function (e) {
        if (!e.target || !e.target.closest) return;

        if (e.target.closest('.sk-donate-modal-close') || e.target.closest('.sk-donate-modal-backdrop')) {
            e.preventDefault();
            close();
            return;
        }

        var amount = e.target.closest('.sk-donate-modal-amount');
        if (amount) {
            e.preventDefault();
            donate(amount.value, amount);
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') close();
    });
}());
