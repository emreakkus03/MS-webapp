const SW_VERSION = 'v3-fixed-recursion';
const API = self.location.origin;

// ==============================================
// 📌 SERVICE WORKER v3 – Guaranteed Upload Queue
// ==============================================

const DB_NAME = "R2UploadDB";
const STORE = "pending";

// --------------------------
// IndexedDB Helpers
// --------------------------
function openDB() {
    return new Promise((resolve) => {
        const req = indexedDB.open(DB_NAME, 1);
        req.onupgradeneeded = (e) => {
            const db = e.target.result;
            if (!db.objectStoreNames.contains(STORE)) {
                db.createObjectStore(STORE, {
                    keyPath: "id",
                    autoIncrement: true
                });
            }
        };
        req.onsuccess = (e) => resolve(e.target.result);
    });
}

async function addItem(data) {
    const db = await openDB();

    // Check of blob al een ArrayBuffer is of nog een Blob
    let blobData;
    if (data.blob instanceof Blob) {
        blobData = await data.blob.arrayBuffer();
    } else {
        blobData = data.blob; // Al een buffer
    }

    const clean = {
        name: data.name,
        fileType: data.fileType,
        task_id: data.task_id,
        namespace_id: data.namespace_id,
        adres_path: data.adres_path,
        csrf: data.csrf,
        blob: blobData
    };

    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE, "readwrite");
        const store = tx.objectStore(STORE);
        const req = store.add(clean);
        req.onsuccess = () => resolve();
        req.onerror = (e) => reject(e);
    });
}


async function getAll() {
    const db = await openDB();
    return new Promise((resolve) => {
        const req = db.transaction(STORE).objectStore(STORE).getAll();
        req.onsuccess = () => resolve(req.result);
    });
}

async function deleteItem(id) {
    const db = await openDB();
    db.transaction(STORE, "readwrite").objectStore(STORE).delete(id);
}

// --------------------------
// Install / Activate
// --------------------------
self.addEventListener("install", () => self.skipWaiting());
self.addEventListener("activate", (event) => event.waitUntil(self.clients.claim()));


// --------------------------
// FRONTEND → SW (Direct bericht)
// --------------------------
self.addEventListener("message", async (event) => {
    if (event.data?.type === "ADD_UPLOAD") {
        await addItem(event.data);

        // Background Sync triggeren
        if ("sync" in self.registration) {
            try {
                await self.registration.sync.register("sync-r2-uploads");
            } catch (e) {
                console.warn("Background Sync niet ondersteund of gefaald", e);
            }
        }

        sendToClients({
            type: "QUEUED",
            file: event.data.name
        });
    }

    if (event.data?.type === "SKIP_WAITING") {
        console.log("⚡ SW: skipWaiting ontvangen");
        self.skipWaiting();
    }
});

// --------------------------
// Helper: Message naar UI
// --------------------------
async function sendToClients(msg) {
    const allClients = await self.clients.matchAll({
        includeUncontrolled: true
    });
    for (const client of allClients) client.postMessage(msg);
}


// --------------------------
// BACKGROUND SYNC HANDLER
// --------------------------
self.addEventListener("sync", async (event) => {
    if (event.tag !== "sync-r2-uploads") return;
    await processQueue();
});


// --------------------------
// Upload Queue Processor
// --------------------------
async function processQueue() {
    const items = await getAll();
    if (items.length === 0) return;

    let done = 0;

    for (const item of items) {
        try {
            sendToClients({
                type: "PROGRESS",
                current: done + 1,
                total: items.length,
                name: item.name,
            });

            // Reconstruct FormData
            const form = new FormData();
            form.append("file", new Blob([item.blob], { type: item.fileType }), item.name);
            form.append("task_id", item.task_id);
            form.append("namespace_id", item.namespace_id);
            form.append("adres_path", item.adres_path);
            form.append("_token", item.csrf);
            form.append("unique_id", item.id);

            // 1. Check Offline
            if (!self.navigator.onLine) {
                throw new Error("OFFLINE");
            }

            // 2. Upload Fetch
            let res;
            try {
                res = await fetch(`${API}/r2/upload`, {
                    method: "POST",
                    headers: {
        // CSRF header is nu optioneel, maar mag blijven
        "X-CSRF-TOKEN": item.csrf 
    },
    credentials: 'include',
                    body: form
                });
            } catch (err) {
                throw new Error("NETWORK_FAIL");
            }

            if (!res.ok) {
                console.warn("SW: upload naar Laravel mislukt", await res.text());
                throw new Error("UPLOAD_FAIL");
            }

            const json = await res.json();
            console.log("SW: Laravel upload OK:", json.path);

            // 3. Register bij Laravel
            const reg = await fetch(`${API}/r2/register-upload`, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": item.csrf
                },
                credentials: 'include',
                body: JSON.stringify({
                    task_id: item.task_id,
                    r2_path: json.path,
                    namespace_id: item.namespace_id,
                    adres_path: item.adres_path
                })
            });

            if (!reg.ok) {
                throw new Error("Register fail");
            }

            // 4. Verwijder uit queue als alles gelukt is
            await deleteItem(item.id);
            done++;

            sendToClients({
                type: "UPLOADED",
                name: item.name,
                done,
                total: items.length
            });

        } catch (err) {
            console.warn(`SW: Upload item '${item.name}' mislukt:`, err.message);
            
            // Als het offline is of netwerkfout, stoppen we de loop maar laten we items in DB staan
            if (err.message === "OFFLINE" || err.message === "NETWORK_FAIL") {
                sendToClients({ type: "UPLOAD_FAILED", name: item.name, reason: "offline/network" });
                return; // Stop queue processing, try again later
            }
            // Bij andere errors (server 500 etc) laten we hem ook staan voor retry
        }
    }

    sendToClients({ type: "COMPLETE" });
}


// ==============================================
// 🚨 DE KRITIEKE FIX (FETCH HANDLER)
// ==============================================
self.addEventListener("fetch", (event) => {
    const url = new URL(event.request.url);

    // Alleen POST requests naar /r2/upload onderscheppen
    if (url.href === `${API}/r2/upload` && event.request.method === "POST") {
        
        event.respondWith(async function() {
            // 1. OFFLINE DETECTIE
            if (!self.navigator.onLine) {
                console.warn("SW FETCH: Offline gedetecteerd → direct naar Queue");
                return await saveToQueueAndRespond(event.request);
            }

            // 2. ONLINE POGING (Pass-Through)
            try {
                // 🔥 HIER ZAT HET PROBLEEM: 
                // Gebruik event.request.clone() om oneindige loop te voorkomen!
                const response = await fetch(event.request.clone());

                // Als server error geeft (bv 500), willen we misschien alsnog queue-en?
                if (!response.ok) {
                    throw new Error(`Server error: ${response.status}`);
                }
                
                return response;

            } catch (err) {
                console.warn("SW FETCH: Netwerkfout/Serverfout → opslaan in queue", err);
                // 3. FALLBACK NAAR QUEUE
                return await saveToQueueAndRespond(event.request);
            }
        }());
    }
    // Alle andere requests gewoon doorlaten
});

// 🛠️ HELPER: Slaat request op in IndexedDB en geeft nep-succes terug aan frontend
async function saveToQueueAndRespond(request) {
    try {
        // We moeten de body clonen en lezen
        const formData = await request.clone().formData();
        const file = formData.get("file");

        await addItem({
            name: file.name,
            fileType: file.type,
            blob: file, // addItem converteert dit naar ArrayBuffer
            task_id: formData.get("task_id"),
            namespace_id: formData.get("namespace_id"),
            adres_path: formData.get("adres_path"),
            csrf: formData.get("_token"),
        });

        // Trigger background sync
        if ("sync" in self.registration) {
            self.registration.sync.register("sync-r2-uploads").catch(console.warn);
        }

        sendToClients({ type: "QUEUED", file: file.name });

        // Geef een 200 OK (of 202 Accepted) terug zodat de frontend denkt dat het gelukt is
        return new Response(
            JSON.stringify({ success: false, queued: true, message: "Offline opgeslagen" }),
            { status: 200, headers: { "Content-Type": "application/json" } }
        );

    } catch (e) {
        console.error("SW: CRITICAL ERROR saving to queue", e);
        return new Response(JSON.stringify({ error: "Storage failed" }), { status: 500 });
    }
}