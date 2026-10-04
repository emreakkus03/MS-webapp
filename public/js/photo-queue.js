/* Shared by the dashboard and Service Worker. Keep v2 and numeric primary keys
 * readable; upload_id is a separate permanent UUID, assigned atomically. */
(() => {
    const DB_NAME = 'R2UploadDB', STORE = 'pending';
    let running;
    function open() {
        return new Promise((resolve, reject) => {
            const req = indexedDB.open(DB_NAME, 2);
            req.onupgradeneeded = () => {
                const db = req.result;
                if (!db.objectStoreNames.contains(STORE)) {
                    const store = db.createObjectStore(STORE, { keyPath: 'id', autoIncrement: true });
                    store.createIndex('by_task_name', ['task_id', 'name', 'adres_path'], { unique: false });
                }
            };
            req.onsuccess = () => { req.result.onversionchange = () => req.result.close(); resolve(req.result); };
            req.onerror = () => reject(req.error);
            req.onblocked = () => reject(new Error('Photo database upgrade blocked; close older tabs.'));
        });
    }
    async function transaction(mode, action) {
        const db = await open();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(STORE, mode);
            let result;
            tx.oncomplete = () => { db.close(); resolve(result); };
            tx.onabort = tx.onerror = () => { db.close(); reject(tx.error || new Error('Photo storage failed')); };
            action(tx.objectStore(STORE), value => { result = value; });
        });
    }
    const all = () => transaction('readwrite', (store, done) => {
        const req = store.getAll();
        req.onsuccess = () => {
            // Assign old rows an identity before a task manifest can reference them.
            for (const item of req.result) {
                if (!item.upload_id) { item.upload_id = crypto.randomUUID(); store.put(item); }
            }
            done(req.result);
        };
    });
    async function add(data) {
        const upload_id = data.upload_id || crypto.randomUUID();
        return transaction('readwrite', (store, done) => {
            // UUID deduplication only. A filename is not a photo identity.
            const req = store.getAll();
            req.onsuccess = () => {
                const existing = req.result.find(row => row.upload_id === upload_id);
                if (existing) { done(existing.upload_id); return; }
                store.add({ ...data, id: upload_id, upload_id, attempts: 0, status: data.status || 'pending', createdAt: Date.now() });
                done(upload_id);
            };
        });
    }
    async function prepare(upload_id, destination) {
        return transaction('readwrite', (store) => {
            const req = store.get(upload_id);
            req.onsuccess = () => {
                const item = req.result;
                if (item?.status === 'draft') store.put({ ...item, ...destination, status: 'pending' });
            };
        });
    }
    async function optimize(upload_id, blob) {
        return transaction('readwrite', (store) => {
            const req = store.getAll();
            req.onsuccess = () => {
                const item = req.result.find(row => row.upload_id === upload_id);
                // A claimed upload has immutable bytes, including on retries.
                if (item && !item.attempts) store.put({ ...item, blob, fileType: blob.type });
            };
        });
    }
    function claim(id) {
        return transaction('readwrite', (store, done) => {
            const req = store.get(id);
            req.onsuccess = () => {
                const item = req.result;
                if (!item || item.status === 'draft' || item.leaseUntil > Date.now() || item.nextAttemptAt > Date.now()) return;
                item.upload_id ||= crypto.randomUUID();
                item.owner = crypto.randomUUID();
                item.leaseUntil = Date.now() + 240000;
                item.attempts = (item.attempts || 0) + 1;
                item.lastAttemptAt = Date.now();
                item.status = 'uploading';
                store.put(item); done(item);
            };
        });
    }
    function settle(item, error) {
        return transaction('readwrite', (store) => {
            const req = store.get(item.id);
            req.onsuccess = () => {
                const current = req.result;
                if (!current || current.owner !== item.owner) return;
                if (!error) { store.delete(item.id); return; }
                store.put({ ...current, status: 'failed', leaseUntil: 0,
                    lastError: String(error.message || error),
                    nextAttemptAt: Date.now() + Math.min(300000, 5000 * 2 ** Math.min(item.attempts - 1, 6)) });
            };
        });
    }
    async function request(url, options = {}) {
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 120000);
        try {
            const res = await fetch(url, { ...options, credentials: 'include', cache: 'no-store',
                headers: { Accept: 'application/json', ...options.headers }, signal: controller.signal });
            if (!res.ok || res.redirected) throw new Error(`Request failed (${res.status}); photo retained.`);
            return await res.json();
        } finally { clearTimeout(timer); }
    }
    function process(notify = async () => {}) {
        // Assign the promise before any asynchronous database read.
        if (running) return running;
        running = (async () => {
            let uploaded = 0;
            const items = await all();
            for (const candidate of items) {
                if (globalThis.navigator?.onLine === false) break;
                const item = await claim(candidate.id);
                if (!item) continue;
                try {
                    await notify({ type: 'PROGRESS', current: uploaded, total: items.length, name: item.name });
                    const form = new FormData();
                    form.append('file', new Blob([item.blob], { type: item.fileType }), item.name);
                    for (const field of ['task_id', 'namespace_id', 'adres_path']) form.append(field, item[field]);
                    form.append('unique_id', item.upload_id);
                    // Both endpoints are already CSRF-exempt; authentication is still required.
                    const receipt = await request('/r2/upload?sw_bypass=true', { method: 'POST', body: form });
                    if (receipt.success !== true || receipt.persisted !== true ||
                        receipt.upload_id !== item.upload_id || !receipt.path) {
                        throw new Error('No durable server receipt; photo retained.');
                    }
                    await settle(item);
                    uploaded++;
                    await notify({ type: 'UPLOADED', upload_id: item.upload_id, task_id: item.task_id, name: item.name });
                } catch (error) {
                    await settle(item, error);
                    await notify({ type: 'UPLOAD_PARTIAL', upload_id: item.upload_id, name: item.name, reason: error.message });
                }
            }
            const remaining = (await all()).length;
            await notify({ type: 'COMPLETE', uploaded, remaining });
            return { uploaded, remaining };
        })().finally(() => { running = null; });
        return running;
    }
    globalThis.PhotoQueue = { open, all, add, prepare, optimize, process, request };
})();
