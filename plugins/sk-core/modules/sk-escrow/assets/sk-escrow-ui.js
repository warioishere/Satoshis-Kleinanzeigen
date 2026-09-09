/*
 * Browser signing UI for the escrow: key generation at checkout and in the
 * Treuhand settings, escrow-address verification on order pages, and PSBT
 * signing into the existing upload forms. All cryptography lives in
 * assets/js/sk-escrow-signer.js (window.SKEscrowSigner); this file only
 * wires it to the markup rendered by the module's PHP.
 *
 * Every widget reads its context from the closest element carrying
 * data-descriptor / data-address / data-role, or from its own data-*.
 */
(function () {
    'use strict';

    var S = window.SKEscrowSigner;
    var L = window.weoSignerL10n || {};
    if (!S) { return; }

    function t(key, arg) {
        var s = L[key] || key;
        return arg === undefined ? s : s.replace('%s', arg);
    }

    function $(sel, root) { return (root || document).querySelector(sel); }
    function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

    function setStatus(el, msg, bad) {
        if (!el) { return; }
        el.textContent = msg;
        el.classList.toggle('weo-status-bad', !!bad);
    }

    function errMsg(e) { return String(e && e.message || e); }

    function context(el) {
        var c = el.closest('[data-descriptor]');
        if (!c) { return null; }
        var address = c.getAttribute('data-address') || '';
        var network = c.getAttribute('data-network') || S.networkOfAddress(address);
        var role = c.getAttribute('data-role') || '';
        var descriptor = c.getAttribute('data-descriptor') || '';
        var xpub = '';
        try {
            var d = S.parseDescriptor(descriptor);
            xpub = role === 'buyer' ? d.xpubs[0] : role === 'seller' ? d.xpubs[1] : '';
        } catch (e) { /* reported by verify */ }
        return { el: c, descriptor: descriptor, address: address, network: network, role: role, xpub: xpub };
    }

    function renderWords(list, words) {
        list.innerHTML = '';
        words.split(' ').forEach(function (w) {
            var li = document.createElement('li');
            li.textContent = w;
            list.appendChild(li);
        });
    }

    // ---- key generation (checkout: buyer, Treuhand settings: vendor) ----

    var pending = null; // { words, target } generated but not yet saved

    function keygenStart(box) {
        var panel = $('.weo-keygen-panel', box);
        pending = { words: S.generateMnemonic(), target: box.getAttribute('data-target') };
        renderWords($('.weo-words', box), pending.words);
        panel.hidden = false;
        setStatus($('.weo-keygen-status', box), '');
    }

    function keygenSave(box) {
        var status = $('.weo-keygen-status', box);
        var pw = $('.weo-keygen-pw', box).value;
        var pw2 = $('.weo-keygen-pw2', box).value;
        if (!pending) { return; }
        if (pw.length < 8) { setStatus(status, t('pwShort'), true); return; }
        if (pw !== pw2) { setStatus(status, t('pwMismatch'), true); return; }
        if (!$('.weo-keygen-ack', box).checked) { setStatus(status, t('ackMissing'), true); return; }
        var network = box.getAttribute('data-network') || 'main';
        var words = pending.words;
        setStatus(status, '…');
        S.createKey(words, pw, network).then(function (xpub) {
            var input = $(box.getAttribute('data-target'));
            if (input) {
                input.value = xpub;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
            try { localStorage.setItem('sk_escrow_last_xpub', xpub); } catch (e) { /* private mode */ }
            pending = null;
            $('.weo-words', box).innerHTML = '';
            $('.weo-keygen-pw', box).value = '';
            $('.weo-keygen-pw2', box).value = '';
            $('.weo-keygen-panel', box).hidden = true;
            setStatus(status, t('keySaved', xpub));
        }).catch(function (e) {
            setStatus(status, errMsg(e), true);
        });
    }

    /*
     * WooCommerce re-renders the payment box on every checkout update, which
     * throws away the generated words and the typed xpub. Put both back.
     */
    function keygenRestore() {
        $$('.weo-keygen').forEach(function (box) {
            var input = $(box.getAttribute('data-target'));
            if (pending && pending.target === box.getAttribute('data-target')) {
                renderWords($('.weo-words', box), pending.words);
                $('.weo-keygen-panel', box).hidden = false;
                return;
            }
            if (input && !input.value) {
                var last = '';
                try { last = localStorage.getItem('sk_escrow_last_xpub') || ''; } catch (e) { /* ignore */ }
                if (last && S.hasKey(last)) {
                    input.value = last;
                    setStatus($('.weo-keygen-status', box), t('keySaved', last));
                }
            }
        });
    }

    // ---- escrow address verification ----

    function verifyAll() {
        $$('.weo-verify').forEach(function (v) {
            var ctx = context(v);
            if (!ctx) { return; }
            var r = S.verifyEscrow(ctx.descriptor, ctx.address, ctx.xpub || null);
            var msg;
            if (r.error) {
                msg = t('verifyError', r.error);
            } else if (r.ok) {
                msg = t('verifyOk');
            } else {
                msg = t('verifyBad');
            }
            v.textContent = msg;
            v.classList.toggle('weo-verify--ok', !!r.ok);
            v.classList.toggle('weo-verify--bad', !r.ok);
            if (!r.ok) {
                // Never invite a deposit to an address the descriptor does not prove.
                $$('.weo-qr, #weo_copy', ctx.el).forEach(function (el) { el.hidden = true; });
            }
            var key = $('.weo-key-state', ctx.el);
            if (key && ctx.xpub) {
                key.textContent = S.hasKey(ctx.xpub) ? t('keyPresent') : t('keyMissing');
            }
        });
    }

    // ---- signing into the upload form ----

    /*
     * The freshly built PSBT is echoed after the order section (order page)
     * or once above all orders (Treuhand tab), so look inside the section
     * first and fall back to the page. A PSBT of another order cannot be
     * signed by mistake: the key is derived from this order's descriptor and
     * the signer refuses a script that does not contain it.
     */
    function psbtSource(ctx) {
        return $('.weo-psbt-source', ctx.el) || $('.weo-psbt-source');
    }

    function signOpen(btn) {
        var form = btn.closest('form');
        var ctx = context(btn);
        var panel = $('.weo-sign-panel', form);
        var status = $('.weo-sign-status', form);
        panel.hidden = false;
        if (!ctx || !ctx.xpub) { setStatus(status, t('verifyBad'), true); return; }
        var source = psbtSource(ctx);
        if (!source || !source.value.trim()) { setStatus(status, t('noPsbt'), true); return; }
        var summary;
        try {
            summary = S.psbtSummary(source.value, ctx.network);
        } catch (e) {
            setStatus(status, t('psbtBad', errMsg(e)), true);
            return;
        }
        var box = $('.weo-sign-summary', form);
        box.innerHTML = '';
        var h = document.createElement('p');
        h.textContent = t('summaryTitle');
        box.appendChild(h);
        var ul = document.createElement('ul');
        summary.outputs.forEach(function (o) {
            var li = document.createElement('li');
            li.textContent = o.address + ' – ' + o.sats.toLocaleString() + ' ' + t('sats');
            ul.appendChild(li);
        });
        box.appendChild(ul);
        var f = document.createElement('p');
        f.textContent = t('fee', summary.feeSat.toLocaleString());
        box.appendChild(f);
        setStatus(status, S.hasKey(ctx.xpub) ? '' : t('keyMissing'), !S.hasKey(ctx.xpub));
    }

    function signConfirm(btn) {
        var form = btn.closest('form');
        var ctx = context(btn);
        var status = $('.weo-sign-status', form);
        var pwInput = $('.weo-sign-pw', form);
        var source = ctx && psbtSource(ctx);
        var target = $('textarea.weo-psbt', form);
        if (!ctx || !ctx.xpub || !source || !target) { setStatus(status, t('noPsbt'), true); return; }
        setStatus(status, '…');
        S.signStored(source.value, ctx.xpub, pwInput.value, ctx.descriptor, ctx.network).then(function (signedPsbt) {
            pwInput.value = '';
            target.value = signedPsbt;
            setStatus(status, t('signed'));
        }).catch(function (e) {
            var m = errMsg(e);
            setStatus(status, m === 'wrong password' ? t('wrongPw') : t('signError', m), true);
        });
    }

    // ---- show / import the words ----

    function keyboxOpen(btn, mode) {
        var box = btn.closest('.weo-keybox');
        box.setAttribute('data-mode', mode);
        $('.weo-keybox-panel', box).hidden = false;
        $('.weo-keybox-words', box).hidden = mode !== 'import';
        $('.weo-keybox-list', box).innerHTML = '';
        setStatus($('.weo-keybox-status', box), '');
    }

    function keyboxGo(btn) {
        var box = btn.closest('.weo-keybox');
        var ctx = context(box);
        var xpub = box.getAttribute('data-xpub') || (ctx && ctx.xpub) || '';
        var network = box.getAttribute('data-network') || (ctx && ctx.network) || 'main';
        var status = $('.weo-keybox-status', box);
        var pw = $('.weo-keybox-pw', box);
        if (!xpub) { setStatus(status, t('keyMissing'), true); return; }
        if (box.getAttribute('data-mode') === 'import') {
            var words = $('.weo-keybox-words', box).value;
            if (!S.validateMnemonic(words)) { setStatus(status, t('invalidWords'), true); return; }
            if (pw.value.length < 8) { setStatus(status, t('pwShort'), true); return; }
            S.importKey(words, pw.value, xpub, network).then(function () {
                pw.value = '';
                $('.weo-keybox-words', box).value = '';
                setStatus(status, t('importOk'));
                verifyAll();
            }).catch(function (e) {
                setStatus(status, errMsg(e).indexOf('do not match') !== -1 ? t('importBad') : errMsg(e), true);
            });
            return;
        }
        S.revealWords(xpub, pw.value).then(function (words) {
            pw.value = '';
            renderWords($('.weo-keybox-list', box), words);
            setStatus(status, '');
        }).catch(function (e) {
            setStatus(status, errMsg(e) === 'wrong password' ? t('wrongPw') : errMsg(e), true);
        });
    }

    document.addEventListener('click', function (ev) {
        var b = ev.target.closest('button');
        if (!b) { return; }
        if (b.classList.contains('weo-keygen-start')) { keygenStart(b.closest('.weo-keygen')); }
        else if (b.classList.contains('weo-keygen-save')) { keygenSave(b.closest('.weo-keygen')); }
        else if (b.classList.contains('weo-keygen-cancel')) { pending = null; $('.weo-keygen-panel', b.closest('.weo-keygen')).hidden = true; }
        else if (b.classList.contains('weo-sign-browser')) { signOpen(b); }
        else if (b.classList.contains('weo-sign-confirm')) { signConfirm(b); }
        else if (b.classList.contains('weo-words-show')) { keyboxOpen(b, 'show'); }
        else if (b.classList.contains('weo-words-import')) { keyboxOpen(b, 'import'); }
        else if (b.classList.contains('weo-keybox-go')) { keyboxGo(b); }
        else { return; }
        ev.preventDefault();
    });

    function init() {
        keygenRestore();
        verifyAll();
        $$('.weo-keybox[data-xpub]').forEach(function (box) {
            var state = $('.weo-key-state', box);
            if (state) { state.textContent = S.hasKey(box.getAttribute('data-xpub')) ? t('keyPresent') : t('keyMissing'); }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    if (window.jQuery) {
        window.jQuery(document.body).on('updated_checkout', keygenRestore);
    }
})();
