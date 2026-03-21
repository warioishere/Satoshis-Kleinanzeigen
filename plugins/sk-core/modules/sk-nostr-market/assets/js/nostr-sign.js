/**
 * SK Nostr Market — NIP-07 Self-Signing
 *
 * After a vendor saves a product, checks if it needs Nostr signing.
 * Uses window.nostr.signEvent() from the browser extension (Alby, nos2x).
 * Sends the signed event to the server for relay publishing.
 */
(function ($) {
    'use strict';

    var SKN = window.skNostrMarket || {};

    // Check for pending products to sign.
    if (!SKN.pendingSign || !SKN.pendingSign.length) return;

    // Check if Nostr extension is available.
    if (!window.nostr) {
        console.warn('[SK Nostr Market] Keine Nostr-Erweiterung gefunden. Inserate werden von Satoshis Kleinanzeigen signiert.');
        // Fallback: tell server to sign with marketplace key.
        SKN.pendingSign.forEach(function (item) {
            $.post(SKN.ajaxurl, {
                action: 'sk_nostr_market_fallback_sign',
                nonce: SKN.nonce,
                post_id: item.post_id
            });
        });
        return;
    }

    // Process each pending product.
    SKN.pendingSign.forEach(function (item) {
        signAndPublish(item);
    });

    async function signAndPublish(item) {
        try {
            // Build unsigned event.
            var event = {
                kind: 30402,
                created_at: Math.floor(Date.now() / 1000),
                content: item.content,
                tags: item.tags
            };

            // Sign with vendor's Nostr extension (NIP-07).
            var signedEvent = await window.nostr.signEvent(event);

            if (!signedEvent || !signedEvent.id) {
                console.error('[SK Nostr Market] Signierung abgebrochen.');
                return;
            }

            // Send signed event to server for relay publishing.
            $.post(SKN.ajaxurl, {
                action: 'sk_nostr_market_publish_signed',
                nonce: SKN.nonce,
                post_id: item.post_id,
                signed_event: JSON.stringify(signedEvent)
            }, function (res) {
                if (res.success) {
                    console.log('[SK Nostr Market] Inserat signiert und veröffentlicht:', res.data.event_id);
                } else {
                    console.error('[SK Nostr Market] Fehler:', res.data && res.data.message);
                }
            });

        } catch (err) {
            console.error('[SK Nostr Market] Signierung fehlgeschlagen:', err.message);
        }
    }

})(jQuery);
