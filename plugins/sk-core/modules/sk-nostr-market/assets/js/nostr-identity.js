/**
 * SK Nostr Market — identity in the listing form.
 *
 * Shows the selection as soon as someone without their own key checks the
 * Nostr option, and creates an identity on request. The generated key is
 * shown immediately: without it, the vendor has no other way to use their
 * identity.
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

            // Fetch the key separately so it never sits in the create
            // response and never ends up in the page source.
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
