// Uses an isolated Laravel-rendered HTML fixture and a local draft API simulator.
// Backend authorization, transactions and storage are covered by the PHP tests.
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { existsSync, readFileSync, mkdirSync } from 'node:fs';
import { dirname, extname, join, resolve, sep } from 'node:path';
import { tmpdir } from 'node:os';
import { spawn } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const fixture = process.argv[2];
assert(fixture && existsSync(fixture), 'Pass a Laravel-rendered purchase-page HTML fixture.');
const chrome = process.env.CHROME_BIN || [
    'C:/Program Files/Google/Chrome/Application/chrome.exe',
    'C:/Program Files/BraveSoftware/Brave-Browser/Application/brave.exe',
    '/usr/bin/chromium', '/usr/bin/google-chrome',
].find(existsSync);
assert(chrome, 'Set CHROME_BIN to an installed Chrome/Chromium binary.');
const work = join(tmpdir(), `rgmo-po-browser-${Date.now()}`);
mkdirSync(work, { recursive: true });
const drafts = new Map();
let purchaseCount = 0;
let origin;
const html = readFileSync(fixture, 'utf8');
const resource = (draft) => ({ ...draft, updated_at: new Date().toISOString() });
const send = (response, status, data) => {
    response.writeHead(status, { 'Content-Type': 'application/json' });
    response.end(JSON.stringify(data));
};

async function readBody(request) {
    const chunks = [];
    for await (const chunk of request) chunks.push(chunk);
    const body = Buffer.concat(chunks);
    if (request.headers['content-type']?.includes('application/json')) return JSON.parse(body.toString());
    const values = await new Request(`${origin}/`, { method: 'POST', headers: request.headers, body }).formData();
    const result = { payload: {}, values };
    for (const [key, value] of values) {
        const item = key.match(/^payload\[items\]\[(\d+)\]\[(\w+)\]$/);
        const header = key.match(/^payload\[(\w+)\]$/);
        if (item) {
            result.payload.items ||= [];
            result.payload.items[Number(item[1])] ||= {};
            result.payload.items[Number(item[1])][item[2]] = value;
        } else if (header) result.payload[header[1]] = value;
        else result[key] = value;
    }
    return result;
}

const server = createServer(async (request, response) => {
    try {
        const url = new URL(request.url, origin);
        if (url.pathname === '/') {
            response.writeHead(200, { 'Content-Type': 'text/html' });
            response.end(html.replaceAll('http://localhost', origin));
            return;
        }
        if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/images/')) {
            const target = resolve(root, 'public', `.${url.pathname}`);
            if (!target.startsWith(resolve(root, 'public') + sep) || !existsSync(target)) return send(response, 404, {});
            const types = { '.js': 'text/javascript', '.css': 'text/css', '.png': 'image/png', '.jpeg': 'image/jpeg' };
            response.writeHead(200, { 'Content-Type': types[extname(target)] || 'application/octet-stream' });
            response.end(readFileSync(target));
            return;
        }
        const match = url.pathname.match(/^\/procurement-drafts\/([a-f0-9-]+)(\/receipt)?$/);
        if (match) {
            const id = match[1];
            const current = drafts.get(id);
            if (request.method === 'GET') {
                if (!current) return send(response, 404, {});
                if (match[2]) { response.end('%PDF-1.4\n%%EOF'); return; }
                return send(response, 200, resource(current));
            }
            assert(request.headers['x-csrf-token'], 'Autosave sends the page CSRF token.');
            const input = await readBody(request);
            if (current && (current.status !== 'active' || current.revision !== Number(input.expected_revision))) {
                return send(response, 409, { message: 'Changed in another tab.', draft: resource(current) });
            }
            const draft = current || { id, revision: 0, status: 'active', receipt: null };
            draft.revision += 1;
            if (request.method === 'DELETE') { draft.status = 'discarded'; draft.payload = {}; }
            else {
                draft.payload = input.payload;
                if (input.receipt?.size) draft.receipt = { name: input.receipt.name, url: `${origin}/procurement-drafts/${id}/receipt`, available: true };
                else if (input.remove_receipt === true || input.remove_receipt === '1') draft.receipt = null;
            }
            drafts.set(id, draft);
            return send(response, current ? 200 : 201, resource(draft));
        }
        if (url.pathname === '/procurements' && request.method === 'POST') {
            const input = await readBody(request);
            const draft = drafts.get(input.procurement_draft_id);
            assert(draft && draft.revision === Number(input.procurement_draft_revision));
            assert.equal(draft.status, 'active');
            draft.status = 'completed'; draft.completed_procurement_id = ++purchaseCount;
            response.writeHead(303, { Location: `/procurements/${purchaseCount}` }); response.end(); return;
        }
        if (/^\/procurements\/\d+$/.test(url.pathname)) { response.end('<h1>Purchase saved</h1>'); return; }
        if (url.pathname === '/notifications/unread-count') return send(response, 200, { count: 0 });
        send(response, 404, {});
    } catch (error) { send(response, 500, { message: error.message }); }
});
await new Promise((ready) => server.listen(0, '127.0.0.1', ready));
origin = `http://127.0.0.1:${server.address().port}`;

const browser = spawn(chrome, [
    '--headless=new', '--remote-debugging-port=0', `--user-data-dir=${work}`,
    '--no-first-run', '--disable-extensions', '--disable-background-networking',
    '--disable-default-apps', '--no-sandbox', '--window-size=1280,900', 'about:blank',
], { windowsHide: true, stdio: 'ignore' });
const sockets = [];
const pause = (ms) => new Promise((ready) => setTimeout(ready, ms));
async function waitUntil(check, label, timeout = 15000) {
    const end = Date.now() + timeout;
    while (Date.now() < end) {
        try { if (await check()) return; } catch {}
        await pause(80);
    }
    throw new Error(`Timed out: ${label}`);
}

async function connect(url) {
    const socket = new WebSocket(url); sockets.push(socket);
    await new Promise((ready, reject) => { socket.addEventListener('open', ready, { once: true }); socket.addEventListener('error', reject, { once: true }); });
    let sequence = 0;
    const pending = new Map();
    const errors = [];
    socket.addEventListener('message', ({ data }) => {
        const message = JSON.parse(data);
        if (message.id && pending.has(message.id)) {
            const { resolve: done, reject } = pending.get(message.id); pending.delete(message.id);
            message.error ? reject(new Error(JSON.stringify(message.error))) : done(message.result);
        }
        if (message.method === 'Runtime.exceptionThrown') errors.push(message.params.exceptionDetails.text + ': ' + (message.params.exceptionDetails.exception?.description || ''));
        if (message.method === 'Page.javascriptDialogOpening') void command('Page.handleJavaScriptDialog', { accept: true });
    });
    const command = (method, params = {}) => new Promise((done, reject) => {
        const id = ++sequence; pending.set(id, { resolve: done, reject }); socket.send(JSON.stringify({ id, method, params }));
    });
    const evaluate = async (expression) => {
        const result = await command('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
        if (result.exceptionDetails) throw new Error(JSON.stringify(result.exceptionDetails));
        return result.result.value;
    };
    await command('Runtime.enable'); await command('Page.enable');
    return { command, evaluate, errors };
}

try {
    const activePort = join(work, 'DevToolsActivePort');
    await waitUntil(() => existsSync(activePort), 'Chrome debugging endpoint');
    const port = readFileSync(activePort, 'utf8').split('\n')[0];
    const debuggerOrigin = `http://127.0.0.1:${port}`;
    const pages = await (await fetch(`${debuggerOrigin}/json/list`)).json();
    const first = await connect(pages.find((page) => page.type === 'page').webSocketDebuggerUrl);
    const open = async (page) => {
        await page.command('Page.navigate', { url: origin });
        await waitUntil(() => page.evaluate('!!window.bootstrap && !!document.querySelector("[data-po-draft-form]")'), 'purchase form loaded');
        await page.evaluate('document.querySelector("[data-bs-target=\\"#newPurchaseOrderModal\\"]").click()');
        await waitUntil(() => page.evaluate('document.querySelector("#newPurchaseOrderModal").classList.contains("show")'), 'purchase modal open');
    };
    await open(first);
    await first.evaluate(`(() => {
        const set = (name,value) => { const input=document.querySelector('[name="'+name+'"]'); input.value=value; input.dispatchEvent(new Event('input',{bubbles:true})); input.dispatchEvent(new Event('change',{bubbles:true})); };
        set('po_number','PO-BROWSER-RECOVERY'); set('supplier_id','1'); set('funding_source_id','1');
        set('items[0][inventory_item_id]','1'); set('items[0][ordered_quantity]','1.'); set('items[0][unit_cost]','10.50');
        set('notes','unfinished terms'); document.querySelector('#add-po-item').click();
        const transfer=new DataTransfer(); transfer.items.add(new File(['%PDF-1.4\\n%%EOF'],'quotation.pdf',{type:'application/pdf'}));
        const file=document.querySelector('#receipt'); file.files=transfer.files; file.dispatchEvent(new Event('change',{bubbles:true}));
    })()`);
    await waitUntil(() => [...drafts.values()].some((draft) => draft.receipt && draft.payload.items.length === 2), 'receipt and incomplete rows autosaved');
    await waitUntil(() => first.evaluate('document.querySelector("[data-po-autosave-status]").textContent.startsWith("Entries saved")'), 'save acknowledged');
    const original = [...drafts.values()][0];
    assert.equal(original.payload.items[0].ordered_quantity, '1.');
    assert.equal(purchaseCount, 0);
    await first.command('Page.reload');
    await waitUntil(() => first.evaluate('!!window.bootstrap && !!document.querySelector("[data-po-draft-form]")'), 'reloaded purchase page');
    await first.evaluate(`(() => { const tbody=document.querySelector('[data-po-drafts-body]'); const button=document.createElement('button');button.dataset.poResume='${original.id}';button.textContent='Continue';tbody.append(button);button.click(); })()`);
    await waitUntil(() => first.evaluate('document.querySelector("[name=po_number]").value === "PO-BROWSER-RECOVERY"'), 'saved form restored after reload');
    assert.equal(await first.evaluate('document.querySelector(".po-quantity").value'), '1.');
    assert.equal(await first.evaluate('document.querySelectorAll(".po-item-row").length'), 2);
    assert.equal(await first.evaluate('document.querySelector("#receipt").files.length'), 0);
    assert.equal(await first.evaluate('document.querySelector("[data-po-receipt-link]").textContent'), 'quotation.pdf');

    await first.evaluate(`(() => { const field=document.querySelector('[name=notes]');field.value='saved before navigation';field.dispatchEvent(new Event('input',{bubbles:true}));const link=document.createElement('a');link.href='/inventory';document.body.append(link);link.click(); })()`);
    await waitUntil(() => original.payload.notes === 'saved before navigation', 'navigation flushes an edit before the debounce');
    await waitUntil(() => first.evaluate('window.location.pathname === "/inventory"'), 'navigation continues after acknowledgement');
    await first.command('Page.navigate', { url: origin });
    await waitUntil(() => first.evaluate('!!window.bootstrap && !!document.querySelector("[data-po-draft-form]")'), 'return from another page');
    await first.evaluate(`(() => { const button=document.createElement('button');button.dataset.poResume='${original.id}';document.querySelector('[data-po-drafts-body]').append(button);button.click(); })()`);
    await waitUntil(() => first.evaluate('document.querySelector("[name=notes]").value === "saved before navigation"'), 'navigated work is recoverable');

    const secondTarget = await (await fetch(`${debuggerOrigin}/json/new?${encodeURIComponent(origin)}`, { method: 'PUT' })).json();
    const second = await connect(secondTarget.webSocketDebuggerUrl);
    await waitUntil(() => second.evaluate('!!window.bootstrap && !!document.querySelector("[data-po-draft-form]")'), 'second tab loaded');
    await second.evaluate(`(() => { const button=document.createElement('button');button.dataset.poResume='${original.id}';document.querySelector('[data-po-drafts-body]').append(button);button.click(); })()`);
    await waitUntil(() => second.evaluate('document.querySelector("[name=po_number]").value === "PO-BROWSER-RECOVERY"'), 'second tab restored');
    await first.evaluate(`(() => { const field=document.querySelector('[name=notes]');field.value='saved in first tab';field.dispatchEvent(new Event('input',{bubbles:true})); })()`);
    await waitUntil(() => original.payload.notes === 'saved in first tab', 'first tab writes newer revision');
    await second.evaluate(`(() => { const field=document.querySelector('[name=notes]');field.value='private second-tab changes';field.dispatchEvent(new Event('input',{bubbles:true})); })()`);
    await waitUntil(() => second.evaluate('document.querySelector("[data-po-autosave-status]").textContent.includes("Another tab")'), 'stale tab reports conflict');
    assert.equal(original.payload.notes, 'saved in first tab');
    assert.equal(await second.evaluate('document.querySelector("[name=notes]").value'), 'private second-tab changes');
    await second.evaluate('document.querySelector("[data-po-autosave-copy]").click()');
    await waitUntil(() => drafts.size === 2 && [...drafts.values()].some((draft) => draft.id !== original.id && draft.payload.notes === 'private second-tab changes'), 'conflicting local work saved as a separate entry');
    const copy = [...drafts.values()].find((draft) => draft.id !== original.id);
    assert.equal(copy.receipt, null, 'a saved attachment is not silently shared with a new copy');
    assert.equal(original.payload.notes, 'saved in first tab');

    await first.evaluate(`(() => { const quantity=document.querySelector('.po-quantity');quantity.value='5';quantity.dispatchEvent(new Event('input',{bubbles:true}));document.querySelector('[data-po-draft-form]').requestSubmit(); })()`);
    await waitUntil(() => purchaseCount === 1, 'explicit save creates one PO');
    await waitUntil(() => first.evaluate('document.body.textContent.includes("Purchase saved")'), 'normal purchase redirect');
    assert.equal(original.status, 'completed');
    assert.equal(original.payload.items.length, 2, 'empty extra row did not block explicit save');
    assert.equal(purchaseCount, 1);
    await second.evaluate(`document.querySelector('[data-po-discard="${copy.id}"]').click()`);
    await waitUntil(() => copy.status === 'discarded', 'explicit discard retires the separate entry');
    assert.deepEqual(first.errors, []);
    assert.deepEqual(second.errors, []);
    console.log('PASS: real Chrome editing, partial numbers, empty rows, receipts, reload/recovery, navigation flush, conflicting tabs, copy, discard and explicit save.');
} finally {
    sockets.forEach((socket) => socket.close());
    browser.kill();
    server.closeAllConnections();
    server.close();
}
