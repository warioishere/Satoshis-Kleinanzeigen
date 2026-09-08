// The social graph signal in the viewer's browser, against a fake relay.
//
// window.nostr and window.WebSocket are replaced before the page loads; the
// fake relay answers REQs from a fixed set of events, some signed and some
// forged. Only the signed ones may count, nothing from a relay may reach the
// DOM unescaped, and a vendor listing themself is not a contact.
//
//   BASE_URL=https://staging.example  STORE_SLUG=testshop-xxx  VENDOR_PUBKEY=<hex>  node graph.test.mjs
import { createRequire } from 'module';
import { keypair, signed, forged, check } from './lib/nostr.mjs';

const require = createRequire(import.meta.url);
let puppeteer;
try { puppeteer = require('puppeteer'); } catch (e) { puppeteer = require(process.env.SK_PUPPETEER || '/home/users/satoshiskleinazeigen/css-audit/node_modules/puppeteer'); }

const BASE = process.env.BASE_URL || 'https://staging.satoshiskleinanzeigen.space';
const SLUG = process.env.STORE_SLUG || 'testshop-xxx';
const VENDOR = process.env.VENDOR_PUBKEY || '3ead455ad1247ac4b0304757e4bec9ccbe6e90d0b48572c20d6f802f01d69980';

const viewer = keypair(), contact = keypair(), contact2 = keypair(), stranger = keypair();
const EVIL = 'x" onmouseover="document.title=1';

const EVENTS = [
  signed(viewer, 3, [['p', contact.pub], ['p', contact2.pub], ['p', VENDOR], ['p', EVIL], ['p', 'constructor']], '', 1700001000),
  forged(viewer.pub, 3, [['p', stranger.pub], ['p', VENDOR]], '', 1700009000),        // newer, forged: ignored
  signed(contact, 3, [['p', VENDOR]]),                                                 // counts
  forged(contact2.pub, 3, [['p', VENDOR]]),                                            // forged: ignored
  signed(stranger, 3, [['p', VENDOR]]),                                                // not a contact
  signed(contact, 1984, [['p', VENDOR, 'spam']], 'real report'),                       // counts
  forged(contact2.pub, 1984, [['p', VENDOR, 'illegal']], 'forged report'),             // ignored
  signed(contact, 1984, [['p', VENDOR, 'malware']], 'Automated: classifier'),          // filtered
  signed(contact, 1984, [['p', VENDOR, 'constructor']], 'prototype type'),             // unknown type
  signed(contact, 0, [], JSON.stringify({ name: '<b>Real</b>' }), 1700002000),
  forged(contact.pub, 0, [], JSON.stringify({ name: 'Forged' }), 1700009000),
];

(async () => {
  const browser = await puppeteer.launch({ headless: 'new', args: ['--no-sandbox', '--disable-dev-shm-usage', '--ignore-certificate-errors'], protocolTimeout: 120000 });
  const page = await browser.newPage();
  let verifierStatus = 0, coreStatus = 0;
  const pageErrors = [];
  page.on('response', r => { if (r.url().includes('sk-nostr-verify')) verifierStatus = r.status(); else if (r.url().includes('/sk-nostr.js')) coreStatus = r.status(); });
  page.on('pageerror', e => pageErrors.push(String(e.message || e)));
  page.on('console', m => { if (m.type() === 'error' && /sk-nostr|sk-social-graph|sk-zaps|sk-key-binding/.test(m.text())) pageErrors.push(m.text()); });

  await page.evaluateOnNewDocument((VIEWER, EVENTS) => {
    window.nostr = { getPublicKey: async () => VIEWER, signEvent: async (e) => e };
    const match = (f, e) => (!f.kinds || f.kinds.includes(e.kind))
      && (!f.authors || f.authors.includes(e.pubkey))
      && (!f['#p'] || e.tags.some(t => t[0] === 'p' && f['#p'].includes(t[1])));
    class FakeWS {
      constructor(url) { this.url = url; setTimeout(() => this.onopen && this.onopen(), 5); }
      send(raw) {
        const msg = JSON.parse(raw); const sub = msg[1];
        const out = EVENTS.filter(e => msg.slice(2).some(f => match(f, e)));
        setTimeout(() => {
          out.forEach(e => this.onmessage && this.onmessage({ data: JSON.stringify(['EVENT', sub, e]) }));
          this.onmessage && this.onmessage({ data: JSON.stringify(['EOSE', sub]) });
        }, 10);
      }
      close() {}
    }
    window.WebSocket = FakeWS;
  }, viewer.pub, EVENTS);

  await page.goto(`${BASE}/store/${SLUG}/vertrauen/?nc=${Date.now()}`, { waitUntil: 'domcontentloaded', timeout: 90000 });
  await page.waitForFunction(() => document.querySelector('.sk-trust-graph:not([hidden])'), { timeout: 30000 }).catch(() => {});
  await new Promise(r => setTimeout(r, 1500));

  const r = await page.evaluate(() => ({
    chipTexts: [...document.querySelectorAll('.sk-trust-graph:not([hidden]) .sk-trust-graph-text')].map(e => e.textContent),
    chipNames: [...document.querySelectorAll('.sk-trust-graph .sk-trust-pop li a')].map(a => a.textContent),
    reportTexts: [...document.querySelectorAll('.sk-trust-report .sk-trust-report-text')].map(e => e.textContent),
    reportTypes: [...document.querySelectorAll('.sk-trust-report .sk-trust-pop-type')].map(e => e.textContent),
    injected: document.querySelectorAll('.sk-trust-pop [onmouseover]').length,
    badHref: [...document.querySelectorAll('.sk-trust-pop a')].filter(a => !/^https:\/\/njump\.me\/[0-9a-f]{64}$/.test(a.getAttribute('href'))).length,
    verifier: typeof window.skNostrVerify,
    core: typeof window.skNostr,
    zapsLoaded: !!document.querySelector('script[src*="sk-zaps.js"]'),
    localViewer: Object.keys(localStorage).some(k => k.endsWith(':viewer')),
  }));
  await browser.close();

  check.eq(coreStatus, 200, 'sk-nostr.js loaded');
  check.eq(r.core, 'object', 'window.skNostr present');
  check.eq(pageErrors, [], 'no script errors on the page');
  check.eq(verifierStatus, 200, 'verifier bundle loaded');
  check.eq(r.verifier, 'function', 'window.skNostrVerify present');
  check.ok(r.chipTexts.length >= 1, 'graph chip visible');
  check.ok(r.chipTexts.every(t => t === 'Du folgst diesem Anbieter · 1 deiner Kontakte folgt'), 'exactly one signed contact counted, forged/self/stranger ignored');
  check.ok(r.chipNames.length > 0 && r.chipNames.every(n => n === '<b>Real</b>'), 'signed profile name used and escaped');
  check.ok(r.reportTexts.length >= 1 && r.reportTexts.every(t => t === '1 deiner Kontakte hat diesen Anbieter gemeldet'), 'one signed report counted');
  check.ok(r.reportTypes.every(t => t === 'Spam'), 'unknown/prototype report type dropped');
  check.eq(r.injected, 0, 'no injected attributes');
  check.eq(r.badHref, 0, 'every popup link is njump + 64 hex');
  check.eq(r.localViewer, false, 'extension key not persisted in localStorage');
  check.done();
})().catch(e => { console.error('ERR', e); process.exit(1); });
