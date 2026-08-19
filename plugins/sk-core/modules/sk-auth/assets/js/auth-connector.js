/**
 * Auth connector dashboard page: reveal the nsec and unlink methods.
 */
jQuery(function($){
    var config = window.skAuthConnector || {};
    var nsecRevealed = false;
    $('#uac-reveal-nsec').on('click', function(){
        if (!nsecRevealed && !confirm('Bist du sicher? Zeige deinen Private Key nur, wenn du ihn exportieren möchtest.')) return;
        nsecRevealed = true;
        $('#uac-nsec-value').css({filter:'none',userSelect:'text'}).text(config.nsec);
        $(this).hide();
        $('#uac-copy-nsec').show();
    });
    $('#uac-copy-nsec').on('click', function(){
        navigator.clipboard.writeText($('#uac-nsec-value').text());
        $(this).text('Kopiert!');
        setTimeout(function(){ $('#uac-copy-nsec').text('Kopieren'); }, 2000);
    });
    $('#uac-delete-nostr-identity').on('click', function(){
        if (!confirm('Wirklich löschen? Du verlierst den Zugriff auf deinen aktuellen nsec — stelle sicher, dass du ihn vorher exportiert hast, falls du den Account weiter nutzen möchtest.')) return;
        var $btn = $(this).prop('disabled', true).text('Lösche...');
        $.post(config.ajaxUrl, {
            action: 'sk_delete_nostr_identity',
            nonce:  config.nonce
        }, function(res){
            if (res.success) { location.reload(); }
            else { $btn.prop('disabled', false).text('Identität löschen'); alert((res.data && res.data.message) || 'Fehler'); }
        });
    });
});
