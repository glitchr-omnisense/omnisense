// Smoke test of the static export in headless Chrome (CI, Node 22: global fetch and WebSocket).
// Each page, at 1440 and at 390 (phone, touch), light and dark: no console error, no exception, no
// failed request, no sideways scroll, what the page must hold (a CSS selector), and - for a site served
// under a folder (GitHub Pages: /omnisense) - no request nor link of the page outside that folder.
//
//   node tests/smoke/chrome.mjs http://localhost:8135[/omnisense] [screenshots dir]
import { spawn } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const base = (process.argv[2] || 'http://localhost:8135').replace(/\/$/, '');
const origin = new URL(base).origin;
const folder = new URL(base).pathname.replace(/\/$/, '');
const outside = (url) => { try { const u = new URL(url); return u.origin === origin && folder !== '' && u.pathname !== folder && !u.pathname.startsWith(folder + '/'); } catch { return false; } };
const shots = process.argv[3] || null;
const CHROME = process.env.CHROME || 'google-chrome';
const PAGES = [
    ['landing', '/', '.om-hero'],
    ['landing-fr', '/fr', '.om-hero'],
    ['guide', '/guides/architecture', '.manual-prose h1'],
    ['packages', '/packages', '.om-hit'],
    // A package with a manual is its manual: one page, its table of contents in a sidebar.
    ['manual', '/docs/omnitrade/1.x', '.manual-prose [data-path]'],
    ['manual-toc', '/docs/omnibase/4.x', '[data-omnidocs-toc] a'],
    ['api', '/docs/omnidrive/1.x/api', '.manual-prose .doc-part-title'],
    ['libraries', '/families/libraries', '.om-rows'],
    ['news', '/news', '.om-news-list li'],
    ['search', '/search?q=stripe', '.om-site-hits li'],
    ['not-found', '/this-page-does-not-exist', '.om-head'],
    // 404.html answers at any depth: its addresses hold there too.
    ['not-found-deep', '/docs/no/such/page', '.om-head'],
];
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

const port = 9300 + Math.floor(Math.random() * 500);
const chrome = spawn(CHROME, ['--headless=new', '--no-sandbox', `--remote-debugging-port=${port}`, `--user-data-dir=${mkdtempSync(join(tmpdir(), 'chrome-'))}`, '--no-first-run', '--hide-scrollbars', 'about:blank'], { stdio: 'ignore' });
let ready = false;
for (let i = 0; i < 100 && !ready; i++) { await sleep(200); try { await fetch(`http://127.0.0.1:${port}/json/version`); ready = true; } catch {} }
if (!ready) { console.error('Chrome did not start'); process.exit(2); }
if (shots) mkdirSync(shots, { recursive: true });

let failures = 0;
for (const [name, path, selector] of PAGES) {
    for (const [width, height, mobile] of [[1440, 1000, false], [390, 844, true]]) {
        for (const scheme of ['light', 'dark']) {
            const target = await (await fetch(`http://127.0.0.1:${port}/json/new?about:blank`, { method: 'PUT' })).json();
            const ws = new WebSocket(target.webSocketDebuggerUrl);
            await new Promise((r) => ws.addEventListener('open', r, { once: true }));
            let id = 0; const pending = new Map(); const problems = []; const urls = new Map();
            ws.addEventListener('message', (e) => {
                const m = JSON.parse(e.data); const p = m.params || {};
                if (m.id && pending.has(m.id)) { pending.get(m.id)(m.result || {}); pending.delete(m.id); return; }
                if (m.method === 'Runtime.exceptionThrown') problems.push('exception: ' + (p.exceptionDetails.exception?.description || p.exceptionDetails.text).slice(0, 200));
                if (m.method === 'Runtime.consoleAPICalled' && p.type === 'error') problems.push('console: ' + p.args.map((a) => a.value ?? a.description ?? '').join(' ').slice(0, 200));
                if (m.method === 'Network.requestWillBeSent') { urls.set(p.requestId, p.request.url); if (outside(p.request.url)) problems.push(`outside ${folder}/: ${p.request.url}`); }
                // The page for unknown addresses answers 404 itself.
                if (m.method === 'Network.responseReceived' && p.response.status >= 400 && !(name.startsWith('not-found') && p.type === 'Document')) problems.push(`http ${p.response.status}: ${p.response.url}`);
                if (m.method === 'Network.loadingFailed' && !p.canceled) problems.push(`failed ${p.errorText}: ${urls.get(p.requestId)}`);
            });
            const send = (method, params = {}) => new Promise((r) => { const n = ++id; pending.set(n, r); ws.send(JSON.stringify({ id: n, method, params })); });
            for (const d of ['Page', 'Runtime', 'Network']) await send(d + '.enable');
            await send('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile });
            await send('Emulation.setTouchEmulationEnabled', { enabled: mobile });
            await send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-color-scheme', value: scheme }] });
            await send('Page.navigate', { url: base + path });
            await sleep(3000);
            const r = await send('Runtime.evaluate', { returnByValue: true, expression: `JSON.stringify({ sw: document.documentElement.scrollWidth, iw: innerWidth, found: !!document.querySelector(${JSON.stringify(selector)}), links: [...document.querySelectorAll('a[href], link[href], form[action]')].map((e) => e.href || e.action).concat([...document.querySelectorAll('[src]')].map((e) => e.src)) })` });
            const info = JSON.parse(r.result.value);
            const away = [...new Set(info.links.filter(outside))];
            if (away.length) problems.push(`${away.length} link(s) outside ${folder}/: ${away.slice(0, 3).join(' ')}`);
            if (info.sw > info.iw) problems.push(`horizontal scroll: ${info.sw} > ${info.iw}`);
            if (!info.found) problems.push(`missing ${selector}`);
            if (shots) {
                const shot = await send('Page.captureScreenshot', { format: 'png' });
                writeFileSync(join(shots, `${name}-${width}-${scheme}.png`), Buffer.from(shot.data, 'base64'));
            }
            console.log(`${problems.length ? 'FAIL' : 'ok  '} ${path} ${width} ${scheme}`);
            for (const p of problems) console.log('     ' + p);
            failures += problems.length;
            ws.close();
            await fetch(`http://127.0.0.1:${port}/json/close/${target.id}`);
        }
    }
}
chrome.kill();
console.log(failures ? `${failures} problem(s)` : 'no problem');
process.exit(failures ? 1 : 0);
