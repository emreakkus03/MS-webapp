import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { webcrypto } from 'node:crypto';
import { IDBFactory, IDBObjectStore } from 'fake-indexeddb';

const source = readFileSync(new URL('../../public/js/photo-queue.js', import.meta.url), 'utf8');
function browser(db = new IDBFactory(), fetcher = receipt, clock = { now: Date.now() }) {
    const context = vm.createContext({ indexedDB: db, Blob, FormData, AbortController, crypto: webcrypto,
        setTimeout, clearTimeout, navigator: { onLine: true },
        Date: class extends Date { static now() { return clock.now; } }, fetch: fetcher });
    vm.runInContext(source, context);
    return { queue: context.PhotoQueue, context, clock, db };
}
async function receipt(url, { body }) {
    return { ok: true, status: 200, json: async () => ({ success: true, persisted: true,
        path: `folder/${body.get('unique_id')}.jpg`, upload_id: body.get('unique_id') }) };
}
const photo = (extra = {}) => ({ name: 'same-name.jpg', blob: new Blob(['photo']), fileType: 'image/jpeg',
    task_id: '12', namespace_id: 'namespace', adres_path: 'Folder, 1', ...extra });
async function edit(queue, id, fields) {
    const db = await queue.open();
    await new Promise((resolve, reject) => {
        const tx = db.transaction('pending', 'readwrite');
        const store = tx.objectStore('pending');
        const req = store.get(id);
        req.onsuccess = () => store.put({ ...req.result, ...fields });
        tx.oncomplete = resolve; tx.onabort = () => reject(tx.error);
    });
    db.close();
}

test('same filenames retain separate UUIDs; success deletes only acknowledged records', async () => {
    const { queue } = browser();
    const one = await queue.add(photo());
    const two = await queue.add(photo({ blob: new Blob(['other']) }));
    assert.notEqual(one, two);
    assert.equal((await queue.all()).length, 2);
    await queue.process();
    assert.equal((await queue.all()).length, 0);
});

test('transaction abort is not mistaken for local persistence', async () => {
    const { queue } = browser();
    const original = IDBObjectStore.prototype.add;
    IDBObjectStore.prototype.add = function (...args) {
        const req = original.apply(this, args);
        req.onsuccess = () => this.transaction.abort();
        return req;
    };
    try { await assert.rejects(queue.add(photo()), /storage failed/); }
    finally { IDBObjectStore.prototype.add = original; }
    assert.equal((await queue.all()).length, 0);
});

test('offline and network failures retain bytes and retry metadata', async () => {
    const env = browser(undefined, async () => { throw new Error('Network disconnected'); });
    await env.queue.add(photo());
    env.context.navigator.onLine = false;
    await env.queue.process();
    assert.equal((await env.queue.all())[0].attempts, 0);
    env.context.navigator.onLine = true;
    await env.queue.process();
    const [row] = await env.queue.all();
    assert.equal(row.status, 'failed');
    assert.equal(row.attempts, 1);
    assert.match(row.lastError, /disconnected/);
    assert.equal(await row.blob.text(), 'photo');
});

test('HTTP success without matching durable receipt never deletes a photo', async () => {
    for (const body of [{ success: true, path: 'r2/only' }, { success: true, persisted: true, upload_id: 'wrong', path: 'r2/x' }]) {
        const { queue } = browser(undefined, async () => ({ ok: true, json: async () => body }));
        await queue.add(photo());
        await queue.process();
        assert.equal((await queue.all()).length, 1);
    }
});

test('lost response then refresh retries the same ID and bytes', async () => {
    const ids = [];
    const first = browser(undefined, async (url, options) => {
        ids.push(options.body.get('unique_id'));
        throw new Error('Server received it; response was lost');
    });
    await first.queue.add(photo());
    await first.queue.process();
    first.clock.now += 6000;
    const second = browser(first.db, async (url, options) => {
        ids.push(options.body.get('unique_id'));
        return receipt(url, options);
    }, first.clock);
    await second.queue.process();
    assert.equal(ids.length, 2);
    assert.equal(ids[0], ids[1]);
    assert.equal((await second.queue.all()).length, 0);
});

test('two contexts atomically claim a record and do not send it twice', async () => {
    let calls = 0;
    const db = new IDBFactory();
    const fetcher = async (url, options) => { calls++; await new Promise(resolve => setTimeout(resolve, 10)); return receipt(url, options); };
    const a = browser(db, fetcher), b = browser(db, fetcher);
    await a.queue.add(photo());
    await Promise.all([a.queue.process(), a.queue.process(), b.queue.process()]);
    assert.equal(calls, 1);
});

test('terminated worker lease expires and another context recovers the photo', async () => {
    let release;
    const first = browser(undefined, (url, options) => new Promise(resolve => { release = () => resolve(receipt(url, options)); }));
    await first.queue.add(photo());
    const oldRun = first.queue.process();
    while (!release) await new Promise(resolve => setTimeout(resolve, 1));
    first.clock.now += 240001;
    const second = browser(first.db, receipt, first.clock);
    await second.queue.process();
    release(); await oldRun;
    assert.equal((await second.queue.all()).length, 0);
});

test('draft survives refresh with readable blob and uploads only after destination binding', async () => {
    const first = browser();
    const id = await first.queue.add(photo({ status: 'draft' }));
    const restored = browser(first.db);
    await restored.queue.process();
    assert.equal((await restored.queue.all())[0].status, 'draft');
    assert.equal(await (await restored.queue.all())[0].blob.text(), 'photo');
    await restored.queue.prepare(id, { namespace_id: 'namespace', adres_path: 'Folder' });
    await restored.queue.process();
    assert.equal((await restored.queue.all()).length, 0);
});

test('optimization cannot mutate bytes after a failed or active network attempt', async () => {
    const { queue } = browser(undefined, async () => { throw new Error('Interrupted'); });
    const id = await queue.add(photo());
    await queue.optimize(id, new Blob(['compressed']));
    await queue.process();
    await queue.optimize(id, new Blob(['different']));
    assert.equal(await (await queue.all())[0].blob.text(), 'compressed');
});

test('legacy numeric records and ArrayBuffers survive without database recreation', async () => {
    const { queue } = browser();
    const db = await queue.open();
    await new Promise((resolve) => {
        const tx = db.transaction('pending', 'readwrite');
        tx.objectStore('pending').add({ ...photo(), blob: new TextEncoder().encode('legacy').buffer, id: 27 });
        tx.oncomplete = resolve;
    });
    db.close();
    await queue.process();
    assert.equal((await queue.all()).length, 0);
});

test('thirty photos with one failed request retain just the failed source', async () => {
    let failId;
    const { queue } = browser(undefined, async (url, options) => {
        if (options.body.get('unique_id') === failId) throw new Error('Single-photo failure');
        return receipt(url, options);
    });
    for (let i = 0; i < 30; i++) {
        const id = await queue.add(photo());
        if (!i) failId = id;
    }
    await queue.process();
    const remaining = await queue.all();
    assert.equal(remaining.length, 1);
    assert.equal(remaining[0].upload_id, failId);
});

test('Service Worker messages extend lifetime and incomplete sync rejects', async () => {
    const handlers = {};
    const self = { location: { origin: 'https://example.test' }, clients: { claim: async () => {}, matchAll: async () => [] },
        registration: {}, addEventListener: (name, fn) => { handlers[name] = fn; } };
    const context = vm.createContext({ self, importScripts() {}, PhotoQueue: { process: async () => ({ remaining: 1 }) } });
    vm.runInContext(readFileSync(new URL('../../public/sw.js', import.meta.url), 'utf8'), context);
    let promise;
    handlers.message({ origin: self.location.origin, data: { type: 'PROCESS_QUEUE' }, waitUntil(value) { promise = value; } });
    assert.ok(promise);
    await assert.rejects(promise, /remain/);
    handlers.sync({ tag: 'sync-r2-uploads', waitUntil(value) { promise = value; } });
    await assert.rejects(promise, /remain/);
});
