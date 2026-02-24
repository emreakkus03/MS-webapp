const SW_VERSION = 'v7-reliability-fix';
const API = self.location.origin;

// ==============================================
// 📌 SERVICE WORKER v7 – Reliability Overhaul
// ==============================================

const DB_NAME = "R2UploadDB";
const STORE = "pending";

let isProcessingQueue = false;

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
        const req = tx.objectStore(STORE).delete(id);
        req.onsuccess = () => resolve();
        req.onerror = () => reject(req.error);
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
        const req = store.add(clean);
        req.onsuccess = () => resolve(true);
        req.onerror = (e) => reject(e);
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
self.addEventListener("message", async (event) => {
    if (event.origin !== self.location.origin) return;

    const data = event.data;
    if (!data || !data.type) return;

    switch (data.type) {
        case "FORCE_PROCESS":
            console.log("⚡ Force Process commando ontvangen");
            await processQueue();
            break;

        case "ADD_UPLOAD":
            // 👇 Dit is nu alleen een backup path — main thread schrijft zelf ook naar IDB
            const added = await addItem(data);
            if (added) {
                triggerSync();
            }
            sendToClients({ type: "QUEUED", file: data.name });
            break;

        case "PROCESS_QUEUE":
            // 👇 Nieuw: main thread vraagt expliciet om queue te verwerken
            console.log("📬 Process queue gevraagd door main thread");
            await processQueue();
            break;

        case "SKIP_WAITING":
            self.skipWaiting();
            break;
    }
});

function triggerSync() {
    if ("sync" in self.registration) {
        self.registration.sync.register("sync-r2-uploads").catch(console.warn);
    } else {
        // Fallback: direct verwerken
        processQueue();
    }
}

async function sendToClients(msg) {
    const allClients = await self.clients.matchAll({ includeUncontrolled: true });
    for (const client of allClients) {
        client.postMessage(msg);
    }
}

// --------------------------
// BACKGROUND SYNC
// --------------------------
self.addEventListener("sync", async (event) => {
    if (event.tag === "sync-r2-uploads") {
        event.waitUntil(processQueue());
    }
});

// --------------------------
// 👇 FIX 3: Haal vers CSRF token op (niet de verlopen token uit IDB)
// --------------------------
async function getFreshCsrf() {
    try {
        const res = await fetch(`${API}/csrf-token`, {
            credentials: 'include',
            cache: 'no-store'
        });
        if (res.ok) {
            const json = await res.json();
            return json.token;
        }
    } catch (e) {
        console.warn("SW: Kon geen vers CSRF token ophalen:", e.message);
    }
    return null;
}

// --------------------------
// QUEUE PROCESSOR (Verbeterd)
// --------------------------
async function processQueue() {
    if (isProcessingQueue) {
        console.log("SW: Queue draait al, skip.");
        return;
    }

    const items = await getAll();
    if (items.length === 0) return;

    isProcessingQueue = true;
    let done = 0;
    let consecutiveFailures = 0;

    console.log(`SW: Start verwerking van ${items.length} items...`);

    // 👇 FIX 3: Haal 1x een vers CSRF token op voor de hele batch
    const freshCsrf = await getFreshCsrf();
    if (!freshCsrf) {
        console.warn("SW: Geen CSRF token beschikbaar. Wacht op volgende poging.");
        isProcessingQueue = false;
        // Probeer over 30 seconden opnieuw
        setTimeout(() => processQueue(), 30000);
        return;
    }

    for (const item of items) {
        try {
            if (!self.navigator.onLine) {
                console.log("SW: Offline, pauze.");
                break;
            }

            // Reset consecutive failures bij success
            sendToClients({
                type: "PROGRESS",
                current: done + 1,
                total: items.length,
                name: item.name,
            });

            // --- STAP 1: Upload naar R2 ---
            const form = new FormData();
            form.append("file", new Blob([item.blob], { type: item.fileType }), item.name);
            form.append("task_id", item.task_id);
            form.append("namespace_id", item.namespace_id);
            form.append("adres_path", item.adres_path);
            form.append("_token", freshCsrf); // 👈 Vers token
            form.append("unique_id", `${item.id}`);

            const uploadUrl = new URL(`${API}/r2/upload`);
            uploadUrl.searchParams.append("sw_bypass", "true");

            let res;
            try {
                res = await fetch(uploadUrl.toString(), {
                    method: "POST",
                    headers: { "X-CSRF-TOKEN": freshCsrf },
                    credentials: 'include',
                    body: form
                });
            } catch (err) {
                console.warn(`SW: Netwerk fout bij upload '${item.name}':`, err.message);
                consecutiveFailures++;
                if (consecutiveFailures >= 3) {
                    console.warn("SW: 3 opeenvolgende fouten, stoppen.");
                    break;
                }
                continue; // Probeer volgende item
            }

            if (res.status === 419) {
                // CSRF verlopen — haal nieuw token en stop deze ronde
                console.warn("SW: CSRF verlopen (419). Stop en probeer later opnieuw.");
                break;
            }

            if (res.status === 429) {
                console.warn("SW: Rate limit (429). Wacht 5s...");
                await new Promise(r => setTimeout(r, 5000));
                consecutiveFailures++;
                if (consecutiveFailures >= 3) break;
                continue;
            }

            if (!res.ok) {
                console.warn(`SW: Server error ${res.status} bij '${item.name}'`);
                consecutiveFailures++;
                if (consecutiveFailures >= 3) break;
                continue;
            }

            const json = await res.json();
            if (!json.path) {
                console.warn("SW: Geen path in response voor:", item.name);
                continue;
            }

            console.log("SW: Upload naar R2 OK:", json.path);

            // --- STAP 2: Registreer bij Laravel (MoveToDropboxJob aanmaken) ---
            let registerOk = false;
            for (let attempt = 0; attempt < 3; attempt++) {
                try {
                    const reg = await fetch(`${API}/r2/register-upload`, {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            "X-CSRF-TOKEN": freshCsrf
                        },
                        credentials: 'include',
                        body: JSON.stringify({
                            task_id: item.task_id,
                            r2_path: json.path,
                            namespace_id: item.namespace_id,
                            adres_path: item.adres_path
                        })
                    });

                    if (reg.ok) {
                        registerOk = true;
                        break;
                    } else if (reg.status === 419) {
                        console.warn("SW: CSRF verlopen bij register. Stop.");
                        break;
                    } else {
                        console.warn(`SW: Register poging ${attempt + 1} mislukt: ${reg.status}`);
                        await new Promise(r => setTimeout(r, 2000));
                    }
                } catch (regErr) {
                    console.warn(`SW: Register netwerk fout poging ${attempt + 1}:`, regErr.message);
                    await new Promise(r => setTimeout(r, 2000));
                }
            }

            if (!registerOk) {
                // 👇 CRUCIAAL: Foto staat in R2 maar job is niet aangemaakt
                // We verwijderen het NIET uit IndexedDB zodat het opnieuw geprobeerd wordt
                console.error(`SW: ❌ Register FAILED voor '${item.name}'. Blijft in queue.`);
                sendToClients({
                    type: "UPLOAD_PARTIAL",
                    name: item.name,
                    reason: "In R2 maar register mislukt"
                });
                consecutiveFailures++;
                if (consecutiveFailures >= 3) break;
                continue;
            }

            // --- STAP 3: Alles OK → verwijder uit IndexedDB ---
            await deleteItem(item.id);
            done++;
            consecutiveFailures = 0; // Reset bij success

            sendToClients({ type: "UPLOADED", name: item.name, done, total: items.length });

            // Rusttijd tussen uploads
            await new Promise(r => setTimeout(r, 800));

        } catch (err) {
            console.error(`SW: Onverwachte fout bij '${item.name}':`, err.message);
            consecutiveFailures++;
            if (consecutiveFailures >= 3) {
                console.warn("SW: Te veel fouten, stoppen.");
                break;
            }
        }
    }

    isProcessingQueue = false;

    // Check of er nog items over zijn
    const remaining = await getAll();
    if (remaining.length > 0 && done > 0) {
        console.log(`SW: Nog ${remaining.length} items over, herstart queue over 5s...`);
        setTimeout(() => processQueue(), 5000);
    }

    sendToClients({ type: "COMPLETE", uploaded: done, remaining: remaining.length });
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
        event.respondWith(saveToQueueAndRespond(event.request));
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

        triggerSync();
        sendToClients({ type: "QUEUED", file: file.name });

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