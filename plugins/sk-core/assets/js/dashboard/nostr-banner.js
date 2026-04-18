/**
 * Nostr Identity Banner — "Erstelle eine Nostr-Identität" prompt.
 * Localized vars from PHP: uobAjax.ajaxurl, uobAjax.nonce.
 */
jQuery(function ($) {
    'use strict';
    var cfg = window.uobAjax || {};

    $('#sk-nostr-banner-create').on('click', function () {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Wird erstellt...');
        $.post(cfg.ajaxurl, {
            action: 'sk_create_nostr_identity',
            nonce:  cfg.nonce,
        }, function (res) {
            if (res.success) {
                $('#sk-nostr-banner').html(
                    '<div style="color:#5cb85c;"><i class="fas fa-check-circle"></i> '
                    + res.data.message + ' — ' + res.data.npub + '</div>'
                );
            } else {
                $btn.prop('disabled', false).html('<i class="fas fa-key"></i> Erstellen');
                alert((res.data && res.data.message) || 'Fehler');
            }
        });
    });

    $('#sk-nostr-banner-dismiss').on('click', function () {
        $('#sk-nostr-banner').fadeOut();
        $.post(cfg.ajaxurl, {
            action: 'uob_complete_onboarding',
            nonce:  cfg.nonce,
            dismiss_nostr: 1,
        });
    });
});
