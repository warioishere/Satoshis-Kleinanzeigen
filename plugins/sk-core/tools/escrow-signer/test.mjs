/*
 * Self-check for the built bundle: run `npm run build && npm test`.
 *
 * Builds a 2-of-3 P2WSH escrow the way the API does, checks the address
 * recomputation, then signs a PSBT with two of the three keys and
 * finalizes it, so a broken derivation path or signing step fails here.
 */
import assert from 'node:assert/strict';
import { webcrypto } from 'node:crypto';
import fs from 'node:fs';
import vm from 'node:vm';
import * as btc from '@scure/btc-signer';
import { HDKey } from '@scure/bip32';
import { base64, hex } from '@scure/base';

const store = new Map();
const sandbox = {
    window: {},
    crypto: webcrypto,
    TextEncoder,
    TextDecoder,
    localStorage: {
        getItem: (k) => (store.has(k) ? store.get(k) : null),
        setItem: (k, v) => store.set(k, String(v)),
        removeItem: (k) => store.delete(k)
    },
    console
};
sandbox.self = sandbox;
vm.createContext(sandbox);
vm.runInContext(fs.readFileSync(new URL('../../assets/js/sk-escrow-signer.js', import.meta.url), 'utf8'), sandbox);
const S = sandbox.window.SKEscrowSigner;

// Three parties, twelve words each.
const words = [S.generateMnemonic(), S.generateMnemonic(), S.generateMnemonic()];
assert.equal(words[0].split(' ').length, 12);
assert.ok(words.every(S.validateMnemonic));
assert.ok(!S.validateMnemonic('abandon abandon abandon'));
const xpubs = words.map((w) => S.xpubFromMnemonic(w, 'main'));
assert.ok(xpubs.every((x) => x.startsWith('xpub')));

// Descriptor exactly as python_api.rpc.build_descriptor() writes it (checksum arbitrary here).
const index = 7;
const descriptor = `wsh(multi(2,${xpubs[0]}/0/${index}/*,${xpubs[1]}/0/${index}/*,${xpubs[2]}/0/${index}/*))#abcdefgh`;
// Objects cross the vm realm boundary, so compare by value, not prototype.
const same = (a, b) => assert.equal(JSON.stringify(a), JSON.stringify(b));
same(S.parseDescriptor(descriptor), { xpubs, index });

// Independent recomputation: derive XPUB/0/N/N by hand and build the script.
const pubkeys = xpubs.map((x) => HDKey.fromExtendedKey(x).deriveChild(0).deriveChild(index).deriveChild(index).publicKey);
const escrow = btc.p2wsh(btc.p2ms(2, pubkeys));
assert.equal(S.escrowAddress(descriptor, 'main'), escrow.address);
same(S.verifyEscrow(descriptor, escrow.address, xpubs[0]), { ok: true, computed: escrow.address, mine: true, error: '' });
assert.equal(S.verifyEscrow(descriptor, escrow.address, 'xpub6nobody').ok, false);
assert.equal(S.verifyEscrow(descriptor, 'bc1qwrongaddress', xpubs[0]).ok, false);
assert.equal(S.verifyEscrow('wsh(sortedmulti(2,a,b,c))', escrow.address, xpubs[0]).ok, false);

// A funding UTXO on the escrow and a sweep PSBT with witness_script + witness_utxo,
// the fields Core's walletcreatefundedpsbt fills in for an imported descriptor.
const fundingTxid = hex.decode('11'.repeat(32));
const seller = btc.p2wpkh(HDKey.fromMasterSeed(new Uint8Array(32).fill(9)).publicKey);
const amount = 66500n;
const fee = 6500n;
const tx = new btc.Transaction();
tx.addInput({
    txid: fundingTxid,
    index: 0,
    witnessUtxo: { script: escrow.script, amount },
    witnessScript: escrow.witnessScript,
    sequence: 0xfffffffd
});
tx.addOutput({ script: seller.script, amount: amount - fee });
const unsigned = base64.encode(tx.toPSBT());

const summary = S.psbtSummary(unsigned, 'main');
same(summary, { inputs: 1, outputs: [{ address: seller.address, sats: 60000 }], feeSat: 6500 });

// Buyer signs, seller signs, combine the two partials, finalize.
const buyerSigned = S.signPsbt(unsigned, words[0], descriptor, 'main');
const sellerSigned = S.signPsbt(unsigned, words[1], descriptor, 'main');
assert.throws(() => S.signPsbt(unsigned, S.generateMnemonic(), descriptor, 'main'), /do not belong/);
const combined = btc.Transaction.fromPSBT(base64.decode(buyerSigned));
combined.combine(btc.Transaction.fromPSBT(base64.decode(sellerSigned)));
assert.equal(combined.getInput(0).partialSig.length, 2);
combined.finalize();
assert.ok(combined.isFinal);
assert.equal(combined.fee, fee);

// Encrypted storage round trip with the sandboxed WebCrypto and localStorage.
const xpub = await S.createKey(words[0], 'correct horse', 'main');
assert.equal(xpub, xpubs[0]);
assert.ok(S.hasKey(xpub));
assert.equal(await S.revealWords(xpub, 'correct horse'), words[0]);
await assert.rejects(S.revealWords(xpub, 'wrong'), /wrong password/);
assert.ok(!JSON.stringify([...store.values()]).includes(words[0].split(' ')[0] + ' '));
assert.equal(await S.signStored(unsigned, xpub, 'correct horse', descriptor, 'main'), buyerSigned);
await assert.rejects(S.importKey(words[2], 'pw', xpubs[0], 'main'), /do not match/);
assert.equal(await S.importKey(words[2], 'pw', xpubs[2], 'main'), xpubs[2]);

console.log('escrow-signer self-check ok');
