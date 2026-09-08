/*!
 * sk-nostr-verify: checks a Nostr event the way NIP-01 defines it.
 *
 * window.skNostrVerify(event) recomputes the event id from
 * [0, pubkey, created_at, kind, tags, content] and verifies the BIP-340
 * Schnorr signature over it with the event's pubkey. True only when both
 * hold; every malformed input is false, never an exception.
 *
 * Built from @noble/curves and @noble/hashes (MIT, Paul Miller) with
 * esbuild: `npm install && npm run build` in tools/nostr-verify writes
 * assets/js/sk-nostr-verify.js, which sk-nostr.js fetches on demand.
 */
import { schnorr } from '@noble/curves/secp256k1.js';
import { sha256 } from '@noble/hashes/sha2.js';
import { bytesToHex, hexToBytes, utf8ToBytes } from '@noble/hashes/utils.js';

var HEX64 = /^[0-9a-f]{64}$/;
var HEX128 = /^[0-9a-f]{128}$/;

function isStringArray(tags) {
    if (!Array.isArray(tags)) { return false; }
    for (var i = 0; i < tags.length; i++) {
        var t = tags[i];
        if (!Array.isArray(t)) { return false; }
        for (var j = 0; j < t.length; j++) {
            if (typeof t[j] !== 'string') { return false; }
        }
    }
    return true;
}

function verify(ev) {
    try {
        if (!ev || typeof ev !== 'object') { return false; }
        var id = String(ev.id || '').toLowerCase();
        var pubkey = String(ev.pubkey || '').toLowerCase();
        var sig = String(ev.sig || '').toLowerCase();
        if (!HEX64.test(id) || !HEX64.test(pubkey) || !HEX128.test(sig)) { return false; }
        if (typeof ev.created_at !== 'number' || typeof ev.kind !== 'number' || typeof ev.content !== 'string') { return false; }
        if (!Number.isInteger(ev.created_at) || !Number.isInteger(ev.kind) || !isStringArray(ev.tags)) { return false; }

        var serialized = JSON.stringify([0, pubkey, ev.created_at, ev.kind, ev.tags, ev.content]);
        var computed = bytesToHex(sha256(utf8ToBytes(serialized)));
        if (computed !== id) { return false; }

        return schnorr.verify(hexToBytes(sig), hexToBytes(id), hexToBytes(pubkey));
    } catch (e) {
        return false;
    }
}

window.skNostrVerify = verify;
