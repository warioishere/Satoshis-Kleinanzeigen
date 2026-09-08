// Test helpers: keys and events with real BIP-340 signatures, or forged ones.
import { schnorr } from '@noble/curves/secp256k1.js';
import { sha256 } from '@noble/hashes/sha2.js';
import { bytesToHex, hexToBytes, utf8ToBytes, randomBytes } from '@noble/hashes/utils.js';

export function keypair() {
  const priv = schnorr.utils.randomSecretKey();
  return { priv, pub: bytesToHex(schnorr.getPublicKey(priv)) };
}

export function randomHex(bytes = 32) {
  return bytesToHex(randomBytes(bytes));
}

function withId(ev) {
  ev.id = bytesToHex(sha256(utf8ToBytes(JSON.stringify([0, ev.pubkey, ev.created_at, ev.kind, ev.tags, ev.content]))));
  return ev;
}

let counter = 0;

/** An event signed by `kp`. */
export function signed(kp, kind, tags, content = '', created_at = null) {
  const ev = withId({ pubkey: kp.pub, kind, tags, content, created_at: created_at ?? 1700000000 + (++counter) });
  ev.sig = bytesToHex(schnorr.sign(hexToBytes(ev.id), kp.priv));
  return ev;
}

/** An event with a correct id and a worthless signature, claiming `pubkey`. */
export function forged(pubkey, kind, tags, content = '', created_at = null) {
  const ev = withId({ pubkey, kind, tags, content, created_at: created_at ?? 1700000000 + (++counter) });
  ev.sig = bytesToHex(randomBytes(64));
  return ev;
}

/** Tiny assertion helper: prints PASS/FAIL, remembers failures. */
export const check = {
  failures: 0,
  eq(actual, expected, label) {
    const ok = JSON.stringify(actual) === JSON.stringify(expected);
    if (!ok) check.failures++;
    console.log((ok ? 'PASS ' : 'FAIL ') + label + (ok ? '' : `  (got ${JSON.stringify(actual)}, expected ${JSON.stringify(expected)})`));
  },
  ok(cond, label) { check.eq(!!cond, true, label); },
  done() { if (check.failures) { console.log(`${check.failures} check(s) failed`); process.exit(1); } console.log('all checks passed'); },
};
