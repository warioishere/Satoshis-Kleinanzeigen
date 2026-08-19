/**
 * "Invoice erstellen" button inside Nostr bridge chats.
 */
(function($) {
    var config = window.skNostrBridge || {};
    // Add "Invoice erstellen" button to bridge chat.
    var $chatArea = $('#dvc-messages-area, .dvc-chat-messages');
    if (!$chatArea.length) return;

    var btnHtml = '<div id="sk-nostr-bridge-invoice" style="padding:12px;background:#1a2332;border-top:1px solid rgba(255,255,255,0.07);display:flex;gap:8px;align-items:center;">' +
        '<input type="number" id="sk-nostr-invoice-amount" value="' + config.priceSats + '" min="1" placeholder="Sats" ' +
        'style="flex:1;background:#0f1923;border:1px solid rgba(255,255,255,0.08);color:#e8ecf0;padding:8px 12px;border-radius:6px;font-size:14px;" />' +
        '<button type="button" id="sk-nostr-invoice-btn" ' +
        'style="background:#f7931a;color:#fff;border:none;padding:8px 16px;border-radius:6px;cursor:pointer;font-size:14px;font-weight:600;white-space:nowrap;">' +
        'Invoice erstellen + senden</button>' +
        '</div>';

    $chatArea.after(btnHtml);

    $('#sk-nostr-invoice-btn').on('click', function() {
        var $btn = $(this);
        var amount = parseInt($('#sk-nostr-invoice-amount').val(), 10);
        if (!amount || amount < 1) {
            alert('Bitte Betrag in Sats eingeben.');
            return;
        }

        $btn.prop('disabled', true).text('Wird erstellt...');

        $.post(config.ajaxUrl, {
            action: 'sk_nostr_bridge_invoice',
            nonce: config.nonce,
            chat_id: config.chatId,
            amount_sats: amount
        }, function(res) {
            $btn.prop('disabled', false).text('Invoice erstellen + senden');
            if (res.success) {
                $btn.text('Gesendet!');
                setTimeout(function() { $btn.text('Invoice erstellen + senden'); }, 3000);
                // Reload chat messages.
                if (typeof window.dvcLoadMessages === 'function') {
                    window.dvcLoadMessages();
                }
            } else {
                alert(res.data && res.data.message ? res.data.message : 'Fehler.');
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('Invoice erstellen + senden');
            alert('Netzwerkfehler.');
        });
    });
})(jQuery);
