/**
 * SK Nostr Market — Identitaet im Inseratsformular.
 *
 * Blendet die Auswahl ein, sobald jemand ohne eigenen Schluessel die
 * Nostr-Option anhakt, und legt auf Wunsch eine Identitaet an. Der erzeugte
 * Schluessel wird sofort gezeigt: ohne ihn kann der Anbieter seine Identitaet
 * nirgendwo sonst benutzen.
 */
(function ($) {
    'use strict';

    var CFG = window.skNostrIdentity || {};

    var $box      = $('#sk-nostr-identity-hint');
    var $checkbox = $('input[name="_sk_nostr_market_post"][type="checkbox"]');

    if (!$box.length || !$checkbox.length) {
        return;
    }

    function toggle() {
        $box.toggle($checkbox.is(':checked'));
    }

    $checkbox.on('change', toggle);
    toggle();

    $('#sk-nostr-create-identity').on('click', function () {
        var $btn = $(this);

        $btn.prop('disabled', true).text(CFG.i18nWorking || 'Wird erstellt…');

        $.post(CFG.ajaxurl, {
            action: 'sk_create_nostr_identity',
            nonce: CFG.nonce
        }).done(function (res) {
            if (!res || !res.success) {
                $btn.prop('disabled', false).text(CFG.i18nRetry || 'Erneut versuchen');
                return;
            }

            // Den Schluessel getrennt holen, damit er nicht in der Antwort des
            // Erstellens haengt und nirgends im Seitenquelltext landet.
            $.post(CFG.ajaxurl, {
                action: 'sk_get_nostr_nsec',
                nonce: CFG.nonce
            }).done(function (key) {
                if (key && key.success && key.data && key.data.nsec) {
                    $('#sk-nostr-nsec').text(key.data.nsec);
                    $('#sk-nostr-identity-result').show();
                    $btn.remove();
                }
            });
        }).fail(function () {
            $btn.prop('disabled', false).text(CFG.i18nRetry || 'Erneut versuchen');
        });
    });

    $('#sk-nostr-copy-nsec').on('click', function () {
        var $b = $(this);
        navigator.clipboard.writeText($('#sk-nostr-nsec').text());
        $b.html('<i class="fas fa-check"></i> ' + (CFG.i18nCopied || 'Kopiert'));
        window.setTimeout(function () {
            $b.html('<i class="fas fa-copy"></i> ' + (CFG.i18nCopy || 'Kopieren'));
        }, 2000);
    });

})(jQuery);
