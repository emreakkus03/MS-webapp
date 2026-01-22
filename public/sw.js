const SW_VERSION = 'v8-lock-fix'; // 👈 Versie omhoog
const API = self.location.origin;

const DB_NAME = "R2UploadDB";
const STORE = "pending";

// 🛑 LOCKING MECHANISME (Tegen dubbele uploads)
let queueRunningPromise = null;

// --- IndexedDB Helpers ---
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

async function addItem(data) {
    const currentItems = await getAll();
    const exists = currentItems.find(item => item.name === data.name);
    if (exists) return; // Deduplicatie bij toevoegen

    const db = await openDB();
    let blobData = (data.blob instanceof Blob) ? await data.blob.arrayBuffer() : data.blob;

    const clean = {
        name: data.name,
        fileType: data.fileType,
        task_id: data.task_id,
        namespace_id: data.namespace_id || "",
        root_path: data.root_path || "", 
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

// --- Lifecycle ---
self.addEventListener("install", () => self.skipWaiting());
self.addEventListener("activate", (event) => event.waitUntil(self.clients.claim()));

// --- Message Handler ---
self.addEventListener("message", async (event) => {
    if (event.origin !== self.location.origin) return;
    
    // Bij 'FORCE_PROCESS' (zodra internet terug is), gebruiken we nu de veilige functie
    if (event.data?.type === "FORCE_PROCESS") {
        console.log("⚡ Force Process: Queue starten...");
        await processQueue();
    }
    
    if (event.data?.type === "ADD_UPLOAD") {
        await addItem(event.data);
        if ("sync" in self.registration) {
            try { await self.registration.sync.register("sync-r2-uploads"); } 
            catch (e) { console.warn("Sync error", e); }
        } else {
            processQueue(); // Fallback
        }
        sendToClients({ type: "QUEUED", file: event.data.name });
    }
});

async function sendToClients(msg) {
    const allClients = await self.clients.matchAll({ includeUncontrolled: true });
    for (const client of allClients) client.postMessage(msg);
}

self.addEventListener("sync", async (event) => {
    if (event.tag === "sync-r2-uploads") await processQueue();
});

// --- SAFE QUEUE PROCESSOR (Met Progress Fix) ---
async function processQueue() {
    if (queueRunningPromise) {
        console.log("SW: 🔒 Queue draait al. Verzoek genegeerd.");
        return queueRunningPromise;
    }

    queueRunningPromise = (async () => {
        console.log("SW: 🚀 Start verwerking...");
        let done = 0; // Lokale teller voor deze sessie
        
        while (true) {
            const items = await getAll();
            if (items.length === 0) break; 

            if (!self.navigator.onLine) {
                console.log("SW: 📵 Offline. Pauze.");
                break; 
            }

            const item = items[0]; 
            
            // 👇 FIX: Bereken totaal dynamisch (wat we al deden + wat er nog ligt)
            // Zo blijft de teller kloppen: 1/6, 2/6, etc.
            const totalInQueue = items.length;
            const realTotal = done + totalInQueue;
            const currentNum = done + 1;

            try {
                // Stuur progressie naar frontend
                sendToClients({ 
                    type: "PROGRESS", 
                    current: currentNum, 
                    total: realTotal, 
                    name: item.name 
                });

                const form = new FormData();
                form.append("photos[]", new Blob([item.blob], { type: item.fileType }), item.name);
                form.append("namespace_id", item.namespace_id || ""); 
                form.append("root_path", item.root_path || ""); 
                form.append("_token", item.csrf);

                const uploadUrl = new URL(`${API}/tasks/${item.task_id}/upload-temp`);
                uploadUrl.searchParams.append("sw_bypass", "true");

                const res = await fetch(uploadUrl.toString(), {
                    method: "POST",
                    headers: { 
                        "X-CSRF-TOKEN": item.csrf,
                        "X-Requested-With": "XMLHttpRequest",
                        "Accept": "application/json"
                    },
                    credentials: 'include',
                    body: form
                });

                if (res.status === 429) throw new Error("RATE_LIMIT");

                const textData = await res.text();
                let json;
                try { json = JSON.parse(textData); } 
                catch (e) { throw new Error(`Server Error (Geen JSON): ${res.status}`); }

                if (!res.ok) throw new Error(json.message || `Server fout: ${res.status}`);

                console.log(`SW: ✅ Upload OK: ${item.name}`);
                await deleteItem(item.id);
                
                done++; // Teller omhoog
                
                // Stuur bevestiging van deze specifieke file
                sendToClients({ type: "UPLOADED", name: item.name });
                
                await new Promise(r => setTimeout(r, 500)); 

            } catch (err) {
                console.warn(`SW: ❌ Fout bij '${item.name}':`, err.message);
                if (["RATE_LIMIT", "Failed to fetch", "NetworkError"].some(s => err.message.includes(s)) || !self.navigator.onLine) {
                    console.log("SW: 🛑 Pauze door netwerkfout.");
                    break;
                }
                await new Promise(r => setTimeout(r, 2000));
            }
        }
        
        console.log("SW: 🏁 Queue klaar.");
        
        // 👇 FIX: Stuur het COMPLETE bericht direct hier, zodat het zeker aankomt
        sendToClients({ type: "COMPLETE" });
        
    })();

    await queueRunningPromise;
    queueRunningPromise = null;
}

// --- Fetch Interceptor ---
self.addEventListener("fetch", (event) => {
    const url = new URL(event.request.url);
    if (url.searchParams.get("sw_bypass") === "true") return;

    if (url.pathname.includes('/upload-temp') && event.request.method === "POST") {
        event.respondWith(saveToQueueAndRespond(event.request));
    }
});

async function saveToQueueAndRespond(request) {
    try {
        const formData = await request.clone().formData();
        // Frontend stuurt 'photos[]', dus dat moeten we ophalen
        const file = formData.get("photos[]") || formData.get("file"); 

        await addItem({
            name: file.name,
            fileType: file.type,
            blob: file, 
            task_id: request.url.split('/tasks/')[1].split('/')[0],
            namespace_id: formData.get("namespace_id"),
            root_path: formData.get("root_path"),
            csrf: formData.get("_token")
        });

        if ("sync" in self.registration) {
            self.registration.sync.register("sync-r2-uploads").catch(console.warn);
        } else {
            processQueue();
        }

        return new Response(JSON.stringify({ success: true, queued: true }), { 
            status: 200, headers: { "Content-Type": "application/json" } 
        });
    } catch (e) {
        return new Response(JSON.stringify({ error: "Storage failed" }), { status: 500 });
    }
}