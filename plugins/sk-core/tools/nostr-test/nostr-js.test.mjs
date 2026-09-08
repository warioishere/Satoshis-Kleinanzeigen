// sk-nostr.js in node: the shared browser helpers against a fake relay, with
// the real verifier bundle. No browser, no network.
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { keypair, signed, forged, check } from './lib/nostr.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));
const core = path.resolve(here, '../../assets/js');

// A minimal window: the bundle defines window.skNostrVerify, sk-nostr reads
// window.skNostrConfig and uses WebSocket, setTimeout and document.
globalThis.window = globalThis;
globalThis.document = { createElement: () => ({}), head: { appendChild: () => {} } };
globalThis.skNostrConfig = { relays: ['ws://fake-a', 'ws://fake-b'], verify: 'unused-here' };

const EVENTS = [];
class FakeWS {
  constructor(url) { this.url = url; this.sent = []; setTimeout(() => this.onopen && this.onopen(), 1); }
  send(raw) {
    const msg = JSON.parse(raw);
    if (msg[0] !== 'REQ') { return; }
    const sub = msg[1];
    const match = (f, e) => (!f.kinds || f.kinds.includes(e.kind)) && (!f.authors || f.authors.includes(e.pubkey)) && (!f['#p'] || e.tags.some(t => t[0] === 'p' && f['#p'].includes(t[1])));
    const out = this.url === 'ws://fake-dead' ? [] : EVENTS.filter(e => msg.slice(2).some(f => match(f, e)));
    setTimeout(() => {
      out.forEach(e => this.onmessage && this.onmessage({ data: JSON.stringify(['EVENT', sub, e]) }));
      if (this.url !== 'ws://fake-dead') { this.onmessage && this.onmessage({ data: JSON.stringify(['EOSE', sub]) }); }
      else { this.onerror && this.onerror(); }
    }, 2);
  }
  close() {}
}
globalThis.WebSocket = FakeWS;

// Load the verifier bundle, then sk-nostr.js.
new Function(fs.readFileSync(path.join(core, 'sk-nostr-verify.js'), 'utf8'))();
new Function(fs.readFileSync(path.join(core, 'sk-nostr.js'), 'utf8'))();
const n = globalThis.skNostr;

const a = keypair(), b = keypair();
const good = signed(a, 3, [['p', b.pub], ['p', 'x" onmouseover="1'], ['e', b.pub]], '', 1700000000);
const newerForged = forged(a.pub, 3, [['p', 'c'.repeat(64)]], '', 1700009000);
const other = signed(b, 1, [['t', 'x']], 'hi', 1700000100);
EVENTS.push(good, newerForged, other, { id: 'nothex', pubkey: a.pub, kind: 3, created_at: 1, tags: [], content: '', sig: 'x' });

(async () => {
  check.eq(typeof n, 'object', 'window.skNostr defined');
  check.eq([n.isHex64(a.pub), n.isHex64(a.pub.toUpperCase()), n.isHex64('x')], [true, false, false], 'isHex64: lowercase 64 hex only');
  check.eq(n.hexKey(a.pub.toUpperCase()), a.pub, 'hexKey lowercases');
  check.eq(n.hexKey('constructor'), '', 'hexKey rejects non-keys');
  check.eq(n.dict()['constructor'], undefined, 'dict() has no prototype');
  check.eq(n.own({ a: 1 }, 'constructor'), false, 'own() ignores the prototype');
  check.eq(n.esc('<a href="x">\'&'), '&lt;a href=&quot;x&quot;&gt;&#39;&amp;', 'esc() escapes the five characters');
  check.eq(n.tagValues(good, 'p'), [b.pub], 'tagValues() hex only by default, drops the poisoned tag');
  check.eq(n.tagValues(good, 'p', { hex: false }).length, 2, 'tagValues({hex:false}) keeps raw values');
  check.eq(n.tagValue(good, 'e'), b.pub, 'tagValue() first value');
  check.eq(n.tagValue(good, 'zzz'), '', 'tagValue() missing -> empty');

  const q = await n.query([{ kinds: [3], authors: [a.pub] }]);
  check.eq(q.answered, 2, 'query(): both fake relays answered');
  check.eq(q.events.map(e => e.id), [good.id], 'query(): forged newer list and malformed event dropped, one signed kept, deduplicated');
  const latest = n.latestPerAuthor(q.events);
  check.eq(latest[a.pub] && latest[a.pub].created_at, 1700000000, 'latestPerAuthor(): the signed one');

  const raw = await n.query([{ kinds: [3], authors: [a.pub] }], { verify: false });
  check.eq(raw.events.length, 2, 'query({verify:false}): raw well-formed events (malformed still dropped)');

  const dead = await n.query([{ kinds: [1] }], { relays: ['ws://fake-dead'] });
  check.eq([dead.events.length, dead.answered], [0, 0], 'query(): a dead relay gives nothing and no EOSE');

  const one = await n.query([{ kinds: [1] }], { count: 1 });
  check.eq([one.events.length, one.answered], [1, 1], 'query({count:1}): only the first relay asked');

  const seen = [];
  const sub = n.subscribe([{ kinds: [1] }], e => seen.push(e.id), { timeout: 500 });
  await new Promise(r => setTimeout(r, 50));
  check.eq(seen, [other.id], 'subscribe(): verified event delivered once across two relays');
  sub.close();

  check.eq(await n.verifyOne(good), true, 'verifyOne(): signed');
  check.eq(await n.verifyOne(newerForged), false, 'verifyOne(): forged');
  check.eq(await n.verifyOne({ id: 1 }), false, 'verifyOne(): malformed');

  globalThis.nostr = undefined;
  const none = await n.waitForNostr(300);
  check.eq(none, null, 'waitForNostr(): null when no extension appears');
  setTimeout(() => { globalThis.nostr = { getPublicKey: async () => a.pub }; }, 100);
  const ext = await n.waitForNostr(1000);
  check.eq(!!ext, true, 'waitForNostr(): resolves once the extension injects');

  check.done();
})().catch(e => { console.error('ERR', e); process.exit(1); });
