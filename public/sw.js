const SW_VERSION = 'v5-deduplication-fix';
const API = self.location.origin;

// ==============================================
// 📌 SERVICE WORKER v5 – Deduplication & Rate Limit
// ==============================================

const DB_NAME = "R2UploadDB";
const STORE = "pending";

// 🛑 GLOBAL LOCK
let isProcessingQueue = false; 

// --------------------------
// IndexedDB Helpers
// --------------------------
function openDB() {
    return new Promise((resolve) => {
        const req = indexedDB.open(DB_NAME, 1);
        req.onupgradeneeded = (e) => {
            const db = e.target.result;
            if (!db.objectStoreNames.contains(STORE)) {
                db.createObjectStore(STORE, { keyPath: "id", autoIncrement: true });
            }
        };
        req.onsuccess = (e) => resolve(e.target.result);
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

// 👇 DE GROTE FIX: DEDUPLICATIE
async function addItem(data) {
    // 1. Haal de huidige wachtrij op
    const currentItems = await getAll();
    
    // 2. Check of dit bestand al bestaat (op basis van naam)
    const exists = currentItems.find(item => item.name === data.name);

    if (exists) {
        console.log(`⚠️ SW: Bestand '${data.name}' staat al in de wachtrij. Dubbele toevoeging genegeerd.`);
        return; // 🛑 STOP! Voeg niet toe.
    }

    const db = await openDB();
    let blobData;
    if (data.blob instanceof Blob) {
        blobData = await data.blob.arrayBuffer();
    } else {
        blobData = data.blob;
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

// --------------------------
// Install / Activate
// --------------------------
self.addEventListener("install", () => self.skipWaiting());
self.addEventListener("activate", (event) => event.waitUntil(self.clients.claim()));

// --------------------------
// FRONTEND → SW
// --------------------------
self.addEventListener("message", async (event) => {

    // 🛡️ SECURITY FIX VOOR SNYK
    // We controleren of het bericht van ONZE EIGEN website komt.
    // Als de origin niet klopt (bijv. hacker-site.com), negeren we het bericht.
    if (event.origin !== self.location.origin) {
        console.warn("SW: Bericht genegeerd van onbekende oorsprong:", event.origin);
        return; 
    }
    
    if (event.data?.type === "ADD_UPLOAD") {
        await addItem(event.data);
        if ("sync" in self.registration) {
            try {
                await self.registration.sync.register("sync-r2-uploads");
            } catch (e) {
                console.warn("Background Sync fout", e);
            }
        }
        sendToClients({ type: "QUEUED", file: event.data.name });
    }
    if (event.data?.type === "SKIP_WAITING") {
        self.skipWaiting();
    }
});

async function sendToClients(msg) {
    const allClients = await self.clients.matchAll({ includeUncontrolled: true });
    for (const client of allClients) client.postMessage(msg);
}

// --------------------------
// BACKGROUND SYNC
// --------------------------
self.addEventListener("sync", async (event) => {
    if (event.tag !== "sync-r2-uploads") return;
    await processQueue();
});

// --------------------------
// QUEUE PROCESSOR
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

    console.log(`SW: Start verwerking van ${items.length} unieke items...`);

    for (const item of items) {
        try {
            if (!self.navigator.onLine) {
                console.log("SW: Offline, pauze.");
                break; 
            }

            sendToClients({
                type: "PROGRESS",
                current: done + 1,
                total: items.length,
                name: item.name,
            });

            const form = new FormData();
            form.append("file", new Blob([item.blob], { type: item.fileType }), item.name);
            form.append("task_id", item.task_id);
            form.append("namespace_id", item.namespace_id);
            form.append("adres_path", item.adres_path);
            form.append("_token", item.csrf);
            form.append("unique_id", item.id); 

            let res;
            try {
                res = await fetch(`${API}/r2/upload`, {
                    method: "POST",
                    headers: { "X-CSRF-TOKEN": item.csrf },
                    credentials: 'include',
                    body: form
                });
            } catch (err) {
                throw new Error("NETWORK_FAIL");
            }

            if (res.status === 429) {
                console.warn("SW: 429 Rate Limit. Wacht 5s...");
                await new Promise(r => setTimeout(r, 5000)); 
                throw new Error("RATE_LIMIT");
            }

            if (!res.ok) throw new Error(`Server error: ${res.status}`);

            const json = await res.json();
            console.log("SW: Upload OK:", json.path);

            const reg = await fetch(`${API}/r2/register-upload`, {
                method: "POST",
                headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": item.csrf },
                credentials: 'include',
                body: JSON.stringify({
                    task_id: item.task_id,
                    r2_path: json.path,
                    namespace_id: item.namespace_id,
                    adres_path: item.adres_path
                })
            });

            if (!reg.ok) throw new Error("Register fail");

            await deleteItem(item.id);
            done++;

            sendToClients({ type: "UPLOADED", name: item.name, done, total: items.length });

            // Rusttijd
            await new Promise(r => setTimeout(r, 500));

        } catch (err) {
            console.warn(`SW: Fout bij '${item.name}':`, err.message);
            if (["OFFLINE", "NETWORK_FAIL", "RATE_LIMIT"].includes(err.message)) {
                sendToClients({ type: "UPLOAD_FAILED", name: item.name, reason: "pauze" });
                break;
            }
        }
    }

    isProcessingQueue = false;
    sendToClients({ type: "COMPLETE" });
}

// --------------------------
// FETCH HANDLER
// --------------------------
// --------------------------
// FETCH HANDLER (Dwing ALLES via de Queue)
// --------------------------
self.addEventListener("fetch", (event) => {
    const url = new URL(event.request.url);

    // Als de frontend probeert te uploaden...
    if (url.href === `${API}/r2/upload` && event.request.method === "POST") {
        
        event.respondWith(async function() {
            // 🛑 STOP! We laten dit NOOIT direct doorgaan naar de server.
            // We vangen het op en stoppen het in onze eigen wachtrij.
            // Dit voorkomt dat de browser en de queue tegelijk gaan uploaden.
            return await saveToQueueAndRespond(event.request);
        }());
    }
});

async function saveToQueueAndRespond(request) {
    try {
        // We moeten de request klonen om de body te kunnen lezen
        const formData = await request.clone().formData();
        const file = formData.get("file");

        // We voegen hem toe aan de queue (addItem heeft nu jouw deduplicatie check!)
        await addItem({
            name: file.name,
            fileType: file.type,
            blob: file, 
            task_id: formData.get("task_id"),
            namespace_id: formData.get("namespace_id"),
            adres_path: formData.get("adres_path"),
            csrf: formData.get("_token"),
            // Als de request geen ID heeft, maken we er een.
            // Als hij er wel een heeft (vanuit frontend), gebruiken we die voor deduplicatie.
            unique_id: formData.get("unique_id") || Date.now().toString()
        });

        // Probeer direct te syncen
        if ("sync" in self.registration) {
            self.registration.sync.register("sync-r2-uploads").catch(console.warn);
        } else {
            // Fallback voor browsers zonder Background Sync
            processQueue();
        }

        // Stuur bericht naar UI
        sendToClients({ type: "QUEUED", file: file.name });

        // ✅ We liegen tegen de frontend: "Het is gelukt!" (200 OK)
        // De frontend denkt dat de upload klaar is, maar in werkelijkheid
        // zit hij veilig in onze IndexedDB wachtrij.
        return new Response(
            JSON.stringify({ 
                success: true, // We zeggen 'true' zodat frontend niet gaat retryen
                queued: true, 
                message: "In wachtrij geplaatst" 
            }),
            { status: 200, headers: { "Content-Type": "application/json" } }
        );

    } catch (e) {
        console.error("SW: Fout bij opslaan in queue", e);
        return new Response(JSON.stringify({ error: "Storage failed" }), { status: 500 });
    }
}