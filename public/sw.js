const SW_VERSION = 'v8-tunnel-fix'; // 👈 Versie omhoog!
const API = self.location.origin;

// ==============================================
// 📌 SERVICE WORKER v8 – Tunnel Fix & UI Feedback
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

// 👇 DEDUPLICATIE
async function addItem(data) {
    const currentItems = await getAll();
    const exists = currentItems.find(item => item.name === data.name);

    if (exists) return; 

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
    if (event.origin !== self.location.origin) return;
    
    if (event.data?.type === "FORCE_PROCESS") {
        console.log("⚡ Force Process commando ontvangen.");
        event.waitUntil(processQueue());
    }
    
    if (event.data?.type === "ADD_UPLOAD") {
        await addItem(event.data);
        if ("sync" in self.registration) {
            try { await self.registration.sync.register("sync-r2-uploads"); } 
            catch (e) { console.warn("Sync error", e); }
        }
        sendToClients({ type: "QUEUED", file: event.data.name });
    }
});

async function sendToClients(msg) {
    const allClients = await self.clients.matchAll({ includeUncontrolled: true });
    for (const client of allClients) client.postMessage(msg);
}

// --------------------------
// BACKGROUND SYNC
// --------------------------
self.addEventListener("sync", (event) => {
    if (event.tag === "sync-r2-uploads") {
        console.log("🤖 Android Background Sync gestart");
        event.waitUntil(processQueue());
    }
});

// --------------------------
// QUEUE PROCESSOR
// --------------------------
async function processQueue() {
    if (isProcessingQueue) return;

    const items = await getAll();
    if (items.length === 0) return;

    isProcessingQueue = true;
    let done = 0;

    console.log(`SW: Verwerken van ${items.length} items...`);

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

            // URL met bypass parameter
            const uploadUrl = new URL(`${API}/r2/upload`);
            uploadUrl.searchParams.append("sw_bypass", "true"); 

            // 
            
            // 👇 DE GROTE FIX: HEADERS OM TUNNEL ERROR 511 TE VOORKOMEN
            const res = await fetch(uploadUrl.toString(), {
                method: "POST",
                headers: { 
                    "X-CSRF-TOKEN": item.csrf,
                    "ngrok-skip-browser-warning": "true", // Voor Ngrok
                    "Bypass-Tunnel-Reminder": "true"      // Voor Localtunnel
                },
                credentials: 'include',
                body: form
            });

            if (!res.ok) {
                console.warn(`SW: Server error ${res.status}.`);
                throw new Error("SERVER_HICCUP");
            }

            const json = await res.json();
            if (!json.path) throw new Error("Missing path");

            console.log("SW: Upload OK:", json.path);

            // Database Registratie (Ook hier headers toevoegen!)
            const reg = await fetch(`${API}/r2/register-upload`, {
                method: "POST",
                headers: { 
                    "Content-Type": "application/json", 
                    "X-CSRF-TOKEN": item.csrf,
                    "ngrok-skip-browser-warning": "true", // Ook hier toevoegen!
                    "Bypass-Tunnel-Reminder": "true"
                },
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

        } catch (err) {
            console.warn(`SW: Fout bij '${item.name}':`, err.message);

            // 👇 UI UPDATE: Vertel frontend dat het mislukt is (Rode balk)
            sendToClients({ type: "UPLOAD_FAILED", name: item.name });

            // Retry Logica
            if (["SERVER_HICCUP", "NETWORK_FAIL", "RATE_LIMIT", "Failed to fetch"].includes(err.message) || err.message.includes("Server error")) {
                console.log("SW: Tijdelijke fout. Wacht 3s...");
                await new Promise(r => setTimeout(r, 3000));
                break; // Pauzeer queue, probeer later opnieuw
            } else {
                break; // Stop bij fatale fout
            }
        }
    }

    isProcessingQueue = false;
    if (done === items.length) {
        sendToClients({ type: "COMPLETE" });
    }
}

// --------------------------
// FETCH HANDLER
// --------------------------
self.addEventListener("fetch", (event) => {
    const url = new URL(event.request.url);
    if (url.searchParams.get("sw_bypass") === "true") return; 

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
            csrf: formData.get("_token"),
            unique_id: formData.get("unique_id") || Date.now().toString()
        });

        if ("sync" in self.registration) {
            self.registration.sync.register("sync-r2-uploads").catch(console.warn);
        } else {
            processQueue();
        }

        sendToClients({ type: "QUEUED", file: file.name });

        return new Response(JSON.stringify({ success: true, queued: true }), { 
            status: 200, headers: { "Content-Type": "application/json" } 
        });

    } catch (e) {
        return new Response(JSON.stringify({ error: "Storage failed" }), { status: 500 });
    }
}