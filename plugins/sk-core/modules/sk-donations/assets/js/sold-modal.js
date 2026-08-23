/**
 * Spenden-Modal nach dem Loeschen eines Inserats.
 *
 * Alle Klicks laufen ueber document, nicht ueber Referenzen auf das Modal:
 * Das Skript wird im Footer vor dem Modal ausgegeben, ein getElementById beim
 * Laden liefert deshalb null und die Behandlung waere still tot.
 *
 * Bezahlt wird ueber denselben BTCPay-Dialog wie Abos und Boosts. Faellt er
 * aus, fuehrt payUrl auf die normale Bezahlseite — kein Klick ins Leere.
 */
(function () {
    'use strict';

    var busy = false;

    function modal() {
        return document.getElementById('sk-donate-modal');
    }

    function close() {
        var el = modal();
        if (el) el.style.display = 'none';
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
