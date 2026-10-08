const SW_VERSION = 'v8-bounded-upload-retries';
const API = self.location.origin;

// ==============================================
// 📌 SERVICE WORKER v8 – Bounded upload retries
// ==============================================

const DB_NAME = "R2UploadDB";
const STORE = "pending";

let isProcessingQueue = false;
let retryTimer = null;
let nextQueueAttemptAt = 0;
let queueFailures = 0;

const RETRY_BASE_MS = 30000;
const RETRY_MAX_MS = 300000;

function retryDelay(attempts) {
    return Math.min(RETRY_MAX_MS, RETRY_BASE_MS * (2 ** Math.min(attempts - 1, 4)));
}

function scheduleRetry(at) {
    clearTimeout(retryTimer);
    retryTimer = setTimeout(() => {
        retryTimer = null;
        void processQueue();
    }, Math.max(5000, at - Date.now()));
}

// The deadline includes reading/parsing the response body, not only its headers.
async function fetchJson(url, options = {}, timeoutMs = 30000) {
    const controller = new AbortController();
    let timeout;
    try {
        return await Promise.race([
            (async () => {
                const headers = new Headers(options.headers);
                headers.set("Accept", "application/json");
                const response = await fetch(url, {
                    ...options, headers, signal: controller.signal, credentials: 'include'
                });
                const contentType = response.headers.get("content-type") || "";
                const loginUrl = /\/(login|signin)\/?$/.test(new URL(response.url || url, API).pathname);
                if (response.redirected || loginUrl || [401, 419].includes(response.status) ||
                    (response.ok && contentType.includes("text/html"))) {
                    throw Object.assign(new Error("Opnieuw aanmelden nodig"), { authRequired: true });
                }
                if (!response.ok) {
                    throw Object.assign(new Error(`HTTP ${response.status}`), { status: response.status });
                }
                if (!/application\/(?:[\w.-]+\+)?json\b/i.test(contentType)) {
                    throw new Error("Geen JSON-response ontvangen");
                }
                return await response.json();
            })(),
            new Promise((_, reject) => {
                timeout = setTimeout(() => {
                    reject(new Error("Uploadrequest timeout"));
                    controller.abort();
                }, timeoutMs);
            })
        ]);
    } finally {
        clearTimeout(timeout);
    }
}

// --------------------------
// IndexedDB Helpers
// --------------------------
function openDB() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(DB_NAME, 2); // 👈 Versie omhoog voor schema update
        req.onupgradeneeded = (e) => {
            const db = e.target.result;
            if (!db.objectStoreNames.contains(STORE)) {
                const store = db.createObjectStore(STORE, { keyPath: "id", autoIncrement: true });
                // 👇 Index voor betere deduplicatie
                store.createIndex("by_task_name", ["task_id", "name", "adres_path"], { unique: false });
            }
        };
        req.onsuccess = (e) => resolve(e.target.result);
        req.onerror = (e) => reject(e.target.error);
    });
}

async function getAll() {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE, "readonly");
        const req = tx.objectStore(STORE).getAll();
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}

async function deleteItem(id) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE, "readwrite");
        tx.objectStore(STORE).delete(id);
        tx.oncomplete = () => { db.close(); resolve(); };
        tx.onabort = () => { db.close(); reject(tx.error || new Error("Delete afgebroken")); };
    });
}

// Retry metadata lives on the existing record; failed photos are never removed.
async function deferItem(id) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE, "readwrite");
        const store = tx.objectStore(STORE);
        const request = store.get(id);
        request.onsuccess = () => {
            const item = request.result;
            if (!item) return;
            item.attempts = Math.min((Number(item.attempts) || 0) + 1, 20);
            item.nextRetryAt = Date.now() + retryDelay(item.attempts);
            store.put(item);
        };
        tx.oncomplete = () => { db.close(); resolve(); };
        tx.onabort = () => { db.close(); reject(tx.error || new Error("Retry opslaan afgebroken")); };
    });
}

// 👇 FIX 2: Deduplicatie op naam + task_id + adres_path (niet alleen naam)
async function addItem(data) {
    const currentItems = await getAll();
    const exists = currentItems.find(item =>
        item.name === data.name &&
        item.task_id === data.task_id &&
        item.adres_path === data.adres_path
    );

    if (exists) {
        console.log(`⚠️ SW: Dubbel genegeerd: '${data.name}' voor task ${data.task_id}`);
        return false; // 👈 Return false zodat caller weet dat het een dubbel was
    }

    const db = await openDB();
    let blobData;
    if (data.blob instanceof Blob) {
        blobData = await data.blob.arrayBuffer();
    } else if (data.blob instanceof ArrayBuffer) {
        blobData = data.blob;
    } else {
        blobData = data.blob;
    }

    const clean = {
        name: data.name,
        fileType: data.fileType,
        task_id: data.task_id,
        namespace_id: data.namespace_id,
        adres_path: data.adres_path,
        blob: blobData,
        addedAt: Date.now() // 👈 Voor debugging
    };

    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE, "readwrite");
        const store = tx.objectStore(STORE);
        store.add(clean);
        tx.oncomplete = () => { db.close(); resolve(true); };
        tx.onabort = () => { db.close(); reject(tx.error || new Error("Opslaan afgebroken")); };
    });
}

// --------------------------
// Install / Activate
// --------------------------
self.addEventListener("install", () => self.skipWaiting());
self.addEventListener("activate", (event) => event.waitUntil(self.clients.claim()));

// --------------------------
// FRONTEND → SW Messages
// --------------------------
self.addEventListener("message", (event) => {
    if (event.origin !== self.location.origin) return;
    const data = event.data;
    if (!data || !data.type) return;

    switch (data.type) {
        case "FORCE_PROCESS":
        case "PROCESS_QUEUE":
            event.waitUntil(processQueue());
            break;
        case "ADD_UPLOAD":
            event.waitUntil((async () => {
                if (await addItem(data)) await triggerSync();
                await sendToClients({ type: "QUEUED", file: data.name });
            })());
            break;
        case "SKIP_WAITING":
            event.waitUntil(self.skipWaiting());
            break;
    }
});

async function triggerSync() {
    if ("sync" in self.registration) {
        try {
            await self.registration.sync.register("sync-r2-uploads");
            return;
        } catch (error) {
            console.warn("SW: Background Sync niet beschikbaar:", error.message);
        }
    }
    return processQueue();
}

async function sendToClients(msg) {
    try {
        const allClients = await self.clients.matchAll({ includeUncontrolled: true });
        for (const client of allClients) client.postMessage(msg);
    } catch (error) {
        console.warn("SW: Statusbericht niet afgeleverd:", error.message);
    }
}

// --------------------------
// BACKGROUND SYNC
// --------------------------
self.addEventListener("sync", (event) => {
    if (event.tag === "sync-r2-uploads") {
        event.waitUntil(processQueue().then(remaining => {
            // Let the browser retry unfinished work even if this worker is stopped.
            if (remaining) throw new Error("Foto's wachten nog op upload");
        }));
    }
});

async function getFreshCsrf() {
    const json = await fetchJson(`${API}/csrf-token`, { cache: 'no-store' });
    if (typeof json?.token !== "string" || !json.token) {
        throw new Error("Geen CSRF token ontvangen");
    }
    return json.token;
}

async function processQueue() {
    if (isProcessingQueue) return true;
    if (Date.now() < nextQueueAttemptAt) {
        scheduleRetry(nextQueueAttemptAt);
        return true;
    }

    // Claim synchronously, before the first await.
    isProcessingQueue = true;
    clearTimeout(retryTimer);
    retryTimer = null;
    let done = 0;
    let remaining = null;
    let authRequired = false;

    try {
        const items = await getAll();
        const dueItems = items.filter(item => !item.nextRetryAt || item.nextRetryAt <= Date.now());
        if (dueItems.length) {
            if (!self.navigator.onLine) throw new Error("Offline");
            const freshCsrf = await getFreshCsrf();
            queueFailures = 0;
            nextQueueAttemptAt = 0;

            for (const [index, item] of dueItems.entries()) {
                if (!self.navigator.onLine) throw new Error("Offline");
                let uploadedToR2 = false;
                try {
                    void sendToClients({
                        type: "PROGRESS", current: index + 1,
                        total: dueItems.length, name: item.name
                    });
                    const form = new FormData();
                    form.append("file", new Blob([item.blob], { type: item.fileType }), item.name);
                    form.append("task_id", item.task_id);
                    form.append("namespace_id", item.namespace_id);
                    form.append("adres_path", item.adres_path);
                    form.append("_token", freshCsrf);
                    form.append("unique_id", `${item.id}`);

                    const upload = await fetchJson(`${API}/r2/upload?sw_bypass=true`, {
                        method: "POST", headers: { "X-CSRF-TOKEN": freshCsrf }, body: form
                    }, 120000);
                    if (upload?.success !== true || typeof upload.path !== "string" || !upload.path) {
                        throw new Error("R2 upload niet bevestigd");
                    }
                    uploadedToR2 = true;
                    const registration = await fetchJson(`${API}/r2/register-upload`, {
                        method: "POST",
                        headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": freshCsrf },
                        body: JSON.stringify({
                            task_id: item.task_id, r2_path: upload.path,
                            namespace_id: item.namespace_id, adres_path: item.adres_path
                        })
                    });
                    if (registration?.success !== true ||
                        !["queued", "already_queued", "already_done"].includes(registration.status)) {
                        throw new Error("Registratie niet bevestigd");
                    }

                    await deleteItem(item.id);
                    done++;
                    void sendToClients({ type: "UPLOADED", name: item.name, done, total: dueItems.length });
                    await new Promise(resolve => setTimeout(resolve, 800));
                } catch (error) {
                    if (error.authRequired) throw error;
                    console.warn(`SW: Upload '${item.name}' uitgesteld:`, error.message);
                    await deferItem(item.id);
                    if (uploadedToR2) {
                        void sendToClients({ type: "UPLOAD_PARTIAL", name: item.name, reason: error.message });
                    }
                    if (error.status === 429) await new Promise(resolve => setTimeout(resolve, 5000));
                }
            }
        }
    } catch (error) {
        authRequired = Boolean(error.authRequired);
        nextQueueAttemptAt = Date.now() + retryDelay(++queueFailures);
        console.warn("SW: Queue tijdelijk gepauzeerd:", error.message);
    } finally {
        try {
            remaining = await getAll();
            if (remaining.length) {
                const earliest = Math.min(...remaining.map(item => Number(item.nextRetryAt) || 0));
                scheduleRetry(Math.max(nextQueueAttemptAt, earliest));
            }
            void sendToClients({
                type: authRequired ? "AUTH_REQUIRED" : "COMPLETE",
                uploaded: done, remaining: remaining.length
            });
        } catch (error) {
            nextQueueAttemptAt = Date.now() + retryDelay(++queueFailures);
            scheduleRetry(nextQueueAttemptAt);
            void sendToClients({ type: "QUEUE_ERROR" });
            console.warn("SW: Wachtrij uitlezen mislukt:", error.message);
        } finally {
            isProcessingQueue = false;
        }
    }
    return remaining === null || remaining.length > 0;
}

// --------------------------
// FETCH HANDLER
// --------------------------
self.addEventListener("fetch", (event) => {
    const url = new URL(event.request.url);

    // SW bypass: laat door naar netwerk
    if (url.searchParams.get("sw_bypass") === "true") {
        return;
    }

    // Onderschep directe /r2/upload calls en queue ze
    if (url.pathname === '/r2/upload' && event.request.method === "POST") {
        const queued = saveToQueueAndRespond(event.request);
        event.respondWith(queued);
        event.waitUntil(queued.then(response => {
            if (response.ok) return triggerSync();
        }));
    }
});

async function saveToQueueAndRespond(request) {
    try {
        const formData = await request.clone().formData();
        const file = formData.get("file");

        await addItem({
            name: file.name,
            fileType: file.type,
            blob: file,
            task_id: formData.get("task_id"),
            namespace_id: formData.get("namespace_id"),
            adres_path: formData.get("adres_path"),
        });

        await sendToClients({ type: "QUEUED", file: file.name });

        return new Response(
            JSON.stringify({ success: true, queued: true, message: "In wachtrij geplaatst" }),
            { status: 200, headers: { "Content-Type": "application/json" } }
        );
    } catch (e) {
        console.error("SW: Fout bij opslaan in queue:", e);
        return new Response(
            JSON.stringify({ error: "Storage failed" }),
            { status: 500, headers: { "Content-Type": "application/json" } }
        );
    }
}