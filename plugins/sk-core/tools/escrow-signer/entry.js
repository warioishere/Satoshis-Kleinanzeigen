/*!
 * sk-escrow-signer: browser-side key for the 2-of-3 on-chain escrow.
 *
 * window.SKEscrowSigner lets buyer and seller take part in the escrow
 * without a hardware wallet: it generates 12 words per trade, derives the
 * xpub the marketplace needs, recomputes the escrow address from the
 * descriptor the API returns, shows what a PSBT pays before it is signed,
 * and signs it. Only the xpub and signed PSBTs ever leave the browser.
 *
 * The words are kept in localStorage encrypted with a password-derived key
 * (PBKDF2-SHA256, AES-GCM). They can be shown in clear so the user can back
 * them up, and re-imported on another browser.
 *
 * Threat model, honestly: this code is served by the marketplace. Whoever
 * controls the server can ship a version that leaks the words. That is the
 * same trust as every browser wallet (Hodl Hodl, Boltz, ...) and cannot be
 * removed here; a user who does not accept it uses a hardware wallet via
 * the PSBT text fields instead. The 2-of-3 setup limits the damage: one
 * stolen key alone moves nothing, and a *lost* key is recoverable because
 * platform plus counterparty can still sign.
 *
 * Built from @scure/bip39, @scure/bip32, @scure/btc-signer (MIT, Paul
 * Miller) with esbuild: `npm install && npm run build` in
 * tools/escrow-signer writes assets/js/sk-escrow-signer.js.
 */
import * as bip39 from '@scure/bip39';
import { wordlist } from '@scure/bip39/wordlists/english.js';
import { HDKey } from '@scure/bip32';
import * as btc from '@scure/btc-signer';
import { base64 } from '@scure/base';

var PBKDF2_ITERATIONS = 600000;
var STORAGE_PREFIX = 'sk_escrow_key:';
var TESTNET_VERSIONS = { private: 0x04358394, public: 0x043587cf };

// BIP48 multisig account for P2WSH. Sparrow and friends derive the same
// account from the words, so a backup restores into a real wallet.
function accountPath(network) {
    return network === 'test' ? "m/48'/1'/0'/2'" : "m/48'/0'/0'/2'";
}

function versions(network) {
    return network === 'test' ? TESTNET_VERSIONS : undefined;
}

function btcNetwork(network) {
    return network === 'test' ? btc.TEST_NETWORK : btc.NETWORK;
}

function networkOfAddress(address) {
    return /^tb1/i.test(String(address || '')) ? 'test' : 'main';
}

function normalizeWords(words) {
    return String(words || '').trim().toLowerCase().split(/\s+/).join(' ');
}

function generateMnemonic() {
    return bip39.generateMnemonic(wordlist, 128);
}

function validateMnemonic(words) {
    return bip39.validateMnemonic(normalizeWords(words), wordlist);
}

function accountKey(words, network) {
    var normalized = normalizeWords(words);
    if (!bip39.validateMnemonic(normalized, wordlist)) {
        throw new Error('invalid mnemonic');
    }
    var seed = bip39.mnemonicToSeedSync(normalized);
    return HDKey.fromMasterSeed(seed, versions(network)).derive(accountPath(network));
}

function xpubFromMnemonic(words, network) {
    return accountKey(words, network).publicExtendedKey;
}

/*
 * wsh(multi(2,XPUB/0/N/*,XPUB/0/N/*,XPUB/0/N/*))#checksum — the shape
 * python_api.rpc.build_descriptor() produces. The API imports it with
 * range [N,N], so the `*` is N as well and each key is XPUB/0/N/N.
 */
var DESCRIPTOR_RE = /^wsh\(multi\(2,([^()]+)\)\)(?:#[a-z0-9]{8})?$/;
var KEY_RE = /^([xt]pub[1-9A-HJ-NP-Za-km-z]{100,120})\/0\/(\d+)\/\*$/;

function parseDescriptor(descriptor) {
    var m = DESCRIPTOR_RE.exec(String(descriptor || '').trim());
    if (!m) { throw new Error('unsupported descriptor'); }
    var parts = m[1].split(',');
    if (parts.length !== 3) { throw new Error('descriptor must hold three keys'); }
    var index = null;
    var xpubs = parts.map(function (p) {
        var k = KEY_RE.exec(p.trim());
        if (!k) { throw new Error('unsupported key expression'); }
        var n = parseInt(k[2], 10);
        if (index === null) { index = n; } else if (index !== n) { throw new Error('inconsistent index'); }
        return k[1];
    });
    return { xpubs: xpubs, index: index };
}

function childPubkey(xpub, index, network) {
    return HDKey.fromExtendedKey(xpub, versions(network))
        .deriveChild(0).deriveChild(index).deriveChild(index).publicKey;
}

function escrowAddress(descriptor, network) {
    var d = parseDescriptor(descriptor);
    var pubkeys = d.xpubs.map(function (x) { return childPubkey(x, d.index, network); });
    return btc.p2wsh(btc.p2ms(2, pubkeys), btcNetwork(network)).address;
}

/*
 * The trust check: the address the server told the buyer to pay must be
 * the one the descriptor really encodes, and the caller's own key must be
 * one of the three. Never deposit when this returns ok=false.
 */
function verifyEscrow(descriptor, expectedAddress, myXpub) {
    var network = networkOfAddress(expectedAddress);
    var res = { ok: false, computed: '', mine: false, error: '' };
    try {
        var d = parseDescriptor(descriptor);
        res.mine = !myXpub || d.xpubs.indexOf(myXpub) !== -1;
        res.computed = escrowAddress(descriptor, network);
        res.ok = res.computed === String(expectedAddress).trim() && res.mine;
    } catch (e) {
        res.error = String(e && e.message || e);
    }
    return res;
}

var PSBT_OPTS = { allowUnknownInputs: true, allowUnknownOutputs: true, allowLegacyWitnessUtxo: true };

function parsePsbt(psbtBase64) {
    return btc.Transaction.fromPSBT(base64.decode(String(psbtBase64 || '').trim()), PSBT_OPTS);
}

/* What the transaction pays and what it costs, for display before signing. */
function psbtSummary(psbtBase64, network) {
    var tx = parsePsbt(psbtBase64);
    var inSum = 0n;
    var outSum = 0n;
    var outputs = [];
    for (var i = 0; i < tx.inputsLength; i++) {
        var inp = tx.getInput(i);
        if (!inp.witnessUtxo) { throw new Error('input without witness utxo'); }
        inSum += inp.witnessUtxo.amount;
    }
    for (var o = 0; o < tx.outputsLength; o++) {
        var out = tx.getOutput(o);
        outSum += out.amount;
        outputs.push({
            address: btc.Address(btcNetwork(network)).encode(btc.OutScript.decode(out.script)),
            sats: Number(out.amount)
        });
    }
    return { inputs: tx.inputsLength, outputs: outputs, feeSat: Number(inSum - outSum) };
}

/* Add this party's signature to every input. Returns the PSBT, base64. */
function signPsbt(psbtBase64, words, descriptor, network) {
    var d = parseDescriptor(descriptor);
    var account = accountKey(words, network);
    if (d.xpubs.indexOf(account.publicExtendedKey) === -1) {
        throw new Error('these words do not belong to this escrow');
    }
    var key = account.deriveChild(0).deriveChild(d.index).deriveChild(d.index);
    var tx = parsePsbt(psbtBase64);
    for (var i = 0; i < tx.inputsLength; i++) {
        tx.signIdx(key.privateKey, i);
    }
    return base64.encode(tx.toPSBT());
}

// ---- encrypted storage ----

function deriveKey(password, salt, iterations) {
    var enc = new TextEncoder();
    return crypto.subtle.importKey('raw', enc.encode(String(password)), 'PBKDF2', false, ['deriveKey'])
        .then(function (base) {
            return crypto.subtle.deriveKey(
                { name: 'PBKDF2', salt: salt, iterations: iterations, hash: 'SHA-256' },
                base,
                { name: 'AES-GCM', length: 256 },
                false,
                ['encrypt', 'decrypt']
            );
        });
}

function encryptWords(words, password) {
    var salt = crypto.getRandomValues(new Uint8Array(16));
    var iv = crypto.getRandomValues(new Uint8Array(12));
    return deriveKey(password, salt, PBKDF2_ITERATIONS).then(function (key) {
        return crypto.subtle.encrypt({ name: 'AES-GCM', iv: iv }, key, new TextEncoder().encode(normalizeWords(words)));
    }).then(function (ct) {
        return {
            v: 1,
            iter: PBKDF2_ITERATIONS,
            salt: base64.encode(salt),
            iv: base64.encode(iv),
            ct: base64.encode(new Uint8Array(ct))
        };
    });
}

function decryptWords(blob, password) {
    if (!blob || blob.v !== 1) { return Promise.reject(new Error('unknown key format')); }
    return deriveKey(password, base64.decode(blob.salt), blob.iter).then(function (key) {
        return crypto.subtle.decrypt({ name: 'AES-GCM', iv: base64.decode(blob.iv) }, key, base64.decode(blob.ct));
    }).then(function (pt) {
        return new TextDecoder().decode(pt);
    }, function () {
        throw new Error('wrong password');
    });
}

function storeKey(xpub, blob) {
    localStorage.setItem(STORAGE_PREFIX + xpub, JSON.stringify(blob));
}

function loadKey(xpub) {
    try {
        var raw = localStorage.getItem(STORAGE_PREFIX + xpub);
        return raw ? JSON.parse(raw) : null;
    } catch (e) {
        return null;
    }
}

function hasKey(xpub) {
    return loadKey(xpub) !== null;
}

/* Generate words, encrypt them under the password, store by xpub. */
function createKey(words, password, network) {
    var xpub = xpubFromMnemonic(words, network);
    return encryptWords(words, password).then(function (blob) {
        storeKey(xpub, blob);
        return xpub;
    });
}

/* Re-import backed-up words; they must produce the expected xpub. */
function importKey(words, password, expectedXpub, network) {
    var xpub = xpubFromMnemonic(words, network);
    if (expectedXpub && xpub !== expectedXpub) {
        return Promise.reject(new Error('words do not match this key'));
    }
    return createKey(words, password, network);
}

function revealWords(xpub, password) {
    var blob = loadKey(xpub);
    if (!blob) { return Promise.reject(new Error('no key stored for this xpub')); }
    return decryptWords(blob, password);
}

function signStored(psbtBase64, xpub, password, descriptor, network) {
    return revealWords(xpub, password).then(function (words) {
        return signPsbt(psbtBase64, words, descriptor, network);
    });
}

window.SKEscrowSigner = {
    generateMnemonic: generateMnemonic,
    validateMnemonic: validateMnemonic,
    xpubFromMnemonic: xpubFromMnemonic,
    parseDescriptor: parseDescriptor,
    escrowAddress: escrowAddress,
    verifyEscrow: verifyEscrow,
    networkOfAddress: networkOfAddress,
    psbtSummary: psbtSummary,
    signPsbt: signPsbt,
    createKey: createKey,
    importKey: importKey,
    hasKey: hasKey,
    revealWords: revealWords,
    signStored: signStored
};
