/**
 * Danger zone of the store settings: delete the own account after two
 * confirmations.
 */
jQuery(function($){
    var config = window.skStoreDeleteAccount || {};
    $('#sk-delete-account-btn').on('click', function(){
        if (!confirm(config.confirmFirst)) return;
        if (!confirm(config.confirmSecond)) return;
        var $btn = $(this);
        $btn.prop('disabled', true).text('Wird gelöscht...');
        $.post(config.ajaxUrl, {
            action: 'sk_delete_own_account',
            _nonce: config.nonce
        }, function(res) {
            if (res.success) {
                window.location.href = config.homeUrl;
            } else {
                alert(res.data && res.data.message ? res.data.message : 'Fehler beim Löschen.');
                $btn.prop('disabled', false).html('<i class="fas fa-trash-alt"></i> Account endgültig löschen');
            }
        }).fail(function(){
            alert('Netzwerkfehler.');
            $btn.prop('disabled', false).html('<i class="fas fa-trash-alt"></i> Account endgültig löschen');
        });
    });
});
