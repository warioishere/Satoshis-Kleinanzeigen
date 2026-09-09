/*
 * Browser side of the escrow: key generation (product page and accept
 * step), escrow-address verification, deposit polling, PSBT signing and
 * the show/import controls for the 12 words. All cryptography lives in
 * assets/js/sk-escrow-signer.js (window.SKEscrowSigner); this file only
 * wires it to the markup rendered by the module's PHP and to its AJAX
 * actions.
 *
 * Every widget reads its context from the closest .weo-escrow-card
 * (data-hash, data-role, data-descriptor, data-address, data-status).
 */
(function () {
    'use strict';

    var S = window.SKEscrowSigner;
    var cfg = window.weoSigner || {};
    var L = cfg.l10n || {};
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

    function post(action, data) {
        var body = new URLSearchParams();
        body.set('action', action);
        body.set('nonce', cfg.nonce || '');
        Object.keys(data || {}).forEach(function (k) { body.set(k, data[k]); });
        return fetch(cfg.ajaxurl, { method: 'POST', credentials: 'same-origin', body: body })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || !res.success) {
                    throw new Error(res && res.data && res.data.message ? res.data.message : t('netError'));
                }
                return res.data || {};
            });
    }

    function context(el) {
        var c = el.closest('.weo-escrow-card');
        if (!c) { return null; }
        var address = c.getAttribute('data-address') || '';
        var network = c.getAttribute('data-network') || S.networkOfAddress(address);
        var role = c.getAttribute('data-role') || '';
        var descriptor = c.getAttribute('data-descriptor') || '';
        var xpub = '';
        try {
            if (descriptor) {
                var d = S.parseDescriptor(descriptor);
                xpub = role === 'buyer' ? d.xpubs[0] : role === 'seller' ? d.xpubs[1] : '';
            }
        } catch (e) { /* reported by verify */ }
        return { el: c, hash: c.getAttribute('data-hash') || '', descriptor: descriptor, address: address, network: network, role: role, xpub: xpub, status: c.getAttribute('data-status') || '' };
    }

    function renderWords(list, words) {
        list.innerHTML = '';
        words.split(' ').forEach(function (w) {
            var li = document.createElement('li');
            li.textContent = w;
            list.appendChild(li);
        });
    }

    // ---- key generation (product page: buyer, accept step: seller) ----

    var pending = null; // { words, target } generated but not yet saved

    function keygenStart(box) {
        pending = { words: S.generateMnemonic(), target: box.getAttribute('data-target') };
        renderWords($('.weo-words', box), pending.words);
        $('.weo-keygen-panel', box).hidden = false;
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
        setStatus(status, t('working'));
        S.createKey(words, pw, network).then(function (xpub) {
            var input = $(box.getAttribute('data-target'));
            if (input) {
                input.value = xpub;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
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

    // ---- escrow address verification + key presence ----

    function verifyAll() {
        $$('.weo-escrow-card').forEach(function (card) {
            var ctx = context(card);
            if (!ctx || !ctx.descriptor) { return; }
            var v = $('.weo-verify', card);
            if (v && ctx.address) {
                var r = S.verifyEscrow(ctx.descriptor, ctx.address, ctx.xpub || null);
                v.textContent = r.error ? t('verifyError', r.error) : r.ok ? t('verifyOk') : t('verifyBad');
                v.classList.toggle('weo-verify--ok', !!r.ok);
                v.classList.toggle('weo-verify--bad', !r.ok);
                if (!r.ok) {
                    // Never invite a deposit to an address the descriptor does not prove.
                    $$('.weo-qr-box', card).forEach(function (el) { el.hidden = true; });
                }
            }
            var key = $('.weo-key-state', card);
            if (key && ctx.xpub) {
                key.textContent = S.hasKey(ctx.xpub) ? t('keyPresent') : t('keyMissing');
            }
        });
    }

    // ---- request handling: accept / decline / cancel ----

    function accept(btn) {
        var ctx = context(btn);
        var box = btn.closest('.weo-accept');
        var msg = $('.weo-msg', ctx.el);
        var xpub = $('.weo-accept-xpub', box).value.trim();
        var payout = $('.weo-accept-payout', box).value.trim();
        if (!/^bc1[0-9a-z]{8,87}$/i.test(payout)) { setStatus(msg, t('addrBad'), true); return; }
        if (!xpub) { setStatus(msg, t('xpubMissing'), true); return; }
        btn.disabled = true;
        setStatus(msg, t('working'));
        post('weo_accept', { hash: ctx.hash, xpub: xpub, payout_address: payout }).then(function (d) {
            setStatus(msg, d.message || '');
            window.location.reload();
        }).catch(function (e) {
            btn.disabled = false;
            setStatus(msg, errMsg(e), true);
        });
    }

    function simple(btn, action, confirmKey, extra) {
        var ctx = context(btn);
        var msg = $('.weo-msg', ctx.el);
        if (confirmKey && !window.confirm(t(confirmKey))) { return; }
        btn.disabled = true;
        post(action, Object.assign({ hash: ctx.hash }, extra || {})).then(function () {
            window.location.reload();
        }).catch(function (e) {
            btn.disabled = false;
            setStatus(msg, errMsg(e), true);
        });
    }

    // ---- deposit polling ----

    function poll(card) {
        var ctx = context(card);
        var out = $('.weo-poll', card);
        if (!ctx || !out) { return; }
        post('weo_status', { hash: ctx.hash }).then(function (d) {
            if (d.status !== ctx.status) {
                window.location.reload();
                return;
            }
            var text = d.label || '';
            if (d.funded_sat) {
                text += ' · ' + d.funded_sat.toLocaleString() + ' ' + t('sats') + ' (' + d.confirmations + ' conf)';
            }
            setStatus(out, text);
        }).catch(function () { /* next round */ });
    }

    // ---- signing ----

    function signStart(btn) {
        var ctx = context(btn);
        var type = btn.getAttribute('data-type') || 'payout';
        var panel = $('.weo-sign-panel', ctx.el);
        var status = $('.weo-sign-status', ctx.el);
        var confirmKey = btn.getAttribute('data-confirm');
        if (confirmKey && !window.confirm(t(confirmKey === 'release' ? 'confirmRelease' : 'confirmRefund'))) { return; }
        panel.hidden = false;
        panel.setAttribute('data-type', type);
        setStatus(status, t('working'));
        post('weo_psbt', { hash: ctx.hash, type: type }).then(function (d) {
            $('.weo-psbt-source', panel).value = d.psbt;
            var summary = S.psbtSummary(d.psbt, ctx.network);
            var box = $('.weo-sign-summary', panel);
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
        }).catch(function (e) {
            setStatus(status, errMsg(e), true);
        });
    }

    function submitPartial(ctx, panel, signedPsbt) {
        var status = $('.weo-sign-status', panel);
        var type = panel.getAttribute('data-type') || 'payout';
        setStatus(status, t('working'));
        return post('weo_partial', { hash: ctx.hash, type: type, psbt: signedPsbt }).then(function (d) {
            setStatus(status, d.message || t('signed'));
            window.setTimeout(function () { window.location.reload(); }, 1200);
        }).catch(function (e) {
            setStatus(status, errMsg(e), true);
        });
    }

    function signConfirm(btn) {
        var ctx = context(btn);
        var panel = btn.closest('.weo-sign-panel');
        var status = $('.weo-sign-status', panel);
        var pwInput = $('.weo-sign-pw', panel);
        var source = $('.weo-psbt-source', panel).value;
        if (!ctx || !ctx.xpub || !source) { setStatus(status, t('psbtBad', ''), true); return; }
        setStatus(status, t('working'));
        S.signStored(source, ctx.xpub, pwInput.value, ctx.descriptor, ctx.network).then(function (signedPsbt) {
            pwInput.value = '';
            return submitPartial(ctx, panel, signedPsbt);
        }).catch(function (e) {
            var m = errMsg(e);
            setStatus(status, m === 'wrong password' ? t('wrongPw') : t('signError', m), true);
        });
    }

    function signUpload(btn) {
        var ctx = context(btn);
        var panel = btn.closest('.weo-sign-panel');
        var signedPsbt = $('.weo-psbt-signed', panel).value.trim();
        if (!/^[A-Za-z0-9+/]+={0,2}$/.test(signedPsbt)) { setStatus($('.weo-sign-status', panel), t('psbtBad', 'Base64'), true); return; }
        submitPartial(ctx, panel, signedPsbt);
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
        else if (b.classList.contains('weo-accept-go')) { accept(b); }
        else if (b.classList.contains('weo-decline')) { simple(b, 'weo_decline', 'confirmDecline', { reason: window.prompt('Grund (optional):') || '' }); }
        else if (b.classList.contains('weo-cancel')) { simple(b, 'weo_cancel', 'confirmCancel'); }
        else if (b.classList.contains('weo-sign-start')) { signStart(b); }
        else if (b.classList.contains('weo-sign-confirm')) { signConfirm(b); }
        else if (b.classList.contains('weo-sign-upload')) { signUpload(b); }
        else if (b.classList.contains('weo-words-show')) { keyboxOpen(b, 'show'); }
        else if (b.classList.contains('weo-words-import')) { keyboxOpen(b, 'import'); }
        else if (b.classList.contains('weo-keybox-go')) { keyboxGo(b); }
        else if (b.classList.contains('weo-copy')) {
            if (navigator.clipboard) { navigator.clipboard.writeText(b.getAttribute('data-copy') || ''); }
            b.textContent = t('copied');
        }
        else { return; }
        ev.preventDefault();
    });

    function init() {
        verifyAll();
        // Open deposits: check the API every 20 s while the page is open.
        $$('.weo-escrow-card[data-status="pending"]').forEach(function (card) {
            poll(card);
            window.setInterval(function () { poll(card); }, 20000);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
