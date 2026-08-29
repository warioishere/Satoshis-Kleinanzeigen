/**
 * Store settings form: AJAX save with toast, media pickers for banner and
 * images, payment-field reset and the payment connection tests.
 */
function skStoreToast(message, type) {
    type = type || 'info';
    var existing = document.querySelector('.dcg-toast[data-toast-id="sk-store"]');
    if (existing && existing.parentNode) existing.parentNode.removeChild(existing);

    var toast = document.createElement('div');
    toast.className = 'dcg-toast dcg-toast--' + type;
    toast.dataset.toastId = 'sk-store';

    var icon = type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle';
    toast.innerHTML =
        '<i class="fas ' + icon + '"></i>' +
        '<span>' + message + '</span>' +
        '<button class="close-toast" type="button" aria-label="Schlie\u00dfen">&times;</button>';

    document.body.appendChild(toast);

    toast.querySelector('.close-toast').addEventListener('click', function() {
        if (toast.parentNode) toast.parentNode.removeChild(toast);
    });

    setTimeout(function() {
        if (!toast.parentNode) return;
        toast.style.transition = 'opacity 0.3s, transform 0.3s';
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(420px)';
        setTimeout(function() { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 350);
    }, 5000);
}

(function($) {
    var config = window.skStoreForm || {};

    $(function() {
        var savedState = config.addressState;
        if (!savedState || savedState === 'N/A') $('#sk-states-box').hide();
    });

    // Save via AJAX
    $('#sk-store-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $btn  = $form.find('[name="sk_update_store_settings"]');
        var originalVal = $btn.val();
        $btn.prop('disabled', true).val(config.savingLabel);
        $('.sk-store-ajax-msg').remove();
        var data = $form.serialize() + '&action=sk_settings&form_id=store-form';
        $.post(config.ajaxUrl, data)
            .done(function(res) {
                $btn.prop('disabled', false).val(originalVal);
                if (res && res.success) {
                    window.onbeforeunload = null;
                    $form.data('submitted', true);
                    skStoreToast(config.savedMessage, 'info');
                } else {
                    var errText = (res && res.data) ? (Array.isArray(res.data) ? res.data.join(', ') : res.data) : 'Fehler beim Speichern.';
                    skStoreToast(errText, 'error');
                }
            })
            .fail(function() {
                $btn.prop('disabled', false).val(originalVal);
            });
    });

    // Banner upload — component rendered by sk_form_media_upload(variant='banner').
    var skBannerFrame;
    $(document).on('click', '.sk-banner-drag', function(e) {
        e.preventDefault();
        var $component = $(this).closest('.sk-banner');
        if (skBannerFrame) { skBannerFrame.open(); return; }
        skBannerFrame = wp.media({ title: config.bannerTitle, button: { text: config.selectLabel }, multiple: false });
        skBannerFrame.on('select', function() {
            var att = skBannerFrame.state().get('selection').first().toJSON();
            $component.find('.sk-file-field').val(att.id);
            $component.find('.sk-banner-img').attr('src', att.url);
            $component.find('.image-wrap').removeClass('sk-hide');
            $component.find('.button-area').addClass('sk-hide');
        });
        skBannerFrame.open();
    });
    $(document).on('click', '.sk-remove-banner-image', function(e) {
        e.preventDefault();
        var $component = $(this).closest('.sk-banner');
        $component.find('.sk-file-field').val('');
        $component.find('.image-wrap').addClass('sk-hide');
        $component.find('.button-area').removeClass('sk-hide');
    });

    // Gravatar / image upload — component rendered by sk_form_media_upload(variant='gravatar').
    // Same pattern works for store profile + SEO OG images.
    var skGravatarFrames = {};
    $(document).on('click', '.sk-gravatar-drag', function(e) {
        e.preventDefault();
        var $component = $(this).closest('.sk-gravatar');
        var key = $component.find('.sk-file-field').attr('name') || 'default';
        if (skGravatarFrames[key]) { skGravatarFrames[key].open(); return; }
        skGravatarFrames[key] = wp.media({ title: config.imageTitle, button: { text: config.selectLabel }, multiple: false });
        skGravatarFrames[key].on('select', function() {
            var att = skGravatarFrames[key].state().get('selection').first().toJSON();
            $component.find('.sk-file-field').val(att.id);
            $component.find('.sk-gravatar-img').attr('src', att.url);
            $component.find('.gravatar-wrap').removeClass('sk-hide');
            $component.find('.gravatar-button-area').addClass('sk-hide');
        });
        skGravatarFrames[key].open();
    });
    $(document).on('click', '.sk-remove-gravatar-image', function(e) {
        e.preventDefault();
        var $component = $(this).closest('.sk-gravatar');
        $component.find('.sk-file-field').val('');
        $component.find('.gravatar-wrap').addClass('sk-hide');
        $component.find('.gravatar-button-area').removeClass('sk-hide');
    });

    // Store slug preview
    (function() {
        var input = document.getElementById('store_slug');
        var preview = document.getElementById('store_slug_preview');
        if (input && preview) {
            input.addEventListener('input', function() {
                preview.textContent = input.value.trim().toLowerCase().replace(/\s+/g, '-');
            });
        }
    })();

    /* ── Payment-Entfernen: sofortige UI-Bereinigung vor Form-Submit ── */
    // Bei Klick auf "Entfernen" den gespeicherten State visuell sofort zurücksetzen,
    // damit der User nicht weiter "gespeichert — leer lassen um beizubehalten" sieht
    // während der AJAX-Save im Hintergrund läuft.
    $(document).on('click', '.sk-payment-remove-link', function(e) {
        e.preventDefault();
        var $link  = $(this);
        var $field = $link.closest('.sk-settings-field');
        var $form  = $link.closest('form');
        var removeField      = $link.data('remove-field');
        var inputField       = $link.data('input-field');
        var defaultHolder    = $link.data('default-placeholder') || '';

        // 1. Remove-Flag setzen
        $form.find('[name="' + removeField + '"]').val('1');
        // 2. Input-Feld zurücksetzen (Placeholder + skp-saved Klasse)
        var $input = $form.find('[name="' + inputField + '"]');
        $input.val('').attr('placeholder', defaultHolder).removeClass('skp-saved');
        // 3. Status-Pill(s) + Entfernen-Link im Feld ausblenden
        $field.find('.sk-settings-status').remove();
        // 4. Form abschicken — AJAX-Save löscht das User-Meta serverseitig.
        $form.trigger('submit');
    });

    /* ── Onchain + Lightning Connection Test Buttons ── */
    var skpAjax = config.ajaxUrl;
    var skpNonce = config.testNonce;

    // BTC Address: enable button when looks like a valid bitcoin address
    $('input[name="btc_address"]').on('input', function() {
        var v = $(this).val().trim();
        var ok = /^(bc1[a-z0-9]{25,90}|[13][a-km-zA-HJ-NP-Z1-9]{25,34})$/i.test(v);
        $('#skp-test-btcaddr').prop('disabled', !ok);
        $('#skp-test-btcaddr-result').text('');
    });

    // xpub: enable button when looks like xpub/ypub/zpub
    $('input[name="btc_xpub"]').on('input', function() {
        var v = $(this).val().trim();
        var ok = /^[xyz]pub[a-km-zA-HJ-NP-Z1-9]{100,120}$/i.test(v);
        $('#skp-test-xpub').prop('disabled', !ok);
        $('#skp-test-xpub-result').text('');
    });

    // NWC: enable button when input matches protocol
    $('input[name="nwc_connection"]').on('input', function() {
        var v = $(this).val().trim();
        $('#skp-test-nwc').prop('disabled', !/^nostr\+walletconnect:\/\/[0-9a-f]{64}\?/.test(v));
        $('#skp-test-nwc-result').text('');
    });

    // LNDHub: enable button when input matches protocol
    $('input[name="lndhub_connection"]').on('input', function() {
        var v = $(this).val().trim();
        $('#skp-test-lndhub').prop('disabled', !/^lndhub:\/\/.+@https?:\/\//.test(v));
        $('#skp-test-lndhub-result').text('');
    });

    // Lightning Address: enable button when looks like email or lnurl
    $('input[name="lightning_address"]').on('input', function() {
        var v = $(this).val().trim();
        var ok = /^[a-zA-Z0-9_.+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/.test(v) || /^lnurl1/i.test(v);
        $('#skp-test-lnaddr').prop('disabled', !ok);
        $('#skp-test-lnaddr-result').text('');
    });

    // Trigger on load (for pre-filled values)
    $('input[name="btc_address"]').trigger('input');
    $('input[name="lightning_address"]').trigger('input');

    function skpTest(btn, resultEl, action, dataFn, onError) {
        btn.on('click', function() {
            var $b = $(this), $r = $(resultEl);
            $b.prop('disabled', true);
            $r.html('<i class="fas fa-spinner fa-spin skp-test-spinner"></i> Teste...');
            $.post(skpAjax, $.extend({ action: action, nonce: skpNonce }, dataFn()), function(res) {
                $b.prop('disabled', false);
                if (res.success) {
                    $r.html('<span class="skp-test-ok">' + (res.data.message || 'OK') + '</span>');
                } else {
                    var msg = (res.data && res.data.message) ? res.data.message : 'Fehler';
                    $r.html('<span class="skp-test-err">' + msg + '</span>');
                    if (typeof onError === 'function') onError(msg);
                }
            }).fail(function() {
                $b.prop('disabled', false);
                $r.html('<span class="skp-test-err">Netzwerkfehler</span>');
            });
        });
    }

    skpTest($('#skp-test-btcaddr'), '#skp-test-btcaddr-result', 'skp_test_btcaddr', function() {
        return { value: $('input[name="btc_address"]').val() };
    });
    skpTest($('#skp-test-xpub'), '#skp-test-xpub-result', 'skp_test_xpub', function() {
        return { value: $('input[name="btc_xpub"]').val() };
    });
    skpTest($('#skp-test-nwc'), '#skp-test-nwc-result', 'skp_test_nwc', function() {
        return { value: $('input[name="nwc_connection"]').val() };
    });
    skpTest($('#skp-test-lndhub'), '#skp-test-lndhub-result', 'skp_test_lndhub', function() {
        return { value: $('input[name="lndhub_connection"]').val() };
    });
    skpTest($('#skp-test-lnaddr'), '#skp-test-lnaddr-result', 'skp_test_lnaddr', function() {
        return { value: $('input[name="lightning_address"]').val() };
    }, function(message) {
        // Eine abgewiesene Lightning-Adresse braucht mehr als eine rote Zeile:
        // der Haendler muss wissen, welche Wallet stattdessen geht.
        skpShowLnaddrModal(message);
    });

    /**
     * Fenster im Muster des Paket-Infofensters — dieselben Klassen, damit
     * kein zweites Modal-Design entsteht.
     */
    function skpShowLnaddrModal(message) {
        var el = document.getElementById('skp-lnaddr-reject');
        if (!el) return;

        if (message) {
            var reason = el.querySelector('#skp-lnaddr-reject-reason');
            if (reason) reason.textContent = message;
        }

        el.classList.add('is-visible');
        var close = el.querySelector('.sk-pack-info__close');
        if (close) close.focus();
    }

    document.addEventListener('click', function (e) {
        if (!e.target || !e.target.closest) return;
        if (e.target.closest('#skp-lnaddr-reject .sk-pack-info__close')
            || e.target.closest('#skp-lnaddr-reject .sk-pack-info__backdrop')) {
            e.preventDefault();
            var el = document.getElementById('skp-lnaddr-reject');
            if (el) el.classList.remove('is-visible');
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        var el = document.getElementById('skp-lnaddr-reject');
        if (el) el.classList.remove('is-visible');
    });

    // Wurde beim Speichern abgewiesen? Dann gleich beim Laden erklaeren.
    if (window.skpLnaddrRejected) {
        skpShowLnaddrModal(window.skpLnaddrRejected);
    }
})(jQuery);
