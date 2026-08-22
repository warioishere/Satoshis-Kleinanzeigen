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

    /**
     * Gleicher Endpunkt, anderer Ort: auf der Nostr/LN-Link-Seite. Nach dem
     * Anlegen wird neu geladen, damit der Abschnitt mit npub, Schluessel-Export
     * und "Identitaet loeschen" erscheint — der rendert serverseitig nur, wenn
     * bereits eine Identitaet existiert.
     */
    $('#sk-nostr-create').on('click', function () {
        var $btn = $(this);
        var $out = $('#sk-nostr-create-status');
        $btn.prop('disabled', true).text('Wird erstellt...');
        $out.text('');
        $.post(cfg.ajaxurl, {
            action: 'sk_create_nostr_identity',
            nonce:  cfg.nonce,
        }, function (res) {
            if (res.success) {
                $out.html('<span style="color:#5cb85c;">' + res.data.message + '</span>');
                window.location.reload();
            } else {
                $btn.prop('disabled', false).text('Nostr-Identität erstellen');
                $out.html('<span style="color:#d9534f;">'
                    + ((res.data && res.data.message) || 'Fehler') + '</span>');
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('Nostr-Identität erstellen');
            $out.html('<span style="color:#d9534f;">Netzwerkfehler. Bitte erneut versuchen.</span>');
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
